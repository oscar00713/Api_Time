<?php

namespace App\Http\Controllers\api\client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientSummaryController extends Controller
{
    public function show(Request $request, $client_id)
    {
        $dbConnection = $request->get('db_connection');

        // Obtener datos básicos del cliente
        $client = DB::connection($dbConnection)->table('clients')->where('id', $client_id)->first();

        if (!$client) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        // Cantidad de turnos pasados (appointments con end_date < ahora y status != 4)
        $appointmentsPast = DB::connection($dbConnection)
            ->table('appointments')
            ->where('client_id', $client_id)
            ->where('status', '!=', 4) // No cancelados
            ->where('end_date', '<', now())
            ->count();

        // Total gastado (campo total_spent en clients, si no existe, sumar de appointments)
        $totalSpent = $client->total_spent ?? null;
        // Si quieres calcularlo desde appointments:
        // $totalSpent = DB::connection($dbConnection)
        //     ->table('appointments')
        //     ->where('client_id', $client_id)
        //     ->where('status', '!=', 4)
        //     ->sum('appointment_price');

        // Cantidad de facturas (no hay tabla invoices, dejar null)
        $invoicesCount = null;
        // Si existiera la tabla invoices:
        // $invoicesCount = DB::connection($dbConnection)
        //     ->table('invoices')
        //     ->where('client_id', $client_id)
        //     ->count();

        // Pendiente de pago (campo owe_money en clients)
        $pending = $client->owe_money ?? null;

        // Suscrito a notificaciones (email o whatsapp)
        $subscribedNotifications = ($client->receive_notifications_email || $client->receive_notifications_whatsapp) ? true : false;

        // ¿Está baneado?
        $isBanned = $client->banned ?? false;

        return response()->json([
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'birthday' => $client->birthday,
            'email' => $client->email,
            'phone' => $client->phone,
            'appointments_past' => $appointmentsPast,
            'total_spent' => $totalSpent,
            'invoices' => $invoicesCount, // Si existiera la tabla invoices, aquí iría el conteo real
            'pending' => $pending,
            'subscribed_notifications' => $subscribedNotifications,
            'isBanned' => $isBanned,
        ]);
    }
}
