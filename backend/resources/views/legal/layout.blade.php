<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <style>
        :root { color-scheme: light; font-family: Georgia, serif; color: #4c3a2e; background: #f6f2da; }
        body { margin: 0; padding: 40px 20px; }
        main { max-width: 760px; margin: 0 auto; padding: 48px; border: 2px solid #a56f40; background: #f9f5dc; box-shadow: 0 12px 30px rgba(78, 57, 38, .16); }
        h1 { margin-top: 0; font-size: clamp(2rem, 7vw, 4rem); text-transform: uppercase; }
        h2 { margin-top: 32px; font-size: 1.25rem; }
        p, li { color: #6c715f; line-height: 1.7; }
        .meta { color: #9b8b72; font: 12px/1.5 monospace; text-transform: uppercase; letter-spacing: .08em; }
        a { color: #b84d2e; }
    </style>
</head>
<body>
<main>
    <p class="meta">{{ config('company.name') }} · {{ config('company.email') }}</p>
    @yield('content')
    <p class="meta">Company no. {{ config('company.number') }} · {{ config('company.address') }}</p>
</main>
</body>
</html>
