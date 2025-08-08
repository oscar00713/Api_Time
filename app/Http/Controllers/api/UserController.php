<?php

namespace App\Http\Controllers\api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Crypt;
use App\Services\CompanyDatabaseService;
use App\Services\DynamicDatabaseService;


class UserController extends Controller
{

    public function __construct(private CompanyDatabaseService $companyDatabaseService, protected DynamicDatabaseService $dynamicDatabaseService) {}
    public function update(Request $request, string $id)
    {
        $user = User::find($id);


        //actualizar el email en las demas DB a las que pertenece
        $companies =  $user->ownedCompanies;

        $companiesInvitados = $user->companies;

        $allCompanies = $companiesInvitados->merge($companies);


        //desloguearme de las compañías que me han asignado
        foreach ($allCompanies as $company) {
            //obtner el servidor y la base de datos
            try {
                $serverName = $company->server_name;

                // Asumimos que tienes una función para obtener las credenciales del servidor
                $server = $this->companyDatabaseService->getCompanyDatabase($serverName);

                if (!$server) {
                    continue; // Si no se puede obtener el servidor, pasar a la siguiente compañía
                }

                $userServer = Crypt::decrypt($server->db_username);
                $passwordServer = Crypt::decrypt($server->db_password);

                DB::purge('dynamic_pgsql');

                $this->dynamicDatabaseService->configureConnection(
                    $server->db_host,
                    $server->db_port,
                    $company->db_name, // Nombre de la base de datos de la compañía
                    $userServer,
                    $passwordServer
                );

                DB::connection('dynamic_pgsql')->table('users')
                    ->where('email', $user->email) //buscar por email
                    ->update([
                        'name' => $request->name ?? $user->name, // Si no se especifica el nombre, usar el mismo
                        'email' => $request->email ?? $user->email, // Si no se especifica el email, usar el mismo

                    ]);
            } catch (\Exception $e) {
                continue;
            }
        }


        $user->update($request->all());

        return response()->json(['message' => 'ok'], 200);
    }

    public  function statusCompany(Request $request)
    {

        $dbConnection = $request->get('db_connection');
        $connection = DB::connection($dbConnection);
        $user = $request->user;

        //return response()->json(['message' => $user], 200);

        try {
            // Verificar si el usuario existe y es un array
            if (!is_array($user) || !isset($user['id'])) {
                throw new \Exception('Usuario no válido');
            }

            // Acceder al ID correctamente (array en lugar de objeto)
            $companyRole = $connection->table('roles')->where('user_id', $user['id'])->first();

            //TODO enviar el room del usuario se llamara start{room_id}


            return response()->json([
                'permissions' => $companyRole ?? null,
                'success' => true,
                'start' => $user['room_id'] ?? null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
