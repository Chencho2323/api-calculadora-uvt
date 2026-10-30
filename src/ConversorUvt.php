<?php

declare(strict_types=1);

namespace InheritDevs\Uvt;

use InvalidArgumentException;

/**
 * Convierte montos entre pesos y UVT con el valor del ano que se indique.
 *
 * La conversion va en las dos direcciones y siempre usa el valor exacto que la
 * tabla tiene para el ano pedido, nunca un valor promedio ni el del ano en
 * curso por defecto: quien llama decide el ano, porque una liquidacion de 2024
 * se hace con la UVT de 2024.
 *
 * En pesos el resultado se redondea al peso, que es la unidad en que se paga.
 * En UVT se conservan dos decimales, que es la precision con la que la DIAN
 * publica las bases gravables y las sanciones.
 */
final class ConversorUvt
{
    /**
     * Decimales que se conservan al expresar un monto en UVT.
     */
    public const DECIMALES_UVT = 2;

    /**
     * Convierte un monto en pesos a UVT.
     *
     * @param int|float $pesos Monto en pesos. No admite negativos.
     * @param int       $anio  Ano cuya UVT se aplica.
     *
     * @throws InvalidArgumentException Si el monto no sirve o el ano no esta en la tabla.
     */
    public static function aUvt(int|float $pesos, int $anio): float
    {
        self::validarMonto($pesos, 'pesos');

        $valorUvt = TablaUvt::valor($anio);

        return round($pesos / $valorUvt, self::DECIMALES_UVT);
    }

    /**
     * Convierte un monto en UVT a pesos.
     *
     * @param int|float $uvt  Monto en UVT. No admite negativos.
     * @param int       $anio Ano cuya UVT se aplica.
     *
     * @throws InvalidArgumentException Si el monto no sirve o el ano no esta en la tabla.
     */
    public static function aPesos(int|float $uvt, int $anio): int
    {
        self::validarMonto($uvt, 'UVT');

        $valorUvt = TablaUvt::valor($anio);

        return (int) round($uvt * $valorUvt, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Expresa un monto en las dos unidades, con el valor de UVT que se uso.
     *
     * Es la forma comoda de devolver la conversion cuando el consumidor de la
     * API necesita mostrar el calculo, no solo el resultado.
     *
     * @return array{anio: int, valor_uvt: int, pesos: int, uvt: float}
     *
     * @throws InvalidArgumentException Si el monto no sirve o el ano no esta en la tabla.
     */
    public static function desglose(int|float $pesos, int $anio): array
    {
        self::validarMonto($pesos, 'pesos');

        $valorUvt = TablaUvt::valor($anio);
        $uvt = round($pesos / $valorUvt, self::DECIMALES_UVT);

        return [
            'anio' => $anio,
            'valor_uvt' => $valorUvt,
            'pesos' => (int) round($pesos, 0, PHP_ROUND_HALF_UP),
            'uvt' => $uvt,
        ];
    }

    /**
     * Rechaza montos negativos y valores que no son numeros reales.
     */
    private static function validarMonto(int|float $monto, string $unidad): void
    {
        if (!is_finite((float) $monto)) {
            throw new InvalidArgumentException(sprintf(
                'El monto en %s debe ser un numero finito.',
                $unidad
            ));
        }

        if ($monto < 0) {
            throw new InvalidArgumentException(sprintf(
                'El monto en %s no puede ser negativo.',
                $unidad
            ));
        }
    }
}
