@extends('business.layouts.app')

@section('title', $invoice->invoice_number)

@push('css_or_js')
<style>
    .invoice-box {
        max-width: 800px;
        margin: 0 auto;
        padding: 30px;
        border: 1px solid #eee;
        box-shadow: 0 0 10px rgba(0,0,0,.08);
        font-size: 14px;
        color: #333;
    }
    .invoice-header {
        border-bottom: 2px solid var(--ug-primary, #0f2440);
        padding-bottom: 20px;
        margin-bottom: 20px;
    }
    .invoice-brand {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--ug-primary, #0f2440);
        letter-spacing: -0.5px;
    }
    .invoice-brand span {
        color: var(--ug-orange, #f47b20);
    }
    .invoice-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--ug-primary, #0f2440);
        text-align: right;
    }
    .invoice-table {
        width: 100%;
        border-collapse: collapse;
    }
    .invoice-table th {
        background: var(--ug-primary, #0f2440);
        color: #fff;
        padding: 10px 12px;
        text-align: left;
        font-size: 13px;
    }
    .invoice-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #eee;
    }
    .invoice-table tr:last-child td {
        border-bottom: none;
    }
    .total-row td {
        font-weight: 700;
        font-size: 16px;
        border-top: 2px solid #333;
        padding-top: 12px;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .status-paid { background: #d4edda; color: #155724; }
    .status-sent { background: #cce5ff; color: #004085; }
    .status-draft { background: #e2e3e5; color: #383d41; }
    .status-overdue { background: #f8d7da; color: #721c24; }
    .status-canceled { background: #e2e3e5; color: #6c757d; }
    .print-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 100;
    }
    @media print {
        .navbar, .page-header, .print-btn, footer { display: none !important; }
        .invoice-box { border: none; box-shadow: none; padding: 0; }
        .main { padding: 0 !important; }
    }
</style>
@endpush

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="page-header-title">{{ $invoice->invoice_number }}</h1>
        <div class="d-flex gap-1">
            <a href="{{ route('business.invoices.index') }}" class="btn btn-secondary">{{ translate('Back to Invoices') }}</a>
            <button class="btn btn--primary" onclick="window.print()">{{ translate('Print') }}</button>
        </div>
    </div>

    <div class="invoice-box">
        <div class="invoice-header d-flex justify-content-between align-items-start">
            <div>
                <div class="invoice-brand">Urban <span>Goodz</span></div>
                <div style="color: #6c757d; font-size: 13px; margin-top: 4px;">{{ translate('Last Mile Delivery Logistics') }}</div>
            </div>
            <div class="text-right">
                <div class="invoice-title">{{ translate('INVOICE') }}</div>
                <div style="color: #6c757d; font-size: 13px; margin-top: 4px;">
                    <strong>{{ $invoice->invoice_number }}</strong>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <h6 style="font-weight: 700; color: var(--ug-primary, #0f2440); margin-bottom: 8px;">{{ translate('Bill To') }}</h6>
                <div style="color: #333; line-height: 1.6;">
                    <strong>{{ $invoice->client?->company_name ?? $invoice->client?->legal_name ?? '-' }}</strong><br>
                    {{ $invoice->client?->address ?? '' }}<br>
                    @if($invoice->client?->city)
                        {{ $invoice->client->city }}, {{ $invoice->client->state ?? '' }} {{ $invoice->client->postal_code ?? '' }}<br>
                    @endif
                    {{ $invoice->client?->billing_email ?? $invoice->client?->email ?? '' }}
                </div>
            </div>
            <div class="col-6 text-right">
                <h6 style="font-weight: 700; color: var(--ug-primary, #0f2440); margin-bottom: 8px;">{{ translate('Invoice Details') }}</h6>
                <div style="color: #333; line-height: 1.6;">
                    <strong>{{ translate('Status') }}:</strong>
                    <span class="status-badge status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span><br>
                    <strong>{{ translate('Date') }}:</strong> {{ $invoice->created_at->format('F d, Y') }}<br>
                    @if($invoice->sent_at)
                        <strong>{{ translate('Sent') }}:</strong> {{ $invoice->sent_at->format('F d, Y') }}<br>
                    @endif
                    @if($invoice->paid_at)
                        <strong>{{ translate('Paid') }}:</strong> {{ $invoice->paid_at->format('F d, Y') }}<br>
                    @endif
                    <strong>{{ translate('Type') }}:</strong> {{ ucfirst($invoice->invoice_type) }}<br>
                    @if($invoice->route)
                        <strong>{{ translate('Route') }}:</strong> {{ $invoice->route->route_name }}
                    @endif
                </div>
            </div>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>{{ translate('Description') }}</th>
                    <th style="width: 120px; text-align: right;">{{ translate('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        {{ ucfirst($invoice->invoice_type) }} {{ translate('invoice for') }}
                        @if($invoice->route)
                            {{ $invoice->route->route_name }}
                        @else
                            {{ translate('delivery services') }}
                        @endif
                        @if($invoice->notes)
                            <br><small style="color: #6c757d;">{{ $invoice->notes }}</small>
                        @endif
                    </td>
                    <td style="text-align: right;">${{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                @if($invoice->tax > 0)
                <tr>
                    <td>{{ translate('Tax') }}</td>
                    <td style="text-align: right;">${{ number_format($invoice->tax, 2) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td><strong>{{ translate('Total') }}</strong></td>
                    <td style="text-align: right;">
                        <strong>${{ number_format($invoice->total, 2) }}</strong>
                        @if($invoice->currency)
                            <small style="color: #6c757d;"> {{ $invoice->currency }}</small>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; text-align: center; color: #6c757d; font-size: 12px;">
            <strong style="color: var(--ug-primary, #0f2440);">Urban Goodz</strong> &mdash;
            {{ translate('Last Mile Delivery Logistics') }}<br>
            {{ translate('Thank you for your business') }}
        </div>
    </div>
@endsection
