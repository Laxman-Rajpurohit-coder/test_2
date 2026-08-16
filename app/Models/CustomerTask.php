<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\BelongsToTenant;

class CustomerTask extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'customer_tasks';

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'conversation_id',
        'title',
        'description',
        'status',
        'due_at',
        'reminder_sent_at',
        'type',
        'template_name',
        'template_language',
        'template_components',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'template_components' => 'array',
    ];

    /**
     * Relationship with the Tenant model.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship with the Contact model.
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
