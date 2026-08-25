# Auditoría completa · 2026-08-25

Pedida directamente por David: analizar todo lo que quedó `[!]`, todo lo «heredado» que se venía
repitiendo en `ESTADO.md` desde REV-02, y una revisión manual de pantallas que el backlog daba por
completas — con autorización para decidir lo que no fuera una regla legal o de negocio genuina.

## Resuelto e implementado esta sesión

- `REV-06` — regla de la fecha entre paréntesis, acotada a correos vs. pantalla.
- D.4 — rangos de edad y antigüedad, implementados en `IndicadoresDeComposicion`.
- Las cuatro de D.6 — orden del dashboard, vigencia de demo (14 días), nombre de la carga inicial,
  empleados retirados en el histórico (fuera de alcance).
- B.3 — confirmado que `config/validaciones.php` ya implementa lo que Angela propuso.
- Prueba intermitente de `CatalogosTest` — le faltaba `orderBy`.
- PHP 8.2 vs 8.3 de `CLAUDE.md` — corregido para decir la verdad.
- El owner sin bloqueo en `/` — nuevo middleware `SoloCliente`, con la excepción de la visita al
  entorno (F5-07).
- `Listado::ordenar()` desempataba siempre ascendente — ahora respeta la dirección pedida.
- El menú desplegable de pantallas pequeñas no atrapaba el foco — mismo mecanismo que F6-06 le dio a
  `Modal.vue`, aplicado a `Armazon.vue`.

## Investigado y descartado, sin inventar

«Meses comerciales de treinta días» y «el texto del empleado inactivo» no tienen rastro en el código,
los documentos ni la bitácora actual. La frase del prototipo sobre el saldo negativo tampoco existe ya
en `prototipos/sistema.html`.

## Confirmado como pregunta real para Angela

B.2 (periodo de prueba en cambio de modalidad) y el fondo de cesantías. Ninguna se inventó.

## Quedó como tarea nueva, porque resultó ser trabajo real

`REV-08` (país de expedición faltante en la plantilla de empleados), `REV-09` (prórrogas ya
realizadas, recogidas por la plantilla y nunca usadas — cupo de prórrogas cortas mal contado en
cargas iniciales), `REV-10` (Usuarios y permisos, pantalla del cliente nunca construida, con
`SoftDeletes` para `User`/`Acceso`/`Invitacion`), `REV-11` (Facturación, la misma historia), `REV-12`
(dos pestañas de la ficha de empresa del proveedor con texto de hace seis fases), `F5-08` (valor
sugerido de vigencia en el formulario de accesos de prueba).

`composer verificar` en verde: Pint, PHPStan, 1.185 Pest, build, 388 Vitest.
