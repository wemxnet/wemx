<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $payment->invoice_id ?: ('#' . $payment->id) }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 24px; }
        .header { width: 100%; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .title { font-size: 26px; font-weight: 700; margin: 0; }
        .muted { color: #6b7280; }
        .section { margin-top: 18px; }
        .section-title { font-size: 14px; font-weight: 700; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f9fafb; font-weight: 700; }
        .billing-grid td { width: 50%; vertical-align: top; border: 0; padding-left: 0; padding-right: 0; }
        .billing-grid td:last-child { text-align: right; }
        .billing-title { font-size: 13px; font-weight: 700; margin-bottom: 6px; }
        .billing-block { line-height: 1.5; }
        .status-text { font-size: 12px; font-weight: 600; color: #4b5563; }
        .right { text-align: right; }
        .totals td { border: 0; padding: 4px 0; }
        .totals .label { color: #4b5563; }
        .totals .value { text-align: right; }
        .grand-total { font-size: 15px; font-weight: 700; border-top: 1px solid #d1d5db; padding-top: 8px; }
    </style>
</head>
<body>
@php
    $companyName = settings('app_name', 'Application');
    $companyAddress = settings('company_address', '');
    $billingFromDetails = trim((string) settings('billing_from_details', ''));
    $billingFromBlock = $billingFromDetails !== '' ? $billingFromDetails : trim((string) $companyAddress);
    $invoiceNumber = $payment->invoice_id ?: ('#' . $payment->id);
    $invoiceDate = $payment->paid_at ?: $payment->created_at;
    $status = ucfirst($payment->status);
    $userAddress = $payment->user?->address;
@endphp

<table class="header">
    <tr>
        <td>
            <h1 class="title">Invoice</h1>
            <div class="muted">{{ $companyName }}</div>
        </td>
        <td class="right">
            <div><strong>Invoice ID:</strong> {{ $invoiceNumber }}</div>
            <div><strong>Date:</strong> {{ $invoiceDate ? $invoiceDate->format(settings('date_format', 'd M Y H:i')) : '-' }}</div>
            <div><strong>Status:</strong> <span class="status-text">{{ $status }}</span></div>
        </td>
    </tr>
</table>

<div class="section">
    <table class="billing-grid">
        <tr>
            <td>
                <div class="billing-title">Billing From</div>
                <div class="billing-block">
                    <div><strong>{{ $companyName }}</strong></div>
                    @if($billingFromBlock !== '')
                        <div>{!! nl2br(e($billingFromBlock)) !!}</div>
                    @else
                        <div class="muted">No billing sender details configured.</div>
                    @endif
                </div>
            </td>
            <td>
                <div class="billing-title">Billing To</div>
                @if($payment->user)
                    <div class="billing-block right">
                        <div><strong>{{ $payment->user->full_name }} ({{ $payment->user->username }})</strong></div>
                        <div>{{ $payment->user->email }}</div>
                        @if($userAddress)
                            @if($userAddress->company_name)
                                <div>{{ $userAddress->company_name }}</div>
                            @endif
                            @if($userAddress->address)
                                <div>{{ $userAddress->address }}</div>
                            @endif
                            @if($userAddress->city || $userAddress->region || $userAddress->zip_code)
                                <div>{{ trim(($userAddress->zip_code ? $userAddress->zip_code . ' ' : '') . ($userAddress->city ?: '') . ($userAddress->region ? ', ' . $userAddress->region : '')) }}</div>
                            @endif
                            @if($userAddress->country)
                                <div>{{ $userAddress->country_name ?? \App\Facades\World::countryName($userAddress->country) }}</div>
                            @endif
                            @if($userAddress->tax_id)
                                <div>Tax ID: {{ $userAddress->tax_id }}</div>
                            @endif
                        @endif
                    </div>
                @else
                    <div class="muted">Guest / Unknown user</div>
                @endif
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Payment Details</div>
    <table>
        <thead>
        <tr>
            <th>Description</th>
            <th>Gateway</th>
            <th>Transaction ID</th>
            <th class="right">Amount</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>{{ $payment->description }}</td>
            <td>{{ $payment->gatewayConfig?->display_name ?: 'N/A' }}</td>
            <td>{{ $payment->transaction_id ?: 'N/A' }}</td>
            <td class="right">{{ priceIn($payment->total(), $payment->currency) }}</td>
        </tr>
        </tbody>
    </table>
</div>

@if($payment->taxDetails)
    <div class="section">
        <div class="section-title">Tax Information</div>
        <table>
            <tbody>
            @if($payment->taxDetails->company_name)
                <tr>
                    <td>Company Name</td>
                    <td class="right">{{ $payment->taxDetails->company_name }}</td>
                </tr>
            @endif
            @if($payment->taxDetails->tax_id)
                <tr>
                    <td>Tax ID</td>
                    <td class="right">{{ $payment->taxDetails->tax_id }}</td>
                </tr>
            @endif
            @if($payment->taxDetails->country)
                <tr>
                    <td>Country</td>
                    <td class="right">{{ \App\Facades\World::countryName($payment->taxDetails->country) }}</td>
                </tr>
            @endif
            @if($payment->taxDetails->region)
                <tr>
                    <td>Region / State</td>
                    <td class="right">{{ $payment->taxDetails->region }}</td>
                </tr>
            @endif
            @if($payment->taxDetails->zip_code)
                <tr>
                    <td>ZIP / Postal Code</td>
                    <td class="right">{{ $payment->taxDetails->zip_code }}</td>
                </tr>
            @endif
            <tr>
                <td>Tax Name</td>
                <td class="right">{{ $payment->taxDetails->tax_name ?: 'Sales Tax' }} {{ $payment->taxDetails->tax_rate ? '(' . $payment->taxDetails->tax_rate . '%)' : '' }}</td>
            </tr>
            </tbody>
        </table>
    </div>
@endif

<div class="section">
    <div class="section-title">Totals</div>
    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="value">{{ priceIn($payment->subtotal, $payment->currency) }}</td>
        </tr>
        <tr>
            <td class="label">Tax</td>
            <td class="value">{{ priceIn($payment->tax, $payment->currency) }}</td>
        </tr>
        <tr>
            <td class="label">Discount</td>
            <td class="value">{{ priceIn($payment->discount, $payment->currency) }}</td>
        </tr>
        <tr class="grand-total">
            <td>Total</td>
            <td class="value">{{ priceIn($payment->total(), $payment->currency) }}</td>
        </tr>
    </table>
</div>

</body>
</html>
