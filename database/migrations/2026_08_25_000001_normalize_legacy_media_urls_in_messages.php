<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $messages = DB::table('messages')
            ->where(function ($query) {
                $query->where('content', 'like', '%127.0.0.1%')
                      ->orWhere('content', 'like', '%ngrok-free.dev%')
                      ->orWhere('content', 'like', '%localhost%');
            })
            ->get();

        foreach ($messages as $msg) {
            $content = $msg->content;
            if (!is_string($content)) continue;

            // Replace full host prefix with relative path /storage/
            $updated = preg_replace('#https?://[^/]+/storage/#i', '/storage/', $content);

            if ($updated !== $content) {
                DB::table('messages')
                    ->where('id', $msg->id)
                    ->update(['content' => $updated]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-reversible data normalization
    }
};
