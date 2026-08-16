<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearTenantMetaCredentialsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:clear-meta-credentials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wipes Meta API credentials for all tenants on redeployment';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Wiping Meta API credentials for all tenants...');

        \Illuminate\Support\Facades\DB::table('tenant_settings')->update([
            'meta_phone_number_id' => null,
            'meta_access_token' => null,
            'meta_waba_id' => null,
        ]);

        $this->info('Meta API credentials wiped successfully.');
    }
}
