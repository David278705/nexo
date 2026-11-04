# Propuesta de tabla de precios

> Paso 03 del plan de salida al mercado. Propuesta del 2026-11-03 para que David y Angela decidan.
> **Los números finales son de ellos**; esto es la cuenta detrás, para que decidan sobre algo calculado y
> no sobre una intuición. Sigue el método del documento 6, A.1: primero el costo, después el precio.

## Las dos variables

El documento 1, 2.4, ya lo fija: el precio depende del **número de empleados** y del **número de usuarios
administradores**. Los tramos de empleados salen del mercado objetivo del mismo documento (1.3):

| Segmento | Empleados |
|---|---|
| Microempresa | Hasta 10 |
| Pequeña | Hasta 50 |
| Mediana | Hasta 200 |

## El costo

Del plan de salida al mercado, en un escenario medio:

| Costo | Al mes | Cómo se comporta |
|---|---|---|
| Fijo: contador, infraestructura, correo, dominio | ~$800.000 | Casi igual con 1 cliente que con 10 |
| Soporte, a $60.000 la hora | Según el tamaño | Es el que crece con cada cliente |

El soporte estimado por cliente y por mes: **1,5 horas** una microempresa, **3** una pequeña y **5** una
mediana. Es una estimación: el número real lo da el primer cliente (paso 30).

## La propuesta

| Plan | Empleados | Usuarios incluidos | Precio mensual | Implementación, una vez |
|---|---|---|---|---|
| **Micro** | Hasta 10 | 1 | **$250.000** | $400.000 |
| **Pequeña** | Hasta 50 | 2 | **$450.000** | $800.000 |
| **Mediana** | Hasta 200 | 3 | **$750.000** | $1.500.000 |
| Usuario adicional | — | +1 | **$40.000** | — |

**Más IVA del 19 %.** El SaaS está gravado (paso 05 del plan), así que el precio publicado tiene que decir
«más IVA» o incluirlo. La recomendación es publicarlo **más IVA**, que es lo habitual en software para
empresas: el cliente que es responsable de IVA lo descuenta.

La **implementación** es un cargo único por la reunión y la carga inicial, que es práctica habitual en el
segmento (documento 6, A.1) y cubre el trabajo más caro de todo el ciclo del cliente. Crece con el tamaño
porque una planta de 200 personas trae más filas, más errores y más vueltas.

## La cuenta

| Plan | Precio | Soporte estimado | Lo que queda para el costo fijo |
|---|---|---|---|
| Micro | $250.000 | $90.000 | $160.000 |
| Pequeña | $450.000 | $180.000 | $270.000 |
| Mediana | $750.000 | $300.000 | $450.000 |

**El punto de equilibrio** son $800.000 de costo fijo. Se cubre con, por ejemplo:

- **3 pequeñas** ($810.000), o
- **2 medianas** ($900.000), o
- **5 micro** ($800.000).

Desde ahí, cada cliente nuevo es ganancia, porque el costo fijo casi no se mueve.

**Para comparar:** el plan Pequeña cuesta el **26 % de un salario mínimo** de 2026 ($1.750.905). Es menos
de lo que cuesta una sola indemnización por un periodo de prueba mal contado o un preaviso que se pasó.

## Dos opciones que conviene decidir

1. **Pago anual con descuento.** Diez meses de precio por doce de servicio (un 17 % menos). Mejora la caja
   y reduce las cancelaciones. Recomendación: ofrecerlo desde el principio.
2. **Precio de lanzamiento para los primeros clientes.** Un descuento a cambio de que sean piloto, den su
   opinión y permitan usar su nombre como referencia. Recomendación: solo con los tres primeros y por
   escrito, con fecha de fin.

## Lo que el sistema hace y no hace con esto

- Los planes **se crean en el panel**, en Suscripciones, con nombre, valor mensual, empleados y usuarios
  incluidos. No hay que tocar código: la tabla `planes` está vacía a propósito, esperando esta decisión.
- No encontré que el sistema **bloquee ni avise** cuando un cliente pasa de los empleados o los usuarios
  de su plan. Con pocos clientes se vigila a mano desde la ficha de la empresa; si se quiere un aviso, es
  una tarea de código.

## Lo que esta propuesta no sabe

- **Cuánto cobra la competencia.** No hay precios públicos verificables de los sistemas comparables en
  Colombia. Antes de publicar, conviene preguntarles a los tres primeros prospectos cuánto pagan hoy, o
  cuánto les cuesta no tener el sistema.
- **El soporte real.** Las horas de arriba son una estimación. Si el primer cliente pide el doble, el
  precio de su plan está mal y hay que subirlo para los siguientes.
