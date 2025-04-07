<?php

namespace App\Http\Controllers\api\db;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Services\LimitCheckService;

class ServicesController extends Controller
{
    /**
     * Display a listing of the resource.
     */ // Add this property to your controller class
    protected $limitCheckService;

    // Update the constructor to inject the service
    public function __construct(LimitCheckService $limitCheckService)
    {
        $this->limitCheckService = $limitCheckService;
    }


    public function index(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $connection = DB::connection($dbConnection);

        try {
            $perPage = $request->query('perPage', 10);
            $currentPage = $request->query('page', 1);
            $filter = $request->query('filter', []); // Obtener todos los filtros
            $orderBy = $filter['order_by'] ?? 'id_desc'; // Obtener orden desde filtro

            // Inicializar consulta
            $query = $connection->table('services')
                ->select([
                    'id',
                    'name',
                    'appointment_duration_minutes',
                    'service_price',
                    'active'
                ]);

            // Aplicar filtro de búsqueda general de services1
            if (!empty($filter['all'])) {
                $searchTerm = '%' . $filter['all'] . '%';
                $query->whereAny(
                    [
                        'name',

                    ],
                    'ilike',
                    $searchTerm
                );
            }

            // Aplicar filtro de estado 'active' si está presente
            if (isset($filter['active'])) {
                $query->where('active', $filter['active']);
            }

            // Ordenamiento
            switch ($orderBy) {
                case 'creation_desc':
                    $query->orderBy('id', 'desc');
                    break;
                case 'creation_asc':
                    $query->orderBy('id', 'asc');
                    break;
                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
                default: // Orden por defecto
                    $query->orderBy('id', 'desc');
                    break;
            }

            // Paginación
            $services = $query->paginate($perPage, ['*'], 'page', $currentPage);

            // Para cada servicio, agregamos sus especialistas y rangos de horarios
            $services->getCollection()->transform(function ($service) use ($connection) {
                // Especialistas asignados al servicio (tabla user_services)
                $service->specialists = $connection->table('users')
                    ->join('user_services', 'users.id', '=', 'user_services.user_id')
                    ->where('user_services.service_id', $service->id)
                    ->select([
                        'users.id',
                        'users.name',
                        'users.email',
                        'user_services.commission_type',
                        'user_services.percentage',
                        'user_services.fixed'
                    ])
                    ->get();

                // Rango/s del servicio (tabla rangos)
                $ranges = $connection->table('rangos')
                    ->where('service_id', $service->id)
                    ->get();

                // Para cada rango se obtienen los días, especialistas y horarios
                $service->ranges = $ranges->map(function ($rango) use ($connection) {
                    // Convertir los booleanos de los días en un array de números
                    $days = [];
                    if ($rango->monday)    $days[] = 1;
                    if ($rango->tuesday)   $days[] = 2;
                    if ($rango->wednesday) $days[] = 3;
                    if ($rango->thursday)  $days[] = 4;
                    if ($rango->friday)    $days[] = 5;
                    if ($rango->saturday)  $days[] = 6;
                    if ($rango->sunday)    $days[] = 7;

                    // Especialistas para este rango (tabla user_range)
                    $specialistsInRange = $connection->table('user_range')
                        ->where('range_id', $rango->id)
                        ->pluck('user_id')
                        ->toArray();

                    // Horarios para este rango (tabla times_range)
                    $times = $connection->table('times_range')
                        ->where('range_id', $rango->id)
                        ->select('hora_inicio as start', 'hora_fim as end')
                        ->get();

                    return [
                        'days' => $days,
                        'specialist_in_range' => $specialistsInRange,
                        'times' => $times,
                    ];
                });

                return $service;
            });

            return response()->json($services);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:tabla',
            'appointment_duration_minutes' => 'required|integer',
            'service_price' => 'required|numeric',
            'active' => 'required|boolean',
            'specialists' => 'required|array',
            'specialists.*.commission_type' => 'required|in:none,fixed,percentage,fixedpluspercentage',
            'ranges' => 'required|array',
        ]);
        // Check if we can add more services
        if (!$this->limitCheckService->canAddService($dbConnection)) {
            return response()->json([
                'error' => 'No se pueden crear más servicios. Se ha alcanzado el límite máximo.'
            ], 403);
        }
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Insertar el servicio
            $serviceId = $query->table('services')->insertGetId([
                'name' => $request->name,
                'appointment_duration_minutes' => $request->appointment_duration_minutes,
                'service_price' => $request->service_price,
                'active' => $request->active,
            ]);

            // Asignar especialistas al servicio (tabla user_services)
            foreach ($request->specialists as $specialist) {
                $commissionType = $specialist['commission_type'];
                $fixed = 0;
                $percentage = 0;
                if ($commissionType === 'fixed') {
                    $fixed = $specialist['fixed'] ?? 0;
                } else if ($commissionType === 'percentage') {
                    $percentage = $specialist['percentage'] ?? 0;
                } else if ($commissionType === 'fixedpluspercentage') {
                    $fixed = $specialist['fixed'] ?? 0;
                    $percentage = $specialist['percentage'] ?? 0;
                }

                $query->table('user_services')->insert([
                    'service_id' => $serviceId,
                    'user_id' => $specialist['id'], // asumiendo que viene así el payload
                    'commission_type' => $commissionType,
                    'percentage' => $percentage,
                    'fixed' => $fixed,
                ]);
            }

