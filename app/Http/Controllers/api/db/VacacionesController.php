<?php

namespace App\Http\Controllers\api\db;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class VacacionesController extends Controller
{
    // Listar todas las vacaciones
    public function index(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $vacaciones = DB::connection($dbConnection)
            ->table('vacaciones')
            ->leftJoin('users', 'vacaciones.employee_id', '=', 'users.id')
            ->select('vacaciones.*', 'users.name as employee_name')
            ->get();
        return response()->json($vacaciones);
    }

    // Mostrar una vacación específica
    public function show(Request $request, $id)
    {
        $dbConnection = $request->get('db_connection');
        $vacacion = DB::connection($dbConnection)
            ->table('vacaciones')
            ->leftJoin('users', 'vacaciones.employee_id', '=', 'users.id')
            ->select('vacaciones.*', 'users.name as employee_name')
            ->where('vacaciones.id', $id)
            ->first();

        if (!$vacacion) {
            return response()->json(['message' => 'Vacación no encontrada'], 404);
        }
        return response()->json($vacacion);
    }

    // Crear una nueva vacación
    public function store(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['employee_id', 'start_date', 'end_date', 'type']);
        $id = DB::connection($dbConnection)->table('vacaciones')->insertGetId($data);
        $vacacion = DB::connection($dbConnection)->table('vacaciones')->where('id', $id)->first();

        return response()->json($vacacion, 201);
    }

    // Actualizar una vacación existente
    public function update(Request $request, $id)
    {
        $dbConnection = $request->get('db_connection');
        $vacacion = DB::connection($dbConnection)->table('vacaciones')->where('id', $id)->first();
        if (!$vacacion) {
            return response()->json(['message' => 'Vacación no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'sometimes|integer|exists:users,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'type' => 'sometimes|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['employee_id', 'start_date', 'end_date', 'type']);
        DB::connection($dbConnection)->table('vacaciones')->where('id', $id)->update($data);
        $vacacion = DB::connection($dbConnection)->table('vacaciones')->where('id', $id)->first();

        return response()->json($vacacion);
    }

    // Eliminar una vacación
    public function destroy(Request $request, $id)
    {
        $dbConnection = $request->get('db_connection');
        $vacacion = DB::connection($dbConnection)->table('vacaciones')->where('id', $id)->first();
        if (!$vacacion) {
            return response()->json(['message' => 'Vacación no encontrada'], 404);
        }
        DB::connection($dbConnection)->table('vacaciones')->where('id', $id)->delete();
        return response()->json(['message' => 'Vacación eliminada correctamente']);
    }
}
