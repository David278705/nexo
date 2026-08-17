---
name: crear-modulo
description: Construye un módulo CRUD completo del sistema siguiendo el patrón compartido - migración, modelo con aislamiento, política, controlador, páginas Vue de listado y formulario, exportación y pruebas. Úsalo cuando la tarea sea crear o completar un módulo como empleados, contratos, incapacidades, vacaciones, licencias, movimientos salariales o terminaciones.
---

# Crear un módulo

Los siete módulos del sistema se comportan igual. Construir el segundo debe parecerse mucho a
construir el primero; si no se parece, algo se está haciendo a mano que debería estar compartido.

## Orden

### 1. Migración
- `tenant_id` indexado, más los índices compuestos de las consultas frecuentes.
- Borrado lógico (`softDeletes`).
- Activa RLS y su política **en la misma migración**.
- Nombres en español, plural, guión bajo.

### 2. Modelo
- Trait `PerteneceATenant`.
- `SoftDeletes`.
- Casts de fechas a `date` (sin hora) y de dinero a decimal de precisión fija.
- Relaciones. Recuerda: las novedades cuelgan de `Vinculacion`, no de `Persona`.
- Registra su observador de auditoría.

### 3. Política de autorización
Verificada en el servidor. Un usuario sin el módulo asignado recibe 403, no solo deja de ver el menú.

### 4. Controlador
- `index` devuelve datos paginados con los filtros del módulo aplicados en el servidor.
- `store` y `update` validan con Form Request. Validación en servidor **siempre**, aunque Vue valide.
- `destroy` es borrado lógico.
- Nada de lógica de cálculo aquí: eso vive en `app/Dominio/`.

### 5. Páginas Vue
- `Pages/<Modulo>/Index.vue` — usa `TablaDatos` y `BarraFiltros`. No reescribas la tabla.
- El registro usa el flujo compartido: botón Nuevo → modal de documento → validación al salir del
  campo → modal grande con ficha del empleado y formulario.
- Estado vacío con su texto propio, tomado de `docs/03-diseno-e-interfaz.md` sección 6.3.

### 6. Exportación
Respeta los filtros activos. Incluye documento, nombre completo y cargo además de los campos propios.

### 7. Pruebas
- Un caso nuevo en `tests/Feature/AislamientoTest.php`.
- CRUD completo.
- Permisos: usuario sin acceso recibe 403.
- Validaciones: documento inexistente, documento de empleado inactivo, fechas incoherentes.

## Antes de dar por terminado

- [ ] `composer verificar` en verde
- [ ] El módulo se ve igual que los demás; nada se salió del patrón
- [ ] Ningún color literal en los componentes
- [ ] La exportación trae nombre y cargo
- [ ] El caso de aislamiento está escrito y pasa
