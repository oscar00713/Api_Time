<?php

namespace App\Http\Controllers\api\service;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class ServiceSpecialController extends Controller
{
    public function openDays(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $daysOfWeek = [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday',
        ];

        // Consultar todos los rangos
        $rangos = DB::connection($dbConnection)->table('rangos')->get();

        // Inicializar array de días abiertos
        $openDays = [];

        foreach ($daysOfWeek as $num => $day) {
            // Si algún rango tiene ese día en true, el negocio está abierto ese día
            if ($rangos->where($day, true)->count() > 0) {
                $openDays[] = $num; // Puedes devolver el número o el nombre, según prefieras
            }
        }

        return response()->json([
            'open_days' => $openDays, // Ejemplo: [1,2,3,4,5] para lunes a viernes
            // 'open_days_names' => array_map(fn($n) => $daysOfWeek[$n], $openDays), // Si quieres los nombres
        ]);
    }
}
