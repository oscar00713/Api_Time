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
                'action' => 'required|string|in:block,relase,check',
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
                case 'check':
                    return $this->processCheckAction($request, $query);
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
        $employee = $request->user(); // Corregimos para obtener el usuario autenticado
    
        // Validamos los datos específicos para bloquear
        $validated = $request->validate([
            'day' => 'required|date',
            'time' => 'required|date_format:H:i:s',
            'user_id' => 'required|integer', // ID del especialista
            'service_id' => 'required|integer',
        ]);
    
        // Buscamos bloqueos existentes para el mismo servicio y especialista
        $existingBlock = $query->table('block_appointments')
            ->where('service_id', $validated['service_id'])
            ->where('user_id', $validated['user_id'])
            ->first();
    
        if ($existingBlock) {
            // Si es el mismo empleado, actualizamos el bloqueo existente
            if ($existingBlock->employee_id == $employee->id) {
                $query->table('block_appointments')
                    ->where('id', $existingBlock->id)
                    ->update([
                        'day' => $validated['day'],
                        'time' => $validated['time'],
                        'updated_at' => Carbon::now()
                    ]);
    
                return response()->json([
                    'message' => 'Bloqueo actualizado exitosamente',
                    'block_id' => $existingBlock->id
                ]);
            }
    
            // Si es otro empleado, verificamos si el bloqueo está activo
            $blockTime = Carbon::parse($existingBlock->updated_at ?? $existingBlock->created_at);
            if ($blockTime->diffInMinutes(Carbon::now()) < 15) {
                return response()->json([
                    'error' => 'Este turno ya está siendo reservado por otro empleado',
                    'blocked_by' => $existingBlock->employee_id
                ], 409);
            }
    
            // Eliminamos el bloqueo expirado
            $query->table('block_appointments')
                ->where('id', $existingBlock->id)
                ->delete();
        }
    
        // Verificamos si el mismo empleado tiene otro bloqueo para el mismo servicio
        $employeeExistingBlock = $query->table('block_appointments')
            ->where('service_id', $validated['service_id'])
            ->where('employee_id', $employee->id)
            ->first();
    
        if ($employeeExistingBlock) {
            // Eliminamos el bloqueo previo del empleado
            $query->table('block_appointments')
                ->where('id', $employeeExistingBlock->id)
                ->delete();
        }
    
        // Creamos el nuevo bloqueo
        $blockId = $query->table('block_appointments')->insertGetId([
            'day' => $validated['day'],
            'time' => $validated['time'],
            'user_id' => $validated['user_id'],
            'service_id' => $validated['service_id'],
            'employee_id' => $employee->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    
        return response()->json([
            'message' => 'Turno bloqueado exitosamente',
            'block_id' => $blockId
        ]);
    }

    /**
     * Procesa la acción de liberar un bloqueo
     */
    private function processReleaseAction(Request $request, $query)
    {
        $employee = $request->user();

        // Validamos los datos específicos para liberar
        $validated = $request->validate([
            'block_id' => 'required|integer',
        ]);

        // Verificamos que el bloqueo exista y pertenezca al empleado
        $block = $query->table('block_appointments')
            ->where('id', $validated['block_id'])
            ->where('employee_id', $employee->id)
            ->first();

        if (!$block) {
            return response()->json([
                'error' => 'No se encontró el bloqueo o no tienes permiso para liberarlo'
            ], 404);
        }

        // Eliminamos el bloqueo
        $query->table('block_appointments')
            ->where('id', $validated['block_id'])
            ->delete();

        return response()->json([
            'message' => 'Bloqueo liberado exitosamente'
        ]);
    }

    /**
     * Procesa la acción de verificar un bloqueo
     */
    private function processCheckAction(Request $request, $query)
    {
        // Validamos los datos específicos para verificar
        $validated = $request->validate([
            'day' => 'required|date',
            'time' => 'required|date_format:H:i:s',
            'user_id' => 'required|integer', // ID del especialista
        ]);

        // Verificamos si el turno está bloqueado
        $block = $query->table('block_appointments')
            ->where('day', $validated['day'])
            ->where('time', $validated['time'])
            ->where('user_id', $validated['user_id'])
            ->first();

        if (!$block) {
            return response()->json([
                'blocked' => false
            ]);
        }

        // Verificamos si el bloqueo ha expirado (15 minutos)
        $blockTime = Carbon::parse($block->updated_at ?? $block->created_at);
        if ($blockTime->diffInMinutes(Carbon::now()) >= 15) {
            // Si ha expirado, lo eliminamos
            $query->table('block_appointments')
                ->where('id', $block->id)
                ->delete();

            return response()->json([
                'blocked' => false
            ]);
        }

        return response()->json([
            'blocked' => true,
            'block_info' => $block
        ]);
    }

    /**
     * Limpia los bloqueos expirados (puede ejecutarse mediante un cron job)
     */
    public function cleanExpiredBlocks(Request $request = null)
    {
        try {
            // Si no hay request (llamada desde comando), creamos una
            if (!$request) {
                $request = new Request();
            }

            // Obtenemos todas las conexiones activas
            $connections = config('database.connections');
            $results = [];

            // Para cada conexión, limpiamos los bloqueos expirados
            foreach ($connections as $connectionName => $config) {
                // Saltamos conexiones que no son PostgreSQL
                if ($config['driver'] !== 'pgsql') {
                    continue;
                }

                try {
                    // Verificamos si la conexión está activa
                    if (DB::connection($connectionName)->getDatabaseName()) {
                        $query = DB::connection($connectionName);

                        // Verificamos si existe la tabla block_appointments
                        $tableExists = $query->getSchemaBuilder()->hasTable('block_appointments');

                        if ($tableExists) {
                            // Eliminamos bloqueos más antiguos que 15 minutos
                            $expirationTime = Carbon::now()->subMinutes(15);

                            $deleted = $query->table('block_appointments')
                                ->where('updated_at', '<', $expirationTime)
                                ->delete();

                            $results[$connectionName] = [
                                'status' => 'success',
                                'blocks_removed' => $deleted
                            ];
                        } else {
                            $results[$connectionName] = [
                                'status' => 'skipped',
                                'reason' => 'Table block_appointments does not exist'
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    $results[$connectionName] = [
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'message' => 'Limpieza de bloqueos expirados completada',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
