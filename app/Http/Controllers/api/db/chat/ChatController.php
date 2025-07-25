<?php

namespace App\Http\Controllers\api\db\chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    // Listar mensajes de chat con paginación
    public function index(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $perPage = $request->query('perPage', 15);
        $page = $request->query('page', 1);
        $messages = $query->table('chat_log')->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
        return response()->json($messages);
    }

    // Crear un nuevo mensaje de chat
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|integer',
            'method_id' => 'required|integer',
            'subject' => 'nullable|string',
            'specialist_id' => 'required|integer',
            'content' => 'required|string',
            'status' => 'nullable|string',
        ]);
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $id = $query->table('chat_log')->insertGetId($validated);
        return response()->json(['id' => $id, 'message' => 'Mensaje creado'], 201);
    }

    // Mostrar un mensaje específico
    public function show(Request $request, $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $msg = $query->table('chat_log')->find($id);
        if (!$msg) {
            return response()->json(['error' => 'Mensaje no encontrado'], 404);
        }
        return response()->json($msg);
    }

    // Actualizar un mensaje
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'subject' => 'nullable|string',
            'content' => 'nullable|string',
            'status' => 'nullable|string',
        ]);
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $updated = $query->table('chat_log')->where('id', $id)->update(array_merge($validated, [
            'updated_at' => now(),
        ]));
        if (!$updated) {
            return response()->json(['error' => 'Mensaje no encontrado o sin cambios'], 404);
        }
        return response()->json(['message' => 'Mensaje actualizado']);
    }

    // Eliminar un mensaje
    public function destroy(Request $request, $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $deleted = $query->table('chat_log')->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['error' => 'Mensaje no encontrado'], 404);
        }
        return response()->json(['message' => 'Mensaje eliminado']);
    }
}
