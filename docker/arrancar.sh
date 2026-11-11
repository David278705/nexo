#!/bin/sh
#
# El arranque de cada contenedor. La misma imagen sirve para los tres procesos;
# NEXO_ROL dice cuál es este:
#
#   web        la aplicación (y las migraciones, antes de atender)
#   worker     la cola: manda los correos de las alertas
#   scheduler  las tareas programadas: alertas diarias, demo, métricas
#
# Sin el worker y el scheduler la aplicación se ve perfecta y ninguna alerta
# sale nunca. Ver docs/08-despliegue.md.

set -e

ROL="${NEXO_ROL:-web}"

# Configuración, rutas, vistas y eventos a caché. Va aquí y no en la imagen
# porque las variables de entorno solo existen al arrancar.
php artisan optimize

case "$ROL" in
    web)
        # Crea o ajusta el rol sin privilegios de la base (solo si hay
        # DB_URL_ADMIN) y después comprueba que la aplicación se conecta con él.
        # Si algo crítico falla, el contenedor no arranca y Railway mantiene la
        # versión anterior en vez de servir una insegura.
        php artisan despliegue:preparar-base
        php artisan despliegue:verificar

        # Con una sola réplica no hay carrera. Con varias, si dos migran a la
        # vez una falla, Railway la reinicia y a la segunda la migración ya
        # está hecha.
        php artisan migrate --force

        # Los catálogos la primera vez y los festivos siempre (PROD-20). Sin
        # esto, una base nueva no tiene ni una ciudad y no se puede crear
        # ninguna empresa.
        php artisan catalogos:inicializar

        # Las cuentas del equipo de NEXO_EQUIPO_INICIAL que todavía no existen
        # (PROD-30). Railway no da una consola dentro del contenedor.
        php artisan equipo:asegurar-inicial

        # Las cuentas de prueba de siempre (owner@nexo.local y
        # admin@empresademo.local), solo si NEXO_CLAVE_CUENTAS_DE_PRUEBA trae
        # una clave (PROD-31). Volver a correrlo no duplica nada.
        if [ -n "${NEXO_CLAVE_CUENTAS_DE_PRUEBA:-}" ]; then
            php artisan db:seed --class=DesarrolloSeeder --force
        fi

        # HTTP plano: Railway termina el HTTPS en su borde y reenvía aquí.
        exec frankenphp php-server --listen ":${PORT:-8080}" --root public/
        ;;

    worker)
        php artisan despliegue:verificar

        # Sin --max-time ni --max-jobs a propósito: los dos hacen que el worker
        # termine con código 0, y Railway, con su política por defecto, solo
        # reinicia lo que falla. La cola moriría en silencio a la hora. Con
        # --memory, si crece demasiado sale con error y Railway lo levanta.
        exec php artisan queue:work --tries=3 --backoff=60 --sleep=3 --memory=256
        ;;

    scheduler)
        php artisan despliegue:verificar
        exec php artisan schedule:work
        ;;

    *)
        echo "NEXO_ROL desconocido: «$ROL». Usa web, worker o scheduler." >&2
        exit 1
        ;;
esac
