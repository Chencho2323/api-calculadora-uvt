<?php

declare(strict_types=1);

namespace InheritDevs\Uvt;

use InvalidArgumentException;

/**
 * Retencion en la fuente a titulo de IVA.
 *
 * La retencion de IVA no se calcula sobre el valor de la factura sino sobre el
 * IVA facturado, y la tarifa depende de la naturaleza del servicio: el 15% para
 * servicios en general, el 10% cuando el servicio se presta entre responsables
 * de IVA y el 0% cuando no hay retencion (bienes que no la soportan, o compras
 * a quien no es responsable con cuantia inferior a la base minima).
 *
 * La base minima son 27 UVT por operacion. Por debajo de esa cuantia no se
 * practica retencion, salvo que la operacion este sujeta a retencion por
 * disposicion especial, caso que aqui no se modela: quien llama lo indica con
 * `sinBaseMinima`.
 *
 * Los valores se redondean en pesos hacia arriba, que es como se liquida en la
 * declaracion mensual.
 */
final class RetencionIva
{
    /**
     * Norma que fija la tarifa y la base minima.
     */
    public const FUENTE = 'Estatuto Tributario, articulos 437-1, 437-2 y 383 (base minima de 27 UVT)';

    /**
     * Tarifas de retencion por tipo de servicio.
     */
    public const TARIFAS = [
        'servicios' => 0.15,
        'servicios_entre_responsables' => 0.10,
        'sin_retencion' => 0.00,
    ];

    /**
     * Tarifa general de IVA en Colombia, en porcentaje.
     */
    public const TARIFA_IVA = 0.19;

    /**
     * Base minima por operacion, en UVT.
     */
    public const BASE_MINIMA_UVT = 27;

    /**
     * Devuelve la tarifa de retencion aplicable a un concepto.
     *
     * @throws InvalidArgumentException Si el concepto no existe.
     */
    public static function tarifa(string $concepto): float
    {
        if (!array_key_exists($concepto, self::TARIFAS)) {
            throw new InvalidArgumentException(sprintf(
                'Concepto de retencion de IVA desconocido: %s. Los validos son: %s.',
                $concepto,
                implode(', ', array_keys(self::TARIFAS))
            ));
        }

        return self::TARIFAS[$concepto];
    }

    /**
     * Indica si una operacion de la cuantia indicada alcanza la base minima.
     *
     * @throws InvalidArgumentException Si la cuantia no sirve o el ano no esta en la tabla.
     */
    public static function alcanzaBaseMinima(int|float $valorOperacion, int $anio): bool
    {
        return self::validarMonto($valorOperacion) >= self::BASE_MINIMA_UVT * TablaUvt::valor($anio);
    }

    /**
     * Calcula la retencion de IVA en pesos.
     *
     * @param int|float $valorOperacion Valor de la operacion antes de IVA, en pesos.
     * @param string    $concepto       Clave de `TARIFAS`.
     * @param int       $anio           Ano cuya UVT se aplica a la base minima.
     * @param bool      $sinBaseMinima  True para no aplicar la base minima de 27 UVT.
     *
     * @throws InvalidArgumentException Si el monto, el concepto o el ano no sirven.
     */
    public static function enPesos(
        int|float $valorOperacion,
        string $concepto,
        int $anio,
        bool $sinBaseMinima = false
    ): int {
        return self::desglose($valorOperacion, $concepto, $anio, $sinBaseMinima)['retencion_pesos'];
    }

    /**
     * Devuelve el calculo completo de la retencion de IVA.
     *
     * @return array{
     *     anio: int,
     *     valor_uvt: int,
     *     valor_operacion: int,
     *     tarifa_iva: float,
     *     iva_generado: int,
     *     concepto: string,
     *     tarifa_retencion: float,
     *     base_minima_pesos: int,
     *     aplica_base_minima: bool,
     *     retencion_pesos: int,
     *     fuente: string
     * }
     *
     * @throws InvalidArgumentException Si el monto, el concepto o el ano no sirven.
     */
    public static function desglose(
        int|float $valorOperacion,
        string $concepto,
        int $anio,
        bool $sinBaseMinima = false
    ): array {
        $valor = self::validarMonto($valorOperacion);
        $tarifa = self::tarifa($concepto);
        $valorUvt = TablaUvt::valor($anio);
        $baseMinima = self::BASE_MINIMA_UVT * $valorUvt;

        $aplicaBaseMinima = !$sinBaseMinima;
        $superaLaBase = $valor >= $baseMinima;
        $grava = !$aplicaBaseMinima || $superaLaBase;

        $ivaGenerado = (int) round($valor * self::TARIFA_IVA, 0, PHP_ROUND_HALF_UP);
        $retencion = $grava
            ? (int) round($ivaGenerado * $tarifa, 0, PHP_ROUND_HALF_UP)
            : 0;

        return [
            'anio' => $anio,
            'valor_uvt' => $valorUvt,
            'valor_operacion' => (int) round($valor, 0, PHP_ROUND_HALF_UP),
            'tarifa_iva' => self::TARIFA_IVA,
            'iva_generado' => $ivaGenerado,
            'concepto' => $concepto,
            'tarifa_retencion' => $tarifa,
            'base_minima_pesos' => $baseMinima,
            'aplica_base_minima' => $aplicaBaseMinima,
            'retencion_pesos' => $retencion,
            'fuente' => self::FUENTE,
        ];
    }

    private static function validarMonto(int|float $monto): float
    {
        $valor = (float) $monto;

        if (!is_finite($valor)) {
            throw new InvalidArgumentException('El valor de la operacion debe ser un numero finito.');
        }

        if ($valor < 0) {
            throw new InvalidArgumentException('El valor de la operacion no puede ser negativo.');
        }

        return $valor;
    }
}
