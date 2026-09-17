<?php

namespace App\Models;

use App\Actions\ServerConnectionActions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerConnection extends Model
{
    protected $table = 'server_connections';

    protected $fillable = [
        'alias',
        'short_description',
        'extension_identifier',
        'receive_alerts',
        'alert_email',
        'config',
        'status',
        'is_active',
        'prevent_purchasing',
        'last_checked_at',
    ];

    protected function casts()
    {
        return [
            'config' => 'encrypted:array',
            'is_active' => 'boolean',
            'prevent_purchasing' => 'boolean',
            'receive_alerts' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function extension()
    {
        return $this->belongsTo(Extension::class, 'extension_identifier', 'identifier');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'extension_identifier', 'identifier');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'connection_id');
    }

    public static function actions(): ServerConnectionActions
    {
        return new ServerConnectionActions;
    }

    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('alias', 'like', "%$search%");
    }
}
