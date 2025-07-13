<?php

namespace App\Http\Controllers\api\lobby;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class CallController extends Controller
{
    //funcion para llamar la cliente
    public function callClient(Request $request)
    {

        $clientId = $request->input('client_id');
        $fecha = $request->input('fecha');
        // Aquí puedes realizar la lógica para llamar al cliente con el ID proporcionado
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $query->table('call')->insert([
            'client_id' => $clientId,
            'fecha' => $fecha,
        ]);
        //devolver el data del cliente
        $client = $query->table('clients')->find($clientId);
        return response()->json(['data' => $client]);

        // Por ejemplo, puedes enviar una notificación al cliente o iniciar una llamado
    }
}
