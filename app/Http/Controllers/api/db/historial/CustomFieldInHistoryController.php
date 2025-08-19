<?php

namespace App\Http\Controllers\api\db\historial;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomFieldInHistoryController extends Controller
{
    public function index(Request $request)
    {
        $db = $request->get('db_connection');
        $items = DB::connection($db)
            ->table('custom_fields_in_history')
            ->get();
        return response()->json($items, 200);
    }

    public function store(Request $request)
    {
        $db = $request->get('db_connection');
        $v = Validator::make($request->all(), [
            'history_category_id' => 'required|integer',
            'custom_field_definition_id' => 'required|integer',
            'required_for_history' => 'boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $data = $v->validated();
        DB::connection($db)->table('custom_fields_in_history')->insert($data);
        return response()->json($data, 201);
    }

    public function destroy(Request $request, $historyCategoryId, $fieldDefId)
    {
        $db = $request->get('db_connection');
        $deleted = DB::connection($db)
            ->table('custom_fields_in_history')
            ->where('history_category_id', $historyCategoryId)
            ->where('custom_field_definition_id', $fieldDefId)
            ->delete();
        if (!$deleted) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(null, 204);
    }
}
