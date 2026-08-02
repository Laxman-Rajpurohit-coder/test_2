<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ContactTag extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    public $timestamps = false; // The migration only has id, tenant_id, name

    protected $guarded = [];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class);
    }
}