            // Procesar cada rango enviado en el payload
            foreach ($request->ranges as $range) {
                // $range['days'] es un array de números (1: lunes, 2: martes, ..., 7: domingo)
                $monday    = in_array(1, $range['days']);
                $tuesday   = in_array(2, $range['days']);
                $wednesday = in_array(3, $range['days']);
                $thursday  = in_array(4, $range['days']);
                $friday    = in_array(5, $range['days']);
                $saturday  = in_array(6, $range['days']);
                $sunday    = in_array(7, $range['days']);

                // Insertar el rango en la tabla rangos
                $rangeId = $query->table('rangos')->insertGetId([
                    'service_id' => $serviceId,
                    'monday'    => $monday,
                    'tuesday'   => $tuesday,
                    'wednesday' => $wednesday,
                    'thursday'  => $thursday,
                    'friday'    => $friday,
                    'saturday'  => $saturday,
                    'sunday'    => $sunday,
                ]);

                // Insertar los horarios para este rango (tabla times_range) SOLO UNA VEZ
                foreach ($range['times'] as $time) {
                    $query->table('times_range')->insert([
                        'range_id' => $rangeId,
                        'hora_inicio' => $time['start'],
                        'hora_fim' => $time['end'],
                    ]);
                }

                // Asignar especialistas para este rango (tabla user_range)
                foreach ($range['specialist_in_range'] as $specialistId) {
                    $query->table('user_range')->insert([
                        'range_id' => $rangeId,
                        'user_id' => $specialistId,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Service created successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        //creame el metodo show
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $service = $query->table('services')->where('id', $id)->first();
        $ranges = $query->table('rangos')->where('service_id', $id)->get();
        $specialists = $query->table('users')->join('user_services', 'users.id', '=', 'user_services.user_id')->where('user_services.service_id', $id)->get();
        $service->specialists = $specialists;
        $service->ranges = $ranges;
        return response()->json($service);


        //return response()->json($service);


    }
    /**
     * Update the specified resource in storage.
     */

    public function update(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        // Validación más flexible
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:tabla,name,' . $id,
            'appointment_duration_minutes' => 'sometimes|integer',
            'service_price' => 'sometimes|numeric',
            'active' => 'sometimes|boolean',
            'specialists' => 'sometimes|array',
            'specialists.*.commission_type' => 'sometimes|in:none,fixed,percentage,fixedpluspercentage',
            'ranges' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Obtener servicio existente
        $service = $query->table('services')->where('id', $id)->first();
        if (!$service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        try {
            $query->beginTransaction();

            // Actualizar solo los campos proporcionados - FIX: No usar array_filter aquí
            $serviceData = [
                'name' => $request->has('name') ? $request->input('name') : $service->name,
                'appointment_duration_minutes' => $request->has('appointment_duration_minutes') ? $request->input('appointment_duration_minutes') : $service->appointment_duration_minutes,
                'service_price' => $request->has('service_price') ? $request->input('service_price') : $service->service_price,
            ];

            // Tratar el campo 'active' de manera especial para manejar valores booleanos
            if ($request->has('active')) {
                $serviceData['active'] = $request->input('active') ? true : false;
            }

            $query->table('services')->where('id', $id)->update($serviceData);

            // Actualizar especialistas solo si se proporcionan
            if ($request->has('specialists')) {
                // Eliminar especialistas anteriores
                $query->table('user_services')->where('service_id', $id)->delete();

                foreach ($request->specialists as $specialist) {
                    $commissionData = [
                        'service_id' => $id,
                        'user_id' => $specialist['id'],
                        'commission_type' => $specialist['commission_type'] ?? 'none',
                        'percentage' => 0,
                        'fixed' => 0
                    ];

                    switch ($commissionData['commission_type']) {
                        case 'fixed':
                            $commissionData['fixed'] = $specialist['fixed'] ?? 0;
                            break;
                        case 'percentage':
                            $commissionData['percentage'] = $specialist['percentage'] ?? 0;
                            break;
                        case 'fixedpluspercentage':
                            $commissionData['fixed'] = $specialist['fixed'] ?? 0;
                            $commissionData['percentage'] = $specialist['percentage'] ?? 0;
                            break;
                    }

                    $query->table('user_services')->insert($commissionData);
                }
            }

            // Actualizar rangos solo si se proporcionan
            if ($request->has('ranges')) {
                // En lugar de eliminar todos los rangos, vamos a actualizarlos
                $existingRanges = DB::connection($dbConnection)
                    ->table('rangos')
                    ->where('service_id', $id)
                    ->get();

                $rangeMap = []; // Para mapear los rangos existentes con los nuevos

                // Primero, actualizar los rangos existentes que coincidan
                foreach ($request->ranges as $index => $rangeData) {
                    // Convertir días a formato booleano
                    $monday    = in_array(1, $rangeData['days']);
                    $tuesday   = in_array(2, $rangeData['days']);
                    $wednesday = in_array(3, $rangeData['days']);
                    $thursday  = in_array(4, $rangeData['days']);
                    $friday    = in_array(5, $rangeData['days']);
                    $saturday  = in_array(6, $rangeData['days']);
                    $sunday    = in_array(7, $rangeData['days']);

                    if ($index < count($existingRanges)) {
                        // Actualizar rango existente
                        $existingRange = $existingRanges[$index];
                        $query->table('rangos')
                            ->where('id', $existingRange->id)
                            ->update([
                                'monday'    => $monday,
                                'tuesday'   => $tuesday,
                                'wednesday' => $wednesday,
                                'thursday'  => $thursday,
                                'friday'    => $friday,
                                'saturday'  => $saturday,
                                'sunday'    => $sunday,
                            ]);

                        $rangeMap[$index] = $existingRange->id;

                        // Actualizar horarios (eliminar los existentes y crear nuevos)
                        $query->table('times_range')
                            ->where('range_id', $existingRange->id)
                            ->delete();

                        foreach ($rangeData['times'] as $time) {
                            $query->table('times_range')->insert([
                                'range_id' => $existingRange->id,
                                'hora_inicio' => $time['start'],
                                'hora_fim' => $time['end'],
                            ]);
                        }

                        // Actualizar especialistas (eliminar los existentes y crear nuevos)
                        $query->table('user_range')
                            ->where('range_id', $existingRange->id)
                            ->delete();

                        foreach ($rangeData['specialist_in_range'] as $specialistId) {
                            $query->table('user_range')->insert([
                                'range_id' => $existingRange->id,
                                'user_id' => $specialistId,
                            ]);
                        }
                    } else {
                        // Crear nuevo rango si hay más rangos en la solicitud que existentes
                        $rangeId = $query->table('rangos')->insertGetId([
                            'service_id' => $id,
                            'monday'    => $monday,
                            'tuesday'   => $tuesday,
                            'wednesday' => $wednesday,
                            'thursday'  => $thursday,
                            'friday'    => $friday,
                            'saturday'  => $saturday,
                            'sunday'    => $sunday,
                        ]);

                        $rangeMap[$index] = $rangeId;

                        // Insertar horarios para el nuevo rango
                        foreach ($rangeData['times'] as $time) {
                            $query->table('times_range')->insert([
                                'range_id' => $rangeId,
                                'hora_inicio' => $time['start'],
                                'hora_fim' => $time['end'],
                            ]);
                        }

                        // Asignar especialistas para el nuevo rango
                        foreach ($rangeData['specialist_in_range'] as $specialistId) {
                            $query->table('user_range')->insert([
                                'range_id' => $rangeId,
                                'user_id' => $specialistId,
                            ]);
                        }
                    }
                }

                // Eliminar rangos sobrantes si hay menos rangos en la solicitud que existentes
                if (count($existingRanges) > count($request->ranges)) {
                    $rangesToDelete = $existingRanges->slice(count($request->ranges));
                    foreach ($rangesToDelete as $range) {
                        // Eliminar relaciones primero
                        $query->table('user_range')->where('range_id', $range->id)->delete();
                        $query->table('times_range')->where('range_id', $range->id)->delete();
                        // Eliminar el rango
                        $query->table('rangos')->where('id', $range->id)->delete();
                    }
                }
            }

            // Confirmar la transacción
            $query->commit();

            return response()->json(['message' => 'Service updated successfully'], 200);
        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            $query->rollBack();
            return response()->json(['error' => 'Failed to update service', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */

    public function destroy(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        // Validar que el servicio exista
        $service = $query->table('services')
            ->where('id', $id)
            ->first();

        if (!$service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        // Iniciar una transacción para asegurar la consistencia
        $query->beginTransaction();

        try {
            // Eliminar los registros asociados en 'user_services'
            // Eliminar rangos y horarios asociados
            DB::connection($dbConnection)->table('user_range')
                ->whereIn('range_id', function ($q) use ($id) {
                    $q->select('id')->from('rangos')->where('service_id', $id);
                })
                ->delete();

            DB::connection($dbConnection)->table('times_range')
                ->whereIn('range_id', function ($q) use ($id) {
                    $q->select('id')->from('rangos')->where('service_id', $id);
                })
                ->delete();

            DB::connection($dbConnection)->table('rangos')->where('service_id', $id)->delete();

            // Eliminar el servicio
            $query->table('services')->where('id', $id)->delete();

            // Confirmar la transacción
            $query->commit();

            return response()->json(['message' => 'Service and associated specialists deleted successfully'], 200);
        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            $query->rollBack();
            return response()->json(['error' => 'Failed to delete service', 'details' => $e->getMessage()], 500);
        }
    }
}
