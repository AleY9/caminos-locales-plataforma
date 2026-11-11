=== Caminos Locales Plataforma ===
Versión: 1.1.14 · Requiere: WordPress 6.4+, PHP 8.0+, Advanced Custom Fields, Bricks

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
1.1.14
* Ayudas del panel (includes/panel-ayuda.php): el «?» con que empiezan los desplegables de ayuda pasa a un
  ícono en círculo (span .cl-ayuda; el estilo está en el CSS de las páginas del panel, en Bricks). La guía rápida
  de «¿Qué ofrecés?» (clhgui) se ve abierta si todavía no hay nada marcado.
* Arreglo: los botones de opción de Bricks se llaman form-field-ID[]; los selectores de clsalc («¿Hasta dónde
  llegás?» → kilómetros) y clfcom (ficha compacta → ocultar Temas) ahora los encuentran.
* Se quita el script de 1.1.13 que mostraba u ocultaba clfpub (no funcionaba; lo hace el CSS de la página 28262).

1.1.13
* Emprendimiento sin página propia (includes/ficha-publica.php): para quien solo ofrece alojamiento y no quiere
  mostrar más que sus cabañas. Se elige en «¿Qué ofrecés?» (campo clfpub → ficha_publica, Sí / No; la pregunta
  se ve solo con Alojamiento y sin Actividades). Con «No»: la dirección del emprendimiento lleva a su primer
  alojamiento (302; si no tiene, 404; la red la sigue viendo), sale de los listados de Emprendimientos e Inicio
  (meta _cl_sin_pagina), no se indexa, {echo:cl_emprendimiento_url} queda vacío y el progreso del panel pide
  solo nombre, localidad, mapa y contacto. Nuevo {echo:cl_emprendimiento_enlace}: el nombre del emprendimiento,
  con enlace solo si tiene página (para la ficha del alojamiento).

1.1.12
* Avisos de cambios: lo importante sigue llegando al momento (fichas nuevas publicadas, ocultar y borrar, altas
  por invitación, cambios de nombre o email del emprendimiento). Los cambios en fichas ya publicadas, volver a
  publicar y duplicar se juntan en un resumen diario a las 20 h (o semanal, o al momento: Herramientas →
  Caminos Locales → «Avisos de cambios», con botón «Mandar el resumen ahora»).

1.1.11
* «Especial para» (Parejas · Familias · Grupos): etiqueta opcional en alojamientos y experiencias. Se marca en
  el panel («Qué tiene» del alojamiento y el formulario de la experiencia, campos clespe / clevep con opciones
  parejas:Parejas…, más un oculto clespx / clevex), filtra en los listados y la ficha la muestra con
  {echo:cl_especial_texto}. No cuenta en el progreso.
* La característica «Ideal para parejas» pasa a «Especial para: Parejas» (una vez) y deja de ofrecerse en «Qué tiene».

1.1.10
* Mi panel, bloque de carga asistida (solo administradores, [cl_panel_admin_emp]): «+ Nueva ficha» crea un
  emprendimiento en borrador a nombre del administrador y abre el panel para completarlo; «Invitar al dueño»
  crea el enlace ligado a esa ficha y lo muestra con «Mandar por WhatsApp»; si la ficha ya es de un emprendedor,
  muestra quién es. Desplegable «Cómo cargar una ficha por alguien» con el procedimiento.

1.1.9
* Invitación para una ficha ya cargada (carga asistida): en Herramientas → Caminos Locales se elige la ficha
  al crear el enlace. Quien lo usa crea su cuenta y la ficha pasa a ser suya, con sus alojamientos,
  experiencias y fotos; no se crea otro emprendimiento. Email y WhatsApp solo se completan si faltaban.
  Mensaje de WhatsApp propio. {echo:cl_inv_emp} completa el nombre del emprendimiento en el formulario.

1.1.8
* «¿Hasta dónde llegás?»: «En toda la región» pasa a «En mi región». Las fichas que lo tenían se
  actualizan solas una vez (opción clp_alcance_118). En Bricks, cambiar la opción del campo clsalc.

1.1.7
* Zona de los servicios: «Aiguá · región Este» y «Región Este» (sin «toda la»).

1.1.6
* Ficha compacta: «Imagen extra (opcional)», vertical 4:5 (afiche o foto), campo ACF imagen_extra
  (field_cl_srv_extra, grupo group_cl_servicio). En el panel, segunda tarjeta del paso Fotos.
* Se puede publicar con la foto principal o con la imagen extra; en una ficha publicada no se puede quitar
  la que queda sola.
* En Bricks: la ventana muestra la imagen antes del mapa (con lightbox); las tarjetas de Servicios locales y
  Prestadores la usan si no hay foto principal.

