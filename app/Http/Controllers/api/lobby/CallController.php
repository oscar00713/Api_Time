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

        $appointmentId = $request->input('appointment_id');
        $appointmentDate = $request->input('appointment_date');


        // Aquí puedes realizar la lógica para llamar al cliente con el ID proporcionado
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $query->table('calls')->insert([
            'appointment_id' => $appointmentId,
            'appointment_date' => $appointmentDate,
        ]);
        //devolver el data del cliente

        return response()->json(['success' => true]);

        // Por ejemplo, puedes enviar una notificación al cliente o iniciar una llamado
    }
}
