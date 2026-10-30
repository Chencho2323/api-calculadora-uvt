<?php

declare(strict_types=1);

namespace InheritDevs\Uvt\Tests;

use InheritDevs\Uvt\ConversorUvt;
use InheritDevs\Uvt\TablaUvt;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Comprueba la conversion entre pesos y UVT: ida, vuelta, redondeo, limites y
 * el desglose que devuelve la API.
 */
final class ConversorUvtTest extends TestCase
{
    /**
     * Casos con la UVT de 2026 (52.374 pesos).
     *
     * @return array<string, array{int, int, float}>
     */
    public static function casos2026(): array
    {
        return [
            'una UVT exacta' => [52374, 2026, 1.0],
            'media UVT' => [26187, 2026, 0.5],
            'cero pesos' => [0, 2026, 0.0],
            'una UVT y media' => [78561, 2026, 1.5],
            // 1.000.000 / 52.374 = 19,0929... -> 19,09
            'un millon' => [1000000, 2026, 19.09],
        ];
    }

    #[DataProvider('casos2026')]
    public function testConviertePesosAUvtConLaUvtDelAno(int $pesos, int $anio, float $esperado): void
    {
        self::assertSame($esperado, ConversorUvt::aUvt($pesos, $anio));
    }

    /**
     * @return array<string, array{int|float, int, int}>
     */
    public static function casosAUvt(): array
    {
        return [
            'una UVT' => [1, 2026, 52374],
            'dos UVT' => [2, 2026, 104748],
            'media UVT' => [0.5, 2026, 26187],
            'cero' => [0, 2026, 0],
            'UVT de 2025' => [1, 2025, 49799],
            'UVT de 2015' => [1, 2015, 28279],
        ];
    }

    #[DataProvider('casosAUvt')]
    public function testConvierteUvtAPesos(int|float $uvt, int $anio, int $esperado): void
    {
        self::assertSame($esperado, ConversorUvt::aPesos($uvt, $anio));
    }

    public function testElAnoCambiaElResultado(): void
    {
        // El mismo millon vale mas UVT en 2015, cuando la UVT era menor.
        self::assertSame(35.36, ConversorUvt::aUvt(1000000, 2015));
        self::assertSame(19.09, ConversorUvt::aUvt(1000000, 2026));
    }

    public function testRedondeaEnPesosAlPesoMasCercano(): void
    {
        // 0,3 UVT de 2026 = 15.712,2 -> 15.712.
        self::assertSame(15712, ConversorUvt::aPesos(0.3, 2026));
    }

    public function testLaIdaYLaVueltaSeAproximanAlMontoOriginal(): void
    {
        foreach ([100000, 1500000, 47065000] as $pesos) {
            $uvt = ConversorUvt::aUvt($pesos, 2026);
            $vuelta = ConversorUvt::aPesos($uvt, 2026);

            // La vuelta se queda dentro del medio punto de UVT del original.
            self::assertEqualsWithDelta(
                $pesos,
                $vuelta,
                TablaUvt::valor(2026) / 200,
                sprintf('La vuelta de %d pesos no cerro', $pesos)
            );
        }
    }

    public function testElDesgloseTraeTodosLosDatos(): void
    {
        $desglose = ConversorUvt::desglose(1000000, 2026);

        self::assertSame(2026, $desglose['anio']);
        self::assertSame(52374, $desglose['valor_uvt']);
        self::assertSame(1000000, $desglose['pesos']);
        self::assertSame(19.09, $desglose['uvt']);
    }

    public function testRechazaUnMontoNegativoEnPesos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El monto en pesos no puede ser negativo.');

        ConversorUvt::aUvt(-1000, 2026);
    }

    public function testRechazaUnMontoNegativoEnUvt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El monto en UVT no puede ser negativo.');

        ConversorUvt::aPesos(-1.5, 2026);
    }

    public function testRechazaUnAnoFueraDeLaTabla(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La tabla cubre de 2015 a 2026');

        ConversorUvt::aUvt(1000000, 2031);
    }
}
