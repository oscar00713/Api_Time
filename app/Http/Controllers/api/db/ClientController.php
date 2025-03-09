<?php

namespace App\Http\Controllers\api\db;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    /* en este crud solo se usaran estos datos  id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100),
    birthday DATE,
    email VARCHAR(255),
    phone VARCHAR(255),
    national_id VARCHAR(30),
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $connection = DB::connection($dbConnection);

        try {
            $perPage = $request->query('perPage', 10);
            $currentPage = $request->query('page', 1);
            $filter = $request->query('filter', []);
            $orderBy = $filter['order_by'] ?? 'id_desc';

            $query = $connection->table('clients');

            // Aplicar filtros (ejemplo)
            if (!empty($filter['name'])) {
                $query->whereAny(
                    [
                        'first_name',
                    ],
                    'LIKE',
                    '%' . $filter['all'] . '%'
                );
            }

            // Aplicar filtros mejorados
            if (!empty($filter['all'])) {
                $searchTerm = '%' . $filter['all'] . '%';
                $query->whereAny(
                    [
                        'first_name',
                        'last_name',
                        'email',
                        'phone',
                        'national_id'
                    ],
                    'ilike',
                    $searchTerm
                );
            }

            // Ordenamiento mejorado
            $orderColumn = 'id';
            $orderDirection = 'desc';

            switch ($orderBy) {
                case 'creation_asc':
                    $orderDirection = 'asc';
                    break;
                case 'name_asc':
                    $orderColumn = 'first_name';
                    $orderDirection = 'asc';
                    break;
                case 'name_desc':
                    $orderColumn = 'first_name';
                    $orderDirection = 'desc';
                    break;
            }

            $query->orderBy($orderColumn, $orderDirection);

            // Paginación correcta
            $clients = $query->paginate($perPage, ['*'], 'page', $currentPage);
            if ($clients->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'current_page' => $currentPage,
                    'per_page' => $perPage,
                    'total' => 0
                ]);
            }


            return response()->json($clients);
        } catch (\Exception $e) {
            // Log del error (recomendado)

            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'birthday' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Insertar el cliente
            $client = $query->table('clients')->insertGetId([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'birthday' => $request->birthday,
                'email' => $request->email,
                'phone' => $request->phone,
                'national_id' => $request->national_id,
            ]);


            DB::commit();

            return response()->json(
                [
                    'message' => 'Client created successfully',
                    'id' => $client
                ],

                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {

        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);
        $client = $query->table('clients')->where('id', $id)->first();

        return response()->json($client);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'birthday' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:255',
            'banned' => 'nullable|boolean',
            'banned_reason' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Construir el array de datos a actualizar
            $dataToUpdate = [];
            $fields = [
                'first_name',
                'last_name',
                'birthday',
                'email',
                'phone',
                'national_id',
                'banned',
                'banned_reason'
            ];

            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $dataToUpdate[$field] = $request->$field;
                }
            }

            // Actualizar el cliente en la tabla 'clients'
            $query->table('clients')->where('id', $id)->update($dataToUpdate);

            DB::commit();
            return response()->json(['message' => 'Client updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection);

        try {
            DB::beginTransaction();

            // Eliminar el cliente
            $query->table('clients')->where('id', $id)->delete();

            DB::commit();
            return response()->json(['message' => 'Client deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
