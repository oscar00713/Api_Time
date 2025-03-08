<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $connection = 'sqlite'; // Usar la conexión de SQLite
    protected $table = 'servers'; // Nombre de la tabla
}
