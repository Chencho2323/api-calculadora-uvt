<?php

declare(strict_types=1);

namespace InheritDevs\Uvt;

/**
 * Metadatos del proyecto y chequeo del entorno de ejecucion.
 *
 * Es la unica pieza que todos los modulos comparten: expone el nombre del
 * paquete, la version y el espacio de nombres raiz, y dice si la maquina donde
 * corre la API tiene lo minimo indispensable.
 */
final class Proyecto
{
    /**
     * Nombre del paquete en Composer.
     */
    public const NOMBRE = 'api-calculadora-uvt';

    /**
     * Espacio de nombres raiz de los componentes de la API.
     */
    public const ESPACIO = 'InheritDevs\\Uvt';

    /**
     * Version del paquete.
     */
    public const VERSION = '0.1.0';

    /**
     * Version minima de PHP que la API soporta.
     */
    public const PHP_MINIMO = '8.3.0';

    /**
     * Extensiones de PHP que la API necesita.
     *
     * @var list<string>
     */
    public const EXTENSIONES = ['json', 'mbstring'];

    /**
     * Devuelve las extensiones que faltan en el entorno actual.
     *
     * @return list<string>
     */
    public static function extensionesFaltantes(): array
    {
        $faltantes = [];

        foreach (self::EXTENSIONES as $extension) {
            if (!extension_loaded($extension)) {
                $faltantes[] = $extension;
            }
        }

        return $faltantes;
    }

    /**
     * Indica si el entorno cumple la version de PHP y las extensiones.
     */
    public static function entornoListo(): bool
    {
        return version_compare(PHP_VERSION, self::PHP_MINIMO, '>=')
            && self::extensionesFaltantes() === [];
    }

    /**
     * Describe el entorno en una linea, para los mensajes de arranque.
     */
    public static function descripcionEntorno(): string
    {
        return sprintf(
            '%s %s sobre PHP %s',
            self::NOMBRE,
            self::VERSION,
            PHP_VERSION
        );
    }
}
