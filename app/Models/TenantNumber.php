<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantNumber extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'integrated_number'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
