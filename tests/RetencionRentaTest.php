<?php

declare(strict_types=1);

namespace InheritDevs\Uvt\Tests;

use InheritDevs\Uvt\RetencionRenta;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Comprueba la retencion de renta por rangos de UVT: cada tramo, los bordes
 * exactos, el excedente sobre la base del tramo y el desglose en pesos.
 */
final class RetencionRentaTest extends TestCase
{
    /**
     * Base en UVT y retencion esperada, calculada a mano con la tabla del
     * articulo 383: impuesto acumulado mas el excedente por la tarifa marginal.
     *
     * @return array<string, array{float, float}>
     */
    public static function casosEnUvt(): array
    {
        return [
            // Primer tramo: hasta 95 UVT no hay retencion.
            'cero UVT' => [0.0, 0.0],
            '95 UVT, el tope del primer tramo' => [95.0, 0.0],
            // Segundo tramo: 19% sobre el excedente de 95 UVT.
            '96 UVT' => [96.0, 0.19],
            '100 UVT' => [100.0, 0.95],
            // Tercer tramo: 10 UVT acumuladas mas 28% sobre el excedente de 150.
            '150 UVT, el tope del segundo tramo' => [150.0, 10.45],
            '200 UVT' => [200.0, 24.0],
            // Cuarto tramo: 69 UVT acumuladas mas 33% sobre el excedente de 360.
            '360 UVT, el tope del tercer tramo' => [360.0, 68.8],
            '500 UVT' => [500.0, 115.2],
            // Quinto tramo: 162 UVT acumuladas mas 35% sobre el excedente de 640.
            '640 UVT, el tope del cuarto tramo' => [640.0, 161.4],
            '800 UVT' => [800.0, 218.0],
            // Sexto tramo: 268 UVT acumuladas mas 37% sobre el excedente de 945.
            '945 UVT, el tope del quinto tramo' => [945.0, 268.75],
            '1500 UVT' => [1500.0, 473.35],
            // Ultimo tramo: 770 UVT acumuladas mas 39% sobre el excedente de 2300.
            '2300 UVT, el tope del sexto tramo' => [2300.0, 769.35],
            '5000 UVT' => [5000.0, 1823.0],
        ];
    }

    #[DataProvider('casosEnUvt')]
    public function testCalculaLaRetencionEnUvtPorTramo(float $base, float $esperado): void
    {
        self::assertEqualsWithDelta($esperado, RetencionRenta::enUvt($base), 0.0001);
    }

    public function testElPrimerTramoNoRetiene(): void
    {
        self::assertSame(0.0, RetencionRenta::enUvt(80));
    }

    public function testDevuelveElTramoQueCorrespondeALaBase(): void
    {
        $tramo = RetencionRenta::tramo(200);

        self::assertSame(150.0, $tramo['desde']);
        self::assertSame(360.0, $tramo['hasta']);
        self::assertSame(0.28, $tramo['tarifa']);
        self::assertSame(10.0, $tramo['impuesto']);
    }

    public function testElUltimoTramoNoTieneTope(): void
    {
        $tramo = RetencionRenta::tramo(9000);

        self::assertNull($tramo['hasta']);
        self::assertSame(0.39, $tramo['tarifa']);
    }

    public function testLosLimitesSonCerradosPorArriba(): void
    {
        // Justo en el borde de 150 UVT manda el tramo que termina ahi.
        self::assertSame(0.19, RetencionRenta::tramo(150)['tarifa']);
        // Un centesimo mas ya cae en el tramo siguiente.
        self::assertSame(0.28, RetencionRenta::tramo(150.01)['tarifa']);
    }

    public function testLaRetencionCreceDentroDeCadaTramo(): void
    {
        // Dentro de un tramo la retencion no puede bajar. En los bordes hay
        // saltos hacia abajo, porque la tabla publica el impuesto acumulado
        // redondeado: al cerrar 150 UVT son 10,45 y al abrir el siguiente
        // tramo arranca en 10. Esos saltos se prueban aparte.
        $tramos = [
            [0.0, 95.0],
            [95.0, 150.0],
            [150.0, 360.0],
            [360.0, 640.0],
            [640.0, 945.0],
            [945.0, 2300.0],
            [2300.0, 5000.0],
        ];

        foreach ($tramos as [$desde, $hasta]) {
            $anterior = -1.0;

            for ($base = $desde; $base <= $hasta; $base += ($hasta - $desde) / 20) {
                $retencion = RetencionRenta::enUvt($base);
                self::assertGreaterThanOrEqual(
                    $anterior,
                    $retencion,
                    sprintf('Bajo dentro del tramo en %s UVT', $base)
                );
                $anterior = $retencion;
            }
        }
    }

