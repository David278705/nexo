#!/usr/bin/env bash
# ============================================================================
#  vibe.sh — Bucle de desarrollo automatico (macOS / Linux)
#
#  Equivalente de vibe.ps1. Ejecuta Claude Code una tarea a la vez hasta que
#  se acabe el backlog, se acaben los creditos o se alcance el limite de vueltas.
#
#  Uso:
#     ./scripts/vibe.sh
#     VUELTAS=20 ./scripts/vibe.sh
#     SIN_LIMITE_PERMISOS=1 ./scripts/vibe.sh
# ============================================================================

set -uo pipefail

VUELTAS="${VUELTAS:-100}"
MAX_TURNOS="${MAX_TURNOS:-250}"
PAUSA="${PAUSA:-5}"
SIN_LIMITE_PERMISOS="${SIN_LIMITE_PERMISOS:-0}"
MODELO="${MODELO:-}"

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ"

rojo() { printf '\033[31m%s\033[0m\n' "$1"; }
verde() { printf '\033[32m%s\033[0m\n' "$1"; }
amar() { printf '\033[33m%s\033[0m\n' "$1"; }
cyan() { printf '\033[36m%s\033[0m\n' "$1"; }
gris() { printf '\033[90m%s\033[0m\n' "$1"; }

abortar() { echo; rojo "  $1"; echo; exit 1; }

command -v claude >/dev/null 2>&1 || abortar "No encuentro el comando 'claude'. Instala Claude Code."
[ -f BACKLOG.md ] || abortar "No encuentro BACKLOG.md. Ejecuta el script desde la raiz del proyecto."
[ -f CLAUDE.md ]  || abortar "No encuentro CLAUDE.md."
if [ ! -d .git ]; then
  amar "  Aviso: esto no parece un repositorio git. Sin git no hay forma de deshacer."
  read -r -p "  Continuar de todos modos? (s/N) " r
  [ "$r" = "s" ] || exit 1
fi

mkdir -p bitacora
REGISTRO="bitacora/bucle-$(date +%Y-%m-%d-%H%M).log"

pendientes() { grep -c '^### \[ \]' BACKLOG.md || true; }
hechas()     { grep -c '^### \[x\]' BACKLOG.md || true; }
bloqueadas() { grep -c '^### \[!\]' BACKLOG.md || true; }
proxima()    { grep -m1 '^### \[ \] ' BACKLOG.md | sed 's/^### \[ \] //' || true; }
log()        { printf '[%s] %s\n' "$(date +%H:%M:%S)" "$1" >> "$REGISTRO"; }

ENCARGO=$(cat <<'FIN'
Trabaja en este proyecto siguiendo el procedimiento del skill 'siguiente'.

Resumen del procedimiento, por si no se carga solo:
1. Lee ESTADO.md y BACKLOG.md.
2. Toma la PRIMERA tarea marcada [ ]. No elijas otra ni saltes tareas.
3. Implementala completa, incluidas sus pruebas.
4. Ejecuta 'composer verificar'. Maximo dos intentos de arreglo si sale en rojo.
   Si tras el segundo intento sigue en rojo, revierte lo que la rompio y deja el repositorio compilando.
5. Marca la tarea [x] en BACKLOG.md, reescribe ESTADO.md completo y haz commit.
6. Escribe un resumen de cinco lineas en bitacora/.

Reglas de esta sesion:
- Trabaja UNA sola tarea y termina. Aunque te sobre tiempo, no empieces otra.
- No pidas confirmacion para nada. Trabaja de forma autonoma.
- No inventes reglas de negocio ni valores legales. Si algo no esta en los documentos,
  marca la tarea [!] con el motivo, anota la pregunta en ESTADO.md y termina la sesion.
- Nunca dejes el repositorio con la verificacion en rojo.
- No ejecutes 'git push'.
FIN
)

