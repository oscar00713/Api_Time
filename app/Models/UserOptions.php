<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOptions extends Model
{
    public $timestamps = false;
    public $incrementing = false; // No usar auto-incremento
    protected $primaryKey = null; // No definir clave primaria

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
