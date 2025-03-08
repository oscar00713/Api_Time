<?php

namespace App\Http\Controllers\api\email;

use App\Http\Controllers\Controller;
use App\Models\Users_Invitations;
use Illuminate\Http\Request;

class InvitacionController extends Controller
{
    public function acceptInvitation($token, Request $request)
    {
        // Verificar el token
        if (!$request->accepted) {
            $userInvitation = new Users_Invitations;
            $userInvitation->update(['accepted' => false]);

            return response()->json(['error' => 'error'], 403);
        }

        $invitation = Users_Invitations::where('invitationtoken', $token)->first();


        if (!$invitation) {
            return response()->json(['error' => 'error'], 404);
        }

        //calcular la hora por 7 dias

        if (now()->diffInHours($invitation->created_at) > 168) {
            return response()->json(['error' => 'error'], 403);
        }

        // Aquí puedes marcar la invitación como aceptada o realizar otra lógica
        Users_Invitations::where('invitationtoken', $token)->update(['accepted' => true]);

        return response()->json(['message' => 'ok'], 200);
    }
}
