<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Cabeceras de seguridad de la tienda en línea (/comprar y /tienda-api).
 *
 * - nosniff y Referrer-Policy en todo.
 * - En la página: no se puede meter en un iframe ajeno (clickjacking sobre el
 *   botón de pagar) y una CSP que solo deja cargar scripts propios. Los estilos
 *   permiten 'unsafe-inline' porque Vue y el campo trampa usan style; los
 *   scripts no.
 * - HSTS solo en producción y por https, para no amarrar el dominio local.
 *
 * La página manda al cliente a Stripe con window.location, que la CSP no limita;
 * form-action incluye Stripe por si algún día el pago se hace con un formulario.
 */
class CabecerasSeguridadTienda
{
    public function handle(Request $request, Closure $next)
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (str_contains((string) $respuesta->headers->get('Content-Type'), 'text/html')) {
            $respuesta->headers->set('X-Frame-Options', 'DENY');
            $respuesta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
            $respuesta->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com",
                "img-src 'self' data:",
                "connect-src 'self'",
                "frame-ancestors 'none'",
                "form-action 'self' https://checkout.stripe.com",
                "base-uri 'self'",
                "object-src 'none'",
            ]));
        }

        if ($request->secure() && app()->environment('production')) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $respuesta;
    }
}
