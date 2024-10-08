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
        .header, .footer {
            text-align: center;
        }
        .header img {
            max-width: 150px;
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
    <h2>{{ $pagoCarta->sucursal->razon_social }}</h2>
    <p>Dirección: [Dirección de la empresa]</p>
    <p>Teléfono: [Teléfono de la empresa]</p>
    <p>Email: [Correo electrónico de la empresa]</p>
    <hr>
</div>

<div class="content">
    {{ $contenido }}
</div>

<div class="signature">
    <div>Firma del Empleado</div>
    <div>Firma del Representante</div>
</div>

<div class="footer">
    <hr>
    <p>{{ config('app.name') }} &copy; {{ date('Y') }}. Todos los derechos reservados.</p>
</div>
</body>
</html>
