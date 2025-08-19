<?php

namespace App\Http\Controllers\api\history;



use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HistoryController extends Controller
{
    // Listar todos los registros
    public function index(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        if (!$dbConnection) {
            return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
        }
        $history = DB::connection($dbConnection)->table('history')->orderByDesc('created_at')->get();
        return response()->json($history, 200);
    }

    // Crear un nuevo registro
    public function store(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        if (!$dbConnection) {
            return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
        }
        $validated = $request->validate([
            'type' => 'required|string|max:20',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'created_by' => 'required|integer|exists:users,id',
            'file' => 'nullable|file|max:10240' // Máx 10MB
        ]);

        $data = [
            'type' => $validated['type'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'created_at' => now(),
            'created_by' => $validated['created_by'],
        ];

        // Si es tipo FILE y viene archivo, guardar y poner la ruta en description
        if (($validated['type'] ?? '') === 'FILE' && $request->hasFile('file')) {
            $userId = $validated['created_by'];
            $path = $request->file('file')->store("history_files/{$userId}", 'public');
            $data['description'] = Storage::url($path);
        }

        $id = DB::connection($dbConnection)->table('history')->insertGetId($data);
        $history = DB::connection($dbConnection)->table('history')->where('id', $id)->first();

        return response()->json($history, 201);
    }

    // Mostrar un registro específico
    public function show(Request $request, $id)
    {
        $dbConnection = $request->get('db_connection');
        if (!$dbConnection) {
            return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
        }
        $history = DB::connection($dbConnection)->table('history')->where('id', $id)->first();
        if (!$history) {
            return response()->json(['error' => 'Registro no encontrado'], 404);
        }
        return response()->json($history);
    }

    // Actualizar un registro
    public function update(Request $request, $id)
    {
        $dbConnection = $request->get('db_connection');
        if (!$dbConnection) {
            return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
        }
        $history = DB::connection($dbConnection)->table('history')->where('id', $id)->first();
        if (!$history) {
            return response()->json(['error' => 'Registro no encontrado'], 404);
        }

        $validated = $request->validate([
            'type' => 'sometimes|string|max:20',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'created_by' => 'sometimes|integer|exists:users,id',
            'file' => 'nullable|file|max:10240'
        ]);

        $data = [];
        if (isset($validated['type'])) $data['type'] = $validated['type'];
        if (isset($validated['title'])) $data['title'] = $validated['title'];
        if (array_key_exists('description', $validated)) $data['description'] = $validated['description'];
        if (isset($validated['created_by'])) $data['created_by'] = $validated['created_by'];

        // Si es tipo FILE y viene archivo, guardar y poner la ruta en description
        $userId = $validated['created_by'] ?? $history->created_by;
        if ((($validated['type'] ?? $history->type) === 'FILE') && $request->hasFile('file')) {
            $path = $request->file('file')->store("history_files/{$userId}", 'public');
            $data['description'] = Storage::url($path);
        }

        DB::connection($dbConnection)->table('history')->where('id', $id)->update($data);
        $history = DB::connection($dbConnection)->table('history')->where('id', $id)->first();

        return response()->json($history);
    }

    // Eliminar un registro
    public function destroy(Request $request, $id)
    {
        $dbConnection = $request->get('db_connection');
        if (!$dbConnection) {
            return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
        }
        $history = DB::connection($dbConnection)->table('history')->where('id', $id)->first();
        if (!$history) {
            return response()->json(['error' => 'Registro no encontrado'], 404);
        }

        // Si es tipo FILE, eliminar el archivo del storage si existe
        if ($history->type === 'FILE' && $history->description) {
            // Eliminar archivo asociado a la URL almacenada
            $urlPath = parse_url($history->description, PHP_URL_PATH);
            $relative = preg_replace('#^/storage/#', '', $urlPath);
            Storage::delete($relative);
        }

        DB::connection($dbConnection)->table('history')->where('id', $id)->delete();
        return response()->json(['message' => 'Registro eliminado correctamente']);
    }
}
