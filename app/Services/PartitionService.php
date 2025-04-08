<?php

namespace App\services;

use Illuminate\Support\Facades\DB;

class PartitionService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    public function ensureYearPartitionExists($year, $dbConnection)
    {
        $partitionName = 'appointments_' . $year;
        $startDate = $year . '-01-01';
        $endDate = ($year + 1) . '-01-01';

        // Usar la conexión específica
        $query = DB::connection($dbConnection);

        // Verificar si la partición ya existe
        $exists = $query->select("
            SELECT 1
            FROM pg_catalog.pg_class c
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE c.relname = ?
            AND n.nspname = current_schema()
        ", [$partitionName]);

        if (empty($exists)) {
            // Crear la partición si no existe
            $query->statement("
                CREATE TABLE $partitionName PARTITION OF appointments
                FOR VALUES FROM ('$startDate') TO ('$endDate')
            ");
            
            // Crear índices para mejorar el rendimiento
            $query->statement("CREATE INDEX idx_employee_id_$partitionName ON $partitionName(employee_id)");
            $query->statement("CREATE INDEX idx_client_id_$partitionName ON $partitionName(client_id)");
            $query->statement("CREATE INDEX idx_service_id_$partitionName ON $partitionName(service_id)");
        }
    }
}
