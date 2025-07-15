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

    //TODO: revisar esta nueva forma de mandar los datos
    public function index(Request $request, $id)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        $dbConnection = $request->get('db_connection');
        $filter = $request->query('filter', []);

        // Construir la consulta principal con joins
        $query = DB::connection($dbConnection)
            ->table('stock_history')
            ->join('variations', 'stock_history.id_variacion', '=', 'variations.id')
            ->leftJoin('users', 'stock_history.created_by', '=', 'users.id') // Asumiendo que hay un campo created_by
            ->where('variations.product_id', $id)
            ->select([
                'stock_history.*',
                'variations.name as variation_name',
                'variations.id as variation_id',
                'users.name as user_name',
                'user.id as user_id'
            ]);

        // Filtrar por change_type si viene en el filtro
        if (!empty($filter['change_type'])) {
            $query->where('stock_history.change_type', $filter['change_type']);
        }

        // Ordenar y paginar
        $stockHistory = $query->orderBy('stock_history.date', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json(['data' => $stockHistory]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $dbConnection = $request->get('db_connection');
        DB::connection($dbConnection)->table('stock_history')->insertGetId([
            'id_variacion' => $request->input('id_variacion'),
            'change_type' => $request->input('change_type'),
            'date' => $request->input('date'),
            'user_id' => $user->id, // Asignar el ID del usuario autenticado
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
