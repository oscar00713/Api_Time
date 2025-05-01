<?php

namespace App\Http\Controllers\api\db;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        $dbConnection = $request->get('db_connection');
        $categories = DB::connection($dbConnection)->table('categories')->paginate($perPage, ['*'], 'page', $page);
        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $id = DB::connection($dbConnection)->table('categories')->insertGetId([
            'name' => $request->input('name'),
            'active' => $request->input('active', true),
        ]);
        return response()->json(['id' => $id], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        $category = DB::connection($dbConnection)->table('categories')->find($id);
        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        DB::connection($dbConnection)->table('categories')->where('id', $id)->update($request->only(['name', 'active']));
        return response()->json(['message' => 'Categoría actualizada']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        DB::connection($dbConnection)->table('categories')->where('id', $id)->delete();
        return response()->json(['message' => 'Categoría eliminada']);
    }
}
