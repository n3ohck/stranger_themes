<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Punto de Venta · {{ config('app.name') }}</title>

    {{-- El SPA habla con /pos-api por JWT, así que no hay sesión ni CSRF de por medio. --}}
    <meta name="pos-api" content="{{ url('/pos-api') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="{{ mix('/css/pos.css') }}" rel="stylesheet">
</head>
<body class="h-full">
    <div id="pos" class="h-full"></div>
    <script src="{{ mix('/js/pos.js') }}" defer></script>
</body>
</html>
