<?php

namespace App\Http\Controllers\api\db;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BlockAppointmentController extends Controller
{
    /**
     * Gestiona las operaciones de bloqueo de turnos (crear, liberar, verificar)
     */
    public function manageBlock(Request $request)
    {
        try {
            // Validamos la acción
            $validated = $request->validate([
                'action' => 'required|string|in:block,release,releaseAll',
            ]);

            // Configuramos la conexión
            $connection = $request->get('db_connection');
            if (!$connection) {
                return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
            }

            $query = DB::connection($connection);

            // Ejecutamos la acción correspondiente
            switch ($validated['action']) {
                case 'block':
                    return $this->processBlockAction($request, $query);
                case 'release':
                    return $this->processReleaseAction($request, $query);
                case 'releaseAll':
                    return $this->processReleaseAllAction($request, $query);
                default:
                    return response()->json(['error' => 'Acción no válida'], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'connection' => $connection ?? 'no connection specified'
            ], 500);
        }
    }

    /**
     * Procesa la acción de bloquear un turno
     */
    private function processBlockAction(Request $request, $query)
    {
        $user = $request->get('user');

        if (!$user) {
            return response()->json(['error' => 'No se especificó el usuario'], 400);
        }

        // Validamos los datos específicos para bloquear
        $validated = $request->validate([
            'datetime_start' => 'required|date',
            'datetime_end' => 'required|date|after:datetime_start',
            'employee_id' => 'required|integer',
            'service_id' => 'required|integer',
        ]);

        // Limpiamos bloqueos antiguos (más de 15 minutos)
        $query->table('block_appointments')
            ->where('created_at', '<', Carbon::now()->subMinutes(15))
            ->delete();

        // Buscamos bloqueos existentes para el mismo servicio y especialista
        $existingBlock = $query->table('block_appointments')
            ->where('service_id', $validated['service_id'])
            ->where('datetime_start', $validated['datetime_start'])
            ->where('employee_id', $validated['employee_id'])
            ->first();


        // Verificamos si existe un bloqueo del mismo usuario para el mismo servicio y hora pero diferente especialista
        $existingUserBlock = $query->table('block_appointments')
            ->where('service_id', $validated['service_id'])
            ->where('datetime_start', $validated['datetime_start'])
            ->where('user_id', $user['id'])
            ->where('employee_id', '!=', $validated['employee_id'])
            ->first();

        // Si existe un bloqueo del mismo usuario para el mismo servicio y hora pero diferente especialista, lo eliminamos
        if ($existingUserBlock) {
            $query->table('block_appointments')
                ->where('id', $existingUserBlock->id)
                ->delete();
        }

        if ($existingBlock && $existingBlock->user_id !== $user['id']) {
            // Si ya existe un bloqueo por otra persona, devolvemos error
            return response()->json([
                'message' => 'error',
                'details' => 'Este turno ya ha sido bloqueado por otro usuario'
            ], 409);
        } else {
            // Creamos el nuevo bloqueo
            $blockId = $query->table('block_appointments')
                ->insertGetId([
                    'datetime_start' => $validated['datetime_start'],
                    'datetime_end' => $validated['datetime_end'],
                    'employee_id' => $validated['employee_id'],
                    'service_id' => $validated['service_id'],
                    'user_id' => $user['id'],
                    'created_at' => Carbon::now(),
                ]);

            return response()->json([
                'message' => 'Turno bloqueado exitosamente',
                'block_id' => $blockId
            ]);
        }
    }

    /**
     * Procesa la acción de liberar un turno específico
     */
    private function processReleaseAction(Request $request, $query)
    {
        $user = $request->get('user');

        if (!$user) {
            return response()->json(['error' => 'No se especificó el usuario'], 400);
        }

        // Validamos los datos específicos para liberar
        $validated = $request->validate([
            'datetime_start' => 'required|date',
            'employee_id' => 'required|integer',
            'service_id' => 'required|integer',
        ]);

        // Buscamos el bloqueo específico
        $existingBlock = $query->table('block_appointments')
            ->where('service_id', $validated['service_id'])
            ->where('datetime_start', $validated['datetime_start'])
            ->where('employee_id', $validated['employee_id'])
            ->first();

        if (!$existingBlock) {
            return response()->json([
                'message' => 'No se encontró ningún bloqueo para este turno'
            ], 404);
        }

        // Solo el usuario que creó el bloqueo puede liberarlo
        if ($existingBlock->user_id !== $user['id']) {
            return response()->json([
                'message' => 'error',
                'details' => 'No tienes permiso para liberar este turno'
            ], 403);
        }

        // Eliminamos el bloqueo
        $query->table('block_appointments')
            ->where('id', $existingBlock->id)
            ->delete();

        return response()->json([
            'message' => 'Turno liberado exitosamente'
        ]);
    }

    /**
     * Procesa la acción de liberar todos los turnos del usuario actual
     */
    private function processReleaseAllAction(Request $request, $query)
    {
        $user = $request->get('user');

        if (!$user) {
            return response()->json(['error' => 'No se especificó el usuario'], 400);
        }

        // Contamos cuántos bloqueos tiene el usuario
        $blockCount = $query->table('block_appointments')
            ->where('user_id', $user['id'])
            ->count();

        // Eliminamos todos los bloqueos del usuario
        $query->table('block_appointments')
            ->where('user_id', $user['id'])
            ->delete();

        return response()->json([
            'message' => 'Se han liberado todos tus turnos bloqueados',
            'count' => $blockCount
        ]);
    }
}
