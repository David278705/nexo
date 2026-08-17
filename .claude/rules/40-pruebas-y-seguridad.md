---
paths:
  - "tests/**"
  - "app/Http/**"
  - "app/Policies/**"
  - "app/Jobs/**"
  - "app/Console/**"
---

# Pruebas y seguridad

Las reglas escritas son sugerencias; las pruebas son la defensa real. Todo lo que importa de verdad
en este proyecto tiene que estar cubierto por una prueba que se ponga en rojo si alguien lo rompe.

## Qué se prueba siempre

| Área | Prueba |
|---|---|
| Aislamiento entre empresas | `tests/Feature/AislamientoTest.php`. Un caso por tabla de negocio, intentando leer datos ajenos por modelo, ruta, exportación y búsqueda. |
| Motor de cálculo | `tests/Unit/Calculo/`. Los diez casos del documento 2, sección 4.2. |
| Permisos por módulo | Un usuario sin acceso a un módulo recibe 403 del servidor, no solo deja de ver el menú. |
| Alertas | Idempotencia (ejecutar dos veces no duplica), corrimiento al día hábil anterior, y que un empleado inactivo no genera ninguna. |
| Importación | Una plantilla con errores deliberados los reporta por número de fila y no escribe nada. |
| Auditoría | Crear, editar y eliminar dejan registro sin que nadie lo escriba a mano. |

## Seguridad mínima

- HTTPS forzado. Contraseñas con hashing moderno. Segundo factor obligatorio para `owner`.
- **Autorización verificada en el servidor en cada petición.** Ocultar una opción del menú no es un
  control de acceso.
- El campo `codigo_diagnostico` es **dato sensible**: solo visible para usuarios con permiso sobre
  novedades, y en el dashboard solo de forma agregada.
- Secretos fuera del código y del repositorio.
- Validación en el servidor siempre, aunque el componente Vue ya valide.

## Cómo escribir las pruebas

- Pest para el servidor, Vitest para componentes Vue con lógica propia.
- Factories para todo. Nada de datos escritos a mano dentro de la prueba.
- Nombres descriptivos en español: `it('no permite leer empleados de otra empresa')`.
- Una prueba comprueba una cosa.

## La verificación

`composer verificar` corre en este orden y **todo tiene que quedar verde**:

1. `pint --test` — formato de PHP
2. `phpstan analyse` — análisis estático
3. `pest` — pruebas del servidor
4. `npm run build` — que el frontend compile
5. `vitest run` — pruebas de componentes

No des una tarea por terminada con la verificación en rojo. Si no puedes dejarla verde, revierte lo
que rompió, marca la tarea `[!]` y explica el motivo en `ESTADO.md`.
