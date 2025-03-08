<?php

namespace App\Observers;

use App\Models\Companies;
use App\Services\CompanyDatabaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Exception;

class CompanyObserver
{
    /**
     * Handle the Companies "created" event.
     */
    protected $databaseService;

    public function __construct(CompanyDatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

    public function creating(Companies $company)
    {
        try {

            // Verificar que el usuario esté autenticado
            $user = Companies::$authenticatedUser;

            $companyData = $this->databaseService->createDatabaseForCompany($company->name, $user);
            // dd($companyData);
            // Asignar el user_id al modelo company
            $company->user_id = $user->id;

            // Asignar la información de la base de datos al modelo
            $company->db_name = $companyData['database_name'];
            $company->server_name = $companyData['server_name'];
            //$company->username = $companyData['username'];
        } catch (Exception $e) {
            throw new Exception("Error al crear la base de datos: " . $e->getMessage());
        }
    }

    public function created(Companies $companies): void
    {
        // $databaseService = new CompanyDatabaseService();

        // try {
        //     $databaseService->createDatabaseForCompany($companies->name);
        // } catch (Exception $e) {
        //     // Log error or handle exception if needed
        //     //  \Log::error("Error creating database for company: " . $e->getMessage());
        // }
    }

    /**
     * Handle the Companies "updated" event.
     */
    public function updated(Companies $companies): void
    {
        //
    }

    /**
     * Handle the Companies "deleted" event.
     */
    public function deleted(Companies $companies): void
    {
        //
    }

    /**
     * Handle the Companies "restored" event.
     */
    public function restored(Companies $companies): void
    {
        //
    }

    /**
     * Handle the Companies "force deleted" event.
     */
    public function forceDeleted(Companies $companies): void
    {
        //
    }
}