1.1.5
* {echo:cl_srv_tel} con formato (+598 99 247 249) en las tarjetas de Prestadores.

1.1.4
* «¿Qué ofrecés?»: el admin ve y marca los rubros curados («… (curado, solo admin)», antes de «Otro»).
* {echo:cl_srv_curado}: '1' si la ficha tiene un rubro curado. En Bricks, las tarjetas de Servicios locales y
  Prestadores llevan data-srv-curado y se pintan en verde claro.

1.1.3
* Rubros curados (cl_srv_rubros_curados): «Servicios para emprendimientos» (slug servicios-para-emprendimientos).
  No aparece en «¿Qué ofrecés?», se asigna desde wp-admin y el panel no lo borra al guardar. En el directorio
  público su filtro solo se ve para la red. Ícono propio (pantalla).

1.1.2
* «Ver cómo queda»: «Ocultar de la web» para una ficha publicada (vuelve a borrador, doble toque para
  confirmar, aviso al admin). Se vuelve a publicar con «Publicar» desde Mi panel.
* Servicios locales: al final de los filtros de tipo, «Alojamientos →» y «Experiencias →» (borde punteado):
  no filtran, llevan a esos listados.

1.1.1
* Mapa desde el celular: además del código de «Insertar un mapa», se acepta el enlace de Compartir →
  Copiar enlace de Google Maps (maps.app.goo.gl o google.com/maps/place). Se guarda como mapa con el pin.
* Mi panel: botón «Publicar» junto al chip «Borrador» (cl_pi('puede_publicar'), cl_pi('url_publicar'),
  cl_pi('txt_publicar')). Si falta algo obligatorio, lleva a «Ver cómo queda» con lo que falta.
* «Qué ofrecés»: con Alojamiento o Actividades se avisa que la ficha es completa (antes el selector
  desaparecía sin explicación).
* Tira de pasos del emprendimiento: en el celular arranca mostrando el paso actual.

1.1.0
* Invitaciones de un solo uso (Herramientas → Caminos Locales → «Invitar a sumarse»): enlace por WhatsApp,
  vence a los 14 días y sirve una vez. Alta con nombre, emprendimiento, email, WhatsApp y contraseña repetida;
  entra directo al panel con el emprendimiento en borrador y avisa al admin.
* Servicios locales: el rubro decide si es servicio; ficha compacta o completa a elección; zona («¿Hasta dónde
  llegás?»), si se muestra al público y si se publica el teléfono. Directorio /servicios-locales/ con filtros de
  zona y tipo (íconos), ventana para las fichas compactas y lista de prestadores en el panel. Las fichas compactas
  no salen en los listados de turismo.
* Panel: «Qué ofrecés» pasa a ser el primer paso; la ficha compacta pide solo lo que se muestra (una foto o logo,
  descripción corta, lugar fijo opcional con mapa, idiomas).
* «Invitaciones y servicios (1.1.0)» en Herramientas → Caminos Locales: pasa del staging las páginas y plantillas
  nuevas, con «Revisar» primero y «Volver atrás».

1.0.3
* «Diseño nuevo»: reconoce también la dirección del sitio codificada en los enlaces (por ejemplo, el acceso con
  ?redirect_to=…), así la cabecera ya no figura como «cambió en este sitio».

1.0.2
* «¿Qué hay en tu lugar?» en «Cómo es llegar y estar» del panel: agua potable, sombra, lugar para sentarse, lugar para comer,
  electricidad, señal de celular, wifi y estacionamiento (opcional, no cuenta en el progreso). Se guarda en el campo ACF en_el_lugar.
* Ficha de alojamiento: botón «Escribir por WhatsApp» (como en experiencias).
* «Diseño nuevo (noviembre 2026)» en Herramientas → Caminos Locales: pasa del staging la paleta, las letras, las plantillas y
  las páginas de Bricks, sin tocar lo que cambió en producción después de la copia. Con «Revisar» primero y «Volver atrás».

1.0.1
* Los emprendedores publican sin revisión: «Enviar a revisión» pasa a «Publicar» en la ficha, los alojamientos
  y las experiencias; el cambio de nombre y de email se aplica directo. Al admin le llega un correo con cada
  publicación y cada cambio, como antes.
* Ajuste «Revisar antes de publicar» en Herramientas → Caminos Locales (apagado), para volver al flujo con revisión.
* Lo que estaba «En revisión» se puede publicar desde el panel.

1.0.0
* Primera versión: los 16 snippets «CL – …» pasan a ser módulos del plugin, con el mismo código.
* Diagnóstico en Herramientas → Caminos Locales (módulos, dependencias, seguridad, caché).
* Actualizaciones desde GitHub (AleY9/caminos-locales-plataforma).
