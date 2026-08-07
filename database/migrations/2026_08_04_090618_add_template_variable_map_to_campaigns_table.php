<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add template_variable_map to campaigns.
     *
     * Stores an ordered array of contact field names that map to MSG91's
     * positional parameters {{1}}, {{2}}, {{3}}...
     *
     * Example: ["first_name", "city"] means:
     *   {{1}} → $contact->first_name  (or custom_fields['first_name'])
     *   {{2}} → $contact->city        (or custom_fields['city'])
     *
     * This aligns with Meta/MSG91's actual template convention (positional,
     * not named) and with the Templates spec's VariableHelper component.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Ordered array: index 0 → {{1}}, index 1 → {{2}}, etc.
            $table->jsonb('template_variable_map')->nullable()->after('template_language');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('template_variable_map');
        });
    }
};
