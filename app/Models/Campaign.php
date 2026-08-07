<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Campaign extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        // Ordered list of contact field names mapping to positional template
        // variables: index 0 → {{1}}, index 1 → {{2}}, etc.
        'template_variable_map' => 'array',
    ];

    public function recipients()
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}

