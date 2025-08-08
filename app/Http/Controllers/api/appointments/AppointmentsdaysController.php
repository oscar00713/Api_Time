<?php

namespace App\Http\Controllers\api\appointments;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class AppointmentsdaysController extends Controller
{
    /**
     * Obtiene todas las citas del día especificado
     * Permite filtrar por servicio, empleado, cliente y estado
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAppointmentsByDay(Request $request)
    {
        // Validar la solicitud
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $dbConnection = $request->get('db_connection');
        $date = Carbon::parse($validated['date'])->startOfDay();
        $endDate = Carbon::parse($validated['date'])->endOfDay();

        // Mostrar solo turnos desde hace 30 minutos en adelante
        //   $from = Carbon::now()->subMinutes(30);

        try {
            $query = DB::connection($dbConnection)->table('appointments')
                ->join('clients', 'appointments.client_id', '=', 'clients.id')
                ->join('services', 'appointments.service_id', '=', 'services.id')
                ->join('users', 'appointments.employee_id', '=', 'users.id')
                ->select([
                    'appointments.*',
                    'clients.first_name as client_first_name',
                    'clients.last_name as client_last_name',
                    'clients.banned as client_banned',
                    'services.name as service_name',
                    'users.name as specialist_name',
                    'users.badge_color as specialist_badge_color'
                ])
                ->whereBetween('appointments.start_date', [$date, $endDate]);
            //  ->where('appointments.start_date', '>=', $from); // <-- Esta línea filtra desde hace 30 minutos

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

            // Ordenamiento manual
            $sortable = ['start_date', 'end_date', 'status', 'service_name', 'specialist_name'];
            $sort = $request->input('sort', 'start_date');
            $direction = $request->input('direction', 'asc');
            if (in_array($sort, $sortable)) {
                $query->orderBy($sort, $direction);
            } else {
                $query->orderBy('start_date', 'asc');
            }

            // Paginación
            $perPage = $request->input('per_page', 16);
            $page = $request->input('page', 1);
            $total = $query->count();
            $results = $query->forPage($page, $perPage)->get();

            return response()->json([
                'data' => $results,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function checkLastUpdate(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        try {
            // Obtener el updated_at más reciente de la tabla appointments

            $lastUpdated = $query->table('appointments')
                ->whereDate('start_date', $validated['date'])
                ->orderByDesc('updated_at')
                ->value('updated_at');

            // Obtener appointment_id de llamadas previas a hace 2 minutos en la fecha solicitada
            $calling = $query->table('call')
                ->whereDate('fecha', $validated['date'])
                ->where('fecha', '<', now()->subMinutes(2))
                ->orderByDesc('fecha')
                ->pluck('appointment_id');

            return response()->json([
                'last_updated_at' => $lastUpdated,
                'calling' => $calling
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
