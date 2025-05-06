<?php

namespace App\Http\Controllers\api\db;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class VariationsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        $dbConnection = $request->get('db_connection');
        $variations = DB::connection($dbConnection)->table('variations')->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['data' => $variations]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $dbConnection = $request->get('db_connection');
        DB::connection($dbConnection)->table('variations')->insert([
            'id_product' => $request->input('id_product'),
            'low_level' => $request->input('low_level'),
            'stock' => $request->input('stock'),
            'alert' => $request->input('alert'),
            'price' => $request->input('price'),
        ]);
        return response()->json(['message' => 'success'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {

        $dbConnection = $request->get('db_connection');
        $variation = DB::connection($dbConnection)->table('variations')->find($id);
        return response()->json(['data' => $variation]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        DB::connection($dbConnection)->table('variations')->where('id', $id)->update([
            'id_product' => $request->input('id_product'),
            'low_level' => $request->input('low_level'),
            'stock' => $request->input('stock'),
            'alert' => $request->input('alert'),
            'price' => $request->input('price'),
        ]);
        return response()->json(['message' => 'success']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        DB::connection($dbConnection)->table('variations')->where('id', $id)->delete();
        return response()->json(['message' => 'success']);
    }
}
