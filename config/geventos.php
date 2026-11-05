<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GestiónEventos - configuración general
    |--------------------------------------------------------------------------
    */

    'nombre' => env('APP_NAME', 'GestiónEventos'),

    // Minutos que se reserva el cupo (o la butaca) mientras el comprador paga
    'reserva_minutos' => (int) env('GEVENTOS_RESERVA_MINUTOS', 10),

    // Tope general de entradas por compra (cada evento puede bajarlo)
    'max_entradas_por_orden' => (int) env('GEVENTOS_MAX_POR_ORDEN', 10),

    // Cargo por servicio que paga el comprador (% sobre el subtotal)
    'cargo_servicio_porcentaje' => (float) env('GEVENTOS_CARGO_SERVICIO', 10),

    // Comisión que la plataforma descuenta a la productora (% sobre el subtotal)
    'comision_porcentaje' => (float) env('GEVENTOS_COMISION', 0),

    // Clave para firmar los QR de las entradas (HMAC). NO cambiar en producción.
    'qr_secret' => env('GEVENTOS_QR_SECRET'),

    'mercadopago' => [
        // Aplicación marketplace de la plataforma (OAuth de productoras)
        'client_id'      => env('MP_CLIENT_ID'),
        'client_secret'  => env('MP_CLIENT_SECRET'),
        'access_token'   => env('MP_ACCESS_TOKEN'),
        'public_key'     => env('MP_PUBLIC_KEY'),
        'webhook_secret' => env('MP_WEBHOOK_SECRET'),
        'redirect_uri'   => env('MP_REDIRECT_URI', rtrim((string) env('APP_URL'), '/') . '/productora/mercadopago/callback'),
        'sandbox'        => (bool) env('MP_SANDBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Listas de la interfaz
    |--------------------------------------------------------------------------
    */

    'categorias' => [
        'recital'     => 'Recital / Música en vivo',
        'fiesta'      => 'Fiesta / Boliche',
        'teatro'      => 'Teatro',
        'stand-up'    => 'Stand-up / Humor',
        'festival'    => 'Festival',
        'deporte'     => 'Deporte',
        'conferencia' => 'Conferencia / Charla',
        'curso'       => 'Curso / Taller',
        'infantil'    => 'Infantil',
        'gastronomia' => 'Gastronomía',
        'otro'        => 'Otro',
    ],

    // [etiqueta, color de badge Bootstrap]
    'estados_evento' => [
        'draft'     => ['Borrador', 'secondary'],
        'published' => ['Publicado', 'success'],
        'paused'    => ['Pausado', 'warning'],
        'cancelled' => ['Cancelado', 'danger'],
        'finished'  => ['Finalizado', 'dark'],
    ],

    'estados_productora' => [
        'pending'   => ['Pendiente', 'warning'],
        'active'    => ['Activa', 'success'],
        'suspended' => ['Suspendida', 'danger'],
    ],

    'provincias' => [
        'Buenos Aires', 'Ciudad Autónoma de Buenos Aires', 'Catamarca', 'Chaco', 'Chubut', 'Córdoba',
        'Corrientes', 'Entre Ríos', 'Formosa', 'Jujuy', 'La Pampa', 'La Rioja', 'Mendoza', 'Misiones',
        'Neuquén', 'Río Negro', 'Salta', 'San Juan', 'San Luis', 'Santa Cruz', 'Santa Fe',
        'Santiago del Estero', 'Tierra del Fuego', 'Tucumán',
    ],

];
