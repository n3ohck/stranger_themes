@php
    use App\Support\Fechas;
    use Carbon\Carbon;

    /*
     * Correo de compra confirmada del sitio web.
     *
     * Todo va en tablas con estilos en línea: es lo único que respetan Gmail,
     * Outlook y Apple Mail por igual. Colores y tono del sitio (#1C1C21, #B11724).
     *
     * El logo se incrusta en el correo (cid) en vez de enlazarse: un enlace
     * depende de que el servidor sea público, y el anterior salía roto. Fuera de
     * un envío real (vista previa) no hay $message y se usa la URL pública.
     */
    $logo = isset($message)
        ? $message->embed(public_path('images/logo-stranger-themes-correo.png'))
        : asset('images/logo-stranger-themes-correo.png');

    $dinero = fn ($valor) => '$' . number_format((float) $valor, 2, '.', ',');
    $nombre = trim(explode(' ', trim((string) $sale->nombre))[0] ?? '');

    // Lo que se cobró de verdad. El total de la venta ya trae el descuento: antes
    // se le volvía a restar y el correo mostraba menos de lo pagado.
    $pagado = $sale->pagos->sum('monto') ?: $sale->total;
    $descuento = (float) $sale->descuento;

    $sucursal = $sale->sucursal;
    $mapa = $sucursal->ubicacion
        ?: 'https://www.google.com/maps/search/?api=1&query=' . urlencode((string) $sucursal->direccion);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>Tu compra en Stranger Themes</title>
