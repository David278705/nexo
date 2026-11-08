# Despliegue en Railway

> Cómo poner el sistema en producción y qué revisar después. El código ya trae todo lo que hace
> falta —la imagen, el arranque, la creación del rol de la base y la revisión de seguridad—; lo
> que queda aquí son las decisiones y los clics que solo puede hacer una persona.

## Cómo está armado

Una sola imagen (`Dockerfile`) y **tres servicios** en Railway que la arrancan distinto, según la
variable `NEXO_ROL`:

| Servicio | `NEXO_ROL` | Qué hace | Si falta |
|---|---|---|---|
| web | `web` | Atiende a los usuarios. Antes de abrir, crea el rol de la base, revisa la configuración y migra. | No hay sistema. |
| worker | `worker` | Manda los correos que deja la cola. | Las alertas se calculan y **no sale ninguna**. |
| scheduler | `scheduler` | Corre las tareas programadas: alertas a las 6:00, demo a las 3:00, métricas el día 1. | Las alertas ni se calculan. |

Más el servicio de **PostgreSQL** de Railway.

Lo que pasa al arrancar está en `docker/arrancar.sh`, y cada línea dice por qué.

## Antes de empezar

Genera dos secretos en tu máquina y guárdalos en un gestor de contraseñas:

```bash
php artisan key:generate --show   # APP_KEY
openssl rand -hex 24              # la clave del rol de la base (solo letras y números)
```

**La clave de la base solo letras y números**: va dentro de una URL, y un `@` o un `/` la romperían.

## Paso 1 · El proyecto y la base

1. Crea un proyecto en Railway y agrégale **PostgreSQL**.
2. Agrega un servicio desde el repositorio de GitHub. Railway encuentra el `Dockerfile` solo.

## Paso 2 · Las variables, iguales en los tres servicios

Lo más cómodo es definirlas una vez como *Shared Variables* del proyecto y compartirlas con los tres
servicios. **`APP_KEY` tiene que ser idéntica en los tres**: es la llave de las sesiones y de los
datos cifrados.

```env
APP_NAME=Nexo
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...                       # la que generaste
APP_URL=https://app.tudominio.co         # el dominio final, con https
APP_TIMEZONE=America/Bogota
APP_LOCALE=es
APP_FALLBACK_LOCALE=es

DB_CONNECTION=pgsql
# El rol de la aplicación, NUNCA el superusuario. «nexo_app» y la clave son tuyos;
# el resto lo completa Railway.
DB_URL=postgresql://nexo_app:LA_CLAVE@${{Postgres.PGHOST}}:${{Postgres.PGPORT}}/${{Postgres.PGDATABASE}}

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# A la salida estándar, que es lo que Railway muestra. Un archivo se perdería.
LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_LEVEL=info

SENTRY_LARAVEL_DSN=                       # el DSN del proyecto en Sentry

DISCO_CERTIFICADOS=local                  # ver «Los certificados» abajo

# El correo se completa en el paso 7. Mientras tanto:
MAIL_MAILER=log
MAIL_FROM_ADDRESS=alertas@tudominio.co
MAIL_FROM_NAME=Nexo

# El canal de soporte que el cliente ve en la barra lateral. Vacío, no se enseña.
SOPORTE_CORREO=soporte@tudominio.co
SOPORTE_HORARIO="Lunes a viernes, de 8:00 a. m. a 5:00 p. m."   # el mismo del SLA
```

## Paso 3 · El servicio web

Variables propias, además de las compartidas:

```env
NEXO_ROL=web
DB_URL_ADMIN=${{Postgres.DATABASE_URL}}   # solo para el primer despliegue; ver paso 6
```

En su configuración:

- **Healthcheck path**: `/up`. Así Railway no le manda tráfico hasta que la aplicación responda, y
  si un despliegue nuevo no arranca, deja el anterior funcionando.
- **Volumen**: monta uno en `/app/storage/app`. Ahí viven los certificados de Cámara de Comercio.
- **Dominio**: el tuyo, en *Networking*. Railway emite el certificado HTTPS solo.

## Paso 4 · El worker y el scheduler

Dos servicios más **desde el mismo repositorio**, sin dominio y sin volumen:

| Servicio | Variable propia | Réplicas | Política de reinicio |
|---|---|---|---|
| worker | `NEXO_ROL=worker` | 1 (o más si la cola crece) | **Always** |
| scheduler | `NEXO_ROL=scheduler` | **Siempre 1** | **Always** |

**«Always» importa**: con la política por defecto, Railway solo reinicia lo que termina con error,
y estos dos procesos tienen que vivir siempre. **El scheduler, siempre una sola réplica**: dos
correrían cada tarea dos veces. Las alertas aguantarían (el índice único de `alertas_enviadas` las
hace idempotentes), pero la restauración de la demo y las métricas no tienen por qué.

## Paso 5 · El primer despliegue

Al desplegar, el log del servicio web debe mostrar, en este orden:

