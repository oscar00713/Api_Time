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
        $dbConnection = $request->get('db_connection');
        $perPage = $request->query('perPage', 10);
        $currentPage = $request->query('page', 1);
        $filter = $request->query('filter', []); // Puede venir como array
        $orderBy = $filter['order_by'] ?? 'id_desc';

        $query = DB::connection($dbConnection)->table('productos')
            ->select([
                'id',
                'name',
                'description',
                'code',
                'categoria_id',
                'expiration_date',
                'active'
            ]);

        // Búsqueda general (por nombre o código de producto, o nombre de variación, insensible a mayúsculas/minúsculas)
        if (!empty($filter['all'])) {
            $searchTerm = '%' . strtolower($filter['all']) . '%';
            $query->leftJoin('variations', 'productos.id', '=', 'variations.product_id')
                ->where(function ($q) use ($searchTerm) {
                    $q->whereRaw('LOWER(productos.name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(productos.code) LIKE ?', [$searchTerm])
                        ->orWhereRaw('(productos.id) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(productos.description) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(variations.name) LIKE ?', [$searchTerm]);
                })
                ->groupBy(
                    'productos.id',
                    'productos.name',
                    'productos.description',
                    'productos.code',
                    'productos.categoria_id',
                    'productos.expiration_date',
                    'productos.active'
                );
        }

        // Filtro por categoría
        if ($request->filled('category')) {
            $query->where('categoria_id', $request->input('category'));
        }

        // Filtro por estado 'active'
        if (isset($filter['active'])) {
            $query->where('active', $filter['active']);
        }

        // Ordenamiento flexible
        switch ($orderBy) {
            case 'creation_desc':
                $query->orderBy('id', 'desc');
                break;
            case 'creation_asc':
                $query->orderBy('id', 'asc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            default:
                $query->orderBy('id', 'desc');
                break;
        }

        $products = $query->paginate($perPage, ['*'], 'page', $currentPage);

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
