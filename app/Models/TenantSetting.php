<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'public_api_key',
        'public_api_key_last_four',
        'widget_title',
        'widget_welcome_msg',
        'widget_color',
        'widget_position',
        'widget_auto_redirect_wa',
        'widget_target_phone',
        'msg91_auth_key',
        'openai_api_key',
        'grok_api_key',
        'gemini_api_key',
        'flowise_endpoint',
        'ai_provider',
        'ai_model',
        'ai_system_prompt',
        'ai_is_active',
        'ai_human_escalation_enabled',
        'ai_confidence_threshold',
        'meta_phone_number_id',
        'meta_access_token',
        'meta_waba_id',
        'facebook_page_id',
        'instagram_account_id',
        'meta_app_secret',
        'meta_webhook_verify_token',
    ];


    /**
     * Encrypted at rest via hand-written accessors using Laravel's encrypt()/decrypt() helpers.
     * Note: $casts does NOT use the native 'encrypted' cast — the manual accessors include
     * a DecryptException catch that silently returns null on key rotation, which is intentional.
     */
    protected $casts = [
        'ai_is_active' => 'boolean',
        'ai_human_escalation_enabled' => 'boolean',
        'ai_confidence_threshold' => 'float',
    ];

    public function getMsg91AuthKeyAttribute($value)
    {
        try {
            return $value ? decrypt($value) : null;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return null;
        }
    }

    public function setMsg91AuthKeyAttribute($value)
    {
        $this->attributes['msg91_auth_key'] = $value ? encrypt($value) : null;
    }

    public function getOpenaiApiKeyAttribute($value)
    {
        try {
            return $value ? decrypt($value) : null;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return null;
        }
    }

    public function setOpenaiApiKeyAttribute($value)
    {
        $this->attributes['openai_api_key'] = $value ? encrypt($value) : null;
    }

    public function getGeminiApiKeyAttribute($value)
    {
        try {
            return $value ? decrypt($value) : null;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return null;
        }
    }

    public function setGeminiApiKeyAttribute($value)
    {
        $this->attributes['gemini_api_key'] = $value ? encrypt($value) : null;
    }

    public function getGrokApiKeyAttribute($value)
    {
        try {
            return $value ? decrypt($value) : null;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return null;
        }
    }

    public function setGrokApiKeyAttribute($value)
    {
        $this->attributes['grok_api_key'] = $value ? encrypt($value) : null;
    }

    public function getMetaAppSecretAttribute($value)
    {
        try {
            return $value ? decrypt($value) : null;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return null;
        }
    }

    public function setMetaAppSecretAttribute($value)
    {
        $this->attributes['meta_app_secret'] = $value ? encrypt($value) : null;
    }

    public function getMetaWebhookVerifyTokenAttribute($value)
    {
        try {
            return $value ? decrypt($value) : null;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return null;
        }
    }

    public function setMetaWebhookVerifyTokenAttribute($value)
    {
        $this->attributes['meta_webhook_verify_token'] = $value ? encrypt($value) : null;
    }

    public function getMetaAccessTokenAttribute($value)
    {
        try {
            return $value ? decrypt($value) : null;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return $value;
        }
    }

    public function setMetaAccessTokenAttribute($value)
    {
        $this->attributes['meta_access_token'] = $value ? encrypt($value) : null;
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
