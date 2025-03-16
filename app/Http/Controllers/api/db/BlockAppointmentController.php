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

        //! no puede haber 2 bloqueos por el mismo empleado
        //* si se manda un bloqueo con el mismo usuario y ya hay uno liberar el anterior y crear el nuevo
        //* si otro empleado manda el mismo servicio y diferente especialista que no esta en la tabla guardarlo como bloqueo para ese especialista pero si es el mismo servicio y especialista mandar un error que ya fue guardado ese turno

        try {
            // Validamos la acción
            $validated = $request->validate([
                'action' => 'required|string|in:block',
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
        $user = $request->get('user'); // Corregimos para obtener el usuario autenticado

        if (!$user) {
            return response()->json(['error' => 'No se especificó el usuario'], 400);
        }

        // Validamos los datos específicos para bloquear
        $validated = $request->validate([
            'datetime_start' => 'required|date',
            'employee_id' => 'required|integer', // ID del especialista
            'service_id' => 'required|integer',
        ]);

        // Eliminamos todos los bloqueos anteriores del operador actual
        $query->table('block_appointments')
            ->orWhere('created_at', '<', Carbon::now()->subMinutes(15))
            ->delete();

        // Buscamos bloqueos existentes para el mismo servicio y especialista
        $existingBlock = $query->table('block_appointments')
            ->where('service_id', $validated['service_id'])
            ->where('datetime_start', $validated['datetime_start'])
            ->where('employee_id', $validated['employee_id'])
            ->first();

        if ($existingBlock && $existingBlock->user_id !== $user['id']) {
            // Si ya existe un bloqueo por otra persona, devolvemos error
            return response()->json([
                'message' => 'El turno ya está bloqueado por otra persona'
            ], 409);
        } elseif ($existingBlock && $existingBlock->user_id === $user['id']) {
            // Si ya existe un bloqueo por la misma persona, lo eliminamos
            $query->table('block_appointments')
                ->where('id', $existingBlock->id)
                ->delete();
            return response()->json([
                'message' => 'Turno desbloqueado exitosamente',
            ]);
        } else {
            // Creamos el nuevo bloqueo
            $blockId = $query->table('block_appointments')
                ->insertGetId([
                    'datetime_start' => $validated['datetime_start'],
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
}
