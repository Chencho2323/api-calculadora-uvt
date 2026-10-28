<?php

declare(strict_types=1);

namespace InheritDevs\Uvt\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Ejecuta el punto de entrada en un proceso aparte y revisa la respuesta JSON
 * del chequeo de salud.
 */
final class PuntoDeEntradaTest extends TestCase
{
    /**
     * @return array{0: int, 1: string}
     */
    private function ejecutarEntrada(): array
    {
        $entrada = dirname(__DIR__) . '/public/index.php';
        $comando = sprintf('%s %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($entrada));

        $salida = [];
        $codigo = 0;
        exec($comando, $salida, $codigo);

        return [$codigo, implode("\n", $salida)];
    }

    public function testDevuelveUnJsonConElEstadoDelServicio(): void
    {
        [$codigo, $texto] = $this->ejecutarEntrada();

        self::assertSame(0, $codigo, 'El punto de entrada termino con error: ' . $texto);

        /** @var array<string, mixed> $datos */
        $datos = json_decode($texto, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('api-calculadora-uvt', $datos['servicio']);
        self::assertSame('disponible', $datos['estado']);
        self::assertSame([], $datos['extensiones_faltantes']);
    }
}
