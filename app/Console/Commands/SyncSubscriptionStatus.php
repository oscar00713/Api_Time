<?php

namespace App\Console\Commands;



use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Companies;
use App\Models\UserSubscription;
use App\Services\DynamicDatabaseService;
use Carbon\Carbon;


class SyncSubscriptionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-subscription-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza el estado de las suscripciones desde Central a las compañías';

    protected $dynamicDatabaseService;

    public function __construct(DynamicDatabaseService $dynamicDatabaseService)
    {
        parent::__construct();
        $this->dynamicDatabaseService = $dynamicDatabaseService;
    }


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sincronización de estados de suscripciones...');

        $companies = Companies::all();

        foreach ($companies as $company) {
            $this->info("Procesando compañía: {$company->name}");

            try {
                $server = $company->server;
                if (!$server) {
                    $this->error("No se encontró servidor para la compañía {$company->name}");
                    continue;
                }

                // Aquí usamos DynamicDatabaseService
                $this->dynamicDatabaseService->configureConnection(
                    $server->db_host,
                    $server->db_port,
                    $company->db_name,
                    $server->db_username,
                    $server->db_password
                );

                $companyUsers = DB::connection('dynamic_pgsql')
                    ->table('users')
                    ->where('central_id', '>', 0)
                    ->get(['id', 'central_id']);

                foreach ($companyUsers as $user) {
                    $subscription = UserSubscription::where('user_id', $user->central_id)
                        ->orderBy('expires_at', 'desc')
                        ->first();

                    if ($subscription) {
                        DB::connection('dynamic_pgsql')->table('subscription_status')
                            ->updateOrInsert(
                                ['user_id' => $user->id],
                                [
                                    'status' => $subscription->status,
                                    'expires_at' => $subscription->expires_at,
                                    'last_synced_at' => Carbon::now(),
                                    'updated_at' => Carbon::now()
                                ]
                            );

                        $this->info("  - Usuario ID {$user->id}: Estado actualizado a {$subscription->status}");
                    } else {
                        $this->warn("  - Usuario ID {$user->id}: No se encontró suscripción en Central");
                    }
                }

                $this->info("Compañía {$company->name} procesada correctamente");
            } catch (\Exception $e) {
                $this->error("Error al procesar compañía {$company->name}: " . $e->getMessage());
            }
        }

        $this->info('Sincronización completada');

        return 0;
    }
}
