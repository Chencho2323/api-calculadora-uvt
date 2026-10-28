<?php

declare(strict_types=1);

/**
 * Punto de entrada de la API.
 *
 * Por ahora solo responde al chequeo de salud del servicio. Los endpoints de
 * calculo se montan sobre este mismo arranque en las tareas siguientes.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use InheritDevs\Uvt\Proyecto;

header('Content-Type: application/json; charset=utf-8');

$listo = Proyecto::entornoListo();

http_response_code($listo ? 200 : 500);

echo json_encode(
    [
        'servicio' => Proyecto::NOMBRE,
        'version' => Proyecto::VERSION,
        'estado' => $listo ? 'disponible' : 'entorno_incompleto',
        'extensiones_faltantes' => Proyecto::extensionesFaltantes(),
    ],
    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
);
