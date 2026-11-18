@php
    use Carbon\Carbon;

    /*
     * Pago que llegó después de que venció el apartado, cuando el horario ya no
     * tenía lugar. Mismo formato que el comprobante: tablas y estilos en línea.
     */
    $logo = isset($message)
        ? $message->embed(public_path('images/logo-stranger-themes-correo.png'))
        : asset('images/logo-stranger-themes-correo.png');

    $dinero = fn ($valor) => '$' . number_format((float) $valor, 2, '.', ',');
    $nombre = trim(explode(' ', trim((string) $compra->nombre))[0] ?? '');
    $primero = Carbon::parse($compra->horarios[0]['inicio']);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>Devolvimos tu pago</title>
</head>
<body style="margin:0; padding:0; background-color:#121214;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#121214;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                   style="width:100%; max-width:600px; background-color:#1C1C21; border:1px solid #33333c;
                          font-family:Montserrat, Helvetica, Arial, sans-serif; color:#d6d6d6;">
                <tr>
                    <td align="center" style="padding:32px 24px 24px;">
                        <img src="{{ $logo }}" width="240" alt="Stranger Themes"
                             style="display:block; width:240px; max-width:80%; height:auto; border:0;">
                    </td>
                </tr>
                <tr><td style="height:4px; background-color:#B11724; font-size:0; line-height:0;">&nbsp;</td></tr>
                <tr>
                    <td style="padding:32px;">
                        <p style="margin:0 0 8px; font-size:12px; font-weight:bold; letter-spacing:2px; text-transform:uppercase; color:#d4202f;">
                            Pago devuelto
                        </p>
                        <h1 style="margin:0 0 16px; font-size:22px; color:#ffffff;">
                            {{ $nombre ? "Hola, {$nombre}." : 'Hola.' }}
                        </h1>
                        <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
                            Recibimos tu pago de <strong style="color:#ffffff;">{{ $dinero($compra->total) }}</strong>
                            para <strong style="color:#ffffff;">{{ optional($compra->producto)->descripcion }}</strong>
                            ({{ $primero->format('d/m/Y g:i a') }}, {{ $compra->personas }} {{ $compra->personas === 1 ? 'persona' : 'personas' }}),
                            pero llegó después de que venció el tiempo de apartado y para entonces el horario ya no tenía lugar.
                        </p>
                        <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
                            <strong style="color:#ffffff;">Te devolvimos el importe completo</strong> a la misma tarjeta.
                            Según tu banco, puede tardar de 5 a 10 días hábiles en reflejarse.
                        </p>
                        <p style="margin:0; font-size:15px; line-height:1.6;">
                            Puedes volver a elegir otro horario en nuestra tienda en línea. Lamentamos el inconveniente.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
