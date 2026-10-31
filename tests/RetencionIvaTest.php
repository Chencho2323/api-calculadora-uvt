<?php

declare(strict_types=1);

namespace InheritDevs\Uvt\Tests;

use InheritDevs\Uvt\RetencionIva;
use InheritDevs\Uvt\TablaUvt;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Comprueba la retencion de IVA: tarifas por concepto, base minima de 27 UVT,
 * el redondeo y el desglose.
 */
final class RetencionIvaTest extends TestCase
{
    /**
     * Casos con base minima aplicada, en pesos de 2026.
     *
     * @return array<string, array{int, string, int}>
     */
    public static function casos2026(): array
    {
        return [
            // 20.000.000 al 19% son 3.800.000 de IVA; el 15% son 570.000.
            'servicios generales' => [20000000, 'servicios', 570000],
            // El mismo IVA al 10% son 380.000.
            'servicios entre responsables' => [20000000, 'servicios_entre_responsables', 380000],
            'concepto sin retencion' => [20000000, 'sin_retencion', 0],
            // 2.000.000 al 19% son 380.000 de IVA; el 15% son 57.000.
            'operacion mediana' => [2000000, 'servicios', 57000],
        ];
    }

    #[DataProvider('casos2026')]
    public function testCalculaLaRetencionEnPesos(int $valor, string $concepto, int $esperado): void
    {
        self::assertSame($esperado, RetencionIva::enPesos($valor, $concepto, 2026));
    }

    public function testLaBaseMinimaEsDe27Uvt(): void
    {
        self::assertSame(27, RetencionIva::BASE_MINIMA_UVT);
        self::assertSame(
            27 * TablaUvt::valor(2026),
            RetencionIva::desglose(5000000, 'servicios', 2026)['base_minima_pesos']
        );
    }

    public function testNoRetienePorDebajoDeLaBaseMinima(): void
    {
        $baseMinima = 27 * TablaUvt::valor(2026);

        self::assertSame(0, RetencionIva::enPesos($baseMinima - 1, 'servicios', 2026));
    }

    public function testRetieneDesdeLaBaseMinimaExacta(): void
    {
        $baseMinima = 27 * TablaUvt::valor(2026);

        self::assertGreaterThan(0, RetencionIva::enPesos($baseMinima, 'servicios', 2026));
    }

    public function testLaBaseMinimaSePuedeSaltarPorDisposicionEspecial(): void
    {
        // 1.000.000 esta por debajo de las 27 UVT de 2026, pero la operacion
        // esta sujeta a retencion: 190.000 de IVA al 15% son 28.500.
        self::assertSame(0, RetencionIva::enPesos(1000000, 'servicios', 2026));
        self::assertSame(28500, RetencionIva::enPesos(1000000, 'servicios', 2026, true));
    }

    public function testLaBaseMinimaDependeDelAno(): void
    {
        $de2020 = RetencionIva::desglose(1500000, 'servicios', 2020)['base_minima_pesos'];
        $de2026 = RetencionIva::desglose(1500000, 'servicios', 2026)['base_minima_pesos'];

        self::assertSame(27 * 35607, $de2020);
        self::assertSame(27 * 52374, $de2026);
        self::assertLessThan($de2026, $de2020);
    }

    public function testUnValorQueAlcanzabaLaBaseEn2020YaNoLaAlcanzaEn2026(): void
    {
        // 1.200.000 superaba las 27 UVT de 2020 (961.389) y no las de 2026.
        self::assertGreaterThan(0, RetencionIva::enPesos(1200000, 'servicios', 2020));
        self::assertSame(0, RetencionIva::enPesos(1200000, 'servicios', 2026));
    }

    public function testDevuelveLaTarifaPorConcepto(): void
    {
        self::assertSame(0.15, RetencionIva::tarifa('servicios'));
        self::assertSame(0.10, RetencionIva::tarifa('servicios_entre_responsables'));
        self::assertSame(0.0, RetencionIva::tarifa('sin_retencion'));
    }

    public function testElDesgloseTraeTodosLosDatos(): void
    {
        $desglose = RetencionIva::desglose(20000000, 'servicios', 2026);

        self::assertSame(2026, $desglose['anio']);
        self::assertSame(52374, $desglose['valor_uvt']);
        self::assertSame(20000000, $desglose['valor_operacion']);
        self::assertSame(0.19, $desglose['tarifa_iva']);
        self::assertSame(3800000, $desglose['iva_generado']);
        self::assertSame('servicios', $desglose['concepto']);
        self::assertSame(0.15, $desglose['tarifa_retencion']);
        self::assertTrue($desglose['aplica_base_minima']);
        self::assertSame(570000, $desglose['retencion_pesos']);
        self::assertStringContainsString('437-1', $desglose['fuente']);
    }

    public function testLaRetencionNuncaSuperaElIvaGenerado(): void
    {
        foreach (['servicios', 'servicios_entre_responsables', 'sin_retencion'] as $concepto) {
            $desglose = RetencionIva::desglose(50000000, $concepto, 2026);
            self::assertLessThanOrEqual($desglose['iva_generado'], $desglose['retencion_pesos']);
        }
    }

    public function testRedondeaEnPesosAlPesoMasCercano(): void
    {
        // 1.500.000 al 19% son 285.000 exactos; el 15% son 42.750.
        self::assertSame(42750, RetencionIva::enPesos(1500000, 'servicios', 2026, true));
    }

    public function testRechazaUnConceptoDesconocido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Concepto de retencion de IVA desconocido: arriendos');

        RetencionIva::tarifa('arriendos');
    }

    public function testRechazaUnValorNegativo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El valor de la operacion no puede ser negativo.');

        RetencionIva::enPesos(-1000, 'servicios', 2026);
    }

    public function testRechazaUnAnoFueraDeLaTabla(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La tabla cubre de 2015 a 2026');

        RetencionIva::enPesos(20000000, 'servicios', 2031);
    }
}
