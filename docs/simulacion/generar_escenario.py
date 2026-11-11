# -*- coding: utf-8 -*-
"""
Genera el escenario de la simulación de Nexo a partir del DÍA 1 (el día en que se crea la empresa y se
cargan los datos).

Produce, en la carpeta de salida:
  - 1 - Empleados actuales (CORRECTA).xlsx      -> debe pasar la simulación sin errores
  - 1 - Empleados actuales (CON ERRORES).xlsx   -> debe fallar, con los errores de ERRORES_ESPERADOS
  - 3 - Historico de incapacidades.xlsx
  - 4 - Historico de vacaciones.xlsx
  - 6 - Historico de aumentos y promociones.xlsx
  - calendario_esperado.md                      -> qué alerta llega, qué día y por quién
  - escenario.json                              -> lo mismo, para comparar de forma automática

Uso:
  python generar_escenario.py --inicio 2026-11-12 --plantillas ./plantillas --salida ./salida

--plantillas es la carpeta con las plantillas originales de Nexo (los .xlsx que vienen en el paquete).
Necesita openpyxl.

Las fechas se calculan con las mismas reglas que el motor del sistema (docs/01, capítulo 9):
  - Un mes se cumple el día anterior al mismo número del mes siguiente.
  - Las alertas salen a las 6:00 (Bogotá) solo en días hábiles (lunes a viernes no festivos). Si la fecha
    de una alerta cae en sábado, domingo o festivo, sale el día hábil inmediatamente anterior.
  - Los resúmenes mensuales salen el último día hábil del mes; el de obra o labor solo en meses impares.
"""
import argparse
import copy
import datetime as dt
import json
import os
import shutil

import openpyxl

FESTIVOS = {dt.date.fromisoformat(f) for f in [
    "2025-01-01", "2025-01-06", "2025-03-24", "2025-04-17", "2025-04-18", "2025-05-01", "2025-06-02",
    "2025-06-23", "2025-06-30", "2025-07-20", "2025-08-07", "2025-08-18", "2025-10-13", "2025-11-03",
    "2025-11-17", "2025-12-08", "2025-12-25", "2026-01-01", "2026-01-12", "2026-03-23", "2026-04-02",
    "2026-04-03", "2026-05-01", "2026-05-18", "2026-06-08", "2026-06-15", "2026-06-29", "2026-07-13",
    "2026-07-20", "2026-08-07", "2026-08-17", "2026-10-12", "2026-11-02", "2026-11-16", "2026-12-08",
    "2026-12-25", "2027-01-01", "2027-01-11", "2027-03-22", "2027-03-25", "2027-03-26", "2027-05-01",
    "2027-05-10", "2027-05-31", "2027-06-07", "2027-07-05", "2027-07-12", "2027-07-20", "2027-08-07",
    "2027-08-16", "2027-10-18", "2027-11-01", "2027-11-15", "2027-12-08", "2027-12-25", "2028-01-01",
    "2028-01-10", "2028-03-20", "2028-04-13", "2028-04-14", "2028-05-01", "2028-05-29", "2028-06-19",
    "2028-06-26", "2028-07-03", "2028-07-10", "2028-07-20", "2028-08-07", "2028-08-21", "2028-10-16",
    "2028-11-06", "2028-11-13", "2028-12-08", "2028-12-25",
]}

UN_DIA = dt.timedelta(days=1)
MESES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre",
         "octubre", "noviembre", "diciembre"]
DIAS = ["lunes", "martes", "miércoles", "jueves", "viernes", "sábado", "domingo"]


# ---------------------------------------------------------------- reglas de fechas del motor

def habil(d):
    return d.weekday() < 5 and d not in FESTIVOS


def habil_anterior_o_igual(d):
    while not habil(d):
        d -= UN_DIA
    return d


def mas_meses(d, n):
    """addMonthsNoOverflow: mismo día, recortado al último día del mes de llegada."""
    m = d.month - 1 + n
    anio, mes = d.year + m // 12, m % 12 + 1
    ultimo = (dt.date(anio + (mes == 12), mes % 12 + 1, 1) - UN_DIA).day
    return dt.date(anio, mes, min(d.day, ultimo))


def cumple_meses_el(ingreso, n):
    return mas_meses(ingreso, n) - UN_DIA


