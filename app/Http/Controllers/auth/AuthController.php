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

        $token = JWTAuth::claims([
            'ip' => $ipAddress,
            // Otros datos personalizados que desees agregar
        ])->fromUser($user);

        $years = 5;
        $minutes = 60 * 24 * 365 * $years;

        // Configuración de cookie adaptada para ambos entornos
        $cookieDomain = $isProduction ? env('COOKIE_DOMAIN', '.timeboard.live') : null;
        $cookieSecure = $isProduction;
        $cookieSameSite = $isProduction ? 'None' : 'Lax';

        return response()->json([
            'success' => true,
        ])->cookie(
            'ataco',                // Nombre de la cookie
            $token,                 // Valor de la cookie
            $minutes,               // Duración en minutos
            '/',                    // Ruta de la cookie
            $cookieDomain,          // Dominio de la cookie (dinámico)
            $cookieSecure,          // Solo enviar por HTTPS en producción
            true,                   // HttpOnly (no accesible desde JS)
            false,                  // Raw
            $cookieSameSite         // SameSite policy (None en producción, Lax en local)
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

        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Cargar las relaciones necesarias
        $user->load(['ownedCompanies', 'companies']);

        $companies = $user->ownedCompanies;
        $companiesInvitados = $user->companies;
        $allCompanies = $companiesInvitados->merge($companies);

        $hash = Str::random(5) . Str::uuid() . Str::random(5);

        // Desloguear de las compañías
        foreach ($allCompanies as $company) {
            try {
                $serverName = $company->server_name;
                $server = $this->companyDatabaseService->getCompanyDatabase($serverName);

                if (!$server) {
                    continue;
                }

                $userServer = Crypt::decrypt($server->db_username);
                $passwordServer = Crypt::decrypt($server->db_password);

                DB::purge('dynamic_pgsql');

                $this->dynamicDatabaseService->configureConnection(
                    $server->db_host,
                    $server->db_port,
                    $company->db_name,
                    $userServer,
                    $passwordServer
                );

                DB::connection('dynamic_pgsql')->table('users')
                    ->where('email', $user->email)
                    ->update([
                        'hash' => $hash,
                    ]);
            } catch (\Exception $e) {
                continue;
            }
        }

        // Actualizar el hash del usuario en la base de datos central
        $user->update([
            'hash' => $hash,
        ]);

        // Invalidar el token JWT
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Exception $e) {
            // Si hay error al invalidar el token, continuar
        }

        // Eliminar la cookie
        $isProduction = app()->environment('production');
        $cookieDomain = $isProduction ? env('COOKIE_DOMAIN', '.timeboard.live') : null;

        return response()->json(['message' => 'ok'], 200)
            ->cookie(
                'ataco',           // Nombre de la cookie
                '',                // Valor vacío para eliminar
                -1,                // Tiempo negativo para expirar inmediatamente
                '/',               // Ruta
                $cookieDomain,     // Dominio
                $isProduction,     // Secure
                true,              // HttpOnly
                false,             // Raw
                $isProduction ? 'None' : 'Lax' // SameSite
            );
    }

    public function status(Request $request)
    {
        $user = $request->user;
    
        // Si no hay usuario autenticado, devolver error
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    
        // Verificar si el usuario es un array y convertirlo a modelo si es necesario
        if (is_array($user)) {
            $userId = $user['id'] ?? null;
            if (!$userId) {
                return response()->json(['error' => 'Invalid user data'], 401);
            }
            $user = User::find($userId);
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }
        }
    
        //preguntar si el usuario tiene el campo de email verificado
        if (!$user->email_verified) {
            return response()->json(['error' => 'NOT_CONFIRMED'], 401);
        }
    
        // Inicializar colecciones vacías para evitar errores
        $ownedCompanies = collect();
        $invitedCompanies = collect();
    
        // Obtener directamente las compañías propias desde la tabla companies
        try {
            $ownedCompanies = Companies::where('user_id', $user->id)->get()->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'server_name' => $company->server_name,
                    'db_name' => $company->db_name,
                    'isOwner' => true,
                ];
            });
        } catch (\Exception $e) {
            // Si hay error, mantener la colección vacía
        }
    
        // Cargar las compañías invitadas a través de la relación
        try {
            $user->load('companies');
            if ($user->companies) {
                $invitedCompanies = $user->companies->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'server_name' => $company->server_name,
                        'db_name' => $company->db_name,
                        'isOwner' => false,
                    ];
                });
            }
        } catch (\Exception $e) {
            // Si hay error, mantener la colección vacía
        }
    
        // Combinar ambas colecciones con manejo de errores
        $allCompanies = collect();
        if (!$ownedCompanies->isEmpty()) {
            $allCompanies = $allCompanies->merge($ownedCompanies);
        }
        if (!$invitedCompanies->isEmpty()) {
            $allCompanies = $allCompanies->merge($invitedCompanies);
        }
    
        // Cargar otras relaciones necesarias
        try {
            $user->load(['userOptions', 'invitations.company']);
            $userOptions = $user->userOptions ?? collect();
        } catch (\Exception $e) {
            $userOptions = collect();
        }
    
        // Procesar invitaciones con manejo de errores
        $userInvitations = collect();
        try {
            if ($user->relationLoaded('invitations') && $user->invitations) {
                $userInvitations = $user->invitations
                    ->filter(function($invitacion) {
                        return $invitacion->accepted === null;
                    })
                    ->map(function($invitacion) {
                        $companyName = $invitacion->company ? $invitacion->company->name : 'Desconocida';
                        return [
                            'invitationtoken' => $invitacion->invitationtoken,
                            'sender_name' => $invitacion->sender_name,
                            'company' => $companyName,
                        ];
                    })
                    ->values();
            }
        } catch (\Exception $e) {
            // Si hay error, mantener la colección vacía
        }
    
        // Agregar información de depuración en desarrollo
        $debug = [];
        if (!app()->environment('production')) {
            $debug = [
                'user_id' => $user->id,
                'user_type' => gettype($request->user),
                'owned_companies_count' => $ownedCompanies->count(),
                'invited_companies_count' => $invitedCompanies->count(),
                'has_owned_companies' => !$ownedCompanies->isEmpty(),
                'has_invited_companies' => !$invitedCompanies->isEmpty(),
                'raw_owned_companies' => $ownedCompanies->toArray(),
                'raw_invited_companies' => $invitedCompanies->toArray(),
                'error_info' => null
            ];
        }
    
        return response()->json([
            'companies' => $allCompanies,
            'userOptions' => $userOptions,
            'userInvitations' => $userInvitations->isEmpty() ? [] : $userInvitations,
            'user' => $user->only(['name', 'email']),
            'success' => true,
            'debug' => $debug
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
