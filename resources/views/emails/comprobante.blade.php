<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Compra</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #1e1e1e; padding: 20px; color: #000;">
<div style="max-width: 600px; margin: auto; background-color: #dcdcdc; padding: 30px; border-radius: 8px;">

    <div style="text-align: center; margin-bottom: 20px;">
        <img src="https://strangerthemes.com/wp-content/uploads/2024/07/lOGO-BLANCO-ST-sombra-grande-2048x802.png" alt="Stranger Themes" style="max-width: 200px;">
    </div>

    <p style="font-size: 16px;">Hola, <strong>{{ $sale->nombre }}</strong>.</p>
    <p style="font-size: 16px;">Este es el comprobante digital de tu compra <strong>{{$sale->folio}}</strong> en linea.</p>

    <table width="100%" cellpadding="10" cellspacing="0" style="margin-top: 20px; background-color: #efefef;">
        <tr style="background-color: #ccc;">
            <td colspan="2" style="font-weight: bold;">Recorrido</td>
        </tr>
        @foreach($sale->reservaciones as $reservacion)
            <tr>
                <td colspan="2" style="font-size: 14px; font-weight: bold;">
                    {{$reservacion->producto->descripcion}} para {{$reservacion->cantidad_personas}} personas para el {{ \Carbon\Carbon::parse($reservacion->fecha)->translatedFormat('d \D\E F \D\E\L Y \a \l\a\s H:i') }} HRS
                </td>
            </tr>
        @endforeach
    </table>
    <table style="margin-top: 2px; background-color: #efefef; padding: 10px;float: inline-end;">
        <tbody>
        <tr>
            <td colspan="2" style="text-align: right; font-size: 18px; font-weight: bold;">
                ${{number_format($sale->total, 2, '.', ',')}}
            </td>
        </tr>
        </tbody>
    </table>
    <br>
    <br>
    <p style="font-size: 14px; margin-top: 30px; text-align: center;">
        <a href="{{$sale->sucursal->ubicacion}}" target="_blank">{{$sale->sucursal->direccion}}</a>
    </p>
    <p style="font-size: 14px; margin-top: 30px; text-align: center;">
        <a href="http://www.strangerthemes.com" target="_blank" style="color: #000;">www.strangerthemes.com</a>
    </p>
</div>
</body>
</html>
