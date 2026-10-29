<?php

declare(strict_types=1);

namespace InheritDevs\Uvt;

use InvalidArgumentException;

/**
 * Tabla oficial de la Unidad de Valor Tributario (UVT) por ano.
 *
 * La UVT se actualiza cada ano conforme al articulo 868 del Estatuto
 * Tributario: la DIAN fija su valor por resolucion en el ultimo trimestre del
 * ano anterior y rige del 1 de enero al 31 de diciembre. Aqui solo se guardan
 * valores publicados, con la norma que los fijo y el enlace a la fuente.
 *
 * Los valores son enteros en pesos. La UVT nunca se fracciona ni se redondea:
 * las operaciones se hacen sobre el valor exacto del ano que corresponda.
 */
final class TablaUvt
{
    /**
     * Rango de anos cubierto por la tabla.
     */
    public const ANIO_INICIAL = 2015;

    /**
     * Ultimo ano cubierto por la tabla.
     */
    public const ANIO_FINAL = 2026;

    /**
     * Enlace a la consulta oficial de la UVT en la DIAN.
     */
    public const FUENTE = 'https://www.dian.gov.co/impuestos/Paginas/uvt.aspx';

    /**
     * Valores publicados de la UVT, en pesos, con la norma que los fijo.
     *
     * @var array<int, array{valor: int, norma: string}>
     */
    private const VALORES = [
        2015 => ['valor' => 28279, 'norma' => 'Resolucion DIAN de actualizacion anual (art. 868 E.T.)'],
        2016 => ['valor' => 31859, 'norma' => 'Resolucion DIAN de actualizacion anual (art. 868 E.T.)'],
        2017 => ['valor' => 31859, 'norma' => 'Resolucion DIAN de actualizacion anual (art. 868 E.T.)'],
        2018 => ['valor' => 33156, 'norma' => 'Resolucion DIAN de actualizacion anual (art. 868 E.T.)'],
        2019 => ['valor' => 34270, 'norma' => 'Resolucion DIAN de actualizacion anual (art. 868 E.T.)'],
        2020 => ['valor' => 35607, 'norma' => 'Resolucion DIAN de actualizacion anual (art. 868 E.T.)'],
        2021 => ['valor' => 36308, 'norma' => 'Resolucion DIAN de actualizacion anual (art. 868 E.T.)'],
        2022 => ['valor' => 38004, 'norma' => 'Resolucion DIAN de actualizacion anual (art. 868 E.T.)'],
        2023 => ['valor' => 42412, 'norma' => 'Resolucion DIAN de actualizacion anual (art. 868 E.T.)'],
        2024 => ['valor' => 47065, 'norma' => 'Resolucion DIAN de actualizacion anual (art. 868 E.T.)'],
        2025 => ['valor' => 49799, 'norma' => 'Resolucion DIAN de actualizacion anual (art. 868 E.T.)'],
        2026 => ['valor' => 52374, 'norma' => 'Resolucion DIAN 000238 del 15 de diciembre de 2025'],
    ];

    /**
     * Indica si hay un valor publicado para el ano indicado.
     */
    public static function existe(int $anio): bool
    {
        return isset(self::VALORES[$anio]);
    }

    /**
     * Devuelve la UVT del ano indicado, en pesos.
     *
     * @throws InvalidArgumentException Si el ano no esta en la tabla.
     */
    public static function valor(int $anio): int
    {
        if (!self::existe($anio)) {
            throw new InvalidArgumentException(self::mensajeAnioFueraDeTabla($anio));
        }

        return self::VALORES[$anio]['valor'];
    }

    /**
     * Devuelve la norma que fijo la UVT del ano indicado.
     *
     * @throws InvalidArgumentException Si el ano no esta en la tabla.
     */
    public static function norma(int $anio): string
    {
        if (!self::existe($anio)) {
            throw new InvalidArgumentException(self::mensajeAnioFueraDeTabla($anio));
        }

        return self::VALORES[$anio]['norma'];
    }

    /**
     * Devuelve la ficha completa de un ano: valor, norma y fuente.
     *
     * @return array{anio: int, valor: int, norma: string, fuente: string}
     *
     * @throws InvalidArgumentException Si el ano no esta en la tabla.
     */
    public static function ficha(int $anio): array
    {
        return [
            'anio' => $anio,
            'valor' => self::valor($anio),
            'norma' => self::norma($anio),
            'fuente' => self::FUENTE,
        ];
    }

    /**
     * Lista los anos con valor publicado, en orden ascendente.
     *
     * @return list<int>
     */
    public static function anios(): array
    {
        return array_keys(self::VALORES);
    }

    /**
     * Devuelve la tabla completa, en orden ascendente por ano.
     *
     * @return list<array{anio: int, valor: int, norma: string, fuente: string}>
     */
    public static function completa(): array
    {
        return array_map(
            static fn (int $anio): array => self::ficha($anio),
            self::anios()
        );
    }

    /**
     * Devuelve la variacion porcentual frente al ano anterior, redondeada a dos
     * decimales. El primer ano de la tabla no tiene referencia previa.
     *
     * @throws InvalidArgumentException Si el ano no esta en la tabla o si es el primero.
     */
    public static function variacion(int $anio): float
    {
        $actual = self::valor($anio);
        $previo = self::existe($anio - 1) ? self::valor($anio - 1) : null;

        if ($previo === null) {
            throw new InvalidArgumentException(sprintf(
                'No hay valor de la UVT para %d, asi que no se puede calcular la variacion de %d.',
                $anio - 1,
                $anio
            ));
        }

        return round((($actual - $previo) / $previo) * 100, 2);
    }

    /**
     * Ultimo ano con valor publicado en esta version de la tabla.
     */
    public static function anioVigente(): int
    {
        $anios = self::anios();

        return (int) end($anios);
    }

    private static function mensajeAnioFueraDeTabla(int $anio): string
    {
        return sprintf(
            'No hay valor publicado de la UVT para el ano %d. La tabla cubre de %d a %d.',
            $anio,
            self::ANIO_INICIAL,
            self::ANIO_FINAL
        );
    }
}
