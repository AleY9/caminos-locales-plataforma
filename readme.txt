=== Caminos Locales Plataforma ===
Versión: 1.0.0 · Requiere: WordPress 6.4+, PHP 8.0+, Advanced Custom Fields, Bricks

Panel del emprendedor, fichas y funciones propias de caminoslocales.com. Reemplaza a los snippets
«CL – …» de Code Snippets, con el mismo código: los emprendedores no notan nada.

== Instalar (o pasar de snippets a plugin) ==
1. Backup (JetBackup).
2. Plugins → Añadir nuevo → Subir plugin → caminos-locales-plataforma.zip → Activar.
   Mientras los snippets sigan activos, el plugin no carga nada (aviso arriba en wp-admin): así nunca
   se repiten funciones ni da error crítico.
3. Code Snippets: desactivar los 16 snippets de la lista de abajo, uno atrás del otro. Al desactivarlos
   todos, el plugin toma su lugar en el pedido siguiente.
4. Herramientas → Caminos Locales: que no quede nada para arreglar.
5. Plugins → «Activar las actualizaciones automáticas».
6. Probar: una ficha de emprendimiento, una de alojamiento, una de experiencia y /panel/.
   Si algo falla: desactivar el plugin y volver a activar los snippets.

== Qué trae (cada módulo era un snippet) ==
modulos/capacidad-mcp-acf.php                (13) Capacidad MCP para escribir campos ACF
modulos/email-emprendimiento-alojamiento.php (14) Email del emprendimiento en alojamientos
modulos/fichas.php                           (15) CL – Fichas: campos ACF y funciones para Bricks
modulos/roles.php                            (16) CL – Roles del back office
modulos/subidas-fotos.php                    (18) CL – Subidas de fotos optimizadas
modulos/carpetas-medios.php                  (19) CL – Carpetas de medios
modulos/panel.php                            (20) CL – Panel
modulos/panel-fotos.php                      (21) CL – Panel: Fotos
modulos/panel-alojamientos.php               (22) CL – Panel: Alojamientos
modulos/panel-eventos.php                    (23) CL – Panel: Eventos (experiencias)
modulos/panel-cuenta.php                     (24) CL – Panel: Cuenta
modulos/redirecciones-sumate.php             (25) CL – Redirecciones de Sumate
modulos/equipamiento-heredado.php            (27) CL – Equipamiento heredado
modulos/capacidad-terminos.php               (28) CL – Capacidad: términos
modulos/fotos-grupo.php                      (29) CL – Fotos Grupo ({cl_fotos:grupo})
modulos/admin-donde-ocurre.php               (30) CL – Admin: condiciones según «¿Dónde ocurre?» (solo wp-admin)

Las funciones siguen con el prefijo cl_: Bricks no cambia.

== Seguridad ==
* El Diagnóstico avisa si el sitio de producción tiene activa la ejecución de PHP por MCP
  (BRICKS_ENABLE_PHP_ABILITIES): eso va solo en el staging.
* El staging nunca se pasa a producción («Push to Live»): pisaría lo que cargan los emprendedores.
  Los cambios llegan a producción como versión nueva del plugin.

== Cambios ==
1.0.0
* Primera versión: los 16 snippets «CL – …» pasan a ser módulos del plugin, con el mismo código.
* Diagnóstico en Herramientas → Caminos Locales (módulos, dependencias, seguridad, caché).
* Actualizaciones desde GitHub (AleY9/caminos-locales-plataforma).
