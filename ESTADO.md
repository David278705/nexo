# Estado del proyecto

**Última sesión:** 2026-08-17 · Tarea F0-02
**Verificación:** verde
**Siguiente tarea:** F0-03 · Multiempresa, capa 1

## Qué se hizo

- `config/reglas_laborales.php` con las **nueve constantes legales**, cada una con la norma citada y
  la fecha de verificación (2026-08-17) en su propio comentario:
  `dias_vacaciones_por_mes` (1,25 · art. 186 CST) · `periodo_prueba_indefinido_meses` (2 · art. 78) ·
  `periodo_prueba_fijo_divisor` (5 · art. 78) · `periodo_prueba_fijo_tope_meses` (2 · art. 78) ·
  `preaviso_dias` (30 · art. 46 mod. Ley 2466 de 2025) · `tope_termino_fijo_anios` (4 · art. 46
  reformado) · `disfrute_minimo_dias_habiles` (6 · art. 190) · `prorrogas_cortas_pactadas` (4) ·
  `prorrogas_cortas_automaticas` (3), las dos últimas según el Concepto MinTrabajo
  08SE2026120300000006157 del 18 de febrero de 2026.
- `config/app.php`: zona horaria `America/Bogota` y locale `es` (con `fallback_locale` `es` y
  `faker_locale` `es_CO`). Los tres comentarios del archivo se reescribieron explicando el porqué.
- `.env` y `.env.example` con `APP_TIMEZONE=America/Bogota`, `APP_LOCALE=es`,
  `APP_FALLBACK_LOCALE=es` y `APP_FAKER_LOCALE=es_CO`.
- Traducciones al español instaladas con `laravel-lang/common` (dev) y `php artisan lang:add es`.
  Quedaron en `lang/es/` y `lang/es.json`.
- `tests/Unit/ReglasLaboralesTest.php`: lee **cada una** de las nueve constantes con su valor,
  comprueba que no hay ni una de más ni de menos, y verifica zona horaria, locale y que los mensajes
  de validación sí se traducen.
- `tests/Pest.php` ahora extiende `Tests\TestCase` también en `Unit`, sin `RefreshDatabase`.

## Decisiones tomadas

- **La quinta parte del periodo de prueba se guarda como divisor entero (`5`), no como `0.2`.**
  El cálculo es `duración pactada ÷ 5` y así queda exacto, sin coma flotante.
- **`1.25` sí queda como decimal.** Es exactamente representable en binario (1 + ¼), así que no hay
  pérdida. La regla de «nunca coma flotante» aplica al dinero, y esto son días.
- **Nueve constantes exactas, ni una más.** La fecha del 25 de junio de 2025 (desde cuándo corren los
  cuatro años en contratos anteriores a la reforma, `docs/06` C.2) **no** se agregó: el backlog pide
  nueve y esa fecha es una regla de transición, no una constante legal de cálculo. Cuando F1-10
  la necesite, se agrega ahí con su comentario.
- **Las constantes se leen con `config()`, nunca se copian a una clase de PHP.** Así el panel del
  proveedor (F1B-04) podrá mostrarlas y, llegado el caso, sobreescribirlas sin desplegar código.
- **`lang` excluido de Pint** (`pint.json`). Son archivos generados por `laravel-lang`, con finales de
  línea y alineación propios; formatearlos a mano los rompería en el siguiente `lang:update`.
- `laravel-lang/common` entra como dependencia **de desarrollo**: solo genera archivos, y los
  archivos generados sí están versionados. En producción no hace falta.

## Preguntas para Angela

*(ninguna; las que ya existen están en `docs/06-decisiones-pendientes.md` bloque D)*

## Decisiones para David

- Sigue abierta la de la sesión anterior: **la máquina tiene PHP 8.2.12 (XAMPP) y `CLAUDE.md` pide
  8.3+**. `composer.json` quedó en `"php": "^8.2"`. Hay que actualizar el PHP local o dejar
  constancia de que el mínimo real del proyecto es 8.2.

## Notas para la próxima sesión

- La siguiente tarea es **F0-03 · Multiempresa, capa 1**: tabla `tenants`, trait `PerteneceATenant`
  con global scope y relleno automático, middleware que resuelve el tenant una sola vez por petición,
  y helper `tenant_actual()`. Fuente: `.claude/rules/10-multiempresa.md`.
- Ojo con F0-03: el trait tiene que ser lo único que filtre. Si aparece un `where('tenant_id', ...)`
  en un controlador, la prueba de F0-05 deja de servir para lo que se escribió.
- Ninguna constante legal debe volver a escribirse dentro de la lógica. Si el motor de cálculo (F2-02)
  necesita un número, sale de `config('reglas_laborales.…')`.
- Para levantar el proyecto en una máquina nueva siguen haciendo falta las dos bases de PostgreSQL
  (`nexo` y `nexo_pruebas`) creadas a mano.
