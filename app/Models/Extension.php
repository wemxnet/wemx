<?php

namespace App\Models;

use App\Extensions\Foundation\ExtensionFoundation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Extension extends Model
{
    protected $table = 'extensions';

    protected $fillable = [
        'identifier',
        'marketplace_id',
        'version',
        'type',
        'name',
        'namespace',
        'status',
        'auto_update',
        'last_updated_at',
    ];

    protected $primaryKey = 'identifier';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'auto_update' => 'boolean',
            'last_updated_at' => 'datetime',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($extension) {
            $extension->type = strtolower($extension->type);
        });
    }

    public function isEnabled(): bool
    {
        return $this->status === 'enabled';
    }

    public function isDisabled(): bool
    {
        return $this->status === 'disabled';
    }

    public function enable(): void
    {
        if (method_exists($this->extension(), 'onEnable')) {
            $this->extension()->onEnable($this);
        }

        // register extension elements
        if (method_exists($this->extension(), 'elements')) {
            $elements = $this->extension()->elements();

            if (! is_array($elements)) {
                throw new \Exception('Extension elements must be an array');
            }

            foreach ($elements as $element) {
                $this->elements()->firstOrCreate(
                    [
                        'element' => $element['element'],
                        'view' => $element['view'] ?? null,
                    ],
                    [
                        'permission' => $element['permission'] ?? null,
                        'attributes' => $element['attributes'] ?? [],
                    ]
                );
            }
        }

        $this->update(['status' => 'enabled']);
    }

    public function disable(): void
    {
        if (method_exists($this->extension(), 'onDisable')) {
            $this->extension()->onDisable($this);
        }

        // unregister extension elements
        $this->elements()->delete();

        $this->update(['status' => 'disabled']);
    }

    public function uninstall(): void
    {
        // todo: delete the extension
    }

    public function elements()
    {
        return $this->hasMany(ExtensionElement::class, 'extension_identifier', 'identifier');
    }

    public static function directories(): array
    {
        return array_merge(config('extensions.directories', []), [
            'Modules' => base_path('extensions/Modules'),
            'Servers' => base_path('extensions/Servers'),
            'Gateways' => base_path('extensions/Gateways'),
        ]);
    }

    public static function discover(): void
    {
        $directories = Extension::directories();

        foreach ($directories as $namespace => $directory) {
            if (File::exists($directory)) {
                $extensions = File::directories($directory);

                foreach ($extensions as $extensionDirectory) {
                    $extensionName = basename($extensionDirectory);
                    $extensionClass = 'Extensions\\'.$namespace.'\\'.$extensionName.'\\'.Str::singular($namespace);

                    if (class_exists($extensionClass)) {
                        try {
                            $extension = new $extensionClass;
                            // check if extensions extends ModuleInterface
                            if (! ($extension instanceof ExtensionFoundation)) {
                                continue;
                            }

                            Extension::updateOrCreate(
                                [
                                    'namespace' => $extensionClass,
                                    'identifier' => $extension->getId(),
                                ],
                                [
                                    'marketplace_id' => $extension->getMarketplaceId(),
                                    'version' => $extension->getVersion(),
                                    'type' => strtolower($extension->extensionType ?? Str::singular($namespace)),
                                    'name' => $extension->getName(),
                                ]
                            );
                        } catch (\Exception $e) {
                            logs()->error("Extension class $extensionClass failed to discover: ".$e->getMessage());
                        }
                    }

                }
            }
        }
    }

    public function extension(): ExtensionFoundation
    {
        if (! class_exists($this->namespace)) {
            $this->delete();
        }

        return new $this->namespace;
    }

    public function functions(): ExtensionFoundation
    {
        return $this->extension();
    }

    public function scopeSearch($query, string $search): void
    {
        if ($search) {
            $query->where('name', 'like', '%'.$search.'%');
        }
    }

    public static function findById($id): self
    {
        return Extension::where('identifier', $id)->first();
    }

    public static function findByNamespace($namespace): self
    {
        return Extension::where('namespace', $namespace)->first();
    }

    public static function findByExternalId($externalId): self
    {
        return Extension::where('external_id', $externalId)->first();
    }

    public static function findByType($type): Collection|\Illuminate\Support\Collection
    {
        return Extension::where('type', $type)->get();
    }

    public static function findByStatus($status): Collection|\Illuminate\Support\Collection
    {
        return Extension::where('status', $status)->get();
    }

    public static function findByAutoUpdate($autoUpdate): Collection|\Illuminate\Support\Collection
    {
        return Extension::where('auto_update', $autoUpdate)->get();
    }

    public static function allEnabled(): Collection|\Illuminate\Support\Collection
    {
        return self::findByStatus('enabled');
    }

    public static function allDisabled(): Collection|\Illuminate\Support\Collection
    {
        return self::findByStatus('disabled');
    }
}
