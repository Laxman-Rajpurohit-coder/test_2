<?php

namespace Modules\AiBot\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiBotSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'ai_bot_settings';

    protected $fillable = [
        'tenant_id',
        'provider',
        'api_key',
        'model_or_chatflow_id',
        'system_prompt',
        'is_active',
        'human_escalation_enabled',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key'                  => 'encrypted',
            'is_active'                => 'boolean',
            'human_escalation_enabled' => 'boolean',
        ];
    }
}
