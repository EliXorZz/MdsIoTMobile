<?php

namespace App\Models;

use App\Enums\CommandStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Command extends Model
{
    use HasUuids;

    protected $primaryKey = 'command_id';

    protected $fillable = [
        'command_id',
        'device_id',
        'action',
        'params',
        'status',
        'issued_at',
        'sent_at',
        'acked_at',
        'timeout_at',
    ];

    protected $casts = [
        'params' => 'array',
        'status' => CommandStatus::class,
        'issued_at' => 'immutable_datetime',
        'sent_at' => 'immutable_datetime',
        'acked_at' => 'immutable_datetime',
        'timeout_at' => 'immutable_datetime',
    ];

    public function uniqueIds(): array
    {
        return ['command_id'];
    }

    public function getEffectiveStatusAttribute(): CommandStatus
    {
        if (! $this->status->isTerminal() && $this->timeout_at->isPast()) {
            return CommandStatus::Timeout;
        }

        return $this->status;
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }
}
