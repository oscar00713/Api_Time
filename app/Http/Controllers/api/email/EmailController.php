<?php

namespace App\Http\Controllers\api\email;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class EmailController extends Controller
{
    public function email_verified(Request $request)
    {
        $request->validate(
            [
                'pin' => 'required|min:4|max:4',
                'email' => 'required|exists:users,email',
            ],
            [
                'pin.required' => 'El PIN es requerido.',
                'pin.min' => 'El PIN debe tener al menos 4 caracteres.',
                'pin.max' => 'El PIN debe tener al menos 4 caracteres.',
                'user.required' => 'El usuario es requerido.',
                'user.exists' => 'El usuario no existe.',
            ]
        );

        $pin = $request->pin;
        $email = $request->email;
        $ipAddress = $request->ip();

        $user = User::where('email', $email)->where('email_hash', $pin)->where('email_verified', false)->first();
        if (!$user) {
            return response()->json(['error' => 'error'], 404);
        }
        $user->email_verified = true;
        $user->email_verified_at = now();
        $user->save();

        $token = JWTAuth::claims([
            'ip' => $ipAddress,
            // Otros datos personalizados que desees agregar
        ])->fromUser($user);

        $years = 5;
        $minutes = 60 * 24 * 365 * $years;
        return response()->json([
            'success' => true,
            // 'expires_in' => auth()->factory()->getTTL() * 60
        ])->cookie(
            'ataco',                // Nombre de la cookie
            $token,                 // Valor de la cookie
            $minutes,               // Duración en minutos (1 día en este caso)
            '/',                    // Ruta de la cookie
            '.timeboard.live',                   // Dominio de la cookie (null para el actual)
            true,                   // Solo enviar por HTTPS si está en producción cambiar a true en producción
            true,                   // Hacerla accesible solo a HTTP (no accesible desde JS)
            false,                  // No marcar como "SameSite" en este ejemplo
            'None'                // Política SameSite (reemplázalo si necesitas 'Lax')
        );

        return response()->json(['message' => 'ok']);
    }
}
