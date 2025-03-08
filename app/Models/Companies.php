<?php

namespace App\Models;

use App\Observers\CompanyObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

use Illuminate\Database\Eloquent\Model;

#[ObservedBy(CompanyObserver::class)]
class Companies extends Model
{
    public static $authenticatedUser;
    //protected $appends = ['additional_data'];

    //enviar data adicional
    // public function getAdditionalDataAttribute()
    // {
    //     return [
    //         'database_name' => $this->db_name,
    //         'server_name' => $this->server_name,
    //     ];
    // }
    /**
     * Relación con el dueño (User) de la compañía.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con los empleados (Users) asignados a esta compañía.
     */
    public function employees()
    {
        return $this->belongsToMany(User::class, 'users__companies', 'company_id', 'user_id');
    }
}
