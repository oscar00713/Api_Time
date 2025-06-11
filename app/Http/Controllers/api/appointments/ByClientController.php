<?php

namespace App\Http\Controllers\api\appointments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ByClientController extends Controller
{
    public function index(Request $request, $client_id)
    {
        $dbConnection = $request->get('db_connection');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');
        $sort = $request->input('sort', 'start_date');
        $direction = $request->input('direction', 'desc');
        $onlyCount = $request->boolean('onlycount', false);

        if (!$client_id) {
            return response()->json(['error' => 'client_id es requerido'], 422);
        }

        $query = DB::connection($dbConnection)->table('appointments')
            ->join('services', 'appointments.service_id', '=', 'services.id')
            ->join('users', 'appointments.employee_id', '=', 'users.id')
            ->select([
                'appointments.*',
                'services.name as service_name',
                'users.name as specialist_name'
            ])
            ->where('appointments.client_id', $client_id);

        // Búsqueda por nombre de servicio o especialista
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('services.name', 'ILIKE', "%$search%")
                    ->orWhere('users.name', 'ILIKE', "%$search%");
            });
        }

        // Solo contar
        if ($onlyCount) {
            $count = $query->count();
            return response()->json(['count' => $count]);
        }

        // Ordenamiento
        $sortable = ['start_date', 'end_date', 'status', 'service_name', 'specialist_name'];
        if (!in_array($sort, $sortable)) {
            $sort = 'start_date';
        }
        $query->orderBy($sort, $direction);

        // Paginación manual
        $total = $query->count();
        $results = $query->forPage($page, $perPage)->get();

        return response()->json([
            'data' => $results,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
        ]);
    }
}
