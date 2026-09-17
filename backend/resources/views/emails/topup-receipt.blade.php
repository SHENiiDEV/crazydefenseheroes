<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treasury Top-Up Receipt</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f6f2da; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #4c3a2e; }
        .wrapper { width: 100%; max-width: 600px; margin: 0 auto; padding: 30px 15px; }
        .card { background-color: #fdfbe8; border: 2px solid #a56f40; border-radius: 6px; padding: 36px 30px; box-shadow: 0 8px 24px rgba(78, 57, 38, 0.12); }
        .header { text-align: center; border-bottom: 2px solid #df5e29; padding-bottom: 20px; margin-bottom: 24px; }
        .brand { font-size: 24px; font-weight: 800; color: #4c3a2e; letter-spacing: 1px; text-transform: uppercase; }
        .brand span { color: #df5e29; }
        .eyebrow { font-family: monospace; font-size: 11px; letter-spacing: 2px; color: #df5e29; text-transform: uppercase; margin-bottom: 6px; font-weight: 700; }
        h1 { font-size: 22px; color: #3b2d23; margin: 0 0 16px; }
        p { font-size: 14px; line-height: 1.6; color: #5c4e3c; margin: 0 0 16px; }
        .receipt-table { width: 100%; border-collapse: collapse; margin: 20px 0; background: #fbf6de; border: 1px solid #d4c4a4; border-radius: 4px; overflow: hidden; }
        .receipt-table th { background: #efe3be; color: #5a4534; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 10px 14px; text-align: left; }
        .receipt-table td { padding: 12px 14px; border-top: 1px solid #e7dbbe; font-size: 13px; color: #42362b; }
        .receipt-table tr.total-row td { background: #f7edcf; font-weight: 800; color: #b74428; font-size: 14px; }
        .right { text-align: right; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(165, 111, 64, 0.25); font-size: 11px; line-height: 1.6; color: #7a705e; text-align: center; }
        .footer p { font-size: 11px; color: #7a705e; margin: 0 0 6px; }
        .footer a { color: #df5e29; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="eyebrow">✦ TREASURY TRANSACTION RECEIPT ✦</div>
                <div class="brand">CRAZY DEFENSE <span>HEROES</span></div>
            </div>

            <h1>Payment Confirmation, {{ $user->name }}!</h1>
            <p>Your treasury refill was successfully authorized and the virtual goods have been credited to your active watch wallet.</p>

            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Package Description</th>
                        <th class="right">Amount Added</th>
                        <th class="right">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>{{ $packageCode }}</strong></td>
                        <td class="right">
                            @if ($diamondsGranted > 0) +{{ $diamondsGranted }} 💎 @endif
                            @if ($coinsGranted > 0) +{{ $coinsGranted }} 🪙 @endif
                        </td>
                        <td class="right">{{ $priceFormatted }}</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="2"><strong>Total Paid</strong></td>
                        <td class="right"><strong>{{ $priceFormatted }}</strong></td>
                    </tr>
                </tbody>
            </table>

            <p style="font-size: 12px; color: #887a64; text-align: center;">Transaction processed securely with 256-bit SSL encryption. Retain this confirmation for your records.</p>

            <div class="footer">
                <p><strong>{{ $company['name'] ?? 'Crazy Defense Heroes Ltd' }}</strong></p>
                <p>Company Registration No: <strong>{{ $company['number'] ?? '14892341' }}</strong></p>
                <p>Registered Address: {{ $company['address'] ?? '71-75 Shelton Street, Covent Garden, London, WC2H 9JQ, United Kingdom' }}</p>
                <p>Email: <a href="mailto:{{ $company['email'] ?? 'info@crazydefensehereoes.co.uk' }}">{{ $company['email'] ?? 'info@crazydefensehereoes.co.uk' }}</a> · Website: <a href="https://{{ $company['domain'] ?? 'crazydefensehereoes.co.uk' }}">{{ $company['domain'] ?? 'crazydefensehereoes.co.uk' }}</a></p>
                <p>© {{ date('Y') }} {{ $company['name'] ?? 'Crazy Defense Heroes Ltd' }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
