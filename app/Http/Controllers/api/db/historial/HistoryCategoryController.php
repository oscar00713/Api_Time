<?php

namespace App\Http\Controllers\api\db\historial;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HistoryCategoryController extends Controller
{
    public function index(Request $request)
    {
        $db = $request->get('db_connection');
        if (!$db) {
            return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
        }
        $categories = DB::connection($db)->table('history_categories')->get();
        return response()->json($categories, 200);
    }

    public function store(Request $request)
    {
        $db = $request->get('db_connection');
        if (!$db) {
            return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
        }
        $v = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:history_categories,name',
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $id = DB::connection($db)->table('history_categories')->insertGetId([
            'name' => $request->input('name'),
        ]);
        $category = DB::connection($db)->table('history_categories')->where('id', $id)->first();
        return response()->json($category, 201);
    }

    public function show(Request $request, $id)
    {
        $db = $request->get('db_connection');
        if (!$db) {
            return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
        }
        $category = DB::connection($db)->table('history_categories')->where('id', $id)->first();
        if (!$category) {
            return response()->json(['error' => 'Categoría no encontrada'], 404);
        }
        return response()->json($category, 200);
    }

    public function update(Request $request, $id)
    {
        $db = $request->get('db_connection');
        if (!$db) {
            return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
        }
        $v = Validator::make($request->all(), [
            'name' => "required|string|max:100|unique:history_categories,name,{$id}",
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $updated = DB::connection($db)->table('history_categories')->where('id', $id)->update([
            'name' => $request->input('name'),
        ]);
        if (!$updated) {
            return response()->json(['error' => 'No se encontró la categoría o no hubo cambios'], 404);
        }
        $category = DB::connection($db)->table('history_categories')->where('id', $id)->first();
        return response()->json($category, 200);
    }

    public function destroy(Request $request, $id)
    {
        $db = $request->get('db_connection');
        if (!$db) {
            return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
        }
        $deleted = DB::connection($db)->table('history_categories')->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['error' => 'Categoría no encontrada'], 404);
        }
        return response()->json(null, 204);
    }
}
