<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Companies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Config;
use App\Services\CompanyDatabaseService;
use App\Services\DynamicDatabaseService;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class CompanyMiddleware
{

    private $companyDatabaseService;

    public function __construct(CompanyDatabaseService $companyDatabaseService, protected DynamicDatabaseService $dynamicDatabaseService)
    {

        $this->companyDatabaseService = $companyDatabaseService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Obtener el token de la cookie
        $token = $request->cookie('ataco');
        if (!$token) {
            return response()->json("UNAUTHORIZED11", 401);
        }

        // Verificar los headers requeridos
        if (!$request->header('d') || !$request->header('s')) {
            return response()->json("UNAUTHORIZED12", 401);
        }

        // Decodificar y verificar el token
        $tokenData = null;

        try {
            $tokenData = JWTAuth::setToken($token)->getPayload(); // Cambia según cómo decodifiques el token
        } catch (\Exception $e) {
            return response()->json("UNAUTHORIZED13", 401);
        }

        //dd($tokenData);
        // Verificar la información mínima requerida en el token y los headers
        if (!$tokenData->get('hash') || !$tokenData->get('email') || !$tokenData->get('ip') || !$request->header('s') || !$request->header('d')) {
            return response()->json("UNAUTHORIZED14", 401);
        }

        // Obtener el servidor por su nombre en Sqlite
        $server = $this->companyDatabaseService->getCompanyDatabase($request->header('s')); //nombre del servidor
        if (!$server || !isset($server->name)) {
            return response()->json("UNAUTHORIZED15", 401);
        }

        $userServer = Crypt::decrypt($server->db_username);
        $passwordServer = Crypt::decrypt($server->db_password);

        $this->dynamicDatabaseService->configureConnection(
            $server->db_host,
            $server->db_port,
            $request->header('d'),
            $userServer,
            $passwordServer
        );


        // Conectar al pool de la compañía
        $result = null;
        try {
            $result =  DB::connection('dynamic_pgsql')->table('users')->where('email', $tokenData->get('email'))->where('hash', $tokenData->get('hash'))->where('active', true)->where('user_type', 'user')->first();

            if (!$result) {
                return response()->json("UNAUTHORIZED16", 401);
            }


            $request->merge(['user' => (array) $result]);
        } catch (\Exception $e) {
            return response()->json("UNAUTHORIZED17" . $e->getMessage(), 401);
        }
        //inyectar el timezone de la tabla companies como request
        // $timezone = Companies::where('db_name', $request->header('d'))->value('timezone');
        // // Use the company timezone if available, otherwise use Laravel's default
        // if ($timezone) {
        //     Config::set('app.timezone', $timezone);
        // }

        $timezone = DB::connection('dynamic_pgsql')->table('settings')->where('name', 'timezone')->value('value');
        // return response()->json($timezone);
        if ($timezone) {
            Config::set('app.timezone', $timezone);
        }


        $request->merge(['db_connection' => $this->dynamicDatabaseService->getConnectionName()]);

        return $next($request);
    }
}
