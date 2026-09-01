<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Stripe (tienda del sitio web)
    |--------------------------------------------------------------------------
    |
    | El cobro se hace con Stripe Checkout, la página alojada por Stripe: el cliente
    | escribe su tarjeta en el dominio de Stripe y este servidor nunca ve ni guarda
    | datos de tarjeta.
    |
    | 'webhook_secret' valida que los avisos de pago vengan de Stripe y no de
    | cualquiera que conozca la URL.
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'moneda' => env('STRIPE_MONEDA', 'mxn'),
    ],

];
