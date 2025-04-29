<?php

namespace App\Http\Controllers\api\db;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use App\Services\PartitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AppoimentCRUDController extends Controller
{
    protected $authService;
    protected $partitionService;

    public function __construct(AuthorizationService $authService, PartitionService $partitionService)
    {
        $this->authService = $authService;
        $this->partitionService = $partitionService;
    }

    public function index(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        try {
            $appointments = $query->table('appointments')
                ->join('clients', 'appointments.client_id', '=', 'clients.id')
                ->join('services', 'appointments.service_id', '=', 'services.id')
                ->join('users', 'appointments.employee_id', '=', 'users.id')
                ->select([
                    'appointments.*',
                    'clients.first_name as client_first_name',
                    'clients.last_name as client_last_name',
                    'services.name as service_name',
                    'users.name as specialist_name'
                ])
                ->orderBy('appointments.start_date', 'desc')
                ->get();

            return response()->json($appointments);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // public function store(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'client_id' => 'required|integer',
    //         'service_id' => 'required|integer',
    //         'employee_id' => 'required|integer',
    //         'start_date' => 'required|date',
    //         'end_date' => 'required|date|after:start_date',
    //         'status' => 'required|integer',
    //         'appointment_price' => 'sometimes|numeric',
    //         'paid' => 'sometimes|boolean',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }

    //     $dbConnection = $request->get('db_connection');
    //     $user = $request->get('user');

    //     // Verificar permisos
    //     if (!$this->authService->canAssignAppointment($user, $request->employee_id, $dbConnection)) {
    //         return response()->json([
    //             'error' => 'No tienes permiso para asignar este turno',
    //             'details' => (int)$user['id'] === $request->employee_id
    //                 ? 'Necesitas el permiso "appointments_self_assign" para asignarte turnos a ti mismo'
    //                 : 'Necesitas el permiso "appointments_self_others" para asignar turnos a otros especialistas'
    //         ], 403);
    //     }

    //     $query = DB::connection($dbConnection);

    //     try {
    //         // Verificar si el especialista está disponible
    //         $existingAppointment = $query->table('appointments')
    //             ->where('employee_id', $request->employee_id)
    //             ->where(function($q) use ($request) {
    //                 $q->whereBetween('start_date', [$request->start_date, $request->end_date])
    //                   ->orWhereBetween('end_date', [$request->start_date, $request->end_date]);
    //             })
    //             ->first();

    //         if ($existingAppointment) {
    //             return response()->json(['error' => 'El especialista ya tiene una cita en ese horario'], 409);
    //         }

    //         // Obtener información de comisión del servicio
    //         $userService = $query->table('user_services')
    //             ->where('user_id', $request->employee_id)
    //             ->where('service_id', $request->service_id)
    //             ->first();

    //         // Obtener el precio del servicio
    //         $service = $query->table('services')
    //             ->where('id', $request->service_id)
    //             ->first();

    //         $appointmentPrice = $request->appointment_price ?? ($service ? $service->service_price : 0);

    //         // Calcular comisiones
    //         $commissionType = 'none';
    //         $commissionPercentage = 0;
    //         $commissionFixed = 0;
    //         $commissionTotal = 0;
    //         $commissionPercentageTotal = 0;
    //         $commissionFixedTotal = 0;

    //         if ($userService) {
    //             if ($userService->percentage > 0) {
    //                 $commissionType = 'percentage';
    //                 $commissionPercentage = $userService->percentage;
    //                 $commissionPercentageTotal = ($appointmentPrice * $commissionPercentage) / 100;
    //                 $commissionTotal = $commissionPercentageTotal;
    //             } elseif ($userService->fixed > 0) {
    //                 $commissionType = 'fixed';
    //                 $commissionFixed = $userService->fixed;
    //                 $commissionFixedTotal = $commissionFixed;
    //                 $commissionTotal = $commissionFixedTotal;
    //             }
    //         }

    //         $appointmentData = [
    //             'client_id' => $request->client_id,
    //             'service_id' => $request->service_id,
    //             'employee_id' => $request->employee_id,
    //             'status' => $request->status,
    //             'start_date' => $request->start_date,
    //             'end_date' => $request->end_date,
    //             'user_comission_applied' => $commissionType,
    //             'user_comission_percentage_applied' => $commissionPercentage,
    //             'user_comission_percentage_total' => $commissionPercentageTotal,
    //             'user_comission_fixed_total' => $commissionFixedTotal,
    //             'user_comission_total' => $commissionTotal,
    //             'appointment_price' => $appointmentPrice,
    //             'paid' => $request->paid ?? false,
    //             'paid_date' => $request->paid ? Carbon::now() : null,
    //         ];

    //         // Since start_date is the primary key, we don't need insertGetId
    //         $query->table('appointments')->insert($appointmentData);

    //         return response()->json([
    //             'message' => 'Cita creada exitosamente',
    //             'start_date' => $request->start_date
    //         ], 201);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|integer',
            'appointment_price' => 'nullable|numeric', // Corregido de 'nulllable' a 'nullable'
            'appointment_paid' => 'nullable|boolean', // Corregido de 'nulllable' a 'nullable'
            'appointment_paid_invoice_id' => 'nullable|integer',
            'appointments' => 'required|array',
            'appointments.*.start' => 'required|date',
            'appointments.*.end' => 'required|date|after:appointments.*.start',
            'appointments.*.selectedEmployee' => 'required|integer',
            'appointments.*.service_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dbConnection = $request->get('db_connection');
        $user = $request->get('user');
        $query = DB::connection($dbConnection);

        try {
            DB::beginTransaction();
            foreach ($request->appointments as $appointment) {
                // Verificar permisos para cada empleado
                if (!$this->authService->canAssignAppointment($user, $appointment['selectedEmployee'], $dbConnection)) {
                    return response()->json([
                        'error' => 'No tienes permiso para asignar este turno',
                        'details' => (int)$user['id'] === $appointment['selectedEmployee']
                            ? 'Necesitas el permiso "appointments_self_assign" para asignarte turnos a ti mismo'
                            : 'Necesitas el permiso "appointments_self_others" para asignar turnos a otros especialistas'
                    ], 403);
                }

                // In the store method, modify the specialist availability check
                // Verificar si el especialista está disponible
                $existingAppointment = $query->table('appointments')
                    ->where('employee_id', $appointment['selectedEmployee'])
                    ->where(function ($q) use ($appointment) {
                        // Add 3-minute tolerance for consecutive appointments
                        $startWithTolerance = Carbon::parse($appointment['start'])->addMinutes(3);
                        $endWithTolerance = Carbon::parse($appointment['end'])->subMinutes(3);

                        // Only consider it an overlap if the appointment significantly overlaps
                        $q->where(function ($innerQ) use ($appointment, $startWithTolerance, $endWithTolerance) {
                            $innerQ->where('start_date', '<', $endWithTolerance)
                                ->where('end_date', '>', $startWithTolerance);
                        });
                    })
                    ->first();

                if ($existingAppointment) {
                    return response()->json(['error' => 'El especialista ya tiene una cita en ese horario'], 409);
                }

                // Obtener información de comisión del servicio
                $userService = $query->table('user_services')
                    ->where('user_id', $appointment['selectedEmployee'])
                    ->where('service_id', $appointment['service_id'])
                    ->first();

                // Calcular comisiones
                $commissionType = 'none';
                $commissionPercentage = 0;
                $commissionFixed = 0;
                $commissionTotal = 0;
                $commissionPercentageTotal = 0;
                $commissionFixedTotal = 0;

                if ($userService) {
                    if ($userService->percentage > 0) {
                        $commissionType = 'percentage';
                        $commissionPercentage = $userService->percentage;
                        $commissionPercentageTotal = ($request->appointment_price * $commissionPercentage) / 100;
                        $commissionTotal = $commissionPercentageTotal;
                    } elseif ($userService->fixed > 0) {
                        $commissionType = 'fixed';
                        $commissionFixed = $userService->fixed;
                        $commissionFixedTotal = $commissionFixed;
                        $commissionTotal = $commissionFixedTotal;
                    }
                }

                $appointmentData = [
                    'client_id' => $request->client_id,
                    'service_id' => $appointment['service_id'],
                    'employee_id' => $appointment['selectedEmployee'],
                    'status' => 0, // Assuming default status
                    'start_date' => $appointment['start'],
                    'end_date' => $appointment['end'],
                    'appointment_date' => date('Y-m-d', strtotime($appointment['start'])),
                    'user_comission_applied' => $commissionType,
                    'user_comission_percentage_applied' => $commissionPercentage,
                    'user_comission_percentage_total' => $commissionPercentageTotal,
                    'user_comission_fixed_total' => $commissionFixedTotal,
                    'user_comission_total' => $commissionTotal,
                    'appointment_price' => $request->appointment_price,
                    'paid' => $request->appointment_paid,
                    'paid_date' => $request->appointment_paid ? Carbon::now() : null,
                    // 'appointment_paid_invoice_id' => $request->appointment_paid_invoice_id,
                ];
                // En el método store
                $year = date('Y', strtotime($appointment['start']));
                $this->partitionService->ensureYearPartitionExists($year, $dbConnection);
                // Insert each appointment
                $query->table('appointments')->insert($appointmentData);
            }

            return response()->json(['message' => 'Citas creadas exitosamente'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function show(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        try {
            $appointment = $query->table('appointments')
                ->join('clients', 'appointments.client_id', '=', 'clients.id')
                ->join('services', 'appointments.service_id', '=', 'services.id')
                ->join('users', 'appointments.employee_id', '=', 'users.id')
                ->where('appointments.start_date', $id)
                ->select([
                    'appointments.*',
                    'clients.first_name as client_first_name',
                    'clients.last_name as client_last_name',
                    'services.name as service_name',
                    'users.name as specialist_name'
                ])
                ->first();

            if (!$appointment) {
                return response()->json(['error' => 'Cita no encontrada'], 404);
            }

            return response()->json($appointment);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'sometimes|integer',
            'service_id' => 'sometimes|integer',
            'employee_id' => 'sometimes|integer',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'status' => 'sometimes|integer',
            'appointment_price' => 'sometimes|numeric',
            'paid' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dbConnection = $request->get('db_connection');
        $user = $request->get('user');
        $query = DB::connection($dbConnection);

        // Si se está cambiando el especialista, verificar permisos
        if ($request->has('employee_id')) {
            if (!$this->authService->canAssignAppointment($user, $request->employee_id, $dbConnection)) {
                return response()->json([
                    'error' => 'No tienes permiso para reasignar este turno',
                    'details' => (int)$user['id'] === $request->employee_id
                        ? 'Necesitas el permiso "appointments_self_assign" para asignarte turnos a ti mismo'
                        : 'Necesitas el permiso "appointments_self_others" para asignar turnos a otros especialistas'
                ], 403);
            }
        }

        try {
            $appointment = $query->table('appointments')->where('start_date', $id)->first();
            if (!$appointment) {
                return response()->json(['error' => 'Cita no encontrada'], 404);
            }

            // Verificar disponibilidad si se cambia la fecha o el especialista
            if ($request->has('start_date') || $request->has('end_date') || $request->has('employee_id')) {
                $startDate = $request->start_date ?? $appointment->start_date;
                $endDate = $request->end_date ?? $appointment->end_date;
                $employeeId = $request->employee_id ?? $appointment->employee_id;

                // In the update method, modify the availability check
                $existingAppointment = $query->table('appointments')
                    ->where('start_date', '!=', $id)
                    ->where('employee_id', $employeeId)
                    ->where(function ($q) use ($startDate, $endDate) {
                        // Add 3-minute tolerance for consecutive appointments
                        $startWithTolerance = Carbon::parse($startDate)->addMinutes(3);
                        $endWithTolerance = Carbon::parse($endDate)->subMinutes(3);

                        // Only consider it an overlap if the appointment significantly overlaps
                        $q->where(function ($innerQ) use ($startDate, $endDate, $startWithTolerance, $endWithTolerance) {
                            $innerQ->where('start_date', '<', $endWithTolerance)
                                ->where('end_date', '>', $startWithTolerance);
                        });
                    })
                    ->first();

                if ($existingAppointment) {
                    return response()->json(['error' => 'El especialista ya tiene una cita en ese horario'], 409);
                }
            }

            $updateData = array_filter($request->all(), function ($key) {
                return in_array($key, [
                    'client_id',
                    'service_id',
                    'employee_id',
                    'end_date',
                    'status',
                    'appointment_price',
                    'paid'
                ]);
            }, ARRAY_FILTER_USE_KEY);

            // If paid status changes to true, set paid_date
            if (isset($updateData['paid']) && $updateData['paid'] && !$appointment->paid) {
                $updateData['paid_date'] = Carbon::now();
            }

            // Recalculate commissions if service or employee changes
            if ($request->has('service_id') || $request->has('employee_id') || $request->has('appointment_price')) {
                $serviceId = $request->service_id ?? $appointment->service_id;
                $employeeId = $request->employee_id ?? $appointment->employee_id;
                $appointmentPrice = $request->appointment_price ?? $appointment->appointment_price;

                $userService = $query->table('user_services')
                    ->where('user_id', $employeeId)
                    ->where('service_id', $serviceId)
                    ->first();

                // Calculate commissions
                $commissionType = 'none';
                $commissionPercentage = 0;
                $commissionTotal = 0;
                $commissionPercentageTotal = 0;
                $commissionFixedTotal = 0;

                if ($userService) {
                    if ($userService->percentage > 0) {
                        $commissionType = 'percentage';
                        $commissionPercentage = $userService->percentage;
                        $commissionPercentageTotal = ($appointmentPrice * $commissionPercentage) / 100;
                        $commissionTotal = $commissionPercentageTotal;
                    } elseif ($userService->fixed > 0) {
                        $commissionType = 'fixed';
                        $commissionFixedTotal = $userService->fixed;
                        $commissionTotal = $commissionFixedTotal;
                    }
                }

                $updateData['user_comission_applied'] = $commissionType;
                $updateData['user_comission_percentage_applied'] = $commissionPercentage;
                $updateData['user_comission_percentage_total'] = $commissionPercentageTotal;
                $updateData['user_comission_fixed_total'] = $commissionFixedTotal;
                $updateData['user_comission_total'] = $commissionTotal;
            }

            // En el método update, cuando creas un nuevo registro
            if ($request->has('start_date')) {
                // Create new appointment with updated data
                $newAppointmentData = array_merge((array)$appointment, $updateData);
                $newAppointmentData['start_date'] = $request->start_date;
                $newAppointmentData['appointment_date'] = date('Y-m-d', strtotime($request->start_date)); // Añadir esta línea

                // Delete old appointment
                $query->table('appointments')->where('start_date', $id)->delete();

                // Insert new appointment
                $query->table('appointments')->insert($newAppointmentData);

                return response()->json([
                    'message' => 'Cita actualizada exitosamente',
                    'new_start_date' => $request->start_date
                ]);
            } else {
                // Just update the existing appointment
                $query->table('appointments')->where('start_date', $id)->update($updateData);

                return response()->json(['message' => 'Cita actualizada exitosamente']);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        try {
            $appointment = $query->table('appointments')->where('start_date', $id)->first();
            if (!$appointment) {
                return response()->json(['error' => 'Cita no encontrada'], 404);
            }

            $query->table('appointments')->where('start_date', $id)->delete();

            return response()->json(['message' => 'Cita eliminada exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
