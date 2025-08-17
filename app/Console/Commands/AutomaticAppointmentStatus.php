<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Companies;
use App\Services\DynamicDatabaseService;

class AutomaticAppointmentStatus extends Command
{

    //TODO problemas para a revisar ya que la conexion hacia la db ya que no se hace desde un enpoint donde esten las credenciales
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:auto {--connection=} {--company_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marca como checkout citas que coinciden con la hora/minuto actual (timezone por settings) cuando automatic_mode=true';

    public function __construct(protected DynamicDatabaseService $dynamicDatabaseService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connectionOpt = $this->option('connection');
        $companyIdOpt = $this->option('company_id');

        if ($connectionOpt) {
            $this->processConnection($connectionOpt);
            return 0;
        }

        if ($companyIdOpt) {
            $company = Companies::find($companyIdOpt);
            if (! $company) {
                $this->error("Compañía {$companyIdOpt} no encontrada");
                return 1;
            }
            if (! $this->configureForCompany($company)) {
                return 1;
            }
            $this->processConnection($this->dynamicDatabaseService->getConnectionName());
            return 0;
        }

        // Fallback: iterar todas las compañías
        $companies = Companies::all();
        foreach ($companies as $company) {
            $this->info("Procesando compañía: {$company->name}");
            if (! $this->configureForCompany($company)) {
                continue;
            }
            $this->processConnection($this->dynamicDatabaseService->getConnectionName());
        }

        return 0;
    }

    private function configureForCompany($company): bool
    {
        try {
            $server = DB::connection('sqlite')->table('servers')
                ->where('name', $company->server_name)
                ->orWhere('id', $company->server_id)
                ->first();
            if (! $server) {
                $this->warn('  - Servidor no encontrado, se omite');
                return false;
            }
            $this->dynamicDatabaseService->configureConnection(
                $server->db_host ?? '127.0.0.1',
                $server->db_port ?? 5432,
                $company->db_name,
                $server->db_username ?? null,
                $server->db_password ?? null
            );
            return true;
        } catch (\Exception $e) {
            $this->error('  - Error configurando conexión: ' . $e->getMessage());
            return false;
        }
    }

    private function processConnection(string $conn): void
    {
        try {
            // Settings
            $automaticStr = DB::connection($conn)->table('settings')->where('name', 'automatic_mode')->value('value');
            $tz = DB::connection($conn)->table('settings')->where('name', 'timezone')->value('value') ?? 'UTC';
            $automatic = in_array(strtolower(trim((string) $automaticStr)), ['1', 'true', 't', 'yes'], true);

            if (! $automatic) {
                $this->info('  - automatic_mode desactivado, se omite');
                return;
            }

            // Minuto actual en timezone
            $nowTz = Carbon::now($tz)->seconds(0);
            $nextMinuteTz = (clone $nowTz)->addMinute();

            // Reducir escaneo: fijar appointment_date al día actual del timezone
            $today = $nowTz->toDateString();

            // Actualizar citas cuyo start_date cae exactamente en este minuto (según tz)
            $affected = DB::connection($conn)->table('appointments')
                ->whereDate('appointment_date', '=', $today)
                ->where('start_date', '>=', $nowTz->toDateTimeString())
                ->where('start_date', '<', $nextMinuteTz->toDateTimeString())
                ->whereNotIn('status', [3, 4])
                ->update([
                    'status' => 3, // checked_out
                    'updated_at' => Carbon::now($tz),
                ]);

            $this->info("  - Citas actualizadas a checkout: {$affected}");
        } catch (\Exception $e) {
            $this->error('  - Error procesando: ' . $e->getMessage());
        }
    }
}