</head>
<body style="margin:0; padding:0; background-color:#121214;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#121214;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                   style="width:100%; max-width:600px; background-color:#1C1C21; border:1px solid #33333c;
                          font-family:Montserrat, Helvetica, Arial, sans-serif; color:#d6d6d6;">

                {{-- Logo --}}
                <tr>
                    <td align="center" style="padding:32px 24px 24px;">
                        <img src="{{ $logo }}" width="240" alt="Stranger Themes"
                             style="display:block; width:240px; max-width:80%; height:auto; border:0;">
                    </td>
                </tr>
                <tr><td style="height:4px; background-color:#B11724; font-size:0; line-height:0;">&nbsp;</td></tr>

                {{-- Saludo --}}
                <tr>
                    <td style="padding:32px 32px 8px;">
                        <p style="margin:0 0 8px; font-size:12px; font-weight:bold; letter-spacing:2px; text-transform:uppercase; color:#d4202f;">
                            Compra confirmada
                        </p>
                        <h1 style="margin:0 0 12px; font-size:26px; line-height:1.25; color:#ffffff;">
                            ¡Nos vemos pronto{{ $nombre ? ', ' . $nombre : '' }}!
                        </h1>
                        <p style="margin:0; font-size:15px; line-height:1.6;">
                            Tu pago quedó registrado. Muestra este correo o tu folio al llegar y prepárate para pasar miedo.
                        </p>
                    </td>
                </tr>

                {{-- Folio --}}
                <tr>
                    <td style="padding:24px 32px 8px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="border:2px dashed #B11724;">
                            <tr>
                                <td align="center" style="padding:16px;">
                                    <p style="margin:0; font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#858585;">Folio</p>
                                    <p style="margin:4px 0 0; font-size:28px; font-weight:bold; letter-spacing:2px; color:#ffffff;">{{ $sale->folio }}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Recorridos --}}
                <tr>
                    <td style="padding:16px 32px 0;">
                        <p style="margin:0 0 12px; font-size:12px; font-weight:bold; letter-spacing:2px; text-transform:uppercase; color:#858585;">
                            {{ $sale->reservaciones->count() > 1 ? 'Tus recorridos' : 'Tu recorrido' }}
                        </p>

                        @foreach ($sale->reservaciones->sortBy('fecha') as $reservacion)
                            @php $fecha = Carbon::parse($reservacion->fecha)->locale('es'); @endphp
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                   style="margin-bottom:12px; background-color:#232329; border-left:4px solid #B11724;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 10px; font-size:19px; font-weight:bold; color:#ffffff;">
                                            {{ optional($reservacion->producto)->descripcion }}
                                        </p>
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                                            <tr>
                                                <td style="padding:3px 0; color:#858585; width:90px;">Fecha</td>
                                                <td style="padding:3px 0; color:#ffffff; font-weight:bold;">{{ ucfirst($fecha->translatedFormat('l j \d\e F \d\e Y')) }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:3px 0; color:#858585;">Hora</td>
                                                <td style="padding:3px 0; color:#ffffff; font-weight:bold;">{{ Fechas::hora($reservacion->fecha) }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:3px 0; color:#858585;">Personas</td>
                                                <td style="padding:3px 0; color:#ffffff; font-weight:bold;">{{ $reservacion->cantidad_personas }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        @endforeach
                    </td>
                </tr>

                {{-- Pago --}}
                <tr>
                    <td style="padding:8px 32px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; border-top:1px solid #33333c;">
                            @if ($descuento > 0)
                                <tr>
                                    <td style="padding:12px 0 4px; color:#858585;">Subtotal</td>
                                    <td align="right" style="padding:12px 0 4px;">{{ $dinero($pagado + $descuento) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0; color:#858585;">
                                        Descuento @if ($sale->codigo_descuento)({{ $sale->codigo_descuento }})@endif
                                    </td>
                                    <td align="right" style="padding:4px 0; color:#4ade80;">−{{ $dinero($descuento) }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding:12px 0; font-size:16px; font-weight:bold; color:#ffffff;">Total pagado</td>
                                <td align="right" style="padding:12px 0; font-size:22px; font-weight:bold; color:#ffffff;">
                                    {{ $dinero($pagado) }} <span style="font-size:12px; font-weight:normal; color:#858585;">MXN</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Aviso --}}
                <tr>
                    <td style="padding:16px 32px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#2a1416; border:1px solid #5c1a20;">
                            <tr>
                                <td style="padding:14px 18px; font-size:13px; line-height:1.6; color:#f3c4c8;">
                                    <strong style="color:#ffffff;">Llega al menos 10 minutos antes.</strong>
                                    Si llegas tarde perderás tu recorrido sin posibilidad de reembolso.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Ubicación --}}
                <tr>
                    <td style="padding:24px 32px 32px;">
                        <p style="margin:0 0 4px; font-size:12px; font-weight:bold; letter-spacing:2px; text-transform:uppercase; color:#858585;">Dónde</p>
                        <p style="margin:0 0 4px; font-size:16px; font-weight:bold; color:#ffffff;">{{ $sucursal->razon_social }}</p>
                        <p style="margin:0 0 16px; font-size:14px; line-height:1.5;">{{ $sucursal->direccion }}</p>
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="background-color:#B11724;">
                                    <a href="{{ $mapa }}" target="_blank"
                                       style="display:inline-block; padding:12px 24px; font-size:13px; font-weight:bold; letter-spacing:1px; text-transform:uppercase; color:#ffffff; text-decoration:none;">
                                        Cómo llegar
                                    </a>
                                </td>
                            </tr>
                        </table>
                        @if ($sucursal->telefono)
                            <p style="margin:16px 0 0; font-size:13px; color:#858585;">
                                ¿Dudas? Llámanos al <a href="tel:{{ preg_replace('/\D/', '', $sucursal->telefono) }}" style="color:#d6d6d6;">{{ $sucursal->telefono }}</a>
                            </p>
                        @endif
                    </td>
                </tr>
            </table>

            {{-- Pie --}}
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px;">
                <tr>
                    <td align="center" style="padding:20px 24px; font-family:Helvetica, Arial, sans-serif; font-size:12px; line-height:1.6; color:#6b6b73;">
                        <a href="https://www.strangerthemes.com" target="_blank" style="color:#9a9aa3; text-decoration:none;">www.strangerthemes.com</a><br>
                        Recibiste este correo porque compraste boletos en nuestro sitio.<br>
                        Stranger Themes® {{ now()->year }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
