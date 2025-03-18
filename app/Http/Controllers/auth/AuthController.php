<?php

namespace App\Http\Controllers\auth;

use App\Models\User;
use App\Models\Companies;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Mail\PostCreatedMail;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
//use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Crypt;
use App\Services\CompanyDatabaseService;
use App\Services\DynamicDatabaseService;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{



    public function __construct(private CompanyDatabaseService $companyDatabaseService, protected DynamicDatabaseService $dynamicDatabaseService) {}

    public function login(Request $request)
    {
        $isProduction = app()->environment('production');
        $credentials = $request->only(['email', 'password']);
        $user = User::where('email', strtolower($credentials['email']))->where('active', true)->first();

        $ipAddress = $request->ip();

        if (!$user) {
            return response()->json(['error' => 'error1'], 401);
        }

        $hashedPassword = $user->password;
        $inputPassword = $credentials['password'];
        //dd(intval($inputPassword));
        //  dd(Hash::info($hashedPassword));

        if (!Hash::check($inputPassword, $hashedPassword)) {

            return response()->json(['error' => 'error2'], 401);
        }

        //preguntar si el usuario  tiene el campo de emiel verificado
        if (!$user->email_verified) {

            //mandar el correo del ping antes verificar los intentos en la tabla users__invitations
            if ($user->invitations->attempts < 3) {

                Mail::to($user->email)->send(new PostCreatedMail($user->email_hash));
                $user->invitations()->create([
                    'attempts' => $user->invitations->attempts + 1,
                    'expiration' => now()->addDays(3),
                ]);
            } else {
                return response()->json(['error' => 'NOT_CONFIRMED'], 401);
            }
        }

        //enviar las companies del usuario relacion ownedCompanies
        // $companies = $user->ownedCompanies;
        // $userOptions = $user->userOptions;
        // $userInvitations = $user->invitations;

        $token = JWTAuth::claims([
            'ip' => $ipAddress,
            // Otros datos personalizados que desees agregar
        ])->fromUser($user);

        $years = 5;
        $minutes = 60 * 24 * 365 * $years;

        // Determinar el dominio dinámicamente pero al estar en producccion pone siempre local corregirlo

        $domain = app()->environment('local') ? 'localhost' : '.timeboard.live';
        $cookieParams = [
            'name' => 'ataco',
            'value' => $token,
            'minutes' => $minutes,
            'path' => '/',
            'domain' => $isProduction ? '.timeboard.live' : 'localhost', // Dominio según entorno
            'secure' => $isProduction, // HTTPS solo en producción
            'httpOnly' => true,
            'sameSite' => $isProduction ? 'None' : 'Lax', // Ajustar según necesidad
        ];
        return response()->json([
            // 'companies' => $companies,
            // 'userOptions' => $userOptions,
            // 'userInvitations' => $userInvitations,
            // 'user' => $user->only(['name', 'email']),
            'success' => true,
            // 'expires_in' => auth()->factory()->getTTL() * 60
        ])->cookie(
            $cookieParams['name'],
            $cookieParams['value'],
            $cookieParams['minutes'],
            $cookieParams['path'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httpOnly'],
            false,
            $cookieParams['sameSite']
        );
    }

    public function register(Request $request)
    {

        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|email',
                'password' => 'required|min:8',
                'name' => 'required'
            ],
            [
                'email.unique' => 'El email ya está registrado',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres',
                'name.required' => 'el nombre es requerido'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Normalizamos el correo a minúsculas
        $email = strtolower($request->email);

        // Verificar si el usuario ya existe
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            // Si el email ya está registrado pero no está verificado y revisar tambien los intentos
            if (!$existingUser->email_verified) {
                // Generar y actualizar el nuevo PIN
                if ($existingUser->invitations->attempts < 3) {

                    $pin = $existingUser->email_hash;

                    // Reenviar el correo con el PIN
                    Mail::to($existingUser->email)->send(new PostCreatedMail($pin));

                    $existingUser->invitations()->create([
                        'attempts' => $existingUser->invitations->attempts + 1,
                        'expiration' => now()->addDays(3),
                    ]);

                    return response()->json(['message' => 'El PIN ha sido reenviado a tu correo electrónico'], 200);
                } else {
                    return response()->json(['error' => 'NOT_CONFIRMED'], 401);
                }
            }

            // Si el email ya está verificado, mostrar un error
            return response()->json(['error' => 'repeated'], 409);
        }

        // Crear un nuevo usuario
        $userData = $request->all();
        $userData['email'] = $email;
        $userData['hash'] = Str::random(5) . Str::uuid() . Str::random(5);
        $pin = rand(1000, 9999);
        $userData['email_hash'] = $pin;

        $user = User::create($userData);

        if (!$user) {
            return response()->json(['error' => 'Ocurrió un error al registrar el usuario'], 500);
        }

        // Enviar el correo con el PIN
        Mail::to($user->email)->send(new PostCreatedMail($pin));

        return response()->json(['message' => 'Successfully registered'], 201);
    }

    public function logout(Request $request)
    {
        $user = $request->user;

        $companies =  $user->ownedCompanies;



        $companiesInvitados = $user->companies;

        $allCompanies = $companiesInvitados->merge($companies);

        // return response()->json(['message' => $allCompanies], 200);

        $hash = Str::random(5) . Str::uuid() . Str::random(5);

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
                        'hash' => $hash, // Generar un nuevo hash
                    ]);
            } catch (\Exception $e) {
                continue;
            }
        }


        $user->update([
            'hash' => $hash,
        ]);

        return response()->json(['message' => 'ok'], 200);
    }

    public function status(Request $request)
    {

        $user = $request->user;


        //preguntar si el usuario  tiene el campo de emiel verificado
        if (!$user->email_verified) {
            return response()->json(['error' => 'NOT_CONFIRMED'], 401);
        }

        $ownedCompanies = $user->ownedCompanies->map(function ($company) {
            // Añadir el atributo `companiesInvitation` como false
            return [
                'id' => $company->id,
                'name' => $company->name,
                'server_name' => $company->server_name,
                'db_name' => $company->db_name,
                'isOwner' => true,
            ];
        });

        $invitedCompanies = $user->companies->map(function ($company) {
            // Añadir el atributo `companiesInvitation` como true
            return [
                'id' => $company->id,
                'name' => $company->name,
                'server_name' => $company->server_name,
                'db_name' => $company->db_name,
                'isOwner' => false,
            ];
        });

        $ownedCompanies = collect($ownedCompanies);
        $invitedCompanies = collect($invitedCompanies);
        // Combinar ambas colecciones
        $allCompanies = $ownedCompanies->merge($invitedCompanies);

        //enviar las companies del usuario relacion ownedCompanies evitar enviar
        $userOptions = $user->userOptions;

        $userInvitations = $user->invitations
            ->filter(fn($invitacion) => $invitacion->accepted === null)
            ->map(fn($invitacion) => [
                'invitationtoken' => $invitacion->invitationtoken,
                'sender_name' => $invitacion->sender_name,
                'company' => $invitacion->company->name,
            ])
            ->values(); // Asegura que los índices sean consecutivos


        return response()->json([
            'companies' => $allCompanies,
            'userOptions' => $userOptions,
            'userInvitations' =>
            $userInvitations->isEmpty() ? [] : $userInvitations,
            'user' => $user->only(['name', 'email']),
            'success' => true
        ], 200);
    }

    public function resendPin(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|email|exists:users,email',
            ],
            [
                'email.exists' => 'El email no está registrado'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->where('email_verified', false)->first();

        if (!$user) {
            return response()->json(['error' => 'El email ya está verificado o no existe'], 404);
        }


        Mail::to($user->email)->send(new PostCreatedMail($user->email_hash));

        return response()->json(['message' => 'PIN reenviado exitosamente']);
    }
}
