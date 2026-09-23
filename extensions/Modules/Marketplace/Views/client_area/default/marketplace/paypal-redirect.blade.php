@extends('theme::layouts.wrapper', [
    'activePage' => 'marketplace',
])

@section('title', 'Redirecting to PayPal')

@section('content')
    <div class="mx-auto max-w-lg px-2 sm:px-4">
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Continue to PayPal</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">You are being redirected to complete your purchase of {{ $sale->resource->name }}.</p>
            <form id="paypal-checkout" method="POST" action="{{ $action }}" class="mt-6">
                @foreach($fields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <x-theme::button.primary type="submit">Continue to PayPal</x-theme::button.primary>
            </form>
        </div>
    </div>
    <script>
        document.getElementById('paypal-checkout').submit();
    </script>
@endsection
