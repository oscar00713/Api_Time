<?php

namespace App\Http\Controllers\api\db\historial;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomFieldValueController extends Controller
{
    public function index(Request $request)
    {
        $db = $request->get('db_connection');
        $values = DB::connection($db)->table('custom_fields_values')->get();
        return response()->json($values, 200);
    }

    public function store(Request $request)
    {
        $db = $request->get('db_connection');
        $v = Validator::make($request->all(), [
            'history_id' => 'nullable|integer',
            'appointment_id' => 'nullable|integer',
            'client_id' => 'nullable|integer',
            'field_id' => 'required|integer',
            'field_value' => 'required',
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $data = $v->validated();
        $id = DB::connection($db)->table('custom_fields_values')->insertGetId($data);
        $new = DB::connection($db)->table('custom_fields_values')->where('id', $id)->first();
        return response()->json($new, 201);
    }

    public function show(Request $request, $id)
    {
        $db = $request->get('db_connection');
        $val = DB::connection($db)->table('custom_fields_values')->where('id', $id)->first();
        if (!$val) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json($val, 200);
    }

    public function update(Request $request, $id)
    {
        $db = $request->get('db_connection');
        $v = Validator::make($request->all(), [
            'field_value' => 'required',
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        $data = $v->validated();
        $updated = DB::connection($db)->table('custom_fields_values')->where('id', $id)->update($data);
        if (!$updated) {
            return response()->json(['error' => 'Not found or no change'], 404);
        }
        $val = DB::connection($db)->table('custom_fields_values')->where('id', $id)->first();
        return response()->json($val, 200);
    }

    public function destroy(Request $request, $id)
    {
        $db = $request->get('db_connection');
        $deleted = DB::connection($db)->table('custom_fields_values')->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(null, 204);
    }
}
