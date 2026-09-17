<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 42px 48px; }
        * { box-sizing: border-box; }
        body { color: #30362f; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.45; }
        .top { border-bottom: 2px solid #d86131; padding-bottom: 22px; }
        .brand { color: #4c3a2e; font-size: 24px; font-weight: bold; letter-spacing: 1px; }
        .brand span { color: #b84d2e; }
        .muted { color: #6c715f; }
        .meta { margin-top: 28px; width: 100%; }
        .meta td { vertical-align: top; width: 50%; }
        .label { color: #9a8a72; font-size: 9px; letter-spacing: 1px; text-transform: uppercase; }
        h1 { color: #5b4233; font-size: 26px; margin: 30px 0 5px; }
        table.items { border-collapse: collapse; margin-top: 34px; width: 100%; }
        .items th { background: #f4edcf; border-bottom: 1px solid #cdb58b; color: #625e4d; font-size: 9px; padding: 10px 8px; text-align: left; text-transform: uppercase; }
        .items td { border-bottom: 1px solid #e4dbc3; padding: 12px 8px; }
        .right { text-align: right; }
        .totals { margin-top: 24px; margin-left: auto; width: 240px; }
        .totals td { padding: 5px 0; }
        .grand td { border-top: 2px solid #d86131; color: #5b4233; font-size: 14px; font-weight: bold; padding-top: 10px; }
        .footer { border-top: 1px solid #e4dbc3; color: #77705d; font-size: 9px; margin-top: 58px; padding-top: 14px; }
    </style>
</head>
<body>
    <div class="top">
        <div class="brand">CRAZY DEFENSE <span>HEROES</span></div>
        <div class="muted">{{ $company['name'] }}<br>{{ $company['number'] }}<br>{{ $company['address'] }}<br>{{ $company['email'] }}</div>
    </div>

    <h1>Invoice</h1>
    <div class="muted">Invoice number: {{ $invoice->invoice_number }}<br>Issued: {{ $invoice->issued_at->format('Y-m-d') }}</div>

    <table class="meta">
        <tr>
            <td><div class="label">Billed to</div><strong>{{ $invoice->user->name }} {{ $invoice->user->surname }}</strong><br>{{ $invoice->user->email }}<br>{{ $invoice->user->address?->street }}<br>{{ $invoice->user->address?->city }}, {{ $invoice->user->address?->postcode }}<br>{{ $invoice->user->address?->country }}</td>
            <td><div class="label">Payment</div>Order {{ $invoice->order->public_id }}<br>Status: paid<br>Currency: {{ $invoice->currency }}</td>
        </tr>
    </table>

    <table class="items">
        <thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Unit price</th><th class="right">Total</th></tr></thead>
        <tbody>
        @foreach ($invoice->order->items as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="right">{{ number_format($item->unit_price_minor / 100, 2, '.', '') }} {{ $item->currency }}</td>
                <td class="right">{{ number_format(($item->unit_price_minor * $item->quantity) / 100, 2, '.', '') }} {{ $item->currency }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ number_format($invoice->subtotal_minor / 100, 2, '.', '') }} {{ $invoice->currency }}</td></tr>
        <tr><td>Tax</td><td class="right">{{ number_format($invoice->tax_minor / 100, 2, '.', '') }} {{ $invoice->currency }}</td></tr>
        <tr class="grand"><td>Total</td><td class="right">{{ number_format($invoice->total_minor / 100, 2, '.', '') }} {{ $invoice->currency }}</td></tr>
    </table>

    <div class="footer">Thank you for supporting Crazy Defense Hereoes. Digital goods are delivered to the account shown above.</div>
</body>
</html>
