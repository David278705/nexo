# ============================================================================
#  vibe.ps1 — Bucle de desarrollo automatico
#
#  Ejecuta Claude Code una tarea a la vez hasta que:
#    · se acabe el backlog
#    · se acaben los creditos
#    · se alcance el limite de vueltas
#    · lo detengas con Ctrl+C
#
#  Uso:
#     .\scripts\vibe.ps1
#     .\scripts\vibe.ps1 -Vueltas 20
#     .\scripts\vibe.ps1 -SinLimitePermisos      # mas rapido, menos seguro
#     .\scripts\vibe.ps1 -Modelo opus            # fuerza un modelo para todo
#
#  Cada vuelta es una sesion nueva de Claude Code, con contexto limpio.
#  El estado vive en los archivos, no en la conversacion. Por eso no se atasca.
#
#  EL MODELO SE ESCOGE POR TAREA. Ver la lista $tareasComplejas mas abajo:
#  el resto de tareas va con el modelo base. Con -Modelo se fuerza uno solo
#  para toda la corrida y la lista se ignora.
# ============================================================================

param(
    [int]$Vueltas = 100,
    [int]$MaxTurnos = 250,
    [int]$PausaSegundos = 5,
    [switch]$SinLimitePermisos,
    [string]$Modelo = "",
    [string]$ModeloBase = "sonnet",
    [string]$ModeloComplejo = "opus"
)

$ErrorActionPreference = "Stop"
$raiz = Split-Path -Parent $PSScriptRoot
Set-Location $raiz

# ---------------------------------------------------------------- comprobaciones
function Abortar($mensaje) {
    Write-Host ""
    Write-Host "  $mensaje" -ForegroundColor Red
    Write-Host ""
    exit 1
}

if (-not (Get-Command claude -ErrorAction SilentlyContinue)) {
    Abortar "No encuentro el comando 'claude'. Instala Claude Code y vuelve a intentar."
}
if (-not (Test-Path "$raiz\BACKLOG.md")) { Abortar "No encuentro BACKLOG.md. Ejecuta el script desde la raiz del proyecto." }
if (-not (Test-Path "$raiz\CLAUDE.md"))  { Abortar "No encuentro CLAUDE.md." }
if (-not (Test-Path "$raiz\.git")) {
    Write-Host "  Aviso: esto no parece un repositorio git. Sin git no hay forma de deshacer." -ForegroundColor Yellow
    $r = Read-Host "  Continuar de todos modos? (s/N)"
    if ($r -ne "s") { exit 1 }
}

New-Item -ItemType Directory -Force -Path "$raiz\bitacora" | Out-Null
$registro = "$raiz\bitacora\bucle-$(Get-Date -Format 'yyyy-MM-dd-HHmm').log"

# ---------------------------------------------------------------- utilidades
function TareasPendientes {
    (Select-String -Path "$raiz\BACKLOG.md" -Pattern '^### \[ \]' -AllMatches).Count
}
function TareasHechas {
    (Select-String -Path "$raiz\BACKLOG.md" -Pattern '^### \[x\]' -AllMatches).Count
}
function TareasBloqueadas {
    (Select-String -Path "$raiz\BACKLOG.md" -Pattern '^### \[!\]' -AllMatches).Count
}
function ProximaTarea {
    $m = Select-String -Path "$raiz\BACKLOG.md" -Pattern '^### \[ \] (.+)$' | Select-Object -First 1
    if ($m) { return $m.Matches[0].Groups[1].Value } else { return "" }
}

# ---------------------------------------------------------------- modelo por tarea
#
# Las tareas donde equivocarse NO lo atrapa ninguna prueba: o tocan el motor de
# calculo y los saldos, o cruzan empresas a proposito, o son revisiones de
# criterio sobre todo el codigo. El resto del backlog es aplicar patrones que el
# proyecto ya tiene resueltos, y para eso el modelo base sobra.
#
# Es una lista, no una regla automatica: si una tarea nueva toca reglas legales
# o aislamiento, agregala aqui a mano.
$tareasComplejas = @(
    'F4-05',   # punto de control: revisar todo el codigo, es juicio puro
    'F5-01',   # lector de plantillas: valida los datos de arranque del cliente
    'F5-02',   # ejecucion de la carga: transaccional y toca los saldos
    'F5-04',   # suscripciones y cobros: dinero, mora y suspension
    'F5-07',   # acceso del owner al entorno del cliente: cruza empresas
    'F6-08',   # revision final
    'REV-03'   # eliminar movimientos y terminaciones: revierte estado del empleado
)

function IdDeTarea($textoDeTarea) {
    $m = [regex]::Match($textoDeTarea, '^([A-Z0-9]+-[0-9]+)')
    if ($m.Success) { return $m.Groups[1].Value } else { return "" }
}

