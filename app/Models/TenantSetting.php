<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'msg91_auth_key',
        'openai_api_key',
        'flowise_endpoint',
        'ai_provider',
        'ai_model',
        'ai_system_prompt',
        'ai_is_active',
        'ai_human_escalation_enabled',
        'ai_confidence_threshold',
    ];

    /**
     * Native Encrypted Casts: Secret API keys are encrypted at rest using APP_KEY.
     */
    protected $casts = [
        'msg91_auth_key' => 'encrypted',
        'openai_api_key' => 'encrypted',
        'ai_is_active' => 'boolean',
        'ai_human_escalation_enabled' => 'boolean',
        'ai_confidence_threshold' => 'float',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
