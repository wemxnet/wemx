{{-- resources/views/components/sidebar.blade.php --}}

<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
    <div class="container-fluid">
        <!-- Toggle button and brand -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
                aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <a href="{{ route('admin.index') }}" class="navbar-brand navbar-brand-autodark">
            @if(!empty(settings('logo', '')))
                <img src="{{ asset(settings('logo')) }}" alt="{{ settings('site_name', 'WemX') }}"
                     class="avatar rounded-5">
            @endif
            <span class="ms-2">{{ settings('app_name', 'WemX') }}</span>
        </a>

        <!-- Sidebar menu -->
        <div class="collapse navbar-collapse" id="sidebar-menu">
            <ul class="navbar-nav pt-lg-3">

                <!-- Dashboard -->
                @perm('admin.dashboard')
                <x-admin::navigation.sidebar-item
                    :title="__('messages.dashboard')"
                    :href="route('admin.index')"
                    :active="$activePage === 'dashboard'">
                    <x-slot name="icon">
                        <x-admin::icon icon="layout-dashboard" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                @perm(['admin.users', 'admin.roles', 'admin.payments', 'admin.orders'])
                <li class="nav-item mt-2 mb-1">
                    <div class="text-muted text-uppercase px-3 small">Customers & Orders</div>
                </li>
                @endperm

                <!-- Customers -->
                @perm('admin.users')
                <x-admin::navigation.sidebar-item
                    title="{{ __('messages.customers') }} ({{ \App\Models\User::count() }})"
                    :href="route('admin.users.index')"
                    :active="$activePage === 'users'">
                    <x-slot name="icon">
                        <x-admin::icon icon="users" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                <!-- Roles -->
                @perm('admin.roles')
                <x-admin::navigation.sidebar-item
                    title="Staff Roles"
                    :href="route('admin.roles.index')"
                    :active="$activePage === 'roles'">
                    <x-slot name="icon">
                        <x-admin::icon icon="tags" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                <!-- Payments -->
                @perm('admin.payments')
                <x-admin::navigation.sidebar-item
                    title="{{ __('messages.payments') }} ({{ \App\Models\Payment::whereStatus('paid')->count() }})"
                    :href="route('admin.payments.index')"
                    :active="$activePage === 'payments'">
                    <x-slot name="icon">
                        <x-admin::icon icon="cash" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                <!-- Orders -->
                @perm('admin.orders')
                <x-admin::navigation.sidebar-item
                    title="{{ __('messages.orders') }} ({{ \App\Models\Order::whereStatus('active')->count() }})"
                    :href="route('admin.orders.index')"
                    :active="$activePage === 'orders'">
                    <x-slot name="icon">
                        <x-admin::icon icon="archive" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                <!-- Subscriptions -->
                @perm('admin.subscriptions')
                <x-admin::navigation.sidebar-item
                    title="Subscriptions ({{ \App\Models\Subscription::whereStatus('active')->count() }})"
                    :href="route('admin.subscriptions.index')"
                    :active="$activePage === 'subscriptions'">
                    <x-slot name="icon">
                        <x-admin::icon icon="refresh" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                <li class="nav-item mt-2 mb-1">
                    <div class="text-muted text-uppercase px-3 small">Products & Services</div>
                </li>

                <!-- Categories -->
                @perm('admin.categories.index')
                <x-admin::navigation.sidebar-item
                    :title="__('messages.categories')"
                    :href="route('admin.categories.index')"
                    :active="$activePage === 'categories'">
                    <x-slot name="icon">
                        <x-admin::icon icon="tag" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                <!-- Packages -->
                @perm('admin.packages.index')
                <x-admin::navigation.sidebar-item
                    :title="__('messages.packages')"
                    :href="route('admin.packages.index')"
                    :active="$activePage === 'packages'">
                    <x-slot name="icon">
                        <x-admin::icon icon="package" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                <!-- Gateways -->
                @perm('admin.gateways.*')
                <x-admin::navigation.sidebar-item
                    :title="__('messages.gateways')"
                    :active="in_array($activePage, ['gateways', 'installed_gateways'])"
                    :dropdown="true"
                    :id="'server-menu'">
                    <x-slot name="icon">
                        <x-admin::icon icon="cash-register" outline/>
                    </x-slot>
                    @perm('admin.gateways.index')
                    <x-admin::navigation.sidebar-dropdown-item
                        :title="__('messages.gateway_configs')"
                        :href="route('admin.gateways.configs.index')"
                        :active="$activePage === 'gateways'"
                        :icon="'adjustments-cog'"/>
                    @endperm
                    @perm('admin.gateways.index')
                    <x-admin::navigation.sidebar-dropdown-item
                        :title="__('messages.installed_gateways')"
                        :href="route('admin.gateways.index')"
                        :active="$activePage === 'installed_gateways'"
                        icon="download"/>
                    @endperm
                </x-admin::navigation.sidebar-item>
                @endperm

                <!-- Servers -->
                @perm('admin.servers.*')
                <x-admin::navigation.sidebar-item
                    :title="__('messages.servers')"
                    :active="in_array($activePage, ['server_connections', 'servers'])"
                    :dropdown="true"
                    :id="'server-menu'">
                    <x-slot name="icon">
                        <x-admin::icon icon="server-cog" outline/>
                    </x-slot>
                    @perm('admin.servers.connections')
                    <x-admin::navigation.sidebar-dropdown-item
                        :title="__('messages.server_connections')"
                        :href="route('admin.servers.connections')"
                        :active="$activePage === 'server_connections'"
                        :icon="'plug-connected'"/>
                    @endperm
                    @perm('admin.servers.index')
                    <x-admin::navigation.sidebar-dropdown-item
                        :title="__('messages.installed_servers')"
                        :href="route('admin.servers.index')"
                        :active="$activePage === 'servers'"
                        :icon="'download'"/>
                    @endperm
                </x-admin::navigation.sidebar-item>
                @endperm

                <li class="nav-item mt-2 mb-1">
                    <div class="text-muted text-uppercase px-3 small">Application Settings</div>
                </li>

                <!-- Settings -->
                @perm('admin.settings.index')
                <x-admin::navigation.sidebar-item
                    :title="__('messages.settings')"
                    :href="route('admin.settings.index')"
                    :active="$activePage === 'settings'"
                    :id="'settings-menu'">
                    <x-slot name="icon">
                        <x-admin::icon icon="settings" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                @perm('admin.settings.index')
                <x-admin::navigation.sidebar-item
                    title="License"
                    :href="route('admin.license.index')"
                    :active="$activePage === 'license'">
                    <x-slot name="icon">
                        <x-admin::icon icon="certificate" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                @perm('admin.settings.index')
                <x-admin::navigation.sidebar-item
                    title="Updates"
                    :href="route('admin.updates.index')"
                    :active="$activePage === 'updates'">
                    <x-slot name="icon">
                        <x-admin::icon icon="refresh" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                @perm('admin.pages.index')
                <x-admin::navigation.sidebar-item
                    :title="__('messages.pages')"
                    :href="route('admin.pages.index')"
                    :active="$activePage === 'pages'">
                    <x-slot name="icon">
                        <x-admin::icon icon="file-text" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                <!-- Currencies -->
                @perm('admin.currencies.index')
                <x-admin::navigation.sidebar-item
                    :title="__('messages.currencies')"
                    :href="route('admin.currencies.index')"
                    :active="$activePage === 'currencies'">
                    <x-slot name="icon">
                        <x-admin::icon icon="world-dollar" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                <!-- Tax Countries -->
                @perm('admin.taxes.index')
                <x-admin::navigation.sidebar-item
                    title="Tax Countries"
                    :href="route('admin.taxes.index')"
                    :active="$activePage === 'taxes'">
                    <x-slot name="icon">
                        <x-admin::icon icon="tax" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm

                <!-- Emails -->
                @perm(['admin.emails.index', 'admin.emails.configure', 'admin.emails.templates'])
                <x-admin::navigation.sidebar-item
                    :title="__('messages.emails')"
                    :active="in_array($activePage, ['emails', 'configure_emails', 'email_templates'])"
                    :dropdown="true"
                    :id="'email-menu'">
                    <x-slot name="icon">
                        <x-admin::icon icon="mail-cog" outline/>
                    </x-slot>
                    @perm('admin.emails.templates')
                    <x-admin::navigation.sidebar-dropdown-item
                        :title="__('messages.email_templates')"
                        :href="route('admin.emails.templates.index')"
                        :active="$activePage === 'email_templates'"
                        :icon="'file-text'"/>
                    @endperm
                    @perm('admin.emails.configure')
                    <x-admin::navigation.sidebar-dropdown-item
                        :title="__('messages.configure_smtp')"
                        :href="route('admin.emails.configure')"
                        :active="$activePage === 'configure_emails'"
                        :icon="'mail-cog'"/>
                    @endperm
                    @perm('admin.emails.index')
                    <x-admin::navigation.sidebar-dropdown-item
                        :title="__('messages.email_history')"
                        :href="route('admin.emails.index')"
                        :active="$activePage === 'emails'"
                        :icon="'send'"/>
                    @endperm
                </x-admin::navigation.sidebar-item>
                @endperm

                <li class="nav-item mt-2 mb-1">
                    <div class="text-muted text-uppercase px-3 small">Third Party</div>
                </li>

                {{-- TEMPORARY: Marketplace sidebar entry disabled — uncomment to restore.
                @perm('admin.marketplace.index')
                <x-admin::navigation.sidebar-item
                    title="Marketplace"
                    :href="route('admin.marketplace.index')"
                    :active="$activePage === 'marketplace'">
                    <x-slot name="icon">
                        <x-admin::icon icon="plug" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm
                --}}

                <!-- Installed Extensions -->
                @perm('admin.extensions.index')
                <x-admin::navigation.sidebar-item
                    title="Installed Extensions"
                    :href="route('admin.extensions.index')"
                    :active="$activePage === 'extensions'">
                    <x-slot name="icon">
                        <x-admin::icon icon="download" outline/>
                    </x-slot>
                </x-admin::navigation.sidebar-item>
                @endperm


                <!-- Extensions section -->
                @foreach(extensionElements(['admin-sidebar-item', 'admin-sidebar-item-dropdown']) as $element)
                    @if(isset($element['permission']) && !auth()->user()->hasPermission($element['permission']))
                        @continue;
                    @endif

                    @if($element['element'] == 'admin-sidebar-item')
                        <x-admin::navigation.sidebar-item
                            :title="$element['attributes']['name'] ?? 'Undefined'"
                            :href="$element['attributes']['href'] ?? '#'"
                            :active="$activePage === (isset($element['attributes']['active']) ? $element['attributes']['active'] : 'undefined')">
                            <x-slot name="icon">
                                <x-admin::icon :icon="$element['attributes']['icon'] ?? 'puzzle'" outline/>
                            </x-slot>
                        </x-admin::navigation.sidebar-item>
                    @elseif($element['element'] == 'admin-sidebar-item-dropdown')
                        <x-admin::navigation.sidebar-item
                            title="{{ $element['attributes']['name'] ?? 'Undefined' }}"
                            :active="in_array($activePage, (is_array($element['attributes']['active']) ? $element['attributes']['active'] : [$element['attributes']['active']]))"
                            :dropdown="true"
                            :id="'marketplace-menu'">
                            <x-slot name="icon">
                                <x-admin::icon icon="{{ $element['attributes']['icon'] }}" outline/>
                            </x-slot>
                            @foreach($element['attributes']['items'] as $item)
                                @if(isset($item['permission']) && !auth()->user()->hasPermission($item['permission']))
                                    @continue;
                                @endif
                                <x-admin::navigation.sidebar-dropdown-item
                                    :title="$item['name'] ?? 'Undefined'"
                                    :href="$item['href'] ?? '#'"
                                    :active="$activePage === (isset($item['active']) ? $item['active'] : 'undefined')"
                                    :icon="$item['icon'] ?? 'puzzle'"/>
                            @endforeach
                        </x-admin::navigation.sidebar-item>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>
</aside>
