<?php

namespace App\Http\Controllers\api\db;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ServicesController extends Controller
{
    /**
     * Display a listing of the resource.
     */


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

            // Aplicar filtro de búsqueda general
            if (!empty($filter['all'])) {
                $searchTerm = '%' . $filter['all'] . '%';
                $query->whereAny(
                    [
                        'name',
                        'id'
                    ],
                    'LIKE',
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
            'name' => 'required|string|max:255',
            'appointment_duration_minutes' => 'required|integer',
            'service_price' => 'required|numeric',
            'active' => 'required|boolean',
            'specialists' => 'required|array',
            'specialists.*.commission_type' => 'required|in:none,fixed,percentage,fixedpluspercentage',
            'ranges' => 'required|array',
        ]);

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
                    // Puedes agregar appointment_comission, percentage y fixed según corresponda
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
                // Asignar especialistas para este rango (tabla user_range)
                foreach ($range['specialist_in_range'] as $specialistId) {
                    $query->table('user_range')->insert([
                        'range_id' => $rangeId,
                        'user_id' => $specialistId,
                    ]);

                    // Insertar los horarios para este rango (tabla times_range)
                    foreach ($range['times'] as $time) {
                        $query->table('times_range')->insert([
                            'range_id' => $rangeId,
                            'hora_inicio' => $time['start'],
                            'hora_fim' => $time['end'],
                        ]);
                    }
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
        //creame el metodo update
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        // Validación más flexible
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
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

            // Actualizar solo los campos proporcionados
            $serviceData = array_filter([
                'name' => $request->input('name', $service->name),
                'appointment_duration_minutes' => $request->input('appointment_duration_minutes', $service->appointment_duration_minutes),
                'service_price' => $request->input('service_price', $service->service_price),
                'active' => $request->input('active', $service->active),
            ]);

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
                // Eliminar rangos y relaciones anteriores
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

                // Insertar nuevos rangos
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
                        'service_id' => $id,
                        'monday'    => $monday,
                        'tuesday'   => $tuesday,
                        'wednesday' => $wednesday,
                        'thursday'  => $thursday,
                        'friday'    => $friday,
                        'saturday'  => $saturday,
                        'sunday'    => $sunday,
                    ]);
                    // Asignar especialistas para este rango (tabla user_range)
                    foreach ($range['specialist_in_range'] as $specialistId) {
                        $query->table('user_range')->insert([
                            'range_id' => $rangeId,
                            'user_id' => $specialistId,
                        ]);

                        // Insertar los horarios para este rango (tabla times_range)
                        foreach ($range['times'] as $time) {
                            $query->table('times_range')->insert([
                                'range_id' => $rangeId,
                                'hora_inicio' => $time['start'],
                                'hora_fim' => $time['end'],
                            ]);
                        }
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