    public function testElBordeDelTramoDe150UvtTieneElSaltoDeLaTabla(): void
    {
        self::assertEqualsWithDelta(10.45, RetencionRenta::enUvt(150), 0.0001);
        // 10 UVT acumuladas mas el excedente de 0,01 UVT al 28%.
        self::assertEqualsWithDelta(10.0028, RetencionRenta::enUvt(150.01), 0.0001);
    }

    public function testLaRetencionCreceDeUnTramoAlSiguiente(): void
    {
        // Al comparar el inicio de cada tramo con el inicio del anterior el
        // acumulado siempre sube: 0, 0, 10, 69, 162, 268 y 770 UVT.
        $inicios = [0.0, 95.0, 150.0, 360.0, 640.0, 945.0, 2300.0];
        $anterior = -1.0;

        foreach ($inicios as $base) {
            $retencion = RetencionRenta::enUvt($base);
            self::assertGreaterThanOrEqual($anterior, $retencion, sprintf('Bajo en %s UVT', $base));
            $anterior = $retencion;
        }
    }

    public function testLaRetencionNuncaExcedeElExcedenteGravado(): void
    {
        foreach ([95, 100, 200, 500, 1000, 3000] as $base) {
            self::assertLessThanOrEqual(
                (float) $base,
                RetencionRenta::enUvt((float) $base),
                sprintf('Retuvo de mas en %s UVT', $base)
            );
        }
    }

    public function testCalculaLaRetencionEnPesosConLaUvtDelAno(): void
    {
        // 200 UVT de 2026 = 10.474.800 pesos; la retencion son 24 UVT exactas.
        self::assertSame(1256976, RetencionRenta::enPesos(10474800, 2026));
    }

    public function testElAnoCambiaLaRetencionEnPesos(): void
    {
        // La misma base nominal en un ano de UVT menor son mas UVT y mas retencion.
        $de2020 = RetencionRenta::enPesos(10474800, 2020);
        $de2026 = RetencionRenta::enPesos(10474800, 2026);

        self::assertGreaterThan($de2026, $de2020);
    }

    public function testElDesgloseTraeTodosLosDatos(): void
    {
        $desglose = RetencionRenta::desglose(10474800, 2026);

        self::assertSame(2026, $desglose['anio']);
        self::assertSame(52374, $desglose['valor_uvt']);
        self::assertSame(10474800, $desglose['base_pesos']);
        self::assertSame(200.0, $desglose['base_uvt']);
        self::assertSame(['desde' => 150.0, 'hasta' => 360.0], $desglose['tramo']);
        self::assertSame(0.28, $desglose['tarifa']);
        self::assertSame(10.0, $desglose['impuesto_tramo_uvt']);
        self::assertSame(24.0, $desglose['retencion_uvt']);
        self::assertSame(1256976, $desglose['retencion_pesos']);
        self::assertStringContainsString('articulo 383', $desglose['fuente']);
    }

    public function testUnPagoPequeñoNoRetiene(): void
    {
        // 2.000.000 de pesos son 38,19 UVT en 2026: primer tramo.
        $desglose = RetencionRenta::desglose(2000000, 2026);

        self::assertSame(0.0, $desglose['retencion_uvt']);
        self::assertSame(0, $desglose['retencion_pesos']);
    }

    public function testLaTablaTieneSieteTramos(): void
    {
        self::assertCount(7, RetencionRenta::rangos());
    }

    public function testRechazaUnaBaseNegativa(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La base en UVT no puede ser negativa.');

        RetencionRenta::enUvt(-10);
    }

    public function testRechazaUnaBaseEnPesosNegativa(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La base en pesos no puede ser negativa.');

        RetencionRenta::enPesos(-500000, 2026);
    }

    public function testRechazaUnAnoFueraDeLaTabla(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La tabla cubre de 2015 a 2026');

        RetencionRenta::enPesos(10474800, 2031);
    }
}
