{{-- Offline fallback. Precached by the service worker, so it must render with the
     network completely down: no @vite, no layout, no remote fonts, no Livewire.
     Colours are literal because app.css is not available here. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#015083">
    <title>{{ $__siteName ?? 'Cameroon Network' }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: linear-gradient(135deg, #015083 0%, #01395e 60%, #012a47 100%);
            color: #ffffff;
            font-family: 'Segoe UI', system-ui, -apple-system, 'Helvetica Neue', Arial, sans-serif;
            text-align: center;
            -webkit-font-smoothing: antialiased;
        }
        .card { max-width: 380px; width: 100%; }
        .glyph {
            width: 76px; height: 76px; margin: 0 auto 22px;
            display: grid; place-items: center;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.12);
        }
        h1 { font-size: 1.4rem; font-weight: 800; margin: 0 0 10px; letter-spacing: -0.01em; }
        p  { font-size: 0.95rem; line-height: 1.55; margin: 0 0 6px; color: rgba(255, 255, 255, 0.82); }
        p.fr { font-size: 0.85rem; color: rgba(255, 255, 255, 0.6); margin-bottom: 26px; }
        button {
            appearance: none; border: 0; cursor: pointer;
            background: #ffffff; color: #01395e;
            font-family: inherit; font-size: 0.95rem; font-weight: 700;
            padding: 12px 30px; border-radius: 9999px;
            transition: transform 0.15s ease, background 0.15s ease;
        }
        button:hover  { background: #eef4f8; }
        button:active { transform: scale(0.97); }
    </style>
</head>
<body>
    <div class="card">
        <div class="glyph">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round">
                <path d="M1 1l22 22"/>
                <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
                <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
                <path d="M10.71 5.05A16 16 0 0 1 22.58 9"/>
                <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
                <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                <path d="M12 20h.01"/>
            </svg>
        </div>

        <h1>You're offline</h1>
        <p>Check your connection and try again. Pages you've already opened may still work.</p>
        <p class="fr">Vous êtes hors ligne. Vérifiez votre connexion et réessayez.</p>

        <button type="button" onclick="location.reload()">
            Try again · Réessayer
        </button>
    </div>

    <script>
        // Come back automatically the moment the connection returns.
        window.addEventListener('online', function () { location.reload(); });
    </script>
</body>
</html>
