<?php

declare(strict_types=1);

namespace InheritDevs\Uvt\Tests;

use InheritDevs\Uvt\TablaUvt;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Comprueba la tabla de UVT: valores publicados, normas que los fijaron,
 * consulta de anos fuera de rango y calculo de la variacion anual.
 */
final class TablaUvtTest extends TestCase
{
    /**
     * Valores oficiales que la tabla debe devolver.
     *
     * @return array<string, array{int, int}>
     */
    public static function valoresOficiales(): array
    {
        return [
            '2015' => [2015, 28279],
            '2018' => [2018, 33156],
            '2022' => [2022, 38004],
            '2024' => [2024, 47065],
            '2025' => [2025, 49799],
            '2026' => [2026, 52374],
        ];
    }

    #[DataProvider('valoresOficiales')]
    public function testDevuelveElValorPublicadoDeCadaAno(int $anio, int $esperado): void
    {
        self::assertSame($esperado, TablaUvt::valor($anio));
    }

    public function testLaTablaCubreLosAnosDeclaradosSinHuecos(): void
    {
        $anios = TablaUvt::anios();

        self::assertSame(TablaUvt::ANIO_INICIAL, $anios[0]);
        self::assertSame(TablaUvt::ANIO_FINAL, $anios[count($anios) - 1]);
        self::assertCount(TablaUvt::ANIO_FINAL - TablaUvt::ANIO_INICIAL + 1, $anios);

        foreach ($anios as $anio) {
            self::assertTrue(TablaUvt::existe($anio));
            self::assertGreaterThan(0, TablaUvt::valor($anio));
        }
    }

    public function testCadaAnoTieneNormaYFuente(): void
    {
        foreach (TablaUvt::completa() as $ficha) {
            self::assertNotSame('', $ficha['norma']);
            self::assertSame(TablaUvt::FUENTE, $ficha['fuente']);
            self::assertSame($ficha['anio'], $ficha['anio']);
        }
    }

    public function testElValorDe2026CitaLaResolucionQueLoFijo(): void
    {
        self::assertStringContainsString('000238', TablaUvt::norma(2026));
        self::assertStringContainsString('2025', TablaUvt::norma(2026));
    }

    public function testElAnoVigenteEsElUltimoDeLaTabla(): void
    {
        self::assertSame(TablaUvt::ANIO_FINAL, TablaUvt::anioVigente());
    }

    public function testRechazaUnAnoAnteriorAlRango(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No hay valor publicado de la UVT para el ano 2014');

        TablaUvt::valor(2014);
    }

    public function testRechazaUnAnoPosteriorAlRango(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La tabla cubre de 2015 a 2026');

        TablaUvt::valor(2027);
    }

    public function testElAnoFueraDeRangoNoExiste(): void
    {
        self::assertFalse(TablaUvt::existe(2030));
        self::assertTrue(TablaUvt::existe(2026));
    }

    public function testCalculaLaVariacionFrenteAlAnoAnterior(): void
    {
        // 52.374 frente a 49.799 = 5,17 %.
        self::assertSame(5.17, TablaUvt::variacion(2026));
    }

    public function testElPrimerAnoNoTieneVariacion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TablaUvt::variacion(TablaUvt::ANIO_INICIAL);
    }
}
