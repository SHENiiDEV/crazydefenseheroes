<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Crazy Defense Hereoes</title>
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
        .starter-box { background-color: #f4edd2; border: 1px dashed #a56f40; border-radius: 4px; padding: 18px; margin: 24px 0; }
        .starter-title { font-size: 12px; font-weight: 700; color: #b74428; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; font-family: monospace; }
        .starter-list { list-style: none; padding: 0; margin: 0; }
        .starter-list li { font-size: 13px; color: #4a3e31; padding: 5px 0; border-bottom: 1px solid rgba(165, 111, 64, 0.2); }
        .starter-list li:last-child { border-bottom: none; }
        .btn-wrap { text-align: center; margin: 30px 0 20px; }
        .btn { display: inline-block; background: linear-gradient(180deg, #e37c38, #b74428); color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 4px; font-weight: 700; font-size: 14px; letter-spacing: 1px; text-transform: uppercase; border: 1px solid #fff3cd; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(165, 111, 64, 0.25); font-size: 11px; line-height: 1.6; color: #7a705e; text-align: center; }
        .footer p { font-size: 11px; color: #7a705e; margin: 0 0 6px; }
        .footer a { color: #df5e29; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="eyebrow">✦ REALM WATCH INITIALIZED ✦</div>
                <div class="brand">CRAZY DEFENSE <span>HEREOES</span></div>
            </div>

            <h1>Welcome to the watch, {{ $user->name }}!</h1>
            <p>Your realm defender account is active and verified. Your battle progress, cards, and treasury wallet are synced across the citadel network.</p>

            <div class="starter-box">
                <div class="starter-title">🎁 Starter Loadout Credited to Your Account:</div>
                <ul class="starter-list">
                    <li>🏹 <strong>Ember Archer</strong> (Fast / First Target) — Level 1</li>
                    <li>🪙 <strong>100 Gold Coins</strong> (Treasury Soft Currency)</li>
                    <li>✧ <strong>Welcome Supply Pack</strong> × 1</li>
                    <li>⚡ <strong>First Watch Boost</strong> × 1</li>
                </ul>
            </div>

            <div class="btn-wrap">
                <a href="https://{{ $company['domain'] ?? 'crazydefensehereoes.co.uk' }}" class="btn">Enter Battle Arena ↗</a>
            </div>

            <p style="font-size: 12px; color: #887a64; text-align: center;">Need assistance? Contact our dispatchers at <a href="mailto:{{ $company['email'] ?? 'info@crazydefensehereoes.co.uk' }}" style="color: #df5e29;">{{ $company['email'] ?? 'info@crazydefensehereoes.co.uk' }}</a>.</p>

            <div class="footer">
                <p><strong>{{ $company['name'] ?? 'Crazy Defense Hereoes Ltd' }}</strong></p>
                <p>Company Registration No: <strong>{{ $company['number'] ?? '14892341' }}</strong></p>
                <p>Registered Address: {{ $company['address'] ?? '71-75 Shelton Street, Covent Garden, London, WC2H 9JQ, United Kingdom' }}</p>
                <p>Email: <a href="mailto:{{ $company['email'] ?? 'info@crazydefensehereoes.co.uk' }}">{{ $company['email'] ?? 'info@crazydefensehereoes.co.uk' }}</a> · Website: <a href="https://{{ $company['domain'] ?? 'crazydefensehereoes.co.uk' }}">{{ $company['domain'] ?? 'crazydefensehereoes.co.uk' }}</a></p>
                <p>© {{ date('Y') }} {{ $company['name'] ?? 'Crazy Defense Hereoes Ltd' }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
