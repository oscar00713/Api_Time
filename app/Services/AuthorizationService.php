<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuthorizationService
{
    /**
     * Verifica si un usuario tiene un permiso específico
     *
     * @param array $user El array del usuario con su información
     * @param string $permission El nombre del permiso a verificar
     * @param string $dbConnection La conexión a la base de datos
     * @return bool
     */
    public function hasPermission(array $user, string $permission, string $dbConnection): bool
    {
        if (!isset($user['id'])) {
            return false;
        }

        $role = DB::connection($dbConnection)
            ->table('roles')
            ->where('user_id', $user['id'])
            ->first();

        if (!$role) {
            return false;
        }

        return isset($role->$permission) && $role->$permission === true;
    }

    /**
     * Verifica si un usuario puede asignar un turno
     *
     * @param array $user El array del usuario con su información
     * @param int $employeeId El ID del especialista al que se asignará el turno
     * @param string $dbConnection La conexión a la base de datos
     * @return bool
     */
    public function canAssignAppointment(array $user, int $employeeId, string $dbConnection): bool
    {
        // Si el usuario es el mismo especialista, necesita permiso para asignarse a sí mismo
        if ((int)$user['id'] === $employeeId) {
            return $this->hasPermission($user, 'appointments_self_assign', $dbConnection);
        }
        
        // Si es para otro especialista, necesita permiso para asignar a otros
        return $this->hasPermission($user, 'appointments_self_others', $dbConnection);
    }
}
