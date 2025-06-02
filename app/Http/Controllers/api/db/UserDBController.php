<?php

namespace App\Http\Controllers\api\db;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class UserDBController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $dbConnection = $request->get('db_connection');
        $query = DB::connection($dbConnection)
            ->table('users')
            ->select('id', 'name', 'email');  // Selecciona solo las columnas necesarias
        // Add this block to filter by name if filter[all] is present
        $filter = $request->input('filter', []);
        if (!empty($filter['all'])) {
            $query->where('name', 'ILIKE', '%' . $filter['all'] . '%');
        }

        $users = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json($users);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