ARGS=(-p "$ENCARGO" --max-turns "$MAX_TURNOS" --output-format text)
if [ "$SIN_LIMITE_PERMISOS" = "1" ]; then
  ARGS+=(--permission-mode bypassPermissions)
else
  ARGS+=(--permission-mode acceptEdits)
fi
[ -n "$MODELO" ] && ARGS+=(--model "$MODELO")

echo
cyan "  ============================================================"
cyan "   Bucle de desarrollo — Sistema de Gestion de Talento Humano"
cyan "  ============================================================"
echo
echo "   Pendientes: $(pendientes)   Hechas: $(hechas)   Bloqueadas: $(bloqueadas)"
echo "   Maximo de vueltas en esta corrida: $VUELTAS"
echo "   Registro: $REGISTRO"
if [ "$SIN_LIMITE_PERMISOS" = "1" ]; then
  echo
  amar "   MODO SIN LIMITE DE PERMISOS: no se pedira confirmacion para nada"
  amar "   y la lista de comandos prohibidos no aplica."
fi
echo
gris "   Ctrl+C para detener."
echo

log "=== Inicio del bucle. Pendientes: $(pendientes) ==="

vuelta=0
sin_avance=0
ultimo=$(hechas)

while [ "$vuelta" -lt "$VUELTAS" ]; do
  p=$(pendientes)
  if [ "$p" -eq 0 ]; then
    verde "  No quedan tareas pendientes. Backlog completo."
    log "Backlog completo."
    break
  fi

  vuelta=$((vuelta + 1))
  tarea=$(proxima)

  echo
  gris "  ------------------------------------------------------------"
  echo "   Vuelta $vuelta de $VUELTAS   ·   $(date +%H:%M:%S)"
  cyan "   $tarea"
  gris "   Quedan $p pendientes"
  gris "  ------------------------------------------------------------"
  echo

  log "Vuelta $vuelta — $tarea"

  claude "${ARGS[@]}"
  codigo=$?
  log "Vuelta $vuelta termino con codigo $codigo"

  if [ "$codigo" -ne 0 ]; then
    echo
    amar "  Claude Code termino con codigo $codigo."
    echo
    amar "  Causas mas probables: se acabaron los creditos, se alcanzo el limite"
    amar "  de $MAX_TURNOS turnos, o un error de red."
    echo
    amar "  Revisa ESTADO.md y vuelve a ejecutar cuando tengas creditos."
    log "Detenido por codigo de salida $codigo"
    break
  fi

  conteo=$(hechas)
  if [ "$conteo" -eq "$ultimo" ]; then
    sin_avance=$((sin_avance + 1))
    amar "  Aviso: esta vuelta no completo ninguna tarea ($sin_avance seguidas)."
    log "Sin avance: $sin_avance vueltas seguidas"
    if [ "$sin_avance" -ge 3 ]; then
      echo
      rojo "  Tres vueltas sin completar ninguna tarea. Me detengo para que revises."
      log "Detenido por falta de avance"
      break
    fi
  else
    sin_avance=0
    ultimo=$conteo
    verde "  Tarea completada. Total hechas: $conteo"
  fi

  [ "$PAUSA" -gt 0 ] && sleep "$PAUSA"
done

echo
cyan "  ============================================================"
cyan "   Resumen de la corrida"
cyan "  ============================================================"
echo "   Vueltas ejecutadas : $vuelta"
echo "   Tareas hechas      : $(hechas)"
echo "   Pendientes         : $(pendientes)"
echo "   Bloqueadas         : $(bloqueadas)"
echo
if [ "$(bloqueadas)" -gt 0 ]; then
  amar "   Hay tareas bloqueadas. Mira las preguntas en ESTADO.md, resuelvelas"
  amar "   con Angela y desmarcalas a [ ] para reintentarlas."
  echo
fi
gris "   Registro completo: $REGISTRO"
echo

log "=== Fin. Hechas: $(hechas) · Pendientes: $(pendientes) · Bloqueadas: $(bloqueadas) ==="
