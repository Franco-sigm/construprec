<?php

/*
 * Qué orígenes pueden llamar a la API desde un navegador.
 *
 * No va en '*' aunque sea lo más cómodo: con el comodín, cualquier sitio que el
 * usuario tenga abierto puede pedirle datos a la API en su nombre. La lista se
 * arma desde el entorno, así que en desarrollo apunta al servidor de Vite y en
 * el hosting al dominio real, sin tocar código.
 */
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // El cálculo no usa cookies ni sesión: es una consulta pura. Dejarlo en false
    // evita que el navegador mande credenciales a un origen que no las necesita.
    'supports_credentials' => false,
];
