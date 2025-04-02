<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LimitCheckService
{
    /**
     * Check if the company has reached the maximum number of employees
     *
     * @param string $dbConnection
     * @return bool
     */
    public function canAddEmployee(string $dbConnection): bool
    {
        $maxEmployees = $this->getMaxValue($dbConnection, 'max_employees');
        $currentEmployeeCount = $this->getCurrentEmployeeCount($dbConnection);
        
        return $currentEmployeeCount < $maxEmployees;
    }
    
    /**
     * Check if the company has reached the maximum number of services
     *
     * @param string $dbConnection
     * @return bool
     */
    public function canAddService(string $dbConnection): bool
    {
        $maxServices = $this->getMaxValue($dbConnection, 'max_services');
        $currentServiceCount = $this->getCurrentServiceCount($dbConnection);
        
        return $currentServiceCount < $maxServices;
    }
    
    /**
     * Get the maximum value from setting_hidden table
     *
     * @param string $dbConnection
     * @param string $settingName
     * @return int
     */
    private function getMaxValue(string $dbConnection, string $settingName): int
    {
        $setting = DB::connection($dbConnection)
            ->table('setting_hidden')
            ->where('name', $settingName)
            ->first();
            
        return $setting ? (int) $setting->value : 0;
    }
    
    /**
     * Get the current count of employees
     *
     * @param string $dbConnection
     * @return int
     */
    private function getCurrentEmployeeCount(string $dbConnection): int
    {
        return DB::connection($dbConnection)
            ->table('users')
            ->where('active', true)
            ->count();
    }
    
    /**
     * Get the current count of services
     *
     * @param string $dbConnection
     * @return int
     */
    private function getCurrentServiceCount(string $dbConnection): int
    {
        return DB::connection($dbConnection)
            ->table('services')
            ->where('active', true)
            ->count();
    }
}
