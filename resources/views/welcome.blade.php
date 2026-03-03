<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="overflow: hidden; height: 100dvh">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>{{ config('app.name', 'Trusted Advisors') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Crimson+Text:ital,wght@0,400;0,600;1,400&display=swap" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Crimson+Text:ital,wght@0,400;0,600;1,400&display=swap"></noscript>
    @vite(['resources/js/app.js'])
</head>
<body style="overflow: hidden; height: 100dvh">
    <div id="app" style="opacity: 0; transition: opacity 0.15s ease-in"></div>
    <script>
        // Reveal app once fonts are ready (or after timeout)
        function revealApp() {
            var el = document.getElementById('app');
            if (el) el.style.opacity = '1';
        }
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(revealApp);
        }
        setTimeout(revealApp, 800);
    </script>
</body>
</html>
