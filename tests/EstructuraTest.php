<?php

declare(strict_types=1);

namespace InheritDevs\Uvt\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Comprueba que el esqueleto del proyecto quede completo: manifiesto de
 * Composer, autocarga, configuracion de PHPUnit y carpetas de trabajo.
 */
final class EstructuraTest extends TestCase
{
    private static function raiz(): string
    {
        return dirname(__DIR__);
    }

    public function testExisteElManifiestoDeComposer(): void
    {
        self::assertFileExists(self::raiz() . '/composer.json');
    }

    public function testElManifiestoDeclaraDependenciasYAutocarga(): void
    {
        $crudo = file_get_contents(self::raiz() . '/composer.json');
        self::assertIsString($crudo);

        /** @var array<string, mixed> $manifiesto */
        $manifiesto = json_decode($crudo, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('inheritdevs/api-calculadora-uvt', $manifiesto['name']);
        self::assertSame('MIT', $manifiesto['license']);
        self::assertSame('>=8.3', $manifiesto['require']['php']);
        self::assertSame(
            'src/',
            $manifiesto['autoload']['psr-4']['InheritDevs\\Uvt\\'] ?? null
        );
        self::assertSame(
            'tests/',
            $manifiesto['autoload-dev']['psr-4']['InheritDevs\\Uvt\\Tests\\'] ?? null
        );
        self::assertArrayHasKey('phpunit/phpunit', $manifiesto['require-dev']);
    }

    public function testLaAutocargaResuelveLasClasesDelProyecto(): void
    {
        self::assertTrue(class_exists(\InheritDevs\Uvt\Proyecto::class));
    }

    public function testExisteLaConfiguracionDePhpUnit(): void
    {
        $configuracion = self::raiz() . '/phpunit.xml';

        self::assertFileExists($configuracion);
        self::assertStringContainsString('tests', (string) file_get_contents($configuracion));
    }

    public function testExistenLasCarpetasDeTrabajo(): void
    {
        foreach (['src', 'tests', 'public'] as $carpeta) {
            self::assertDirectoryExists(self::raiz() . '/' . $carpeta, $carpeta);
        }
    }

    public function testElVerificadorEsEjecutable(): void
    {
        $verificador = self::raiz() . '/verificar.sh';

        self::assertFileExists($verificador);
        self::assertTrue(is_executable($verificador));
    }
}
