<?php

declare(strict_types=1);

namespace InheritDevs\Uvt\Tests;

use InheritDevs\Uvt\Proyecto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Proyecto::class)]
final class ProyectoTest extends TestCase
{
    public function testExponeElNombreDelPaquete(): void
    {
        self::assertSame('api-calculadora-uvt', Proyecto::NOMBRE);
    }

    public function testExponeElEspacioDeNombresRaiz(): void
    {
        self::assertSame('InheritDevs\\Uvt', Proyecto::ESPACIO);
    }

    public function testLaVersionSigueElFormatoSemantico(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Proyecto::VERSION);
    }

    public function testElEntornoActualCumpleLosRequisitos(): void
    {
        self::assertSame([], Proyecto::extensionesFaltantes());
        self::assertTrue(Proyecto::entornoListo());
        self::assertTrue(version_compare(PHP_VERSION, Proyecto::PHP_MINIMO, '>='));
    }

    public function testLaDescripcionDelEntornoMencionaNombreYVersion(): void
    {
        $descripcion = Proyecto::descripcionEntorno();

        self::assertStringContainsString(Proyecto::NOMBRE, $descripcion);
        self::assertStringContainsString(Proyecto::VERSION, $descripcion);
        self::assertStringContainsString(PHP_VERSION, $descripcion);
    }
}
