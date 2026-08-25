<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

class CheckLiveDbCommand extends Command
{
    protected $signature = 'db:check-live';
    protected $description = 'Check live database status, counts, and tenant details';

    public function handle()
    {
        $this->info("==========================================");
        $this->info("   RAILWAY LIVE DATABASE DIAGNOSTICS      ");
        $this->info("==========================================");
        $this->info("DB Connection: " . config('database.default'));
        $this->info("DB Name:       " . config('database.connections.' . config('database.default') . '.database'));
        $this->info("DB Host:       " . config('database.connections.' . config('database.default') . '.host'));

        $users = User::withoutGlobalScopes()->get(['id', 'name', 'email', 'tenant_id']);
        $this->info("\n--- USERS IN LIVE DATABASE ---");
        foreach ($users as $u) {
            $this->info("User ID {$u->id} | Email: {$u->email} | Tenant ID: {$u->tenant_id}");
        }

        $totalConvs = DB::table('conversations')->count();
        $totalMsgs  = DB::table('messages')->count();
        $this->info("\n--- TOTAL RECORDS IN LIVE DATABASE ---");
        $this->info("Total Conversations: {$totalConvs}");
        $this->info("Total Messages:      {$totalMsgs}");

        $tenantConvs = DB::table('conversations')
            ->select('tenant_id', 'channel', DB::raw('count(*) as total'))
            ->groupBy('tenant_id', 'channel')
            ->get();

        $this->info("\n--- CONVERSATION COUNT BY TENANT & CHANNEL ---");
        foreach ($tenantConvs as $tc) {
            $this->info("Tenant ID {$tc->tenant_id} | Channel: {$tc->channel} | Count: {$tc->total}");
        }

        $this->info("\n--- LATEST 10 CONVERSATIONS IN LIVE DATABASE ---");
        $latest = DB::table('conversations')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get(['id', 'tenant_id', 'customer_number', 'customer_name', 'channel', 'created_at', 'updated_at']);

        foreach ($latest as $l) {
            $this->info("ID: {$l->id} | Tenant: {$l->tenant_id} | Phone: {$l->customer_number} | Channel: {$l->channel} | Updated: {$l->updated_at}");
        }

        $this->info("==========================================");
    }
}
