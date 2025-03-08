<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Users_Companies extends Model
{

    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
