<?php

namespace App\Http\Controllers\api\db;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AppointmentStatusController extends Controller
{
    /**
     * Cambia el estado de la cita de checked_in a in_room y actualiza date_in_room.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeStatus(Request $request)
    {
        $validated = $request->validate([
            'appointment_id' => 'required|integer',
        ]);

        $dbConnection = $request->get('db_connection');
        if (!$dbConnection) {
            return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
        }
        $query = DB::connection($dbConnection);

        $appointment = $query->table('appointments')
            ->where('id', $validated['appointment_id'])
            ->first();

        if (!$appointment) {
            return response()->json(['error' => 'Cita no encontrada'], 404);
        }

        // "checked_in" = 1, "in_room" = 2
        if ($appointment->status != 1) {
            return response()->json([
                'error' => 'El estado de la cita debe ser checked_in para cambiar a in_room'
            ], 422);
        }

        $now = Carbon::now();
        $updated = $query->table('appointments')
            ->where('id', $validated['appointment_id'])
            ->update([
                'status' => 2,
                'date_in_room' => $now,
                'updated_at' => $now,
            ]);

        if (!$updated) {
            return response()->json(['error' => 'No se pudo actualizar el estado'], 500);
        }

        $updatedAppointment = $query->table('appointments')
            ->where('id', $validated['appointment_id'])
            ->first();

        return response()->json([
            'message' => 'Estado actualizado a in_room',
            'appointment' => $updatedAppointment
        ], 200);
    }
}
