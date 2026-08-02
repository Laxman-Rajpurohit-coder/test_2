<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Contact extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'custom_fields' => 'array',
    ];

    public function contactGroups()
    {
        return $this->belongsToMany(ContactGroup::class);
    }

    public function contactTags()
    {
        return $this->belongsToMany(ContactTag::class);
    }
}
