<?php

namespace App\Http\Controllers\api\appointments;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class AppointmentsmonthController extends Controller
{
    /**
     * Obtiene todas las citas de un mes, incluyendo una semana antes y una semana después
     * Si no se elige Specialist, agrupa los turnos por día mostrando la cantidad de turnos por día
     * Permite filtrar por servicio, empleado, cliente y estado
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAppointmentsByMonth(Request $request)
    {
        // Validar la solicitud
        $validated = $request->validate([
            'start_date' => 'required|date',
        ]);

        $dbConnection = $request->get('db_connection');

        // Calcular el rango de fechas: una semana antes y una semana después del mes
        $date = Carbon::parse($validated['start_date']);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        // Añadir una semana antes y una semana después
        $startDate = $startOfMonth->copy()->subWeek()->startOfDay();
        $endDate = $endOfMonth->copy()->addWeek()->endOfDay();

        try {
            // Consulta base para obtener las citas
            $query = DB::connection($dbConnection)->table('appointments')
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
                ->whereBetween('appointments.start_date', [$startDate, $endDate]);

            // Filtros manuales
            if ($request->filled('service_id')) {
                $query->where('appointments.service_id', $request->input('service_id'));
            }
            if ($request->filled('employee_id')) {
                $query->where('appointments.employee_id', $request->input('employee_id'));
            }
            if ($request->filled('client_id')) {
                $query->where('appointments.client_id', $request->input('client_id'));
            }
            if ($request->filled('status')) {
                $query->where('appointments.status', $request->input('status'));
            }

            // Verificar si se debe agrupar por día (cuando no se especifica employee_id)
            if (!$request->filled('employee_id')) {
                // Agrupar por día y contar citas
                $appointments = DB::connection($dbConnection)
                    ->table('appointments')
                    ->whereBetween('start_date', [$startDate, $endDate])
                    // Aplicar los mismos filtros aquí
                    ->when($request->filled('service_id'), function ($q) use ($request) {
                        $q->where('service_id', $request->input('service_id'));
                    })
                    ->when($request->filled('client_id'), function ($q) use ($request) {
                        $q->where('client_id', $request->input('client_id'));
                    })
                    ->when($request->filled('status'), function ($q) use ($request) {
                        $q->where('status', $request->input('status'));
                    })
                    ->select([
                        DB::raw('DATE(start_date) as date'),
                        DB::raw('COUNT(*) as total_appointments')
                    ])
                    ->groupBy(DB::raw('DATE(start_date)'))
                    ->orderBy('date')
                    ->get();

                return response()->json([
                    'grouped' => true,
                    'data' => $appointments
                ]);
            } else {
                // Ordenamiento manual
                $sortable = ['start_date', 'end_date', 'status', 'service_name', 'specialist_name'];
                $sort = $request->input('sort', 'start_date');
                $direction = $request->input('direction', 'asc');
                if (in_array($sort, $sortable)) {
                    $query->orderBy($sort, $direction);
                } else {
                    $query->orderBy('start_date', 'asc');
                }

                // Paginación manual
                $perPage = $request->input('per_page', 16);
                $page = $request->input('page', 1);
                $total = $query->count();
                $results = $query->forPage($page, $perPage)->get();

                return response()->json([
                    'grouped' => false,
                    'data' => $results,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
