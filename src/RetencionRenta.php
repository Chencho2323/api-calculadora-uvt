<?php

declare(strict_types=1);

namespace InheritDevs\Uvt;

use InvalidArgumentException;

/**
 * Retencion en la fuente a titulo de renta por rangos de UVT.
 *
 * Aplica la tabla del articulo 383 del Estatuto Tributario, que es la que usa
 * el procedimiento de retencion sobre pagos gravables mensuales: cada tramo
 * tiene una tarifa marginal que solo se aplica al excedente sobre la base del
 * tramo, mas un impuesto acumulado que viene de los tramos anteriores. Por eso
 * la retencion no se puede calcular como un porcentaje plano del pago.
 *
 * La base se expresa en UVT con dos decimales, igual que el conversor, para que
 * el desglose se pueda recalcular a mano con los datos que devuelve la API.
 *
 * Ojo con los bordes: la tabla publicada no es continua. Al cerrar el tramo de
 * 150 UVT la formula da 10,45 UVT, y al abrir el de 360 arranca en 10 UVT,
 * porque el impuesto acumulado se publica redondeado. Esos saltos son de la
 * norma, no del codigo, y aqui se reproducen tal cual: quien liquide con esta
 * clase obtiene lo mismo que con la tabla oficial.
 */
final class RetencionRenta
{
    /**
     * Norma que fija la tabla.
     */
    public const FUENTE = 'Estatuto Tributario, articulo 383 (tabla de retencion por rangos de UVT)';

    /**
     * Decimales con los que se expresa la base en UVT.
     */
    public const DECIMALES_UVT = 2;

    /**
     * Tramos vigentes: desde y hasta en UVT, tarifa marginal, base del tramo y
     * el impuesto acumulado en UVT que se suma al excedente.
     *
     * El ultimo tramo no tiene tope: `hasta` va en null.
     *
     * @var list<array{desde: float, hasta: float|null, tarifa: float, base: float, impuesto: float}>
     */
    private const RANGOS = [
        ['desde' => 0.0, 'hasta' => 95.0, 'tarifa' => 0.00, 'base' => 0.0, 'impuesto' => 0.0],
        ['desde' => 95.0, 'hasta' => 150.0, 'tarifa' => 0.19, 'base' => 95.0, 'impuesto' => 0.0],
        ['desde' => 150.0, 'hasta' => 360.0, 'tarifa' => 0.28, 'base' => 150.0, 'impuesto' => 10.0],
        ['desde' => 360.0, 'hasta' => 640.0, 'tarifa' => 0.33, 'base' => 360.0, 'impuesto' => 69.0],
        ['desde' => 640.0, 'hasta' => 945.0, 'tarifa' => 0.35, 'base' => 640.0, 'impuesto' => 162.0],
        ['desde' => 945.0, 'hasta' => 2300.0, 'tarifa' => 0.37, 'base' => 945.0, 'impuesto' => 268.0],
        ['desde' => 2300.0, 'hasta' => null, 'tarifa' => 0.39, 'base' => 2300.0, 'impuesto' => 770.0],
    ];

    /**
     * Devuelve la tabla de tramos tal como se aplica.
     *
     * @return list<array{desde: float, hasta: float|null, tarifa: float, base: float, impuesto: float}>
     */
    public static function rangos(): array
    {
        return self::RANGOS;
    }

    /**
     * Devuelve el tramo que le corresponde a una base expresada en UVT.
     *
     * Los limites son cerrados por arriba: una base de 95 UVT cae en el primer
     * tramo, cuya tarifa es cero.
     *
     * @return array{desde: float, hasta: float|null, tarifa: float, base: float, impuesto: float}
     *
     * @throws InvalidArgumentException Si la base en UVT es negativa o no es un numero.
     */
    public static function tramo(int|float $baseUvt): array
    {
        $base = self::validarBaseUvt($baseUvt);

        foreach (self::RANGOS as $rango) {
            if ($rango['hasta'] === null || $base <= $rango['hasta']) {
                return $rango;
            }
        }

        // El ultimo tramo no tiene tope, asi que este punto no se alcanza.
        throw new InvalidArgumentException('No hay tramo de retencion para la base indicada.');
    }