def dias_corridos(desde, hasta):
    return (hasta - desde).days + 1


def ultimo_habil_del_mes(d):
    fin = dt.date(d.year + (d.month == 12), d.month % 12 + 1, 1) - UN_DIA
    return habil_anterior_o_igual(fin)


def fin_prueba(e):
    """Último día del periodo de prueba, o None si no aplica."""
    ingreso = e["ingreso"]
    dos_meses = dias_corridos(ingreso, cumple_meses_el(ingreso, 2))
    if e["tipo"] == "Término indefinido":
        dias = dos_meses
    elif e["tipo"] == "Término fijo":
        fin_inicial = e["prorrogas"][0][0] - UN_DIA if e.get("prorrogas") else e["fin"]
        dias = min(dias_corridos(ingreso, fin_inicial) // 5, dos_meses)
    else:
        if not e.get("estimada"):
            return None
        dias = min(dias_corridos(ingreso, e["estimada"]) // 5, dos_meses)
    return ingreso + dt.timedelta(days=dias - 1) if dias >= 1 else None


def disparos(e):
    """Las alertas individuales de una persona: lista de (tipo, fecha del disparo, fecha del hecho)."""
    out = []
    termina = fin_prueba(e)
    if termina:
        if e["tipo"] == "Término indefinido":
            out += [("Periodo de prueba: completó un mes", cumple_meses_el(e["ingreso"], 1), termina),
                    ("Periodo de prueba: faltan 15 días", termina - dt.timedelta(days=15), termina),
                    ("Periodo de prueba: víspera del último día", termina - UN_DIA, termina)]
        else:
            nombre = "término fijo" if e["tipo"] == "Término fijo" else "obra o labor"
            out += [(f"Periodo de prueba ({nombre}): faltan dos días", termina - dt.timedelta(days=2), termina),
                    (f"Periodo de prueba ({nombre}): víspera del último día", termina - UN_DIA, termina)]
    if e["tipo"] == "Término fijo":
        limite = e["fin"] - dt.timedelta(days=30)
        out += [("Plazo para definir la renovación (32 días antes del fin)", limite - dt.timedelta(days=2), limite),
                ("Efecto del silencio: último día para avisar la no renovación", limite, limite)]
        cortas = len([p for p in e.get("prorrogas", []) if mas_meses(p[0], 12) > p[1] + UN_DIA])
        inicial_corto = e.get("prorrogas") and mas_meses(e["ingreso"], 12) > e["prorrogas"][0][0]
        if inicial_corto and cortas >= 3:
            out.append(("Última renovación corta: la próxima debe ser de un año", limite - dt.timedelta(days=2), e["fin"]))
    # La alerta de un disparo solo existe si el disparo no es anterior al ingreso.
    return [(t, d, h) for (t, d, h) in out if d >= e["ingreso"]]


# ---------------------------------------------------------------- texto

def larga(d):
    return f"{DIAS[d.weekday()]} {d.day} de {MESES[d.month - 1]} de {d.year}"


def corta(d):
    return f"{d.day:02d}/{d.month:02d}/{d.year}"


# ---------------------------------------------------------------- el escenario

def buscar_ingreso(objetivo, calcular, desde, hasta):
    """El primer ingreso, entre desde y hasta, para el que calcular(ingreso) == objetivo."""
    d = desde
    while d <= hasta:
        if calcular(d) == objetivo:
            return d
        d += UN_DIA
    raise SystemExit(f"No se encontró un ingreso para el objetivo {objetivo}")


def construir(dia1):
    # B[k]: el k-ésimo día hábil después del día 1. B[1] es la primera mañana con alertas.
    B, d = [None], dia1
    while len(B) < 12:
        d += UN_DIA
        if habil(d):
            B.append(d)

    corte_mes = ultimo_habil_del_mes(dia1)
    if corte_mes <= dia1:
        corte_mes = ultimo_habil_del_mes(mas_meses(dia1.replace(day=1), 1))

    hace = lambda dias: dia1 - dt.timedelta(days=dias)
    ind = lambda n: (lambda ing: cumple_meses_el(ing, n))

    def ingreso_indefinido(objetivo, cual):
        def calc(ing):
            e = {"tipo": "Término indefinido", "ingreso": ing}
            return {t: dd for (t, dd, _) in disparos(e)}[cual]
        return buscar_ingreso(objetivo, calc, hace(80), dia1)

    def ingreso_fijo(objetivo, meses_contrato, cual):
        def calc(ing):
            e = {"tipo": "Término fijo", "ingreso": ing, "fin": cumple_meses_el(ing, meses_contrato)}
            return {t: dd for (t, dd, _) in disparos(e)}[cual]
        return buscar_ingreso(objetivo, calc, hace(80), dia1)

    sabado = B[2]
    while sabado.weekday() != 5:
        sabado += UN_DIA

    e = []

    def persona(**k):
        base = dict(tipo_doc="Cédula de ciudadanía", genero="Femenino", nacimiento=dt.date(1990, 5, 10),
                    pais="Colombia", depto="Antioquia", ciudad="Medellín", eps="Sura EPS", pension="Protección",
                    arl="ARL Sura", riesgo="I", caja="Comfama", banco="Bancolombia", bonificacion=0,
                    prorrogas=[], fin=None, estimada=None, segundo_nombre="", segundo_apellido="")
        base.update(k)
        base["expedicion"] = base.get("expedicion") or mas_meses(base["nacimiento"], 12 * 18 + 1)
        e.append(base)
        return base

    # E1 — indefinido: «completó un mes» el B1.
    persona(clave="E1", doc="1037601001", nombre="Laura", segundo_nombre="Camila", apellido="Gómez",
            segundo_apellido="Ruiz", cargo="Auxiliar contable", tipo="Término indefinido",
            ingreso=ingreso_indefinido(B[1], "Periodo de prueba: completó un mes"), salario=1900000, saldo=1.25,
            proposito="Primera alerta de la simulación: completó un mes de periodo de prueba (B1).")
    # E2 — indefinido: «faltan 15 días» el B2.
    persona(clave="E2", doc="1037601002", nombre="Andrés", apellido="Restrepo", segundo_apellido="Mejía",
            genero="Masculino", cargo="Analista de compras", tipo="Término indefinido",
            ingreso=ingreso_indefinido(B[2], "Periodo de prueba: faltan 15 días"), salario=3100000, saldo=1.25,
            proposito="Aviso de 15 días antes del fin del periodo de prueba (B2).")
    # E3 — indefinido: víspera el B3.
    persona(clave="E3", doc="1037601003", nombre="Valentina", apellido="Ospina", segundo_apellido="Cano",
            cargo="Ejecutiva comercial", tipo="Término indefinido",
            ingreso=ingreso_indefinido(B[3], "Periodo de prueba: víspera del último día"), salario=2800000,
            saldo=2.5, bonificacion=300000, proposito="Víspera del último día del periodo de prueba (B3).")
    # E4 — fijo de 6 meses: «faltan dos días» el B2.
    e4_ing = ingreso_fijo(B[2], 6, "Periodo de prueba (término fijo): faltan dos días")
    persona(clave="E4", doc="1037601004", nombre="Juan", segundo_nombre="Esteban", apellido="Zapata",
            segundo_apellido="Henao", genero="Masculino", cargo="Operario de bodega", tipo="Término fijo",
            ingreso=e4_ing, fin=cumple_meses_el(e4_ing, 6), salario=1750905, saldo=0.75, riesgo="III",
            proposito="Periodo de prueba de un término fijo de 6 meses: aviso de dos días (B2).")
    # E5 — fijo de 4 meses: víspera el B4.
    e5_ing = ingreso_fijo(B[4], 4, "Periodo de prueba (término fijo): víspera del último día")
    persona(clave="E5", doc="1037601005", nombre="Daniela", apellido="Arango", segundo_apellido="López",
            cargo="Recepcionista", tipo="Término fijo", ingreso=e5_ing, fin=cumple_meses_el(e5_ing, 4),
            salario=1750905, saldo=0.5, proposito="Periodo de prueba de un término fijo de 4 meses: víspera (B4).")
    # E6 y E7 — fijo de un año. El último día para avisar (fin − 30) es un día hábil L, y el aviso de plazo
    # (L − 2) sale un día hábil ANTES que L: así hay un día para renovar a E7 antes de que llegue el silencio.
    L = B[2]
    while not (habil(L) and B[1] <= habil_anterior_o_igual(L - dt.timedelta(days=2)) < L):
        L += UN_DIA
    fin67 = L + dt.timedelta(days=30)
    for clave, doc, nombre, apellido, genero, proposito in [
        ("E6", "1037601006", "Carlos", "Montoya", "Masculino",
         "Plazo de renovación (B1) y, si nadie hace nada, efecto del silencio dos días después."),
        ("E7", "1037601007", "Paula", "Jaramillo", "Femenino",
         "Igual que E6, pero Angela le registra la RENOVACIÓN el día 2: el efecto del silencio NO debe llegar."),
    ]:
        persona(clave=clave, doc=doc, nombre=nombre, apellido=apellido, segundo_apellido="Duque", genero=genero,
                cargo="Asesor de servicio al cliente", tipo="Término fijo", ingreso=mas_meses(fin67, -12) + UN_DIA,
                fin=fin67, salario=2200000, saldo=10.5, proposito=proposito)
    # E8 — fijo inicial de 4 meses con tres prórrogas de 4 meses: última renovación corta.
    fin8 = B[3] + dt.timedelta(days=32)
    p3 = (mas_meses(fin8 + UN_DIA, -4), fin8)
    p2 = (mas_meses(p3[0], -4), p3[0] - UN_DIA)
    p1 = (mas_meses(p2[0], -4), p2[0] - UN_DIA)
    persona(clave="E8", doc="1037601008", nombre="Sebastián", apellido="Villa", segundo_apellido="Correa",
            genero="Masculino", cargo="Mensajero", tipo="Término fijo", ingreso=mas_meses(p1[0], -4), fin=fin8,
            prorrogas=[p1, p2, p3], salario=1750905, saldo=6.25, riesgo="II",
            proposito="Tres prórrogas inferiores a un año: alerta de «última renovación corta» junto con el plazo "
                      "de renovación (B3), y efecto del silencio dos días después.")
    # E9 — obra o labor con fecha estimada: «faltan dos días» el B3.
    def calc9(ing):
        x = {"tipo": "Obra o labor", "ingreso": ing, "estimada": cumple_meses_el(ing, 8)}
        return {t: dd for (t, dd, _) in disparos(x)}["Periodo de prueba (obra o labor): faltan dos días"]
    e9_ing = buscar_ingreso(B[3], calc9, hace(80), dia1)
    persona(clave="E9", doc="1037601009", nombre="Mateo", apellido="Cardona", segundo_apellido="Ríos",
            genero="Masculino", cargo="Maestro de obra", tipo="Obra o labor", ingreso=e9_ing,
            estimada=cumple_meses_el(e9_ing, 8), salario=2600000, saldo=1.25, riesgo="IV",
            proposito="Obra o labor CON fecha estimada: periodo de prueba sugerido, aviso de dos días (B3) y "
                      "víspera, con la nota de que es una estimación. Aparece también en el listado bimestral.")
    # E10 — obra o labor sin fecha estimada: solo el listado bimestral.
    persona(clave="E10", doc="1037601010", nombre="Luisa", segundo_nombre="Fernanda", apellido="Patiño",
            segundo_apellido="Gil", cargo="Interventora de obra", tipo="Obra o labor", ingreso=dt.date(2024, 3, 4),
            salario=4200000, saldo=8.0, riesgo="IV",
            proposito="Obra o labor SIN fecha estimada: ninguna alerta de prueba; aparece en el listado bimestral "
                      "de obra o labor. Angela la cambia a término indefinido durante la prueba.")
    # E11 — indefinido: la víspera cae en sábado y sale el viernes anterior.
    persona(clave="E11", doc="1037601011", nombre="Natalia", apellido="Henao", segundo_apellido="Giraldo",
            cargo="Diseñadora gráfica", tipo="Término indefinido",
            ingreso=ingreso_indefinido(sabado, "Periodo de prueba: víspera del último día"), salario=3000000,
            saldo=1.25, proposito="La víspera cae en SÁBADO: la alerta sale el viernes anterior y el texto lo dice "
                                  "(«en N días», con la fecha).")
    # E12 — indefinido: «completó un mes» el B5, pero se termina el día 2.
    persona(clave="E12", doc="1037601012", nombre="Felipe", apellido="Ochoa", segundo_apellido="Marín",
            genero="Masculino", cargo="Auxiliar de despachos", tipo="Término indefinido",
            ingreso=ingreso_indefinido(B[5], "Periodo de prueba: completó un mes"), salario=1750905, saldo=0.0,
            proposito="Angela registra su TERMINACIÓN el día 2: su alerta del B5 NO debe llegar (empleado inactivo).")
    # E13 — fijo vencido hace 20 días y sin gestionar: resumen mensual de vencidos.
    fin13 = dia1 - dt.timedelta(days=20)
    persona(clave="E13", doc="1037601013", nombre="Diana", apellido="Bedoya", segundo_apellido="Toro",
            cargo="Auxiliar administrativa", tipo="Término fijo", ingreso=mas_meses(fin13, -6) + UN_DIA, fin=fin13,
            salario=2000000, saldo=7.5,
            proposito="Contrato a término fijo YA VENCIDO sin prórroga ni terminación: sale en el resumen mensual "
                      "«contratos vencidos sin registrar».")
    # E14 — saldo que cruza los 15 días este mes: resumen mensual de acumulación.
    persona(clave="E14", doc="1037601014", nombre="Jorge", segundo_nombre="Iván", apellido="Salazar",
            segundo_apellido="Muñoz", genero="Masculino", cargo="Jefe de logística", tipo="Término indefinido",
            ingreso=dt.date(2019, 2, 18), salario=5200000, saldo=14.95, depto="Bogotá D.C.", ciudad="Bogotá D.C.",
            eps="Sanitas EPS", caja="Compensar", banco="Banco Davivienda",
            proposito="Saldo cargado de 14,95 días: en este mes cruza los 15 y sale en el resumen mensual de "
                      "acumulación de vacaciones.")
    # E15 — cumple un año el mes siguiente al resumen sin haber tomado 6 días hábiles seguidos.
    mes_sig = mas_meses(corte_mes.replace(day=1), 1)
    e15_ing = mas_meses(mes_sig.replace(day=10), -12) + UN_DIA
    persona(clave="E15", doc="1037601015", nombre="Mariana", apellido="Quintero", segundo_apellido="Vargas",
            cargo="Analista de nómina", tipo="Término indefinido", ingreso=e15_ing, salario=3400000, saldo=6.0,
            proposito="Cumple un año el mes siguiente sin haber tomado 6 días hábiles seguidos (solo tomó 3, en el "
                      "histórico): sale en el resumen mensual «vacaciones pendientes».")
    # E16 — control: antigua, sin alertas.
    persona(clave="E16", doc="43887216", nombre="Gloria", segundo_nombre="Inés", apellido="Cárdenas",
            segundo_apellido="Rendón", cargo="Gerente administrativa", tipo="Término indefinido",
            ingreso=dt.date(2014, 8, 4), nacimiento=dt.date(1975, 2, 3), salario=9800000, bonificacion=800000,
            saldo=22.5, proposito="Control: no debe recibir ninguna alerta. Tiene histórico de incapacidad y de "
                                  "aumento.")
    return e, B, corte_mes


# ---------------------------------------------------------------- calendario esperado

def cumplen_anio_el_mes_siguiente(empleados, corte):
    """Quienes cumplen años de servicio el mes siguiente al corte sin 6 días hábiles seguidos de vacaciones.
    En el escenario nadie tiene un periodo de 6 días o más dentro de su último año (E15 tomó solo 3)."""
    desde = mas_meses(corte.replace(day=1), 1)
    hasta = mas_meses(desde, 1) - UN_DIA
    quienes = []
    for x in empleados:
        n = 12
        while cumple_meses_el(x["ingreso"], n) < desde:
            n += 12
        if cumple_meses_el(x["ingreso"], n) <= hasta and x["clave"] != "E16":
            quienes.append(x["clave"])
    return quienes


def calendario(empleados, dia1, B, corte_mes, horizonte):
    filas = []
    for x in empleados:
        for tipo, disparo, hecho in disparos(x):
            envio = habil_anterior_o_igual(disparo)
            if dia1 < envio <= horizonte:
                filas.append({"envio": envio, "disparo": disparo, "hecho": hecho, "tipo": tipo,
                              "quien": x["clave"], "doc": x["doc"]})
    mes = corte_mes
    while mes <= horizonte:
        # Vencidos: todo término fijo cuya fecha de fin ya pasó (si nadie lo renovó ni lo terminó).
        vencidos = [x["clave"] for x in sorted(empleados, key=lambda y: y["fin"] or dt.date.max)
                    if x["tipo"] == "Término fijo" and x["fin"] < mes]
        resumenes = [("Resumen mensual: contratos vencidos sin registrar", vencidos),
                     # E14 es la única que cruza los 15 días, y solo en el primer cierre: los demás saldos
                     # están lejos de 15.
                     ("Resumen mensual: vacaciones acumuladas (completó 15 días)", ["E14"] if mes == corte_mes else []),
                     ("Resumen mensual: vacaciones pendientes (cumple un año)",
                      cumplen_anio_el_mes_siguiente(empleados, mes))]
        if mes.month % 2 == 1:
            resumenes.append(("Resumen bimestral: contratos de obra o labor activos", ["E10", "E9"]))
        # Un resumen sin nadie no se envía.
        resumenes = [(t, q) for (t, q) in resumenes if q]
        for tipo, quienes in resumenes:
            filas.append({"envio": mes, "disparo": mes, "hecho": mes, "tipo": tipo, "quien": ",".join(quienes),
                          "doc": ""})
        mes = ultimo_habil_del_mes(mas_meses(mes.replace(day=1), 1))
    filas.sort(key=lambda f: (f["envio"], f["quien"], f["tipo"]))
    return filas


# ---------------------------------------------------------------- Excel

def escribir(plantilla, salida, hoja, filas):
    shutil.copyfile(plantilla, salida)
    wb = openpyxl.load_workbook(salida)
    ws = wb[hoja]
    titulos = {c.value: c.column for c in ws[1] if c.value}
    for r in range(2, ws.max_row + 1):
        for c in range(1, ws.max_column + 1):
            ws.cell(r, c).value = None
    for i, fila in enumerate(filas, start=2):
        for titulo, valor in fila.items():
            if titulo not in titulos:
                raise SystemExit(f"La plantilla {plantilla} no tiene la columna «{titulo}»")
            celda = ws.cell(i, titulos[titulo])
            celda.value = valor
            if isinstance(valor, dt.date):
                celda.number_format = "DD/MM/YYYY"
    wb.save(salida)


def fila_empleado(x):
    f = {
        "Tipo de documento": x["tipo_doc"], "Número de documento": x["doc"], "Primer nombre": x["nombre"],
        "Segundo nombre": x["segundo_nombre"] or None, "Primer apellido": x["apellido"],
        "Segundo apellido": x["segundo_apellido"] or None, "Género": x["genero"],
        "Fecha de nacimiento": x["nacimiento"], "País de nacimiento": x["pais"],
        "Departamento de nacimiento": x["depto"], "Ciudad de nacimiento": x["ciudad"],
        "Fecha de expedición del documento": x["expedicion"], "País de expedición del documento": "Colombia",
        "Departamento de expedición del documento": x["depto"], "Ciudad de expedición del documento": x["ciudad"],
        "Dirección de residencia": f"Calle {int(x['doc'][-2:]) + 10} # {int(x['doc'][-2:]) + 40}-15",
        "Departamento de residencia": x["depto"], "Ciudad de residencia": x["ciudad"],
        "Celular": "30" + x["doc"][-8:], "Correo electrónico": f"{x['nombre'].lower()}.{x['clave'].lower()}@example.com",
        "EPS": x["eps"], "Fondo de pensión": x["pension"], "ARL": x["arl"], "Nivel de riesgo": x["riesgo"],
        "Caja de compensación": x["caja"], "Cargo": x["cargo"], "Tipo de contrato": x["tipo"],
        "Fecha de ingreso": x["ingreso"], "Fecha de finalización del contrato": x["fin"],
        "Salario mensual": x["salario"], "Bonificación mensual": x["bonificacion"], "Entidad bancaria": x["banco"],
        "Días de vacaciones disponibles hoy": x["saldo"], "Fecha de finalización estimada": x["estimada"],
    }
    for n, (ini, fin) in enumerate(x["prorrogas"], start=1):
        f[f"Fecha de inicio de la prórroga {n}"] = ini
        f[f"Fecha de fin de la prórroga {n}"] = fin
    for clave in ["Celular"]:
        f[clave] = f[clave][:10]
    return f


# Cada error deliberado: (fila del Excel, qué se rompe, cómo se rompe).
def con_errores(filas):
    malas = [copy.deepcopy(f) for f in filas]
    errores = []

    def romper(i, columna, valor, motivo):
        malas[i][columna] = valor
        errores.append({"fila": i + 2, "columna": columna, "motivo": motivo})

    romper(0, "Tipo de documento", "Cédula", "Tipo de documento que no está en la lista")
    romper(1, "EPS", "Sura", "EPS escrita distinto del catálogo («Sura» en vez de «Sura EPS»)")
    romper(2, "Fecha de ingreso", "31/02/2026", "Fecha imposible, escrita como texto")
    romper(3, "Fecha de finalización del contrato", None, "Término fijo sin fecha de finalización")
    romper(4, "Primer apellido", None, "Falta un dato obligatorio (primer apellido)")
    romper(5, "Número de documento", filas[0]["Número de documento"], "Número de documento repetido en el archivo")
    romper(6, "Días de vacaciones disponibles hoy", None, "Falta el saldo de vacaciones (el dato más importante)")
    romper(7, "Fecha de fin de la prórroga 3", malas[7]["Fecha de fin de la prórroga 3"] - dt.timedelta(days=5),
           "La última prórroga no termina el mismo día que la fecha de finalización del contrato")
    romper(9, "Fecha de finalización del contrato", dt.date(2027, 1, 31),
           "Obra o labor con fecha de finalización de contrato (solo aplica a término fijo)")
    romper(10, "Correo electrónico", "natalia.henao@", "Correo con formato inválido")
    romper(11, "Ciudad de residencia", "Medallo", "Ciudad que no existe en el departamento")
    romper(12, "Tipo de contrato", "Prestación de servicios", "Tipo de contrato que el sistema no maneja (y por eso también avisa que la fecha de finalización solo va en término fijo)")
    return malas, errores


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--inicio", required=True, help="Día 1: AAAA-MM-DD, el día en que se crea la empresa y se carga")
    ap.add_argument("--plantillas", required=True)
    ap.add_argument("--salida", required=True)
    ap.add_argument("--dias", type=int, default=35, help="Horizonte del calendario, en días")
    a = ap.parse_args()

    dia1 = dt.date.fromisoformat(a.inicio)
    if not habil(dia1):
        raise SystemExit("El día 1 debe ser un día hábil (lunes a viernes, no festivo).")
    os.makedirs(a.salida, exist_ok=True)
    empleados, B, corte_mes = construir(dia1)
    horizonte = max(dia1 + dt.timedelta(days=a.dias), corte_mes)

    p = lambda nombre: os.path.join(a.plantillas, nombre)
    o = lambda nombre: os.path.join(a.salida, nombre)
    filas = [fila_empleado(x) for x in empleados]
    escribir(p("1 - Empleados actuales (obligatoria).xlsx"), o("1 - Empleados actuales (CORRECTA).xlsx"),
             "Empleados", filas)
    malas, errores = con_errores(filas)
    escribir(p("1 - Empleados actuales (obligatoria).xlsx"), o("1 - Empleados actuales (CON ERRORES).xlsx"),
             "Empleados", malas)

    por = {x["clave"]: x for x in empleados}
    escribir(p("3 - Historico de incapacidades (opcional).xlsx"), o("3 - Historico de incapacidades.xlsx"),
             "Incapacidades", [
                 {"Número de documento": por["E16"]["doc"], "Fecha de inicio": dt.date(2025, 9, 8),
                  "Fecha de terminación": dt.date(2025, 9, 12), "Código de diagnóstico": "J06.9",
                  "¿Es prórroga?": "No", "EPS que la expide": "Sura EPS", "Origen": "Común"},
                 {"Número de documento": por["E14"]["doc"], "Fecha de inicio": dt.date(2026, 3, 2),
                  "Fecha de terminación": dt.date(2026, 3, 16), "Código de diagnóstico": "S82.0",
                  "¿Es prórroga?": "No", "EPS que la expide": "Sanitas EPS", "Origen": "Laboral"},
             ])
    e15 = por["E15"]["ingreso"]
    vac15 = mas_meses(e15, 4)
    while vac15.weekday() != 1 or vac15 in FESTIVOS:   # un martes
        vac15 += UN_DIA
    escribir(p("4 - Historico de vacaciones (opcional).xlsx"), o("4 - Historico de vacaciones.xlsx"),
             "Vacaciones", [
                 {"Número de documento": por["E15"]["doc"], "Día inicial de vacaciones": vac15,
                  "Día final de vacaciones": vac15 + dt.timedelta(days=2)},
                 {"Número de documento": por["E16"]["doc"], "Día inicial de vacaciones": dt.date(2025, 12, 22),
                  "Día final de vacaciones": dt.date(2026, 1, 9)},
             ])
    escribir(p("6 - Historico de aumentos y promociones (opcional).xlsx"),
             o("6 - Historico de aumentos y promociones.xlsx"), "Movimientos salariales", [
                 {"Número de documento": por["E16"]["doc"], "Tipo de movimiento": "Aumento salarial",
                  "Fecha de efectividad": dt.date(2026, 1, 1), "Cargo anterior": "Gerente administrativa",
                  "Cargo nuevo": "Gerente administrativa", "Salario anterior": 9200000, "Salario nuevo": 9800000},
                 {"Número de documento": por["E14"]["doc"], "Tipo de movimiento": "Promoción",
                  "Fecha de efectividad": dt.date(2024, 7, 1), "Cargo anterior": "Coordinador de logística",
                  "Cargo nuevo": "Jefe de logística", "Salario anterior": 4300000, "Salario nuevo": 5200000},
             ])

    cal = calendario(empleados, dia1, B, corte_mes, horizonte)

    def jornada(d):
        """Jornada 1 es el día de la carga; cada día hábil siguiente suma una."""
        n, x = 1, dia1
        while x < d:
            x += UN_DIA
            n += habil(x)
        return n

    with open(o("calendario_esperado.md"), "w", encoding="utf-8") as f:
        f.write(f"# Calendario esperado de alertas\n\nDía 1 (creación y carga): **{larga(dia1)}**. "
                f"Las alertas salen a las 6:00 a. m. (hora de Bogotá) y llegan al correo del superadministrador.\n\n")
        f.write("## Empleados del escenario\n\n| Clave | Documento | Nombre | Contrato | Ingreso | Fin | "
                "Fin estimado | Fin del periodo de prueba | Para qué está |\n|---|---|---|---|---|---|---|---|---|\n")
        for x in empleados:
            fp = fin_prueba(x)
            f.write(f"| {x['clave']} | {x['doc']} | {x['nombre']} {x['apellido']} | {x['tipo']} | {corta(x['ingreso'])} | "
                    f"{corta(x['fin']) if x['fin'] else '—'} | {corta(x['estimada']) if x['estimada'] else '—'} | "
                    f"{corta(fp) if fp else '—'} | {x['proposito']} |\n")
        f.write("\n## Alertas, día por día\n\n| Llega el | Jornada | Quién | Alerta | Fecha de la que "
                "habla |\n|---|---|---|---|---|\n")
        for c in cal:
            nota = "" if c["disparo"] == c["envio"] else f" (adelantada desde el {larga(c['disparo'])})"
            f.write(f"| {larga(c['envio'])} | {jornada(c['envio'])} | {c['quien']} | {c['tipo']}{nota} | "
                    f"{corta(c['hecho'])} |\n")
        f.write("\nDías hábiles sin ninguna alerta: no llega correo, y eso también es correcto.\n")
        f.write("\n## Errores que debe reportar el archivo CON ERRORES\n\n| Fila | Columna | Error sembrado |\n"
                "|---|---|---|\n")
        for er in errores:
            f.write(f"| {er['fila']} | {er['columna']} | {er['motivo']} |\n")

    with open(o("escenario.json"), "w", encoding="utf-8") as f:
        json.dump({
            "dia1": dia1.isoformat(),
            "empleados": [{k: (v.isoformat() if isinstance(v, dt.date) else v) for k, v in x.items()
                           if k != "prorrogas"} | {"prorrogas": [[i.isoformat(), j.isoformat()] for i, j in x["prorrogas"]]}
                          for x in empleados],
            "alertas": [{k: (v.isoformat() if isinstance(v, dt.date) else v) for k, v in c.items()} for c in cal],
            "errores": errores,
        }, f, ensure_ascii=False, indent=2)

    print(f"Listo. Día 1: {larga(dia1)}. {len(cal)} alertas esperadas hasta el {larga(horizonte)}.")
    print(f"Archivos en {a.salida}")


if __name__ == "__main__":
    main()