1. `Rol «nexo_app» listo: sin superusuario y sin BYPASSRLS.` (de `despliegue:preparar-base`)
2. La revisión de `despliegue:verificar`: con `✔` lo esencial y `!` lo que todavía falta (el
   correo, por ahora). **Si aparece un `✘`, el servicio no arranca, y es a propósito**: el mensaje
   dice qué corregir.
3. Las migraciones.

El worker y el scheduler muestran solo la revisión y quedan esperando trabajo.

## Paso 6 · La primera cuenta y la limpieza

Desde la consola del servicio web (en el panel de Railway, o `railway ssh` con la CLI):

```bash
php artisan equipo:invitar tu@correo.co "Tu nombre"
php artisan equipo:invitar angela@correo.co "Angela"
```

Cada uno imprime un enlace de invitación: ábrelo, define la contraseña y activa el segundo factor
con Google Authenticator o 1Password. **Trata el enlace como una contraseña**: sirve una vez y vence
en 7 días. Si se pierde, el mismo comando emite otro.

Después, **borra `DB_URL_ADMIN` del servicio web**: el rol ya existe y un secreto de superusuario no
tiene por qué seguir dentro de un servicio expuesto a internet. Si algún día cambias la clave del
rol, la vuelves a poner un despliegue y la quitas otra vez.

## Paso 7 · Dominio y correo

Esto es la tarea `F3-06b` del backlog: proveedor transaccional, SPF, DKIM y DMARC en el DNS, y
después:

```env
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=alertas@tudominio.co
```

Y la prueba: `php artisan alertas:prueba` desde la consola. El correo tiene que llegar a la
**bandeja de entrada**, no a spam.

## Paso 8 · Comprobar que quedó bien

- [ ] `php artisan despliegue:verificar` en la consola: todo en `✔`, sin avisos.
- [ ] Entrar al sistema por el dominio: carga con estilos (si sale en blanco o sin estilos, mira la
      consola del navegador; casi siempre es `APP_URL` sin `https://`).
- [ ] **Las IPs del registro de accesos son las reales.** Entra, y en la base revisa
      `select ip, created_at from accesos order by id desc limit 3;`. Tiene que salir tu IP
      pública, no una `10.x.x.x` ni `100.x.x.x` de Railway. Si sale la de Railway, el proxy tiene
      más saltos de los esperados y el límite de intentos de acceso estaría contando a todos los
      usuarios como uno solo: avísale a quien mantenga el código.
- [ ] Al día siguiente, las 6:00 en Bogotá: en el panel del proveedor, «Alertas» muestra la
      ejecución del día.
- [ ] El simulacro de restauración de la base (`F6-07`, `docs/07-plan-de-incidentes.md` sección 4).

## Los documentos legales

Viven en `resources/legal/`, un archivo Markdown por documento: términos del servicio, acuerdo de
tratamiento de datos, plantilla de autorización para empleados y política de tratamiento. Hoy los
cuatro son **borradores con un aviso en lugar del texto**, y `despliegue:verificar` lo recuerda en
cada arranque.

**Antes de vender**, con los textos revisados por el abogado (documento 6, B.4): pega cada uno en su
archivo, cambia `estado: borrador` por `estado: vigente` y sube la `version`. No hace falta tocar
código. Cada empresa tendrá que aceptar las versiones nuevas la próxima vez que entre su
superadministrador, y hasta entonces nadie de esa empresa puede trabajar: es a propósito.

Los cuatro se leen en `https://app.tudominio.co/legal/{nombre-del-archivo}`, sin sesión, para
enlazarlos desde el sitio web.

## Los certificados

Hoy se guardan en el volumen del servicio web (`DISCO_CERTIFICADOS=local`). Es lo más simple y
basta mientras el servicio web tenga **una sola réplica**: un volumen solo se puede montar en un
servicio.

Para crecer a varias réplicas, se pasan a un almacenamiento tipo S3 (Cloudflare R2, AWS S3, el que
sea) **sin tocar código**:

```bash
composer require league/flysystem-aws-s3-v3
```

```env
DISCO_CERTIFICADOS=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
AWS_ENDPOINT=...                 # solo si no es AWS, por ejemplo R2
```

Los que ya estaban en el volumen hay que copiarlos al bucket una vez.

## Lo que no hay que hacer

- **Conectar `DB_URL` con el superusuario.** PostgreSQL no le aplica RLS y el aislamiento entre
  empresas deja de proteger. `despliegue:verificar` no deja arrancar así, pero que no haga falta.
- **Pasar FrankenPHP a modo worker** (u Octane) para ganar velocidad. Mantiene la aplicación viva
  entre peticiones, y la empresa en curso de una podría pasar a la siguiente.
- **Dos réplicas del scheduler.**
- **`APP_DEBUG=true` en producción**, ni un momento: enseña el código y la configuración a cualquiera
  que provoque un error. Tampoco arranca así.
