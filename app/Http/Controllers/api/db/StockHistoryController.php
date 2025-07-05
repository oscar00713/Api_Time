<?php

namespace App\Http\Controllers\api\db;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class StockHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $id)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        $dbConnection = $request->get('db_connection');
        $filter = $request->query('filter', []);

        // Obtener todos los IDs de variaciones asociadas al producto
        $variationIds = DB::connection($dbConnection)
            ->table('variations')
            ->where('product_id', $id)
            ->pluck('id')
            ->toArray();

        // Construir la consulta de historial de stock
        $query = DB::connection($dbConnection)
            ->table('stock_history')
            ->whereIn('id_variacion', $variationIds);

        // Filtrar por change_type si viene en el filtro
        if (!empty($filter['change_type'])) {
            $query->where('change_type', $filter['change_type']);
        }

        $stockHistory = $query->orderBy('date', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json(['data' => $stockHistory]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $dbConnection = $request->get('db_connection');
        DB::connection($dbConnection)->table('stock_history')->insertGetId([
            'id_variacion' => $request->input('id_variacion'),
            'change_type' => $request->input('change_type'),
            'date' => $request->input('date'),
            'stock_from' => $request->input('stock_from'),
            'stock_to' => $request->input('stock_to'),
        ]);
        return response()->json(['message' => "success"], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        $stockHistory = DB::connection($dbConnection)->table('stock_history')->find($id);
        return response()->json(['data' => $stockHistory]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {

        $dbConnection = $request->get('db_connection');
        DB::connection($dbConnection)->table('stock_history')->where('id', $id)->update([
            'id_variacion' => $request->input('id_variacion'),
            'change_type' => $request->input('change_type'),
            'date' => $request->input('date'),
            'stock_from' => $request->input('stock_from'),
            'stock_to' => $request->input('stock_to')
        ]);
        return response()->json(['message' => 'success'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        DB::connection($dbConnection)->table('stock_history')->where('id', $id)->delete();
        return response()->json(['message' => 'success'], 200);
    }
}
