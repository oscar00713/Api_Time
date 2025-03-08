<?php

namespace App\Http\Controllers\api\user_option;

use App\Models\UserOptions;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserOptionsController extends Controller
{
    public function index()
    {

        $userOptions = UserOptions::all();

        return response()->json($userOptions);
    }

    public function createOrUpdate(Request $request)
    {
        // Validar los datos de entrada
        $user = $request->user;
        $name = $request->name;
        $value = $request->value;


        // Crear o actualizar el registro
        $userOption = UserOptions::updateOrCreate(
            [
                'user_id' => $user->id, // Condiciones para buscar el registro
                'name' => $name, // Nombre del campo a actualizar o establecer
            ],
            [
                'value' => $value, // Valor del campo a actualizar o establecer
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $userOption,
            'message' => 'User option created or updated successfully',
        ]);
    }



    public function destroy(Request $request) {}
}
