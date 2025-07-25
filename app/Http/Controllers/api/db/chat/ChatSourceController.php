<?php

namespace App\Http\Controllers\api\db\chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatSourceController extends Controller
{
    // Listar fuentes de chat
    public function index(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $sources = $query->table('chat_sources')->get();
        return response()->json($sources);
    }

    // Crear una nueva fuente de chat
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:chat_sources',
            'token' => 'nullable|json',
        ]);
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        // Si token viene como string, decodificar
        if (isset($validated['token']) && is_string($validated['token'])) {
            $validated['token'] = json_decode($validated['token'], true);
        }
        $id = $query->table('chat_sources')->insertGetId($validated);
        return response()->json(['id' => $id, 'message' => 'Fuente creada'], 201);
    }

    // Mostrar una fuente específica
    public function show(Request $request, $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $source = $query->table('chat_sources')->find($id);
        if (!$source) {
            return response()->json(['error' => 'Fuente no encontrada'], 404);
        }
        return response()->json($source);
    }

    // Actualizar una fuente
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:50|unique:chat_sources,name,' . $id,
            'token' => 'nullable|json',
        ]);
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        if (isset($validated['token']) && is_string($validated['token'])) {
            $validated['token'] = json_decode($validated['token'], true);
        }
        $updated = $query->table('chat_sources')->where('id', $id)->update(array_merge($validated, [
            'updated_at' => now(),
        ]));
        if (!$updated) {
            return response()->json(['error' => 'Fuente no encontrada o sin cambios'], 404);
        }
        return response()->json(['message' => 'Fuente actualizada']);
    }

    // Eliminar una fuente
    public function destroy(Request $request, $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $deleted = $query->table('chat_sources')->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['error' => 'Fuente no encontrada'], 404);
        }
        return response()->json(['message' => 'Fuente eliminada']);
    }
}
