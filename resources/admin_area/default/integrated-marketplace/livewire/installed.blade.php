<?php

use App\Models\IntegratedMarketplaceInstallation;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, IntegratedMarketplaceInstallation>
     */
    #[Computed]
    public function installations()
    {
        return IntegratedMarketplaceInstallation::query()
            ->with('user')
            ->orderByDesc('installed_at')
            ->orderByDesc('id')
            ->get();
    }
}

?>

<div>
    <p class="text-secondary mb-3">Resources installed from the integrated marketplace, newest first.</p>

    @if($this->installations->isEmpty())
        <div class="empty">
            <p class="empty-title">Nothing installed yet</p>
            <p class="empty-subtitle text-secondary">Resources you install from the marketplace are listed here, including ones that were removed afterwards.</p>
            <div class="empty-action">
                <a href="{{ route('admin.marketplace.index') }}" wire:navigate class="btn btn-primary">Browse marketplace</a>
            </div>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Resource</th>
                            <th>Version</th>
                            <th>Namespace</th>
                            <th>Location</th>
                            <th>Installed</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->installations as $installation)
                            @php($present = $installation->isPresent())
                            <tr wire:key="installation-{{ $installation->id }}">
                                <td>
                                    <a href="{{ route('admin.marketplace.show', $installation->resource_slug) }}" wire:navigate class="fw-medium">{{ $installation->resource_name }}</a>
                                    <div class="text-secondary small">
                                        {{ $installation->resource_slug }}
                                        @if($installation->category)
                                            · {{ $installation->category }}
                                        @endif
                                        @if($installation->identifier)
                                            · {{ $installation->identifier }}
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $installation->version !== '' ? $installation->version : '—' }}</td>
                                <td class="text-secondary"><code>{{ $installation->namespace ?: '—' }}</code></td>
                                <td class="text-secondary"><code>{{ $installation->path }}</code></td>
                                <td>
                                    <div>{{ $installation->installed_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</div>
                                    <div class="text-secondary small">{{ $installation->user?->username ?? 'Unknown' }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $present ? 'bg-green-lt' : 'bg-red-lt' }}">{{ $present ? 'Installed' : 'Deleted' }}</span>
                                    <div class="text-secondary small mt-1">{{ $present ? 'Successfully installed' : 'Was installed, but the namespace/extension could not be found' }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
