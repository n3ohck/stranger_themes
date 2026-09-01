<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprar boletos · Stranger Themes</title>
    <meta name="description" content="Compra tus boletos para los recorridos de terror de Stranger Themes: Mansión Winchester, Manicomio Abandonado y Escape Room.">

    <meta name="tienda-api" content="{{ url('/tienda-api') }}">
    <meta name="sitio-web" content="https://strangerthemes.com">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Jim+Nightshade&display=swap" rel="stylesheet">

    <link href="{{ mix('/css/tienda.css') }}" rel="stylesheet">
</head>
<body>
    <div id="tienda"></div>
    <script src="{{ mix('/js/tienda.js') }}" defer></script>
</body>
</html>