function ModeloParaTarea($textoDeTarea) {
    if ($Modelo -ne "") { return $Modelo }
    $id = IdDeTarea $textoDeTarea
    if ($id -ne "" -and $tareasComplejas -contains $id) { return $ModeloComplejo }
    return $ModeloBase
}
function Log($texto) {
    $linea = "[{0}] {1}" -f (Get-Date -Format 'HH:mm:ss'), $texto
    Add-Content -Path $registro -Value $linea
}

# Frases con las que Claude Code avisa que se acabo el limite de uso o el saldo.
# Cuando aparecen, el CLI a veces sale con codigo 0 y otras con codigo distinto
# de 0 (visto: "You've hit your session limit" salio con codigo 1), asi que ni
# el codigo de salida ni una sola frase bastan por si solos.
$patronLimite = 'usage limit reached|Claude usage limit|session limit|rate limit|Credit balance is too low|limit will reset|insufficient credits|Please run /login|Invalid API key'

function HoraDeReinicio($texto) {
    $m = [regex]::Match($texto, '(?:resets?|reset at|try again)\s+(?:at\s+)?([^\r\n\.]{3,40})', 'IgnoreCase')
    if ($m.Success) { return $m.Groups[1].Value.Trim() } else { return "" }
}

# ---------------------------------------------------------------- el encargo
$encargo = @"
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
"@

# ---------------------------------------------------------------- argumentos
#
# El modelo se decide en cada vuelta, ya con la tarea a la vista, asi que los
# argumentos se arman dentro del bucle y no una sola vez aqui.
function ArgumentosPara($modeloDeLaVuelta) {
    $a = @("-p", $encargo, "--max-turns", "$MaxTurnos", "--output-format", "text")
    if ($SinLimitePermisos) {
        $a += @("--permission-mode", "bypassPermissions")
    } else {
        $a += @("--permission-mode", "acceptEdits")
    }
    if ($modeloDeLaVuelta -ne "") { $a += @("--model", $modeloDeLaVuelta) }
    return $a
}

# ---------------------------------------------------------------- arranque
Write-Host ""
Write-Host "  ============================================================" -ForegroundColor Cyan
Write-Host "   Bucle de desarrollo — Sistema de Gestion de Talento Humano" -ForegroundColor Cyan
Write-Host "  ============================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "   Pendientes: $(TareasPendientes)   Hechas: $(TareasHechas)   Bloqueadas: $(TareasBloqueadas)"
Write-Host "   Maximo de vueltas en esta corrida: $Vueltas"
Write-Host "   Registro: $registro"
if ($Modelo -ne "") {
    Write-Host "   Modelo: $Modelo para todas las tareas (forzado)" -ForegroundColor Magenta
} else {
    Write-Host "   Modelo: $ModeloBase, y $ModeloComplejo en $($tareasComplejas.Count) tareas de criterio" -ForegroundColor Magenta
    Write-Host "           ($($tareasComplejas -join ', '))" -ForegroundColor DarkGray
}
if ($SinLimitePermisos) {
    Write-Host ""
    Write-Host "   MODO SIN LIMITE DE PERMISOS: no se pedira confirmacion para nada" -ForegroundColor Yellow
    Write-Host "   y la lista de comandos prohibidos no aplica. Uselo solo en un repo" -ForegroundColor Yellow
    Write-Host "   con git y sin datos reales." -ForegroundColor Yellow
}
Write-Host ""
Write-Host "   Ctrl+C para detener. Se detiene limpio al terminar la vuelta." -ForegroundColor DarkGray
Write-Host ""

Log "=== Inicio del bucle. Pendientes: $(TareasPendientes) ==="

$vuelta = 0
$sinAvance = 0
$ultimoConteo = TareasHechas

