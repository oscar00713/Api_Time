<?php

namespace App\Http\Controllers\api;

use App\Models\Companies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use App\Services\CompanyDatabaseService;
use App\Services\DynamicDatabaseService;


class CompanyController extends Controller
{

    public function __construct(private CompanyDatabaseService $companyDatabaseService, protected DynamicDatabaseService $dynamicDatabaseService) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    // /**
    //  * Store a newly created resource in storage.
    //  */
    public function store(Request $request)
    {
        $name = $request->name;
        $user = $request->user;
        // dd($name, $user);
        $company = Companies::create([
            'name' => $name,
        ]);

        return response()->json([
            'message' => 'Company created successfully',
            'data' => $company

        ], 201);
    }

    // /**
    //  * Display the specified resource.
    //  */
    public function show(string $id)
    {
        //
    }

    // /**
    //  * Update the specified resource in storage.
    //  */
    public function update(Request $request, string $id)
    {
        //
    }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    public function destroy(string $id, Request $request)
    {
        //comprobar en la tabla companies que el usuario sea el propietario
        $password = $request->password;
        $user = $request->user;

        //revisar si el password es correcto
        if (!Hash::check($password, $user->password)) {
            return response()->json(['error' => 'error'], 403);
        }
        $company = Companies::where('user_id', $user->id)->where('id', $id)->first();
        if (!$company) {
            return response()->json(['error' => 'error'], 403);
        }

        //obtner el servidor y la base de datos
        $server = $company->server_name;
        $dbName = $company->db_name;

        $server = $this->companyDatabaseService->getCompanyDatabase($server);
        $userServer = Crypt::decrypt($server->db_username);
        $passwordServer = Crypt::decrypt($server->db_password);


        $this->dynamicDatabaseService->configureConnection(
            $server->db_host,
            $server->db_port,
            $dbName,
            $userServer,
            $passwordServer
        );
        try {
            // Revocar conexiones existentes a la base de datos
            DB::statement("REVOKE CONNECT ON DATABASE \"$dbName\" FROM PUBLIC");

            // Terminar las conexiones activas a la base de datos
            DB::statement("
            SELECT pg_terminate_backend(pid)
            FROM pg_stat_activity
            WHERE pid <> pg_backend_pid()
              AND datname = '$dbName'
        ");

            // Eliminar la base de datos
            DB::statement("DROP DATABASE \"$dbName\"");

            $company->delete();

            return response()->json(['message' => 'Base de datos eliminada exitosamente.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar la base de datos.', 'details' => $e->getMessage()], 500);
        }
    }
}
