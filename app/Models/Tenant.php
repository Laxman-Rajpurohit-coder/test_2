<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_active'];

    public function settings()
    {
        return $this->hasOne(TenantSetting::class);
    }

    public function numbers()
    {
        return $this->hasMany(TenantNumber::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
