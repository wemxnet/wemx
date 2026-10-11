@extends('admin::layouts.wrapper', [
    'activePage' => 'license',
])

@section('title', 'License')

@section('content')
    @if (session('status'))
        <div class="alert alert-success mb-3" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-secondary mb-1">Current Plan</div>
                    <div class="h3 mb-0">
                        {{ $licenseData['plan_name'] ?? 'Not Available' }} ({{ $licenseData['billing_cycle'] ?? 'Not Available' }})
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-secondary mb-1">Status</div>
                    <div class="h3 mb-0">
                        @php($licenseStatus = (string) data_get($licenseData, 'status', ''))
                        @if ($licenseStatus === 'active')
                            <span class="badge bg-green-lt">Active</span>
                        @elseif ($licenseStatus !== '')
                            <span class="badge bg-red-lt">{{ ucfirst($licenseStatus) }}</span>
                        @else
                            <span class="badge bg-secondary-lt">Unknown</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-secondary mb-1">Expiry Date</div>
                    <div class="h3 mb-0">{{ $licenseData['expires_at_human'] ?? 'Not Available' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-secondary mb-1">Domain</div>
                    <div class="h3 mb-0">{{ $licenseData['domain'] ?? request()->getHost() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">License Key</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.license.update') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">License Key</label>
                            <textarea class="form-control" rows="4" name="license_key" placeholder="WMX-XXXX-XXXX-XXXX-XXXX">{{ $licenseKey }}</textarea>
                            @error('license_key')
                                <small class="text-danger">{{ $message }}</small>
                            @else
                                <small class="form-hint">Save to .env and cache validated license data.</small>
                            @enderror
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save License</button>
                            <button type="submit" class="btn btn-outline-secondary" formaction="{{ route('admin.license.verify') }}">Verify License</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">License Details</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <tbody>
                        <tr>
                            <td class="text-secondary">Plan</td>
                            <td>{{ $licenseData['plan_name'] ?? 'Not Available' }} ({{ $licenseData['billing_cycle'] ?? 'Not Available' }})</td>
                        </tr>
                        <tr>
                            <td class="text-secondary">Contact Email</td>
                            <td>{{ $licenseData['email'] ?? 'Not Available' }}</td>
                        </tr>
                        <tr>
                            <td class="text-secondary">Licensed Domain</td>
                            <td>{{ $licenseData['domain'] ?? request()->getHost() }}</td>
                        </tr>
                        <tr>
                            <td class="text-secondary">Last Validation</td>
                            <td>{{ $licenseData['last_checked_at_human'] ?? 'Not Available' }}</td>
                        </tr>
                        <tr>
                            <td class="text-secondary">Expiry</td>
                            <td>{{ $licenseData['expires_at_human'] ?? 'Not Available' }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Plan Limits</h3>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Admin Users</span>
                            <strong>{{ $limitStaffAccounts }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Client Accounts</span>
                            <strong>{{ $limitMaxUsers }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Active Orders</span>
                            <strong>{{ $limitMaxOrders }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Max Gateways</span>
                            <strong>{{ $limitMaxGateways }}</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Max Servers</span>
                            <strong>{{ $limitMaxServers }}</strong>
                        </li>
                        @foreach($planFeatures as $feature => $included)
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>{{ $feature }}</span>
                                <strong class="{{ $included ? 'text-success' : 'text-secondary' }}">{{ $included ? 'Included' : 'Not included' }}</strong>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Support</h3>
                </div>
                <div class="card-body">
                    <p class="text-secondary mb-3">
                        Need help with activation, domain migration, or plan upgrades?
                    </p>
                    <a href="#" class="btn btn-outline-primary w-100">Contact Support</a>
                </div>
            </div>
        </div>
    </div>
@endsection

