<?php

namespace App\Http\Controllers\api\invitacion;

use App\Models\Companies;
use Illuminate\Http\Request;
use App\Models\Users_Invitations;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Users_Companies;
use Illuminate\Support\Facades\Crypt;
use App\Services\CompanyDatabaseService;
use App\Services\DynamicDatabaseService;

class UserInvitationController extends Controller
{

    public function __construct(private CompanyDatabaseService $companyDatabaseService, protected DynamicDatabaseService $dynamicDatabaseService) {}
    /**
     * Display a listing of the resource.
     */
    public function index() {}

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

    public function mostrarDataInvitacion(Request $request)
    {
        $invitation = Users_Invitations::where('invitationtoken', $request->hash)->first();

        if (!$invitation) {
            return response()->json(['error' => 'error1'], 403);
        }

        $company = Companies::where('id', $invitation->company_id)->first();

        if (!$company) {
            return response()->json(['error' => 'error2'], 403);
        }

        //armar el array de los datos
        $data = [
            'sender_email' => $invitation->sender_email,
            'invited_email' => $invitation->email,
            'sender_name' => $invitation->sender_name,
            'company' => $company->name,
        ];

        return response()->json($data);
    }


    public function acceptInvitations(Request $request)
    {
        //verificar que accepte sea boolean
        if (!is_bool($request->accepted)) {
            return response()->json(['error' => 'error'], 403);
        }

        $aceptado = $request->accepted;
        $invitationtoken = $request->invitationtoken;
        $user = $request->user;

        //dd($user);

        $invitacion = Users_Invitations::where('email', $user->email)->where('invitationtoken', $invitationtoken)->where('accepted', null)->first();

        //dd($invitacion);

        if (!$invitacion) {
            return response()->json(['error' => 'error'], 403);
        }


        $company = Companies::where('id', $invitacion->company_id)->first();

        if (!$company) {
            return response()->json(['error' => 'error'], 403);
        }
        // Obtener el nombre del servidor de cada compañía
        $serverName = $company->server_name;

        // Asumimos que tienes una función para obtener las credenciales del servidor
        $server = $this->companyDatabaseService->getCompanyDatabase($serverName);

        $userServer = Crypt::decrypt($server->db_username);
        $passwordServer = Crypt::decrypt($server->db_password);

        // Configurar la conexión dinámica
        $this->dynamicDatabaseService->configureConnection(
            $server->db_host,
            $server->db_port,
            $company->db_name, // Nombre de la base de datos de la compañía
            $userServer,
            $passwordServer
        );

        // buscar en la tabla specialists en el campo invitation_emai
        //TODO:cambio porque se ara todo en la tabla users ya no existira la tabla specialistas
        if (!$aceptado) {
            DB::connection('dynamic_pgsql')->table('users_temp')->where('email', $user->email)->update(['user_type' => 'rejected']);
            $invitacion->accepted = false;
            $invitacion->save();


            //si la rechazamos borrar la invitacion
            // $invitacion->delete();
            return response()->json(['message' => 'ok'], 200);
        }

        //guardar en la tabla users_companies
        Users_Companies::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        //TODO:acordase de add el Phone en un futuro

        // if (!DB::connection('dynamic_pgsql')->table('users')->where('id', $user->id)->exists()) {
        //     DB::connection('dynamic_pgsql')->table('users')->insert([
        //         'id' => $user->id,
        //         'name' => $user->name,
        //         'email' => $user->email,
        //         'hash' => $user->hash,
        //         'active' => true,
        //     ]);
        // }

        //insertar los datos en la tabla users_temp
        $usersTemp = DB::connection('dynamic_pgsql')->table('users_temp')->where('email', $user->email)->first();




        DB::connection('dynamic_pgsql')->table('users')->insert([
            'id' => $user->id,
            'name' => $user->name,
            'fixed_salary' => $usersTemp->fixed_salary,
            'user_type' => 'user',
            'email' => $user->email,
            'hash' => $user->hash,
            'active' => true,
            'phone' => $usersTemp->phone,
            'badge_color' => $usersTemp->badge_color,
            'manage_salary' => $usersTemp->manage_salary,
            'fixed_salary_frecuency' => $usersTemp->fixed_salary_frecuency,
            'use_room' => $usersTemp->use_room,
            'registration' => $usersTemp->registration,
        ]);

        $roles = json_decode($usersTemp->roles, true);
        $rolesData = ['user_id' => $user->id];

        // Asignar valores a cada rol en la tabla de `roles`
        foreach ($roles as $value) { // Solo iteramos los valores
            $rolesData[$value] = true; // Asignamos "true" a cada permiso
        }

        // Insertar en una sola consulta
        DB::connection('dynamic_pgsql')->table('roles')->insert($rolesData);

        //ccargar los datos de roles


        $invitacion->delete();
        //borrar de la tabla users_temp
        DB::connection('dynamic_pgsql')->table('users_temp')->where('email', $user->email)->delete();
    }
}
