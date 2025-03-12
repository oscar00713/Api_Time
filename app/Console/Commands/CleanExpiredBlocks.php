<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\api\db\BlockAppointmentController;

class CleanExpiredBlocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:clean-blocks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired appointment blocks from all PostgreSQL connections';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Cleaning expired blocks for all PostgreSQL connections");
        
        $controller = new BlockAppointmentController();
        $response = $controller->cleanExpiredBlocks();
        
        $result = json_decode($response->getContent(), true);
        
        if (isset($result['results'])) {
            $this->info("Cleaning results:");
            
            foreach ($result['results'] as $connection => $info) {
                if ($info['status'] === 'success') {
                    $this->info("- {$connection}: Removed {$info['blocks_removed']} expired blocks");
                } elseif ($info['status'] === 'skipped') {
                    $this->comment("- {$connection}: Skipped ({$info['reason']})");
                } else {
                    $this->error("- {$connection}: Error - {$info['message']}");
                }
            }
        } else {
            $this->error("Error: " . ($result['error'] ?? 'Unknown error'));
        }
    }
}
