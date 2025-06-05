<?php

namespace App\Http\Controllers\api\appointments;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

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

            // Aplicar filtros usando QueryBuilder
            $queryBuilder = QueryBuilder::for($query)
                ->allowedFilters([
                    AllowedFilter::exact('service_id'),
                    AllowedFilter::exact('employee_id'),
                    AllowedFilter::exact('client_id'),
                    AllowedFilter::exact('status'),
                ])
                ->allowedSorts(['start_date', 'end_date', 'status', 'service_name', 'specialist_name'])
                ->defaultSort('start_date');

            // Verificar si se debe agrupar por día (cuando no se especifica employee_id)
            if (!$request->has('filter.employee_id')) {
                // Agrupar por día y contar citas
                $appointments = DB::connection($dbConnection)
                    ->table('appointments')
                    ->whereBetween('start_date', [$startDate, $endDate])
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
                // Si se especifica employee_id, devolver las citas individuales
                $appointments = $queryBuilder
                    ->paginate($request->input('per_page', 16))
                    ->appends($request->query());

                return response()->json($appointments);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
