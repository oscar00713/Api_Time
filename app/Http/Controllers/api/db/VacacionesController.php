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

    // Crear nuevas vacaciones para varios empleados
    public function store(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $validator = Validator::make($request->all(), [
            'selectedSpecialists' => 'required|array|min:1',
            'selectedSpecialists.*' => 'integer|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $appointmentsToReschedule = [];
        foreach (
            $request->selectedSpecialists
            as $employee_id
        ) {
            // Buscar turnos asignados en el rango de vacaciones
            $appointments = DB::connection($dbConnection)->table('appointments')
                ->where('employee_id', $employee_id)
                ->where(function ($q) use ($request) {
                    $q->where('start_date', '<=', $request->end_date)
                        ->where('end_date', '>=', $request->start_date);
                })
                ->get();

            if ($appointments->count() > 0) {
                foreach ($appointments as $appt) {
                    $appointmentsToReschedule[] = $appt;
                }
            }
        }

        // Si hay turnos que deben posponerse, devolverlos y NO registrar las vacaciones aún
        if (count($appointmentsToReschedule) > 0) {
            return response()->json([
                'message' => 'Existen turnos asignados en el rango de vacaciones. Deben ser pospuestos antes de registrar las vacaciones.',
                'appointments_to_reschedule' => $appointmentsToReschedule
            ], 409);
        }

        // Si no hay turnos, registrar las vacaciones normalmente
        $vacaciones = [];
        foreach (
            $request->selectedSpecialists
            as $employee_id
        ) {
            $data = [
                'employee_id' => $employee_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'type' => $request->type,
            ];
            $id = DB::connection($dbConnection)->table('vacaciones')->insertGetId($data);
            $vacacion = DB::connection($dbConnection)->table('vacaciones')->where('id', $id)->first();
            $vacaciones[] = $vacacion;
        }

        return response()->json($vacaciones, 201);
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
