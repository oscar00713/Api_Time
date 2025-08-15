<?php

namespace App\Http\Controllers\api\appointments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AppointmentlastestController extends Controller
{
    /**
     * Muestra los turnos del día ordenados por updated_at y luego created_at (desc)
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $dbConnection = $request->get('db_connection');
        $date = Carbon::parse($validated['date'])->startOfDay();
        $endDate = Carbon::parse($validated['date'])->endOfDay();

        // Mostrar solo turnos desde hace 30 minutos en adelante
        $from = Carbon::now()->subMinutes(30);

        try {
            $query = DB::connection($dbConnection)->table('appointments')
                ->join('clients', 'appointments.client_id', '=', 'clients.id')
                ->join('services', 'appointments.service_id', '=', 'services.id')
                ->join('users', 'appointments.employee_id', '=', 'users.id')
                ->select([
                    'appointments.*',
                    'clients.first_name as client_first_name',
                    'clients.last_name as client_last_name',
                    'services.name as service_name',
                    'users.name as specialist_name',
                    'users.badge_color as specialist_badge_color'
                ])
                ->whereBetween('appointments.start_date', [$date, $endDate])
                ->where('appointments.start_date', '>=', $from);

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

            // Ordenar por updated_at DESC y luego por start_date DESC
            $query->orderBy('appointments.updated_at', 'desc')
                ->orderBy('appointments.start_date', 'desc');

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
}
