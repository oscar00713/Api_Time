<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DynamicDatabaseService
{
    public function configureConnection($host, $port, $database, $username, $password)
    {

        Config::set("database.connections.dynamic_pgsql", [
            'driver' => 'pgsql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);

        DB::purge('dynamic_pgsql');
    }

    public function getConnectionName()
    {
        return 'dynamic_pgsql';
    }
}
