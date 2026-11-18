{{--
    Error pages. Inline styles only, no Vite, no Alpine: a 500 or 503 can happen
    when the build or the app itself is what's broken, and the page must still
    show. Text in the request's language.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · {{ $__siteName ?? 'Cameroon Network' }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
               background: #f1f5f9; color: #0f172a; padding: 24px; }
        .card { width: 100%; max-width: 420px; background: #fff; border-radius: 20px; overflow: hidden;
                box-shadow: 0 20px 50px -12px rgba(15, 23, 42, .18); text-align: center; }
        .flag { height: 6px; background: linear-gradient(90deg, #009639 0 33.3%, #CE1126 33.3% 66.6%, #FCD116 66.6%); }
        .body { padding: 36px 28px 32px; }
        .code { font-size: 14px; font-weight: 700; letter-spacing: .12em; color: #64748b; }
        h1 { margin: 10px 0 8px; font-size: 24px; line-height: 1.25; color: #243a5c; }
        p { margin: 0 0 24px; font-size: 15px; line-height: 1.55; color: #475569; }
        a.btn { display: inline-block; padding: 12px 22px; border-radius: 12px; background: #243a5c; color: #fff;
                font-weight: 700; font-size: 15px; text-decoration: none; }
        a.btn:hover { background: #1b2c47; }
    </style>
</head>
<body>
    <main class="card">
        <div class="flag"></div>
        <div class="body">
            <div class="code">@yield('code')</div>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <a class="btn" href="{{ url('/') }}">{{ \App\Support\Bilingual::t('Back to the home page', "Retour à l'accueil") }}</a>
        </div>
    </main>
</body>
</html>
