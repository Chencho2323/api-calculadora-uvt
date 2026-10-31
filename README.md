# API de conversion por UVT y retenciones

Convierte montos a UVT con la tabla oficial de cada ano, aplica las retenciones de renta e IVA vigentes y devuelve el resultado con su desglose. Sirve para liquidar nomina, contratos y facturacion.

![CI](https://github.com/Chencho2323/api-calculadora-uvt/actions/workflows/ci.yml/badge.svg)
![Licencia](https://img.shields.io/badge/licencia-MIT-03A8FA?style=flat-square)

**Stack:** PHP 8.3 · SQLite · PHPUnit

## Que resuelve

Este repositorio es parte del portafolio publico de
[InheritDevs](https://inheritdevs.com/): software empresarial que funciona en
produccion. El codigo esta aqui para que se pueda leer, ejecutar y auditar.

- Codigo completo, no un ejemplo recortado.
- Pruebas que corren en cada push (`npm test`, `phpunit` o `pytest`, segun el
  stack) y en cada pull request.
- Sin dependencias ocultas: lo que dice el README es lo que hace falta.

## Instalacion

```bash
git clone https://github.com/Chencho2323/api-calculadora-uvt.git
cd api-calculadora-uvt
```

Los comandos exactos de instalacion y ejecucion estan en la seccion siguiente.

## Uso

La aplicacion expone su funcionalidad por linea de comandos o por HTTP, segun
el caso. Los ejemplos concretos estan en `docs/` o en el `README` ampliado de
cada version.

### Conversion entre pesos y UVT

```php
use InheritDevs\Uvt\ConversorUvt;

ConversorUvt::aUvt(1000000, 2026);   // 19.09 UVT
ConversorUvt::aPesos(19.09, 2026);   // 1000000
ConversorUvt::desglose(1000000, 2026);
```

### Retencion de renta por rangos

Aplica la tabla del articulo 383 del Estatuto Tributario sobre el pago gravable
del mes, expresado en UVT.

```php
use InheritDevs\Uvt\RetencionRenta;

RetencionRenta::enPesos(10474800, 2026);   // 1256976 (200 UVT de 2026)
RetencionRenta::enUvt(200);                // 24.0 UVT
RetencionRenta::desglose(10474800, 2026);  // tramo, tarifa y acumulado
```

Los tramos son los de la norma: hasta 95 UVT no hay retencion, y de ahi en
adelante el pago paga el acumulado del tramo mas la tarifa marginal sobre el
excedente. En los bordes hay saltos hacia abajo porque la tabla publica el
acumulado redondeado; el codigo los reproduce tal cual.

### Retencion de IVA

Se calcula sobre el IVA facturado, con base minima de 27 UVT por operacion.

```php
use InheritDevs\Uvt\RetencionIva;

RetencionIva::enPesos(20000000, 'servicios', 2026);                        // 570000
RetencionIva::enPesos(20000000, 'servicios_entre_responsables', 2026);     // 380000
RetencionIva::enPesos(1000000, 'servicios', 2026);                         // 0, no alcanza 27 UVT
RetencionIva::enPesos(1000000, 'servicios', 2026, sinBaseMinima: true);    // 28500
```

Conceptos: `servicios` (15%), `servicios_entre_responsables` (10%) y
`sin_retencion` (0%).

## Pruebas

```bash
# el comando exacto lo declara el proyecto en verificar.sh
./verificar.sh
```

## Estructura

```
src/        codigo de la aplicacion
test(s)/    pruebas automaticas
docs/       documentacion de uso
```

## Licencia

MIT. Ver `LICENSE`.
