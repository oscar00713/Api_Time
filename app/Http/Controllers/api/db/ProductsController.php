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
        $query = DB::connection($dbConnection)->table('productos');

        // Filtro por categoría si viene en la petición
        if ($request->filled('category')) {
            $query->where('categoria_id', $request->input('category'));
        }

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        // Para cada producto, obtener sus variaciones
        foreach ($products as &$product) {
            $product->variations = DB::connection($dbConnection)
                ->table('variations')
                ->where('product_id', $product->id)
                ->get();
        }
        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $productData = $request->only([
            'categoria_id',
            'name',
            'code',
            'description',
            'expiration_date'
        ]);
        $id = DB::connection($dbConnection)->table('productos')->insertGetId($productData);

        // Guardar variaciones si vienen en la petición
        $variations = $request->input('variations');
        if ($variations && is_array($variations)) {
            foreach ($variations as $variation) {
                $variation['product_id'] = $id;
                DB::connection($dbConnection)->table('variations')->insert($variation);
            }
        }
        return response()->json(['data' => $id], 201);
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
        $productData = $request->only([
            'categoria_id',
            'name',
            'code',
            'description',
            'expiration_date'
        ]);
        DB::connection($dbConnection)->table('productos')->where('id', $id)->update($productData);

        // Actualizar variaciones si vienen en la petición
        $variations = $request->input('variations');
        if ($variations && is_array($variations)) {
            // Obtener variaciones actuales antes de cualquier cambio
            $currentVariations = DB::connection($dbConnection)
                ->table('variations')
                ->where('product_id', $id)
                ->get()
                ->keyBy('id');

            $sentIds = [];
            foreach ($variations as $variation) {
                if (empty($variation['name'])) {
                    return response()->json(['error' => 'Variation name is required'], 422);
                }
                $variation['product_id'] = $id;

                if (!empty($variation['id']) && isset($currentVariations[$variation['id']])) {
                    // Actualizar variación existente
                    $varId = $variation['id'];
                    $oldVariation = $currentVariations[$varId];

                    // Si el stock cambió, registrar en stock_history
                    if (isset($variation['stock']) && $variation['stock'] != $oldVariation->stock) {
                        DB::connection($dbConnection)->table('stock_history')->insert([
                            'id_variacion' => $varId,
                            'change_type' => 'adjustment',
                            'date' => now(),
                            'stock_from' => $oldVariation->stock,
                            'stock_to' => $variation['stock'],
                        ]);
                    }

                    unset($variation['id']);
                    DB::connection($dbConnection)->table('variations')->where('id', $varId)->update($variation);
                    $sentIds[] = $varId;
                } else {
                    // Insertar nueva variación
                    unset($variation['id']);
                    $newId = DB::connection($dbConnection)->table('variations')->insertGetId($variation);
                    $sentIds[] = $newId;
                }
            }

            // Eliminar variaciones que no vinieron en el request
            DB::connection($dbConnection)->table('variations')
                ->where('product_id', $id)
                ->whereNotIn('id', $sentIds)
                ->delete();
        }
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
