<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class ServerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // crear un hash para la contraseña

        $password =  Crypt::encrypt('JHkjsdafffiu7869sdf098fgdf67akddh');
        $user =  Crypt::encrypt('HJuiyammmmsd7967as56d7756asd');

        DB::connection('sqlite')->table('servers')->insert([
            ['name' => 'Server1', 'db_username' => $user, 'db_password' => $password, 'db_port' => '5432', 'db_host' => '143.110.157.0', 'choosable_for_new_clients' => true],
        ]);
    }
}
