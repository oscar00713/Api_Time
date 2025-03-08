<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Users_Invitations extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
