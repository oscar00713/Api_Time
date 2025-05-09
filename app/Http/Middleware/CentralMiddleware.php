<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use App\Models\Companies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;


class CentralMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('ataco');
        if (! $token) {
            return response()->json(['error' => 'error'], 401);
        }
        try {
            // Decodificar el token sin validarlo (para acceder a los claims)
            $payload = JWTAuth::setToken($token)->getPayload();

            // Obtener campos específicos del token
            $email = $payload->get('email');
            $hash = $payload->get('hash');
            $ip = $payload->get('ip');

            // Buscar al usuario en la base de datos con base en los datos del token
            if ($ip != $request->ip()) {
                return response()->json(['error' => 'UNAUTHORIZED1'], 401);
            }

            $user = User::where('email', $email)->where('hash', $hash)->where('active', true)->select('id', 'name', 'email', 'hash', 'email_verified', 'active', 'password')->first();


            if (!$user) {
                return response()->json(['error' => 'UNAUTHORIZED2'], 401);
            }

            // Inyectar los datos del usuario en la solicitud
            $request->merge(['user' => $user]);
            Companies::$authenticatedUser = $user;
        } catch (\Exception $e) {
            return response()->json(['error' => 'UNAUTHORIZED3'], 401);
        }

        // Continuar con la siguiente parte de la solicitud
        return $next($request);
    }
}
