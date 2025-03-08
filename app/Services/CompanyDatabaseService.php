<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Crypt;

class CompanyDatabaseService
{
    public function createDatabaseForCompany($companyName, $user)
    {
        // Obtener configuración del servidor
        $server = DB::connection('sqlite')->table('servers')
            ->where('choosable_for_new_clients', true)
            ->first();

        //dd($server);

        if (!$server) {
            throw new Exception("No hay servidores disponibles para nuevos clientes");
        }
        $userServer = Crypt::decrypt($server->db_username);
        $passwordServer = Crypt::decrypt($server->db_password);

        //dd($userServer, $passwordServer);
        // dd($userServer, $passwordServer);
        // Sanitizar el nombre de la compañía
        $sanitizedCompanyName = $this->sanitizeCompanyName($companyName);

        $databaseName = 'z' . $sanitizedCompanyName . '_' . Str::uuid()->toString();
        $databaseName = str_replace('-', '_', $databaseName);  // Reemplazar guiones por guiones bajos
        $databaseName = substr($databaseName, 0, 30);  // Limitar la longitud del nombre a 30 caracteres
        DB::statement("CREATE DATABASE {$databaseName}");

        // Configuración temporal para la nueva base de datos

        Config::set("database.connections.dynamic_pgsql", [
            'driver' => 'pgsql',
            'host' => $server->db_host ?? '127.0.0.1',
            'port' => $server->db_port ?? 5432,
            'database' => $databaseName ?? 'default_db',
            'username' => $userServer ?? 'postgres', // Si db_username es null, se usa el valor predeterminado
            'password' => $passwordServer ?? '', // Si db_password es null, se usa una cadena vacía
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);
        // Verificar si la conexión se ha configurado correctamente
        // if (Config::has('database.connections.dynamic_pgsql')) {
        //     logger()->info('La conexión dynamic_pgsql se configuró exitosamente');
        // } else {
        //     logger()->error('Error: La conexión dynamic_pgsql no se configuró');
        // }
        // Asegúrate de purgar la conexión en caché, en caso de que Laravel esté usando una conexión previa
        DB::purge('dynamic_pgsql');

        // Ejecutar las migraciones desde el archivo SQL
        DB::connection('dynamic_pgsql')->unprepared(
            file_get_contents(database_path('sql/SQL_company_schema.sql'))
        );

        DB::connection('dynamic_pgsql')->table('users')->insert([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'user_type' => 'user',
            'hash' => $user->hash,
            'active' => true,
        ]);

        //tambien agregar los roles de la compañía
        DB::connection('dynamic_pgsql')->table('roles')->insert([
            'user_id' => $user->id,
            'manage_services' => true,
            'manage_users' => true,
            'manage_cash' => true,
            'view_client_history' => true,
            'create_client_history' => true,
            'cancel_client_history' => true,
            'appointments_own' => true,
            'appointments_other' => true,
            'appointments_self_assign' => true,
            'appointments_self_others' => true,
            'appointments_cancel_own' => true,
            'appointments_cancel_others' => true,
            'appointments_reschedule_own' => true,
            'appointments_reschedule_others' => true,
            'history_view' => true,
            'history_create' => true,
            'history_edit' => true,
            'history_delete' => true,
            'employees_create' => true,
            'employees_edit' => true,
            'employees_delete' => true,
            'manage_register' => true,
            'edit_money_own' => true,
            'edit_money_any' => true,
            'audit_register' => true,
            'view_reports' => true,
            'generate_reports' => true,
            'delete_reports' => true,
            'stock_add' => true,
            'stock_edit' => true,
        ]);

        return [
            'database_name' => $databaseName,
            'server_name' => $server->name,
        ];
    }

    private function sanitizeCompanyName($name)
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', '_', $name); // Elimina caracteres no válidos
        return trim($name, '_'); // Elimina guiones bajos al inicio o final
    }

    public function getCompanyDatabase($serverName)
    {
        $server = DB::connection('sqlite')->table('servers')
            ->where('name', $serverName)
            ->first();

        if (!$server) {
            throw new Exception("No hay servidores disponibles para nuevos clientes");
        }

        return $server;
    }
}
