<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Receipt {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #4c3a2e; background-color: #f6f2da; margin: 0; padding: 20px; }
        .card { max-width: 580px; margin: 0 auto; background: #fdfbe8; border: 2px solid #a56f40; border-radius: 6px; padding: 30px; }
        .header { border-bottom: 2px solid #df5e29; padding-bottom: 16px; margin-bottom: 20px; text-align: center; }
        .brand { font-size: 22px; font-weight: 800; color: #4c3a2e; }
        .brand span { color: #df5e29; }
        .footer { margin-top: 30px; padding-top: 18px; border-top: 1px solid rgba(165, 111, 64, 0.25); font-size: 11px; color: #7a705e; text-align: center; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="brand">CRAZY DEFENSE <span>HEREOES</span></div>
        </div>
        <p>Hello {{ $invoice->user->name }},</p>
        <p>Thank you for your Crazy Defense Hereoes purchase. Your official fiscal receipt is attached as a PDF.</p>
        <p><strong>Invoice:</strong> {{ $invoice->invoice_number }}<br>
        <strong>Total:</strong> {{ number_format($invoice->total_minor / 100, 2, '.', '') }} {{ $invoice->currency }}</p>
        <p>Keep this confirmation for your records.</p>
        <div class="footer">
            <p><strong>{{ config('company.name', 'Crazy Defense Hereoes Ltd') }}</strong></p>
            <p>Registration No: <strong>{{ config('company.number', '14892341') }}</strong></p>
            <p>Address: {{ config('company.address', '71-75 Shelton Street, Covent Garden, London, WC2H 9JQ, United Kingdom') }}</p>
            <p>Support: <a href="mailto:{{ config('company.email', 'info@crazydefensehereoes.co.uk') }}">{{ config('company.email', 'info@crazydefensehereoes.co.uk') }}</a> · <a href="https://{{ config('company.domain', 'crazydefensehereoes.co.uk') }}">{{ config('company.domain', 'crazydefensehereoes.co.uk') }}</a></p>
        </div>
    </div>
</body>
</html>
