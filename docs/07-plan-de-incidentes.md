# Plan de incidentes

> Control mínimo que pide `docs/06-decisiones-pendientes.md`, A.5: «Plan de incidentes escrito,
> aunque sea de una página». Los tres primeros escenarios ya estaban en
> `docs/04-panel-del-proveedor.md`, capítulo 14; este documento los reúne con lo que agrega la
> infraestructura real —Railway y Sentry— y suma el escenario que faltaba: que una alerta legal no
> salga, que es la promesa central del producto (`CLAUDE.md`).

## 1. Si una alerta legal no sale

Es el incidente que más le duele al cliente y el que menos ruido hace: nadie nota un correo que no
llegó hasta que es tarde.

1. **Confirmar si el proceso corrió.** Panel del proveedor → Salud del sistema: la tarjeta «Proceso
   de alertas de hoy» dice si `alertas:diarias` ya se ejecutó y con qué resultado. Si dice «Sin
   ejecutar» pasado el mediodía, el `schedule` de Railway no disparó — revisar ahí primero, no en el
   código.
2. **Si el proceso corrió pero una empresa quedó fallida:** el historial de la misma pantalla lo dice
   por fecha. `ProcesoDiarioDeAlertas` sigue con las demás empresas cuando una falla, así que un fallo
   no es silencioso para el resto; Sentry recibe la traza de la excepción concreta.
3. **Si el proceso corrió bien pero el correo no llegó a la bandeja:** revisar Alertas → entregabilidad
   del mes en el panel. Es un problema de dominio o de proveedor transaccional (SPF/DKIM/DMARC), no del
   motor — ver F3-06b y las líneas comentadas de `.env.example`.
4. **Reintentar sin esperar al día siguiente:** `php artisan alertas:diarias --fecha=<la que falló>`.
   El índice único de `alertas_enviadas` impide que esto duplique lo que sí salió.
5. **Avisar al cliente si el vencimiento ya pasó o está cerca.** Un aviso tardío por parte de Nexo es
   mejor que el cliente descubriendo solo que perdió un plazo legal.
6. Anotar la causa en la bitácora del cliente (documento 4, 13.2) y, si el fallo revela un caso que la
   batería de alertas no cubría, agregarlo a las pruebas.

## 2. Si el sistema se cae

1. Confirmar el alcance: ¿es Railway entero, la base de datos, o un servicio caído?
2. Revisar Sentry primero: una excepción masiva ahí suele explicar la caída antes que los logs.
3. Avisar por correo a los clientes afectados antes de que ellos escriban.
4. Resolver. Si hace falta restaurar datos, ver la sección 4 de abajo.
5. Avisar que ya está resuelto, en una línea, sin jerga técnica.
6. Anotar la causa y qué se hará para que no se repita.

## 3. Si hay un incidente de seguridad

**Crítico.** El acuerdo de tratamiento de datos fija un plazo de notificación que corre aunque no se
sepa aún el alcance completo.

1. Contener: cortar el acceso, cambiar credenciales, aislar lo afectado.
2. Determinar qué datos y qué clientes se vieron comprometidos.
3. Notificar a los clientes afectados dentro del plazo pactado.
4. Apoyar al cliente en sus deberes de reporte ante la Superintendencia de Industria y Comercio, sin
   asumirlos: son deberes de él como responsable del tratamiento.
5. Consultar al abogado antes de cualquier comunicación pública.
6. Documentar todo en el momento.

## 4. Si hay que restaurar una copia de seguridad

Railway hace *Point-in-Time Recovery* automático sobre PostgreSQL (WAL continuo, retención de 7 días).
Restaurar **no** se hace sobre el servicio de producción:

1. Desde el panel de Railway, restaurar a un punto en el tiempo. Esto crea un servicio de base de
   datos **nuevo**; el de producción sigue sirviendo tráfico sin tocarse.
2. Verificar en el servicio nuevo que un registro conocido está donde debe.
3. Si la restauración era para recuperar datos reales (no una prueba), apuntar la aplicación al
   servicio nuevo y descartar el viejo. Si era una prueba, descartar el servicio nuevo.
4. Dejar constancia en `bitacora/` de cuándo se hizo y qué se verificó — la única evidencia de que la
   copia de seguridad de verdad sirve es haberla restaurado.

## 5. Si un cálculo estaba mal

El incidente más probable de todos y el que nadie prevé: no rompe nada visible, solo produce números
equivocados en todos los clientes a la vez.

1. Corregir el cálculo y volver a ejecutarlo sobre los datos existentes.
2. Identificar qué clientes y qué registros quedaron afectados.
3. Avisarles, con el dato viejo y el nuevo. Ocultarlo es peor: el cliente puede haber liquidado a
   alguien con ese número.
4. Agregar el caso a la batería de pruebas del motor para que no vuelva a pasar.

## Dónde mirar primero, siempre

| Señal | Dónde |
|---|---|
| Excepciones de la aplicación, con traza | Sentry |
| Proceso diario de alertas, cola, errores | Panel del proveedor → Salud del sistema |
| Entregabilidad del correo | Panel del proveedor → Alertas |
| Estado de la base de datos y despliegue | Panel de Railway |
