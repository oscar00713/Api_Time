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

        try {
            // Usar QueryBuilder de Spatie para permitir filtrado y ordenamiento
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
                ->whereBetween('appointments.start_date', [$date, $endDate]);

            $appointments = QueryBuilder::for($query)
                ->allowedFilters([
                    AllowedFilter::exact('service_id'),
                    AllowedFilter::exact('employee_id'),
                    AllowedFilter::exact('client_id'),
                    AllowedFilter::exact('status'),
                ])
                ->allowedSorts(['start_date', 'end_date', 'status', 'service_name', 'specialist_name'])
                ->defaultSort('start_date')
                ->paginate($request->input('per_page', 16))
                ->appends($request->query());

            return response()->json($appointments);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
