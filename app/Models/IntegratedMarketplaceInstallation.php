<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntegratedMarketplaceInstallation extends Model
{
    protected $fillable = [
        'user_id',
        'marketplace_resource_id',
        'resource_slug',
        'resource_name',
        'category',
        'version_id',
        'version',
        'namespace',
        'identifier',
        'path',
        'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPresent(): bool
    {
        if ($this->path !== '' && ! is_dir(base_path($this->path))) {
            return false;
        }

        if (! is_string($this->namespace) || $this->namespace === '') {
            return $this->path !== '' && is_dir(base_path($this->path));
        }

        $classFile = base_path('extensions/'.str_replace('\\', '/', Str::after($this->namespace, 'Extensions\\')).'.php');

        return is_file($classFile);
    }

    public function statusLabel(): string
    {
        return $this->isPresent() ? 'Installed' : 'Deleted';
    }

    public function statusDescription(): string
    {
        return $this->isPresent()
            ? 'Successfully installed'
            : 'Was installed, but the namespace/extension could not be found';
    }
}
