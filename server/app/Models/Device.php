<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'name',
        'type',
        'ip_address',
        'mac_address',
        'command_topic',
        'state_topic',
        'availability_topic',
        'state',
        'is_online',
        'channels',
        'last_seen_at',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'last_seen_at' => 'datetime',
        'channels' => 'integer',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(DeviceLog::class)->latest();
    }
}
