<?php

namespace App\Http\Controllers\api\db\historial;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomFieldDefinitionController extends Controller
{
    public function index(Request $request)
    {
        $db = $request->get('db_connection');
        $defs = DB::connection($db)->table('custom_fields_definitions')->get();
        return response()->json($defs, 200);
    }

    public function store(Request $request)
    {
        $db = $request->get('db_connection');
        $v = Validator::make($request->all(), [
            'in_appointments' => 'boolean',
            'in_client' => 'boolean',
            'field_name' => 'required|string|max:100',
            'field_type' => 'required|string|max:50',
            'options' => 'nullable|string',
            'required_for_appointment' => 'boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $data = $v->validated();
        $id = DB::connection($db)->table('custom_fields_definitions')->insertGetId($data);
        $new = DB::connection($db)->table('custom_fields_definitions')->where('id', $id)->first();
        return response()->json($new, 201);
    }

    public function show(Request $request, $id)
    {
        $db = $request->get('db_connection');
        $def = DB::connection($db)->table('custom_fields_definitions')->where('id', $id)->first();
        if (!$def) {
            return response()->json(['error' => 'Definition not found'], 404);
        }
        return response()->json($def, 200);
    }

    public function update(Request $request, $id)
    {
        $db = $request->get('db_connection');
        $v = Validator::make($request->all(), [
            'in_appointments' => 'boolean',
            'in_client' => 'boolean',
            'field_name' => 'string|max:100',
            'field_type' => 'string|max:50',
            'options' => 'nullable|string',
            'required_for_appointment' => 'boolean',
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $data = $v->validated();
        $updated = DB::connection($db)->table('custom_fields_definitions')->where('id', $id)->update($data);
        if (!$updated) {
            return response()->json(['error' => 'No changes or not found'], 404);
        }
        $def = DB::connection($db)->table('custom_fields_definitions')->where('id', $id)->first();
        return response()->json($def, 200);
    }

    public function destroy(Request $request, $id)
    {
        $db = $request->get('db_connection');
        $deleted = DB::connection($db)->table('custom_fields_definitions')->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(null, 204);
    }
}