    /**
     * Calcula la retencion de renta en UVT, sin redondear.
     *
     * Se deja sin redondear a proposito: el redondeo se hace una sola vez, al
     * pasar a pesos, para no arrastrar el error de dos redondeos.
     *
     * @throws InvalidArgumentException Si la base en UVT es negativa o no es un numero.
     */
    public static function enUvt(int|float $baseUvt): float
    {
        $base = self::validarBaseUvt($baseUvt);
        $rango = self::tramo($base);

        return $rango['impuesto'] + ($base - $rango['base']) * $rango['tarifa'];
    }

    /**
     * Calcula la retencion de renta en pesos para una base en pesos.
     *
     * @param int|float $basePesos Pago gravable del mes, en pesos. No admite negativos.
     * @param int       $anio      Ano cuya UVT se aplica.
     *
     * @throws InvalidArgumentException Si la base no sirve o el ano no esta en la tabla.
     */
    public static function enPesos(int|float $basePesos, int $anio): int
    {
        self::validarBasePesos($basePesos);

        $valorUvt = TablaUvt::valor($anio);
        $baseUvt = round($basePesos / $valorUvt, self::DECIMALES_UVT);

        return (int) round(self::enUvt($baseUvt) * $valorUvt, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Devuelve el calculo completo: tramo, base en UVT, tarifa y retencion.
     *
     * @return array{
     *     anio: int,
     *     valor_uvt: int,
     *     base_pesos: int,
     *     base_uvt: float,
     *     tramo: array{desde: float, hasta: float|null},
     *     tarifa: float,
     *     base_tramo_uvt: float,
     *     impuesto_tramo_uvt: float,
     *     retencion_uvt: float,
     *     retencion_pesos: int,
     *     fuente: string
     * }
     *
     * @throws InvalidArgumentException Si la base no sirve o el ano no esta en la tabla.
     */
    public static function desglose(int|float $basePesos, int $anio): array
    {
        self::validarBasePesos($basePesos);

        $valorUvt = TablaUvt::valor($anio);
        $baseUvt = round($basePesos / $valorUvt, self::DECIMALES_UVT);
        $rango = self::tramo($baseUvt);
        $enUvt = self::enUvt($baseUvt);

        return [
            'anio' => $anio,
            'valor_uvt' => $valorUvt,
            'base_pesos' => (int) round($basePesos, 0, PHP_ROUND_HALF_UP),
            'base_uvt' => $baseUvt,
            'tramo' => ['desde' => $rango['desde'], 'hasta' => $rango['hasta']],
            'tarifa' => $rango['tarifa'],
            'base_tramo_uvt' => $rango['base'],
            'impuesto_tramo_uvt' => $rango['impuesto'],
            'retencion_uvt' => round($enUvt, self::DECIMALES_UVT),
            'retencion_pesos' => (int) round($enUvt * $valorUvt, 0, PHP_ROUND_HALF_UP),
            'fuente' => self::FUENTE,
        ];
    }

    private static function validarBaseUvt(int|float $baseUvt): float
    {
        $base = (float) $baseUvt;

        if (!is_finite($base)) {
            throw new InvalidArgumentException('La base en UVT debe ser un numero finito.');
        }

        if ($base < 0) {
            throw new InvalidArgumentException('La base en UVT no puede ser negativa.');
        }

        return $base;
    }

    private static function validarBasePesos(int|float $basePesos): void
    {
        $base = (float) $basePesos;

        if (!is_finite($base)) {
            throw new InvalidArgumentException('La base en pesos debe ser un numero finito.');
        }

        if ($base < 0) {
            throw new InvalidArgumentException('La base en pesos no puede ser negativa.');
        }
    }
}
