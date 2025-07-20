<?php

namespace App\Http\Controllers\api\appointments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class SearchController extends Controller
{

    //funcion search del appointments
    public function index(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $searchTerm = $request->input('filter.all');
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
                ]);

            // Filtrar por ID de cita
            if ($searchTerm) {
                $appointments->where(function ($query) use ($searchTerm) {
                    if (is_numeric($searchTerm)) {
                        $query->where('appointments.id', $searchTerm);
                    }

                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $searchTerm)) {
                        $query->orWhereDate('appointments.appointment_date', $searchTerm);
                    }

                    $query->orWhere(function ($q) use ($searchTerm) {
                        $q->where('clients.first_name', 'ILIKE', "%{$searchTerm}%")
                            ->orWhere('clients.last_name',  'ILIKE', "%{$searchTerm}%")
                            ->orWhere('services.name',      'ILIKE', "%{$searchTerm}%")
                            ->orWhere('users.name',         'ILIKE', "%{$searchTerm}%")
                            ->orWhere('appointments.status', 'ILIKE', "%{$searchTerm}%");
                    });
                });
            }

            $appointments = $appointments->orderBy('appointments.start_date', 'desc')->get();

            return response()->json($appointments);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
