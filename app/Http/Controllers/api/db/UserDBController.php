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
        $dbConnection = $request->get('db_connection');

        // Preparamos las dos subconsultas
        $usersQuery = DB::connection($dbConnection)
            ->table('users')
            ->select('id', 'name', 'email', 'user_type');

        $tempUsersQuery = DB::connection($dbConnection)
            ->table('users_temp')
            ->select('id', 'name', 'email', 'user_type');

        // Realizamos la unión
        $unionQuery = $usersQuery->unionAll($tempUsersQuery);

        // Para poder paginar con union, se recomienda envolver la consulta en una subconsulta
        $paginated = DB::connection($dbConnection)
            ->table(DB::raw("({$unionQuery->toSql()}) as sub"))
            ->mergeBindings($unionQuery) // importante para pasar los bindings de la unión
            ->paginate(10);

        return response()->json($paginated);
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
