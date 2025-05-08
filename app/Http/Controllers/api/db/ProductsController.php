<?php

namespace App\Http\Controllers\api\db;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $dbConnection = $request->get('db_connection');
        $products = DB::connection($dbConnection)->table('productos')->paginate($perPage, ['*'], 'page', $page);
        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $id = DB::connection($dbConnection)->table('productos')->insertGetId($request->only([
            'id_categoria',
            'codigo',
            'descripcion',
            'stock',
            'cost',
            'extra fee',
            'Markup',
            'precio_venta'
        ]));
        return response()->json(['id' => $id], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        $producto = DB::connection($dbConnection)->table('productos')->find($id);
        return response()->json($producto);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        DB::connection($dbConnection)->table('productos')->where('id', $id)->update($request->only([
            'id_categoria',
            'codigo',
            'descripcion',
            'stock',
            'cost',
            'extra fee',
            'Markup',
            'precio_venta'
        ]));
        return response()->json(['message' => 'success'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        DB::connection($dbConnection)->table('productos')->where('id', $id)->delete();
        return response()->json(['message' => 'Producto eliminado']);
    }
}
