<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header .company-info {
            float: right;
            text-align: left;
        }
        .header .logo img {
            float: left;
            max-width: 100px; /* Puedes ajustar el tamaño del logo aquí */
        }
        .content {
            margin-top: 20px;
        }
        .content table {
            width: 100%;
            border-collapse: collapse;
        }
        .content table, .content th, .content td {
            border: 1px solid black;
            padding: 10px;
            text-align: left;
        }
        .signature {
            margin-top: 50px;
            text-align: center;
        }
        .signature div {
            display: inline-block;
            width: 40%;
            text-align: center;
            border-top: 1px solid black;
            padding-top: 10px;
        }
    </style>
</head>
<body>
<div class="header">
    <div class="company-info">
        <h2 style="padding-top: 25px;">{{ $pagoCarta->sucursal->razon_social }}</h2>
    </div>
    <div class="logo">
        <img src="{{asset($sucursal->logotipo)}}" alt="Logo de la Empresa">
    </div>
    <br>
    <br>
    <br>
    <br>
</div>

<hr>

<div class="content">
    {!! $contenido !!}
</div>
@if($contenido_adicional)
    <hr>
    <div class="content">
        {!! $contenido_adicional !!}
    </div>
@endif
</body>
</html>
