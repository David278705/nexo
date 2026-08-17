---
paths:
  - "resources/js/**"
  - "resources/css/**"
  - "resources/views/**"
---

# Interfaz

El objetivo declarado del proyecto es que el producto **no parezca hecho por una sola persona en sus
ratos libres**. Eso se consigue con consistencia, no con adornos.

Antes de construir una pantalla, abre `prototipos/sistema.html` (o `panel-proveedor.html`) y mira
cómo se ve. El detalle está en `docs/03-diseno-e-interfaz.md`.

## Cómo se conecta con el servidor

Inertia, no una API. Los datos llegan como props desde el controlador de Laravel:

```php
return Inertia::render('Empleados/Index', [
    'empleados' => $empleados,
    'filtros'   => $filtros,
]);
```

- Navegación con `<Link>` de Inertia. **Nada de Vue Router.**
- Formularios con `useForm` de Inertia, enviando a rutas normales de Laravel.
- **Nada de `axios` ni `fetch` para traer datos de pantalla.** Si necesitas refrescar, usa
  `router.reload({ only: [...] })`.
- El filtrado, el ordenamiento y la paginación se resuelven en el servidor y vuelven como props.
  No traigas la tabla entera para filtrarla en el navegador.

## Sistema de diseño

Definido como variables CSS en `resources/css/app.css`. **Nunca escribas un color literal en un componente.**

| Uso | Valor |
|---|---|
| Marca y navegación | `#123A5C` |
| Acción (único azul clicable) | `#1B6EC2` |
| Éxito | `#157347` · Advertencia `#B45309` · Error `#B02A37` |
| Texto | `#111827` principal · `#4B5563` secundario · `#9CA3AF` terciario |
| Bordes `#E5E7EB` · Fondo `#F7F8FA` | |

Tipografía Inter. Espaciados múltiplos de 4. Radio 6px, 8px en modales. Ancho máximo 1280px.

Dos reglas de color: el **rojo solo cuando algo está mal de verdad**, y **nada que no sea interactivo
se pinta del azul de acción**.

## Componentes compartidos

Se construyen una vez en `resources/js/Components/` y las siete pantallas de listado los reutilizan.
Si estás escribiendo por segunda vez el mismo bloque de tabla o de filtros, detente y extráelo.

`TablaDatos` · `BarraFiltros` · `Modal` · `CampoTexto` · `CampoFecha` · `Selector` (con búsqueda si
tiene más de diez opciones) · `Etiqueta` de estado · `Aviso` · `EstadoVacio` · `TarjetaIndicador` ·
`Notificacion`.

## Comportamiento obligatorio

- **Tablas**: encabezado fijo al hacer scroll, filas de 44px, cifras a la derecha, fila completa
  clicable, paginación de 25. Los filtros aplicados se ven como etiquetas removibles.
- **Exportar** respeta los filtros y lo dice: «Exportar 43 registros filtrados». Toda exportación de
  novedades incluye documento, nombre completo y cargo.
- **Formularios**: se valida al salir del campo, no al guardar. Se marcan los campos *opcionales*,
  no los obligatorios. El botón de guardar se deshabilita mientras envía.
- **Registro de novedad**: siempre el mismo flujo. Botón Nuevo → modal pequeño que pide solo el
  documento → validación al salir del campo → modal grande con ficha del empleado arriba y formulario
  debajo. Idéntico en los cinco módulos.
- **Acciones destructivas**: la confirmación explica la consecuencia calculada, no pregunta
  «¿está seguro?». Ejemplo: «Se devolverán 8 días hábiles. El saldo pasará de 17,25 a 25,25 días».
- **Estados vacíos**: nunca «No hay datos». Título, una línea de explicación y el botón de la
  primera acción. Los textos exactos están en el documento 3, sección 6.3.
- **Toda referencia temporal lleva la fecha entre paréntesis**: «Mañana (14 de junio)».

## Accesibilidad

Contraste mínimo 4,5:1. El color nunca es el único portador de información. Todo recorrible con
teclado y con foco visible. Etiquetas reales, no solo *placeholder*. Funciona al 200% de zoom.

## Móvil

Hasta 1024px: barra lateral colapsada, tablas en formato tarjeta con los tres campos más importantes,
modales a pantalla completa.
