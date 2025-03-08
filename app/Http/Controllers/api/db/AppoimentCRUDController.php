<?php

namespace App\Http\Controllers\api\db;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AppoimentCRUDController extends Controller
{
    public function index(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        try {
            $appointments = $query->table('appointments')
                ->join('clients', 'appointments.client_id', '=', 'clients.id')
                ->join('services', 'appointments.service_id', '=', 'services.id')
                ->join('users', 'appointments.user_id', '=', 'users.id')
                ->select([
                    'appointments.*',
                    'clients.first_name as client_first_name',
                    'clients.last_name as client_last_name',
                    'services.name as service_name',
                    'users.name as specialist_name'
                ])
                ->orderBy('appointments.date', 'desc')
                ->get();

            return response()->json($appointments);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|integer',
            'service_id' => 'required|integer',
            'user_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        try {
            // Verificar si el especialista está disponible
            $existingAppointment = $query->table('appointments')
                ->where('user_id', $request->user_id)
                ->where(function($q) use ($request) {
                    $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->start_date, $request->end_date]);
                })
                ->first();

            if ($existingAppointment) {
                return response()->json(['error' => 'El especialista ya tiene una cita en ese horario'], 409);
            }

            // Obtener información de comisión del servicio
            $userService = $query->table('user_services')
                ->where('user_id', $request->user_id)
                ->where('service_id', $request->service_id)
                ->first();

            $appointmentData = [
                'date' => $request->start_date,
                'client_id' => $request->client_id,
                'service_id' => $request->service_id,
                'user_id' => $request->user_id,
                'status' => $request->status,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'user_comission_applied' => $userService ? 1 : 0,
                'user_comission_percentage' => $userService ? $userService->percentage : 0,
                'user_comission_fixed' => $userService ? $userService->fixed : 0,
            ];

            $id = $query->table('appointments')->insertGetId($appointmentData);

            return response()->json(['message' => 'Cita creada exitosamente', 'id' => $id], 201);
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
                ->join('users', 'appointments.user_id', '=', 'users.id')
                ->where('appointments.id', $id)
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
            'user_id' => 'sometimes|integer',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'status' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        try {
            $appointment = $query->table('appointments')->where('id', $id)->first();
            if (!$appointment) {
                return response()->json(['error' => 'Cita no encontrada'], 404);
            }

            // Verificar disponibilidad si se cambia la fecha o el especialista
            if ($request->has('start_date') || $request->has('end_date') || $request->has('user_id')) {
                $startDate = $request->start_date ?? $appointment->start_date;
                $endDate = $request->end_date ?? $appointment->end_date;
                $userId = $request->user_id ?? $appointment->user_id;

                $existingAppointment = $query->table('appointments')
                    ->where('id', '!=', $id)
                    ->where('user_id', $userId)
                    ->where(function($q) use ($startDate, $endDate) {
                        $q->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate]);
                    })
                    ->first();

                if ($existingAppointment) {
                    return response()->json(['error' => 'El especialista ya tiene una cita en ese horario'], 409);
                }
            }

            $updateData = array_filter($request->all(), function($key) {
                return in_array($key, ['client_id', 'service_id', 'user_id', 'start_date', 'end_date', 'status']);
            }, ARRAY_FILTER_USE_KEY);

            if ($request->has('service_id') || $request->has('user_id')) {
                $userService = $query->table('user_services')
                    ->where('user_id', $request->user_id ?? $appointment->user_id)
                    ->where('service_id', $request->service_id ?? $appointment->service_id)
                    ->first();

                $updateData['user_comission_applied'] = $userService ? 1 : 0;
                $updateData['user_comission_percentage'] = $userService ? $userService->percentage : 0;
                $updateData['user_comission_fixed'] = $userService ? $userService->fixed : 0;
            }

            $query->table('appointments')->where('id', $id)->update($updateData);

            return response()->json(['message' => 'Cita actualizada exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        try {
            $appointment = $query->table('appointments')->where('id', $id)->first();
            if (!$appointment) {
                return response()->json(['error' => 'Cita no encontrada'], 404);
            }

            $query->table('appointments')->where('id', $id)->delete();

            return response()->json(['message' => 'Cita eliminada exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
