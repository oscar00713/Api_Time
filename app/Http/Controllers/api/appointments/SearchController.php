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
            if ($request->has('id')) {
                $appointments->where('appointments.id', $request->input('id'));
            }

            // Filtrar por nombre o apellido del cliente
            if ($request->has('client_name')) {
                $clientName = $request->input('client_name');
                $appointments->where(function ($q) use ($clientName) {
                    $q->where('clients.first_name', 'ILIKE', '%' . $clientName . '%')
                        ->orWhere('clients.last_name', 'ILIKE', '%' . $clientName . '%');
                });
            }

            // Filtrar por fecha de cita
            if ($request->has('date')) {
                $appointments->whereDate('appointments.appointment_date', $request->input('date'));
            }

            $appointments = $appointments->orderBy('appointments.start_date', 'desc')->get();

            return response()->json($appointments);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