while ($vuelta -lt $Vueltas) {

    $pendientes = TareasPendientes
    if ($pendientes -eq 0) {
        Write-Host "  No quedan tareas pendientes. Backlog completo." -ForegroundColor Green
        Log "Backlog completo."
        break
    }

    $vuelta++
    $tarea = ProximaTarea
    $marca = Get-Date -Format 'HH:mm:ss'
    $modeloDeLaVuelta = ModeloParaTarea $tarea
    $porQueEseModelo = if ($Modelo -ne "") {
        "forzado con -Modelo"
    } elseif ($modeloDeLaVuelta -eq $ModeloComplejo) {
        "tarea de la lista de criterio"
    } else {
        "tarea de patron conocido"
    }

    Write-Host ""
    Write-Host "  ------------------------------------------------------------" -ForegroundColor DarkGray
    Write-Host "   Vuelta $vuelta de $Vueltas   ·   $marca" -ForegroundColor White
    Write-Host "   $tarea" -ForegroundColor Cyan
    Write-Host "   Modelo: $modeloDeLaVuelta ($porQueEseModelo)" -ForegroundColor Magenta
    Write-Host "   Quedan $pendientes pendientes" -ForegroundColor DarkGray
    Write-Host "  ------------------------------------------------------------" -ForegroundColor DarkGray
    Write-Host ""

    Log "Vuelta $vuelta — $tarea — modelo: $modeloDeLaVuelta ($porQueEseModelo)"

    $inicio = Get-Date
    # La salida se ve en pantalla y ademas queda guardada, para poder revisarla
    # despues y para saber si la vuelta murio por limite de uso.
    $logVuelta = "$raiz\bitacora\vuelta-$(Get-Date -Format 'yyyy-MM-dd-HHmmss').log"
    $argumentos = ArgumentosPara $modeloDeLaVuelta
    & claude @argumentos 2>&1 | Tee-Object -FilePath $logVuelta
    $codigo = $LASTEXITCODE
    $duracion = [math]::Round(((Get-Date) - $inicio).TotalMinutes, 1)

    $salida = if (Test-Path $logVuelta) { Get-Content $logVuelta -Raw -ErrorAction SilentlyContinue } else { "" }
    if ($null -eq $salida) { $salida = "" }

    Log "Vuelta $vuelta termino con codigo $codigo en $duracion min · registro: $(Split-Path -Leaf $logVuelta)"

    if ($salida -match $patronLimite) {
        $cuando = HoraDeReinicio $salida
        Write-Host ""
        Write-Host "  Se acabo el limite de uso de tu suscripcion." -ForegroundColor Yellow
        if ($cuando -ne "") {
            Write-Host "  Se reinicia: $cuando" -ForegroundColor Yellow
        }
        Write-Host ""
        Write-Host "  No gasto mas vueltas. Vuelve a ejecutar el script cuando se" -ForegroundColor Yellow
        Write-Host "  reinicie tu limite y retoma exactamente donde iba." -ForegroundColor Yellow
        Write-Host ""
        Write-Host "  Detalle en: $logVuelta" -ForegroundColor DarkGray
        Log "Detenido por limite de uso. Reinicio: $cuando"
        break
    }

    if ($codigo -ne 0) {
        Write-Host ""
        Write-Host "  Claude Code termino con codigo $codigo." -ForegroundColor Yellow
        Write-Host ""
        Write-Host "  Las causas mas probables son:" -ForegroundColor Yellow
        Write-Host "    · se acabaron los creditos de la suscripcion"
        Write-Host "    · se alcanzo el limite de $MaxTurnos turnos en una sola tarea"
        Write-Host "    · un error de red"
        Write-Host ""
        Write-Host "  Revisa ESTADO.md para ver donde quedo y vuelve a ejecutar el" -ForegroundColor Yellow
        Write-Host "  script cuando tengas creditos. Retoma solo donde iba." -ForegroundColor Yellow
        Write-Host ""
        Write-Host "  Salida completa de esta vuelta: $logVuelta" -ForegroundColor DarkGray
        Log "Detenido por codigo de salida $codigo"
        break
    }

    # --- antibloqueo: si tres vueltas no mueven el contador de hechas, parar ---
    $conteo = TareasHechas
    if ($conteo -eq $ultimoConteo) {
        $sinAvance++
        Write-Host "  Aviso: esta vuelta no completo ninguna tarea ($sinAvance seguidas)." -ForegroundColor Yellow
        Log "Sin avance: $sinAvance vueltas seguidas"
        if ($sinAvance -ge 3) {
            Write-Host ""
            Write-Host "  Tres vueltas sin completar ninguna tarea. Me detengo para que" -ForegroundColor Red
            Write-Host "  revises que esta pasando. Mira ESTADO.md y el registro." -ForegroundColor Red
            Log "Detenido por falta de avance"
            break
        }
    } else {
        $sinAvance = 0
        $ultimoConteo = $conteo
        Write-Host "  Tarea completada. Total hechas: $conteo" -ForegroundColor Green
    }

    if ($PausaSegundos -gt 0 -and $vuelta -lt $Vueltas) {
        Start-Sleep -Seconds $PausaSegundos
    }
}

# ---------------------------------------------------------------- cierre
Write-Host ""
Write-Host "  ============================================================" -ForegroundColor Cyan
Write-Host "   Resumen de la corrida" -ForegroundColor Cyan
Write-Host "  ============================================================" -ForegroundColor Cyan
Write-Host "   Vueltas ejecutadas : $vuelta"
Write-Host "   Tareas hechas      : $(TareasHechas)"
Write-Host "   Pendientes         : $(TareasPendientes)"
Write-Host "   Bloqueadas         : $(TareasBloqueadas)" -ForegroundColor $(if ((TareasBloqueadas) -gt 0) { "Yellow" } else { "Gray" })
Write-Host ""
if ((TareasBloqueadas) -gt 0) {
    Write-Host "   Hay tareas bloqueadas. Mira las preguntas en ESTADO.md," -ForegroundColor Yellow
    Write-Host "   resuelvelas con Angela y desmarcalas a [ ] para reintentarlas." -ForegroundColor Yellow
    Write-Host ""
}
Write-Host "   Registro completo: $registro" -ForegroundColor DarkGray
Write-Host ""

Log "=== Fin. Hechas: $(TareasHechas) · Pendientes: $(TareasPendientes) · Bloqueadas: $(TareasBloqueadas) ==="
