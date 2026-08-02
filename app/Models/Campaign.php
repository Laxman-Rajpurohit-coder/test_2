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

    public function recipients()
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
