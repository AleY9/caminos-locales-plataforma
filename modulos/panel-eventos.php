<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 23 «CL – Panel: Eventos» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — Panel: Mis experiencias (fase 4, v1.15, oct. 2026)
 * v1.15: {echo:cl_ev_pi('dato')} para la tarjeta «Mis experiencias» de Mi panel en Bricks (muestra | txt | accion | url),
 *        con singular y plural («1 publicada»). Una experiencia sin «cuándo» elegido dice «Sin fecha» (antes «Todo el año»).
 * v1.14: las direcciones del panel salen de las páginas (IDs 28332 y 28334), no de la URL escrita a mano:
 *        funcionan con /panel/experiencias/ y /panel/experiencia/. Las viejas (/panel/eventos/ y /panel/evento/)
 *        redirigen (301) a las nuevas con los mismos parámetros. {echo:cl_ev_link('fotos')} para los enlaces de Bricks
 *        (datos | fotos | vista | lista). Textos en femenino («Publicada», «ninguna», «pasadas», «la ve»…).
 * v1.13: «Borrar» (a la papelera, con doble toque) en cada tarjeta y en «Más opciones». Las fotos no se tocan.
 * v1.12: botones en cada tarjeta de «Mis experiencias»: Duplicar, Ocultar (con confirmación) y Volver a publicar.
 *        Ocultar y volver a publicar desde la lista vuelven a la lista con el aviso arriba.
 * v1.11: «Ocultar de la web» (publicada → borrador oculto) y «Volver a publicar» sin pasar de nuevo por revisión
 *        (solo experiencias ya aprobadas una vez). «Duplicar»: copia en borrador con textos, categorías, precio e
 *        imágenes, sin fecha. Tarjetas de código en proporción 5:4, como los listados.
 * v1.10: afiche opcional (ACF `afiche`, imagen tipo Instagram) en el paso Fotos. Para enviar a revisión alcanza con
 *        foto principal o afiche. Las tarjetas usan la foto principal; si no hay, el afiche entero sobre su versión
 *        difuminada. {echo:cl_ev_tiene_foto} y {echo:cl_ev_solo_afiche} para las condiciones de Bricks.
 * v1.9: mapa propio para experiencias «En otro lugar» (campo «Mapa (opcional)», clevub → `ubicacion` del evento).
 *       La ficha usa ese mapa en vez del del emprendimiento ({echo:cl_ev_dato('mapa')} y [cl_ev_mapa]).
 *       «Mapa» como sugerencia opcional en «Ver cómo queda» (solo en otro lugar).
 * v1.8: textos del panel y correos: «evento» → «experiencia»; «Agenda» → «Experiencias».
 * v1.7: «¿Dónde ocurre?» (en mi emprendimiento / en otro lugar): «Dónde» solo se pide si es en otro lugar.
 *       Categorías (Formas de andar) editables, plegadas. {echo:cl_ev_donde} muestra el lugar propio si es en otro lugar.
 *       (La sección «Cómo es la experiencia» de la v1.6 se sacó: los detalles van en la descripción.)
 * v1.5: [cl_ev_proximos] también en la ficha del evento ("Otras propuestas", sin el actual); {echo:cl_ev_donde}.
 * v1.4: próximos eventos en 2 columnas en celular.
 * v1.3: [cl_ev_proximos] y {echo:cl_ev_proximos_hay} para la ficha del emprendimiento.
 * v1.2: fecha corta de varios días: "17–19 oct".
 * v1.1: "En una línea" hasta 180 caracteres.
 * Code Snippets: tipo PHP, "Ejecutar en todas partes". Sin <?php.
 * Necesita "CL – Panel" (v14), "CL – Panel: Fotos" y "CL – Fichas".
 *
 * Páginas (el CPT interno sigue siendo `evento`):
 *   Mis experiencias   (página 28332, /panel/experiencias/) → [cl_panel_eventos] (lista + agregar)
 *   Editar experiencia (página 28334, /panel/experiencia/)  → ?ev=ID&paso=datos|fotos|vista — formulario de Bricks
 *                      + [cl_panel_ev_cabecera], [cl_panel_ev_fotos], [cl_panel_ev_vista]. Valores: {echo:cl_ev_valor('campo')}
 *
 * Un evento es "con fecha" (un día o varios; deja de verse en la Agenda cuando pasa) o
 * "todo el año / a coordinar" (siempre visible). Sin fecha = todo el año.
 * - Vinculado fijo al emprendimiento; región, categorías y "Publicación: Evento" se copian de él.
 *   Contacto, mapa y dirección salen del emprendimiento: {echo:cl_ev_dato('telefono')}, [cl_ev_mapa]…
 *   Si es «En otro lugar», el mapa es el propio de la experiencia (si lo cargaron).
 * - Fotos por casilla: principal + galería de 4 (galeria_eventos_foto1…4), sin huecos.
 * - Agenda: la consulta del bucle (elemento gvijrm) muestra solo vigentes, ordenados por fecha
 *   (metas internos _cl_ev_orden y _cl_ev_fin, se calculan solos).
 * - Evento nuevo: borrador → Enviar a revisión → lo publica el admin → email al emprendedor.
 */

define( 'CL_EV_MAX', 30 ); // eventos por emprendimiento (vigentes y pasados en borrador/publicados)
define( 'CL_EV_PAG_LISTA', 28332 );  // Mis experiencias
define( 'CL_EV_PAG_EDITAR', 28334 ); // Editar experiencia

// ---------- Utilidades ----------

function cl_ev_emp_de( $ev ) {
	$rel   = get_post_meta( $ev, 'corresponde_a_emprendimiento', true );
	$first = is_array( $rel ) ? reset( $rel ) : $rel;
	$id    = absint( is_object( $first ) ? $first->ID : $first );
	return ( $id && 'emprendimientos' === get_post_type( $id ) ) ? $id : 0;
}

function cl_ev_de_emp( $emp ) {
	if ( ! $emp ) {
		return array();
	}
	$args = array(
		'post_type'   => 'evento',
		'post_status' => array( 'publish', 'draft', 'pending' ),
		'numberposts' => 60,
		'fields'      => 'ids',
		'meta_key'    => '_cl_ev_orden',
		'orderby'     => 'meta_value_num',
		'order'       => 'ASC',
		'meta_query'  => array( array( 'key' => 'corresponde_a_emprendimiento', 'value' => '"' . (int) $emp . '"', 'compare' => 'LIKE' ) ),
	);
	if ( ! current_user_can( 'manage_options' ) ) {
		$args['author'] = get_current_user_id();
	}
	$ids = get_posts( $args );
	if ( ! $ids ) { // eventos viejos sin meta de orden todavía
		unset( $args['meta_key'] );
		$args['orderby'] = 'title';
		$ids             = get_posts( $args );
	}
	return array_map( 'intval', $ids );
}

function cl_ev_id( $pedido = null ) {
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return 0;
	}
	$id = null !== $pedido ? (int) $pedido : ( isset( $_GET['ev'] ) ? (int) $_GET['ev'] : 0 );
	if ( ! $id || 'evento' !== get_post_type( $id ) || ! in_array( get_post_status( $id ), array( 'publish', 'draft', 'pending' ), true ) ) {
		return 0;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return $id;
	}
	$emp = cl_panel_emp_id();
	return ( $emp && (int) get_post_field( 'post_author', $id ) === get_current_user_id() && cl_ev_emp_de( $id ) === $emp ) ? $id : 0;
}

// Dirección de una página del panel por su ID (sigue andando si se le cambia el slug).
function cl_ev_pag( $pag ) {
	$url = get_permalink( $pag );
	return $url ? $url : home_url( CL_EV_PAG_LISTA === $pag ? '/panel/experiencias/' : '/panel/experiencia/' );
}

function cl_ev_url_lista( $emp = 0 ) {
	return cl_ev_pag( CL_EV_PAG_LISTA ) . ( current_user_can( 'manage_options' ) && $emp ? '?emp=' . (int) $emp : '' );
}

function cl_ev_url( $ev, $paso = 'datos' ) {
	$args = array( 'paso' => $paso, 'ev' => (int) $ev );
	if ( current_user_can( 'manage_options' ) ) {
		$args['emp'] = cl_ev_emp_de( $ev );
	}
	return add_query_arg( $args, cl_ev_pag( CL_EV_PAG_EDITAR ) );
}

// {echo:cl_ev_link('fotos')} — enlaces de los pasos en Bricks: datos | fotos | vista | lista.
function cl_ev_link( $paso = 'datos' ) {
	$id = cl_ev_id();
	if ( 'lista' === $paso ) {
		return esc_url( cl_ev_url_lista( $id ? cl_ev_emp_de( $id ) : cl_panel_emp_id() ) );
	}
	if ( ! $id || ! in_array( $paso, array( 'datos', 'fotos', 'vista' ), true ) ) {
		return esc_url( cl_ev_url_lista( function_exists( 'cl_panel_emp_id' ) ? cl_panel_emp_id() : 0 ) );
	}
	return esc_url( cl_ev_url( $id, $paso ) );
}

function cl_ev_url_vista( $id ) {
	if ( 'publish' === get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	return add_query_arg( array( 'post_type' => 'evento', 'p' => (int) $id, 'cl_vista' => 1 ), home_url( '/' ) );
}

// Direcciones viejas del panel (/panel/eventos/ y /panel/evento/) → las nuevas, con los mismos parámetros.
// Solo actúa si la dirección vieja ya no existe (404), así que no hace nada antes de cambiar el slug.
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore
	$ruta = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
	$mapa = array( 'panel/eventos' => CL_EV_PAG_LISTA, 'panel/evento' => CL_EV_PAG_EDITAR );
	if ( ! isset( $mapa[ $ruta ] ) ) {
		return;
	}
	$dest = get_permalink( $mapa[ $ruta ] );
	if ( ! $dest || trim( (string) wp_parse_url( $dest, PHP_URL_PATH ), '/' ) === $ruta ) {
		return;
	}
	$qs = (string) wp_parse_url( $uri, PHP_URL_QUERY );
	wp_safe_redirect( $dest . ( '' !== $qs ? '?' . $qs : '' ), 301 );
	exit;
}, 0 );

// ---------- Fechas ----------

// "2026-10-17", "17/10/2026" o "20261017" → "20261017", o '' si no es válida.
function cl_ev_fecha_ymd( $v ) {
	$v = trim( (string) $v );
	if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m ) ) {
		list( , $y, $mo, $d ) = $m;
	} elseif ( preg_match( '#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})$#', $v, $m ) ) {
		list( , $d, $mo, $y ) = $m;
		$y = strlen( $y ) === 2 ? '20' . $y : $y;
	} elseif ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $v, $m ) ) {
		list( , $y, $mo, $d ) = $m;
	} else {
		return '';
	}
	return checkdate( (int) $mo, (int) $d, (int) $y ) ? sprintf( '%04d%02d%02d', $y, $mo, $d ) : '';
}

// "19:30", "19.30", "19:30:00" o "19" → "19:30:00", o '' si no es válida.
function cl_ev_hora( $v ) {
	$v = trim( str_replace( array( 'hs', 'h', '.', ' ' ), array( '', '', ':', '' ), strtolower( (string) $v ) ) );
	if ( ! preg_match( '/^(\d{1,2})(?::(\d{2}))?(?::\d{2})?$/', $v, $m ) ) {
		return '';
	}
	$h  = (int) $m[1];
	$mi = isset( $m[2] ) ? (int) $m[2] : 0;
	return ( $h <= 23 && $mi <= 59 ) ? sprintf( '%02d:%02d:00', $h, $mi ) : '';
}

function cl_ev_hoy() {
	return current_time( 'Ymd' );
}

// Datos de fecha del evento.
function cl_ev_fechas( $id ) {
	$ini   = cl_ev_fecha_ymd( get_post_meta( $id, 'fecha_del_evento', true ) );
	$fin   = cl_ev_fecha_ymd( get_post_meta( $id, 'fecha_de_finalizacion', true ) );
	$fin   = ( $ini && $fin && $fin > $ini ) ? $fin : '';
	$rango = trim( (string) get_post_meta( $id, 'rango_fechas', true ) );
	$rango = ( mb_strlen( $rango ) < 3 || 'Consultá' === $rango ) ? '' : $rango; // restos del formulario viejo ("al")
	return array(
		'ini'   => $ini,
		'fin'   => $fin,
		'hi'    => cl_ev_hora( get_post_meta( $id, 'hora_de_inicio', true ) ),
		'hf'    => cl_ev_hora( get_post_meta( $id, 'hora_de_finalizacion', true ) ),
		'rango' => $rango,
		'todo'  => ! $ini,
	);
}

// Modo para el formulario: unico | varios | todo | '' (sin elegir todavía).
function cl_ev_modo( $id ) {
	$f = cl_ev_fechas( $id );
	if ( $f['ini'] ) {
		return $f['fin'] ? 'varios' : 'unico';
	}
	return 'Sí' === get_post_meta( $id, 'periodico', true ) ? 'todo' : '';
}

// true si ya pasó (solo eventos con fecha).
function cl_ev_pasado( $id = 0 ) {
	$id = absint( $id ) ? absint( $id ) : get_the_ID();
	$f  = cl_ev_fechas( $id );
	return $f['ini'] && ( $f['fin'] ? $f['fin'] : $f['ini'] ) < cl_ev_hoy() ? '1' : '';
}

function cl_ev_fmt_dia( $ymd, $corto = false ) {
	$ts = strtotime( $ymd . ' 12:00' );
	$y  = substr( $ymd, 0, 4 ) !== current_time( 'Y' ) ? ' Y' : '';
	if ( $corto ) {
		return date_i18n( 'D j M' . $y, $ts );
	}
	return date_i18n( 'l j \d\e F' . ( $y ? ' \d\e Y' : '' ), $ts );
}

function cl_ev_fmt_hora( $h ) {
	return $h ? substr( $h, 0, 5 ) : '';
}

// {echo:cl_ev_cuando} → "Sábado 17 de octubre · 19:30 a 22:00" / "Del 17 al 19 de octubre" / "Todo el año · …".
function cl_ev_cuando( $id = 0, $corto = false ) {
	$id = absint( $id ) ? absint( $id ) : get_the_ID();
	if ( ! $id || 'evento' !== get_post_type( $id ) ) {
		return '';
	}
	$f = cl_ev_fechas( $id );
	if ( $f['todo'] ) {
		if ( '' === cl_ev_modo( $id ) ) {
			return esc_html( 'Sin fecha' ); // copia o borrador sin «cuándo» elegido
		}
		$t = $corto ? 'Todo el año' : ( $f['rango'] ? $f['rango'] : 'Todo el año · consultá fechas' );
		return esc_html( $t );
	}
	if ( $f['fin'] ) {
		$mismo_mes = substr( $f['ini'], 0, 6 ) === substr( $f['fin'], 0, 6 );
		if ( $corto ) {
			$t = $mismo_mes
				? date_i18n( 'j', strtotime( $f['ini'] ) ) . '–' . date_i18n( 'j M', strtotime( $f['fin'] ) )
				: date_i18n( 'j M', strtotime( $f['ini'] ) ) . ' – ' . date_i18n( 'j M', strtotime( $f['fin'] ) );
		} else {
			$t = 'Del ' . ( $mismo_mes ? date_i18n( 'j', strtotime( $f['ini'] ) ) : date_i18n( 'j \d\e F', strtotime( $f['ini'] ) ) ) . ' al ' . date_i18n( 'j \d\e F', strtotime( $f['fin'] ) );
		}
	} else {
		$t = cl_ev_fmt_dia( $f['ini'], $corto );
	}
	$t = mb_strtoupper( mb_substr( $t, 0, 1 ) ) . mb_substr( $t, 1 );
	if ( $f['hi'] ) {
		$t .= ' · ' . cl_ev_fmt_hora( $f['hi'] ) . ( $f['hf'] && ! $corto ? ' a ' . cl_ev_fmt_hora( $f['hf'] ) : '' );
	}
	if ( $f['rango'] && ! $corto ) {
		$t .= ' · ' . $f['rango'];
	}
	return esc_html( $t );
}

function cl_ev_cuando_corto( $id = 0 ) {
	return cl_ev_cuando( $id, true );
}

// Metas internos para ordenar y filtrar la Agenda.
function cl_ev_meta( $id ) {
	$f = cl_ev_fechas( $id );
	update_post_meta( $id, '_cl_ev_orden', $f['ini'] ? $f['ini'] : '99999999' );
	update_post_meta( $id, '_cl_ev_fin', $f['ini'] ? ( $f['fin'] ? $f['fin'] : $f['ini'] ) : '99999999' );
}

// Se recalculan al guardar en wp-admin y una vez para los eventos que ya existían.
add_action( 'acf/save_post', function ( $post_id ) {
	if ( is_numeric( $post_id ) && 'evento' === get_post_type( $post_id ) ) {
		cl_ev_meta( (int) $post_id );
	}
}, 20 );
add_action( 'init', function () {
	if ( get_option( 'cl_ev_meta_v1' ) ) {
		return;
	}
	foreach ( get_posts( array( 'post_type' => 'evento', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
		cl_ev_meta( $id );
	}
	update_option( 'cl_ev_meta_v1', 1, false );
}, 30 );

// Agenda (página 10272, bucle gvijrm): solo vigentes, ordenados por fecha (los de todo el año al final).
add_filter( 'bricks/posts/query_vars', function ( $qv, $settings, $element_id ) {
	if ( 'gvijrm' !== $element_id ) {
		return $qv;
	}
	$qv['meta_query']   = isset( $qv['meta_query'] ) && is_array( $qv['meta_query'] ) ? $qv['meta_query'] : array();
	$qv['meta_query'][] = array( 'key' => '_cl_ev_fin', 'value' => cl_ev_hoy(), 'compare' => '>=', 'type' => 'NUMERIC' );
	$qv['meta_key']     = '_cl_ev_orden';
	$qv['orderby']      = array( 'meta_value_num' => 'ASC', 'title' => 'ASC' );
	unset( $qv['order'] );
	return $qv;
}, 10, 3 );

// ---------- Datos del emprendimiento para la ficha del evento ----------

// Mapa propio de una experiencia «En otro lugar» (URL para el iframe), o ''.
function cl_ev_mapa_lugar( $id ) {
	$v = trim( (string) get_post_meta( $id, 'ubicacion', true ) );
	if ( '' === $v || ! function_exists( 'cl_mapa_extraer_url' ) ) {
		return '';
	}
	return (string) cl_mapa_extraer_url( $v );
}

// {echo:cl_ev_dato('telefono')} — nombre, url, localidad, direccion, como_llegar, como_llegar_url,
// telefono, whatsapp, web, facebook, instagram, email, mapa.
function cl_ev_dato( $clave, $post_id = 0 ) {
	$id = absint( $post_id ) ? absint( $post_id ) : get_the_ID();
	if ( ! $id || 'evento' !== get_post_type( $id ) ) {
		return '';
	}
	$emp = cl_ev_emp_de( $id );
	if ( ! $emp || ! in_array( get_post_status( $emp ), array( 'publish', 'draft', 'pending' ), true ) ) {
		return '';
	}
	switch ( $clave ) {
		case 'nombre':
			return esc_html( get_the_title( $emp ) );
		case 'url':
			return 'publish' === get_post_status( $emp ) ? esc_url( get_permalink( $emp ) ) : '';
		case 'localidad':
		case 'direccion':
		case 'como_llegar':
			$k = 'direccion' === $clave ? 'direccion_o_punto_de_encuentro' : $clave;
			return nl2br( esc_html( (string) get_post_meta( $emp, $k, true ) ) );
		case 'como_llegar_url':
			return function_exists( 'cl_como_llegar_url' ) ? esc_url( cl_como_llegar_url( $emp ) ) : '';
		case 'telefono':
			return function_exists( 'cl_tel_mostrar' ) ? esc_html( cl_tel_mostrar( $emp ) ) : '';
		case 'whatsapp':
			return function_exists( 'cl_tel_wa' ) ? esc_html( cl_tel_wa( $emp ) ) : '';
		case 'web':
			return esc_url( (string) get_post_meta( $emp, 'url_sitio_web', true ) );
		case 'facebook':
			return esc_url( (string) get_post_meta( $emp, 'facebookemp', true ) );
		case 'instagram':
			return function_exists( 'cl_instagram_url' ) ? esc_url( cl_instagram_url( $emp ) ) : '';
		case 'email':
			return sanitize_email( (string) get_post_meta( $emp, 'email-emp', true ) );
		case 'mapa':
			// En otro lugar: el mapa propio de la experiencia (o ninguno). Si no, el del emprendimiento.
			if ( cl_ev_en_otro_lugar( $id ) ) {
				return esc_url( cl_ev_mapa_lugar( $id ) );
			}
			return function_exists( 'cl_mapa_url_propia' ) ? esc_url( cl_mapa_url_propia( $emp ) ) : '';
	}
	return '';
}

add_shortcode( 'cl_ev_mapa', function () {
	$url = cl_ev_dato( 'mapa' );
	return $url ? '<iframe src="' . $url . '" title="Mapa" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen style="border:0;width:100%;height:100%"></iframe>' : '';
} );

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_ev_valor', 'cl_ev_dato', 'cl_ev_cuando', 'cl_ev_cuando_corto', 'cl_ev_pasado', 'cl_ev_link' ) );
} );

// ---------- Campos del formulario ----------

function cl_ev_pasos() {
	return array(
		'ev_datos' => array(
			'clevti' => 'titulo',
			'clevbr' => 'breve_descripcion',
			'clevmo' => 'modo',
			'clevfi' => 'fecha_del_evento',
			'clevff' => 'fecha_de_finalizacion',
			'clevhi' => 'hora_de_inicio',
			'clevhf' => 'hora_de_finalizacion',
			'clevra' => 'rango_fechas',
			'clevlu' => 'lugar',
			'clevub' => 'ubicacion',
			'clevpr' => 'precio_de_la_actividad',
			'clevde' => 'contenido',
			'clevdo' => 'donde_ocurre',
			'clevca' => 'categorias',
		),
	);
}

function cl_ev_keys() {
	return array(
		'breve_descripcion'      => 'field_61fbd777dacfe',
		'lugar'                  => 'field_61b667f5b096d',
		'ubicacion'              => 'field_6ac64dc09e396',
		'periodico'              => 'field_61b789e967c92',
		'mas_de_un_dia'          => 'field_6241dbe4eafe8',
		'rango_fechas'           => 'field_6247318e53cff',
		'fecha_del_evento'       => 'field_61b66895b096f',
		'fecha_de_finalizacion'  => 'field_61b668e6b0970',
		'hora_de_inicio'         => 'field_61b66900b0971',
		'hora_de_finalizacion'   => 'field_61b6692bb0972',
		'precio_de_la_actividad' => 'field_61b66946b0973',
		'donde_ocurre'           => 'field_6ac64d69c0f48',
	);
}

// ¿La experiencia ocurre en otro lugar (no en el emprendimiento)?
function cl_ev_en_otro_lugar( $id ) {
	return 'En otro lugar' === get_post_meta( $id, 'donde_ocurre', true );
}

// {echo:cl_ev_valor('campo')} — valor actual para precargar el formulario.
function cl_ev_valor( $campo ) {
	$id = cl_ev_id();
	if ( ! $id ) {
		return '';
	}
	$f = cl_ev_fechas( $id );
	switch ( $campo ) {
		case 'id':
			return (string) $id;
		case 'titulo':
			return (string) get_post_field( 'post_title', $id );
		case 'contenido':
			return cl_panel_contenido_a_texto( get_post_field( 'post_content', $id ) );
		case 'modo':
			return cl_ev_modo( $id );
		case 'fecha_del_evento':
			return $f['ini'] ? substr( $f['ini'], 0, 4 ) . '-' . substr( $f['ini'], 4, 2 ) . '-' . substr( $f['ini'], 6, 2 ) : '';
		case 'fecha_de_finalizacion':
			return $f['fin'] ? substr( $f['fin'], 0, 4 ) . '-' . substr( $f['fin'], 4, 2 ) . '-' . substr( $f['fin'], 6, 2 ) : '';
		case 'hora_de_inicio':
			return cl_ev_fmt_hora( $f['hi'] );
		case 'hora_de_finalizacion':
			return cl_ev_fmt_hora( $f['hf'] );
		case 'rango_fechas':
			return $f['rango'];
		case 'lugar':
		case 'ubicacion':
			return cl_ev_en_otro_lugar( $id ) ? (string) get_post_meta( $id, $campo, true ) : '';
		case 'donde_ocurre':
			return cl_ev_en_otro_lugar( $id ) ? 'otro' : 'emp';
		case 'categorias':
			$ids = wp_get_object_terms( $id, 'category', array( 'fields' => 'ids' ) );
			$ids = is_wp_error( $ids ) ? array() : array_intersect( array_map( 'intval', $ids ), cl_ev_formas() );
			return implode( ',', $ids );
		case 'breve_descripcion':
		case 'precio_de_la_actividad':
			return (string) get_post_meta( $id, $campo, true );
	}
	return '';
}

// Formas de andar (categorías que puede elegir el emprendedor). Usa la lista de «CL – Panel» si existe.
function cl_ev_formas() {
	return defined( 'CL_FORMAS_DE_ANDAR' ) ? CL_FORMAS_DE_ANDAR : array( 98, 88, 111, 108, 110, 116, 202, 203, 204 );
}

// ---------- Guardado ----------

add_action( 'bricks/form/custom_action', function ( $form ) {
	$f    = $form->get_fields();
	$paso = isset( $f['form-field-clpaso'] ) ? sanitize_key( $f['form-field-clpaso'] ) : '';
	if ( 'ev_datos' !== $paso ) {
		return;
	}
	$error = function ( $msg ) use ( $form ) {
		$form->set_result( array( 'action' => 'cl_ev', 'type' => 'error', 'message' => $msg ) );
	};
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return $error( 'Tu sesión venció. Volvé a iniciar sesión.' );
	}
	$clave = 'cl_panel_rl_' . get_current_user_id();
	$n     = (int) get_transient( $clave );
	if ( $n >= 30 ) {
		return $error( 'Guardaste muchas veces seguidas. Esperá unos minutos y probá de nuevo.' );
	}
	set_transient( $clave, $n + 1, 10 * MINUTE_IN_SECONDS );

	$ev = cl_ev_id( isset( $f['form-field-clevid'] ) ? (int) $f['form-field-clevid'] : 0 );
	if ( ! $ev ) {
		return $error( 'No encontramos esta experiencia. Volvé a Mis experiencias y entrá de nuevo.' );
	}
	$emp = cl_ev_emp_de( $ev );
	if ( ! $emp ) {
		return $error( 'Esta experiencia no está vinculada a un emprendimiento. Escribinos y lo revisamos.' );
	}
	$v = function ( $id ) use ( $f ) {
		$x = isset( $f[ 'form-field-' . $id ] ) ? $f[ 'form-field-' . $id ] : '';
		$x = is_array( $x ) ? (string) reset( $x ) : (string) $x;
		return wp_unslash( $x );
	};

	// Textos
	$titulo = trim( sanitize_text_field( $v( 'clevti' ) ) );
	if ( '' === $titulo ) {
		return $error( 'El nombre de la experiencia no puede quedar vacío.' );
	}
	$otro = 'otro' === sanitize_key( $v( 'clevdo' ) );
	if ( $otro && '' === trim( sanitize_text_field( $v( 'clevlu' ) ) ) ) {
		return $error( 'Si es en otro lugar, escribí dónde es.' );
	}

	// Mapa del lugar (opcional, solo en otro lugar): el código de Google Maps → URL del mapa.
	$mapa = '';
	if ( $otro && '' !== trim( $v( 'clevub' ) ) ) {
		if ( mb_strlen( $v( 'clevub' ) ) > 3000 ) {
			return $error( 'El mapa es demasiado largo. Pegá solo el enlace de Compartir → Copiar enlace.' );
		}
		$mapa = function_exists( 'cl_mapa_normalizar' ) ? (string) cl_mapa_normalizar( trim( $v( 'clevub' ) ) ) : '';
		if ( '' === $mapa ) {
			return $error( 'No pudimos leer el mapa. En Google Maps buscá el lugar, tocá Compartir → Copiar enlace y pegalo acá. Desde la compu también sirve Compartir → Insertar un mapa.' );
		}
	}

	$campos = array(
		'breve_descripcion'      => array( trim( sanitize_text_field( $v( 'clevbr' ) ) ), 180 ),
		'lugar'                  => array( trim( sanitize_text_field( $v( 'clevlu' ) ) ), 150 ),
		'precio_de_la_actividad' => array( trim( sanitize_text_field( $v( 'clevpr' ) ) ), 80 ),
		'rango_fechas'           => array( trim( sanitize_text_field( $v( 'clevra' ) ) ), 100 ),
	);
	foreach ( $campos as $c => $x ) {
		if ( mb_strlen( $x[0] ) > $x[1] ) {
			return $error( 'Un texto supera el largo máximo (' . $x[1] . ' caracteres).' );
		}
	}
	$desc = trim( sanitize_textarea_field( $v( 'clevde' ) ) );
	if ( mb_strlen( $desc ) > 2500 ) {
		return $error( 'La descripción supera los 2500 caracteres.' );
	}

	// Cuándo
	$modo = sanitize_key( $v( 'clevmo' ) );
	if ( ! in_array( $modo, array( 'unico', 'varios', 'todo' ), true ) ) {
		return $error( 'Elegí cuándo es: un día, varios días o todo el año.' );
	}
	$ini = $fin = $hi = $hf = '';
	if ( 'todo' !== $modo ) {
		$ini = cl_ev_fecha_ymd( $v( 'clevfi' ) );
		if ( ! $ini ) {
			return $error( 'Elegí la fecha.' );
		}
		if ( 'varios' === $modo ) {
			$fin = cl_ev_fecha_ymd( $v( 'clevff' ) );
			if ( ! $fin || $fin <= $ini ) {
				return $error( 'La fecha de cierre tiene que ser después de la de inicio.' );
			}
		}
		$antes = cl_ev_fechas( $ev );
		if ( ( $fin ? $fin : $ini ) < cl_ev_hoy() && ( $antes['ini'] !== $ini || $antes['fin'] !== $fin ) ) {
			return $error( 'Esa fecha ya pasó. Si la actividad se repite, elegí «Todo el año o a coordinar» y contalo en la aclaración.' );
		}
		$hi = '' !== trim( $v( 'clevhi' ) ) ? cl_ev_hora( $v( 'clevhi' ) ) : '';
		$hf = '' !== trim( $v( 'clevhf' ) ) ? cl_ev_hora( $v( 'clevhf' ) ) : '';
		if ( ( '' !== trim( $v( 'clevhi' ) ) && ! $hi ) || ( '' !== trim( $v( 'clevhf' ) ) && ! $hf ) ) {
			return $error( 'Escribí la hora así: 19:30.' );
		}
	}

	if ( ! $otro ) {
		$campos['lugar'][0] = ''; // En el emprendimiento: la ficha muestra el emprendimiento y su localidad.
	}

	$k = cl_ev_keys();
	foreach ( $campos as $c => $x ) {
		update_field( $k[ $c ], $x[0], $ev );
	}
	update_field( $k['donde_ocurre'], $otro ? 'En otro lugar' : 'En el emprendimiento', $ev );
	// Mapa: se vacía si es en el emprendimiento; si es en otro lugar, solo se toca si el campo vino en el formulario.
	if ( ! $otro ) {
		update_field( $k['ubicacion'], '', $ev );
	} elseif ( array_key_exists( 'form-field-clevub', $f ) ) {
		update_field( $k['ubicacion'], $mapa, $ev );
	}

	// Categorías: solo se tocan las Formas de andar; si no queda ninguna, se copian las del emprendimiento.
	if ( array_key_exists( 'form-field-clevca', $f ) ) {
		$elegidas = $f['form-field-clevca'];
		$elegidas = is_array( $elegidas ) ? $elegidas : explode( ',', (string) $elegidas );
		$elegidas = array_values( array_intersect( array_map( 'intval', $elegidas ), cl_ev_formas() ) );
		$actuales = wp_get_object_terms( $ev, 'category', array( 'fields' => 'ids' ) );
		$actuales = is_wp_error( $actuales ) ? array() : array_map( 'intval', $actuales );
		$otras    = array_values( array_diff( $actuales, cl_ev_formas(), array( 1 ) ) );
		wp_set_object_terms( $ev, array_values( array_unique( array_merge( $otras, $elegidas ) ) ), 'category', false );
	}
	update_field( $k['fecha_del_evento'], $ini, $ev );
	update_field( $k['fecha_de_finalizacion'], $fin, $ev );
	update_field( $k['hora_de_inicio'], $hi, $ev );
	update_field( $k['hora_de_finalizacion'], $hf, $ev );
	update_field( $k['mas_de_un_dia'], 'varios' === $modo ? 'Sí' : 'No', $ev );
	update_field( $k['periodico'], 'todo' === $modo ? 'Sí' : 'No', $ev );

	$upd = array( 'ID' => $ev );
	if ( get_post_field( 'post_title', $ev ) !== $titulo ) {
		$upd['post_title'] = mb_substr( $titulo, 0, 100 );
	}
	if ( cl_panel_contenido_a_texto( get_post_field( 'post_content', $ev ) ) !== $desc ) {
		$upd['post_content'] = cl_panel_texto_a_bloques( $desc );
	}
	if ( count( $upd ) > 1 ) {
		wp_update_post( wp_slash( $upd ) );
	}
	cl_ev_sincronizar( $ev, $emp );
	cl_ev_meta( $ev );

	if ( ! current_user_can( 'manage_options' ) && 'publish' === get_post_status( $ev ) ) {
		$user = wp_get_current_user();
		wp_mail(
			get_option( 'admin_email' ),
			'[Caminos Locales] Cambios en la experiencia ' . get_the_title( $ev ),
			'Experiencia: ' . get_the_title( $ev ) . "\nEmprendimiento: " . get_the_title( $emp ) . "\nUsuario: " . $user->user_login . "\n\nVer: " . get_permalink( $ev ) . "\nEditar: " . admin_url( 'post.php?post=' . $ev . '&action=edit' )
		);
	}
	$form->set_result( array( 'action' => 'cl_ev', 'type' => 'success', 'message' => 'Guardado ✓' ) );
} );

// Vínculo fijo, región, categorías y "Publicación: Evento" desde el emprendimiento.
function cl_ev_sincronizar( $ev, $emp ) {
	$ev  = (int) $ev;
	$emp = (int) $emp;
	if ( ! $ev || ! $emp ) {
		return;
	}
	update_field( 'field_67210d5f3b761', array( $emp ), $ev );
	$inv = get_post_meta( $emp, 'evento_del_emprendimiento', true );
	$inv = is_array( $inv ) ? array_map( 'strval', $inv ) : array();
	if ( ! in_array( (string) $ev, $inv, true ) ) {
		$inv[] = (string) $ev;
		update_field( 'field_67211bf25ff90', $inv, $emp );
	}
	if ( term_exists( 179, 'publicacion' ) ) {
		wp_set_object_terms( $ev, array( 179 ), 'publicacion', true );
	}
	$reg = wp_get_object_terms( $emp, 'region', array( 'fields' => 'ids' ) );
	if ( ! is_wp_error( $reg ) ) {
		wp_set_object_terms( $ev, array_map( 'intval', $reg ), 'region', false );
	}
	$cats = wp_get_object_terms( $ev, 'category', array( 'fields' => 'ids' ) );
	$cats = is_wp_error( $cats ) ? array() : array_diff( array_map( 'intval', $cats ), array( 1 ) );
	if ( ! $cats ) {
		$ec = wp_get_object_terms( $emp, 'category', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $ec ) && $ec ) {
			wp_set_object_terms( $ev, array_map( 'intval', $ec ), 'category', false );
		}
	}
}

// ---------- Agregar evento ----------

add_action( 'template_redirect', function () {
	if ( empty( $_POST['cl_ev_nuevo'] ) || ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return;
	}
	$emp = cl_panel_emp_id( isset( $_POST['emp'] ) ? (int) $_POST['emp'] : null );
	if ( ! $emp || ! isset( $_POST['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_clnonce'] ), 'cl_ev_nuevo_' . $emp ) ) {
		return;
	}
	$volver = cl_ev_url_lista( $emp );
	$nombre = mb_substr( trim( sanitize_text_field( wp_unslash( isset( $_POST['nombre'] ) ? (string) $_POST['nombre'] : '' ) ) ), 0, 100 );
	if ( '' === $nombre ) {
		wp_safe_redirect( add_query_arg( 'error', 'nombre', $volver ) );
		exit;
	}
	if ( count( cl_ev_de_emp( $emp ) ) >= CL_EV_MAX ) {
		wp_safe_redirect( add_query_arg( 'error', 'max', $volver ) );
		exit;
	}
	$es_admin = current_user_can( 'manage_options' );
	if ( ! $es_admin ) {
		$clave = 'cl_ev_nuevos_' . get_current_user_id();
		$n     = (int) get_transient( $clave );
		if ( $n >= 5 ) {
			wp_safe_redirect( add_query_arg( 'error', 'muchos', $volver ) );
			exit;
		}
		set_transient( $clave, $n + 1, DAY_IN_SECONDS );
	}
	$dueno = (int) get_post_field( 'post_author', $emp );
	$id    = wp_insert_post( array(
		'post_type'   => 'evento',
		'post_status' => 'draft',
		'post_title'  => $nombre,
		'post_author' => $es_admin && $dueno ? $dueno : get_current_user_id(),
	), true );
	if ( is_wp_error( $id ) ) {
		wp_safe_redirect( add_query_arg( 'error', 'crear', $volver ) );
		exit;
	}
	cl_ev_sincronizar( $id, $emp );
	cl_ev_meta( $id );
	if ( 'Sí' !== get_post_meta( $emp, 'publica_eventos', true ) ) {
		update_field( 'field_67210edb742e9', 'Sí', $emp );
	}
	wp_safe_redirect( cl_ev_url( $id, 'datos' ) );
	exit;
}, 1 );

// ---------- Chequeo, envío a revisión, publicación ----------

function cl_ev_galeria( $id ) {
	$fotos = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$v = (int) get_post_meta( $id, 'galeria_eventos_foto' . $i, true );
		if ( $v && wp_attachment_is_image( $v ) ) {
			$fotos[] = $v;
		}
	}
	return $fotos;
}

// ---------- Afiche ----------

// ID del afiche (ACF `afiche`), o 0.
function cl_ev_afiche( $id ) {
	$v = (int) get_post_meta( $id, 'afiche', true );
	return ( $v && wp_attachment_is_image( $v ) ) ? $v : 0;
}

// Imagen para tarjetas: array( id, es_afiche ). Foto principal; si no hay, el afiche.
function cl_ev_img_tarjeta( $id ) {
	$t = (int) get_post_thumbnail_id( $id );
	if ( $t ) {
		return array( $t, false );
	}
	$a = cl_ev_afiche( $id );
	return array( $a, (bool) $a );
}

// {echo:cl_ev_tiene_foto} → "1" si hay foto principal.
function cl_ev_tiene_foto( $post_id = 0 ) {
	$id = absint( $post_id ) ? absint( $post_id ) : get_the_ID();
	return ( $id && get_post_thumbnail_id( $id ) ) ? '1' : '';
}

// {echo:cl_ev_solo_afiche} → "1" si no hay foto principal pero sí afiche.
function cl_ev_solo_afiche( $post_id = 0 ) {
	$id = absint( $post_id ) ? absint( $post_id ) : get_the_ID();
	return ( $id && ! get_post_thumbnail_id( $id ) && cl_ev_afiche( $id ) ) ? '1' : '';
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_ev_tiene_foto', 'cl_ev_solo_afiche' ) );
} );

// CSS del afiche entero sobre su versión difuminada (una vez por página).
function cl_ev_afiche_css() {
	static $hecho = false;
	if ( $hecho ) {
		return '';
	}
	$hecho = true;
	return '<style>.clafi{position:relative;overflow:hidden}.clafi::before{content:"";position:absolute;inset:-24px;background:inherit;background-size:cover;background-position:center;filter:blur(18px) brightness(.85)}.clafi img{position:relative;width:100%;height:100%;object-fit:contain;display:block}</style>';
}

// Caja de imagen para tarjetas de código: foto (cover) o afiche entero sobre fondo difuminado.
// $clase: clase de la caja (tamaño y bordes). $vacio: texto si no hay imagen.
function cl_ev_img_html( $id, $size, $clase, $vacio = '' ) {
	list( $att, $afiche ) = cl_ev_img_tarjeta( $id );
	$url = $att ? wp_get_attachment_image_url( $att, $size ) : '';
	if ( ! $url ) {
		return '<div class="' . esc_attr( $clase ) . '">' . esc_html( $vacio ) . '</div>';
	}
	if ( $afiche ) {
		return cl_ev_afiche_css() . '<div class="' . esc_attr( $clase ) . ' clafi" style="background-image:url(' . esc_url( $url ) . ')"><img src="' . esc_url( $url ) . '" alt="" loading="lazy"></div>';
	}
	return '<div class="' . esc_attr( $clase ) . '" style="background-image:url(' . esc_url( $url ) . ')"></div>';
}

function cl_ev_chequeo( $id ) {
	$oblig = array(
		array( 'Nombre', '' !== trim( (string) get_post_field( 'post_title', $id ) ), 'datos' ),
		array( 'Cuándo', '' !== cl_ev_modo( $id ), 'datos' ),
		array( 'Fecha que no haya pasado', ! cl_ev_pasado( $id ), 'datos' ),
		array( 'Descripción', '' !== trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ) ), 'datos' ),
		array( 'Foto principal o afiche', get_post_thumbnail_id( $id ) || cl_ev_afiche( $id ), 'fotos' ),
	);
	$suger = array(
		array( 'En una línea', '' !== trim( (string) get_post_meta( $id, 'breve_descripcion', true ) ), 'datos' ),
		array( 'Precio', '' !== trim( (string) get_post_meta( $id, 'precio_de_la_actividad', true ) ), 'datos' ),
		array( 'Más fotos', count( cl_ev_galeria( $id ) ) >= 1, 'fotos' ),
	);
	if ( cl_ev_en_otro_lugar( $id ) ) {
		$suger[] = array( 'Mapa del lugar', '' !== trim( (string) get_post_meta( $id, 'ubicacion', true ) ), 'datos' );
	}
	return array( $oblig, $suger );
}

// ¿Ya la aprobó Caminos Locales alguna vez? (puede volver a publicarse sin revisión)
function cl_ev_aprobada( $id ) {
	return (bool) get_post_meta( $id, '_cl_ev_aprobada', true );
}

// Oculta = estuvo publicada y el emprendedor la sacó de la web.
function cl_ev_oculta( $id ) {
	return 'draft' === get_post_status( $id ) && cl_ev_aprobada( $id );
}

function cl_ev_chip( $id ) {
	if ( cl_ev_oculta( $id ) ) {
		return '<span style="display:inline-block;padding:3px 9px;border-radius:999px;background:#e6e1d6;color:#4a4335;font-size:13px;font-weight:700">Oculta</span>';
	}
	if ( cl_ev_pasado( $id ) ) {
		return '<span style="display:inline-block;padding:3px 9px;border-radius:999px;background:#ececec;color:#454545;font-size:13px;font-weight:700">Ya pasó</span>';
	}
	$m = array(
		'publish' => array( 'Publicada', '#dcebe4', '#174a3a' ),
		'pending' => array( 'En revisión', '#fdf0d5', '#6b4300' ),
		'draft'   => array( 'Borrador', '#ececec', '#454545' ),
	);
	$e = get_post_status( $id );
	$e = isset( $m[ $e ] ) ? $m[ $e ] : $m['draft'];
	return '<span style="display:inline-block;padding:3px 9px;border-radius:999px;background:' . $e[1] . ';color:' . $e[2] . ';font-size:13px;font-weight:700">' . esc_html( $e[0] ) . '</span>';
}

add_action( 'template_redirect', function () {
	if ( empty( $_POST['cl_ev_enviar'] ) || ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return;
	}
	$id = cl_ev_id( isset( $_POST['ev'] ) ? (int) $_POST['ev'] : 0 );
	if ( ! $id || ! isset( $_POST['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_clnonce'] ), 'cl_ev_enviar_' . $id ) ) {
		return;
	}
	$vuelta = cl_ev_url( $id, 'vista' );
	$estado_antes = get_post_status( $id );
	if ( 'draft' !== $estado_antes && ! ( 'pending' === $estado_antes && cl_sin_revision() ) ) {
		wp_safe_redirect( $vuelta );
		exit;
	}
	list( $oblig ) = cl_ev_chequeo( $id );
	foreach ( $oblig as $o ) {
		if ( ! $o[1] ) {
			wp_safe_redirect( add_query_arg( 'faltan', 1, $vuelta ) );
			exit;
		}
	}
	$user = wp_get_current_user();
	$emp  = cl_ev_emp_de( $id );
	// v1.0.1: sin revisión (integrantes de confianza), se publica al momento y se avisa al admin.
	if ( cl_sin_revision() ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		cl_ev_meta( $id );
		wp_mail(
			get_option( 'admin_email' ),
			'[Caminos Locales] Experiencia publicada: ' . get_the_title( $id ),
			'La experiencia «' . get_the_title( $id ) . '» de ' . get_the_title( $emp ) . ' se publicó desde el panel.' . "\n" .
			'Cuándo: ' . wp_specialchars_decode( cl_ev_cuando( $id ) ) . "\n" .
			'Usuario: ' . $user->user_login . "\n\n" .
			'Ver: ' . get_permalink( $id ) . "\n" .
			'Editar: ' . admin_url( 'post.php?post=' . $id . '&action=edit' )
		);
		wp_safe_redirect( add_query_arg( 'listo', 1, $vuelta ) );
		exit;
	}
	wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ) );
	wp_mail(
		get_option( 'admin_email' ),
		'[Caminos Locales] Experiencia para revisar: ' . get_the_title( $id ),
		'La experiencia «' . get_the_title( $id ) . '» de ' . get_the_title( $emp ) . ' se envió a revisión.' . "\n" .
		'Cuándo: ' . wp_specialchars_decode( cl_ev_cuando( $id ) ) . "\n" .
		'Usuario: ' . $user->user_login . "\n\n" .
		'Vista previa: ' . cl_ev_url_vista( $id ) . "\n" .
		'Revisar y publicar: ' . admin_url( 'post.php?post=' . $id . '&action=edit' )
	);
	wp_safe_redirect( add_query_arg( 'enviado', 1, $vuelta ) );
	exit;
}, 1 );

// Toda experiencia que se publica queda marcada como aprobada.
add_action( 'transition_post_status', function ( $nuevo, $viejo, $post ) {
	if ( 'evento' === $post->post_type && 'publish' === $nuevo ) {
		update_post_meta( $post->ID, '_cl_ev_aprobada', 1 );
	}
}, 5, 3 );

// Aviso al admin (ocultar, volver a publicar, duplicar).
function cl_ev_aviso_admin( $id, $que ) {
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}
	$user = wp_get_current_user();
	$emp  = cl_ev_emp_de( $id );
	wp_mail(
		get_option( 'admin_email' ),
		'[Caminos Locales] ' . $que . ': ' . get_the_title( $id ),
		'Experiencia: ' . get_the_title( $id ) . "\nEmprendimiento: " . get_the_title( $emp ) . "\nUsuario: " . $user->user_login . "\n\n" . $que . ".\n\nVer: " . cl_ev_url_vista( $id ) . "\nEditar: " . admin_url( 'post.php?post=' . $id . '&action=edit' )
	);
}

add_action( 'template_redirect', function () {
	$accion = '';
	foreach ( array( 'ocultar', 'republicar', 'duplicar', 'borrar' ) as $a ) {
		if ( ! empty( $_POST[ 'cl_ev_' . $a ] ) ) {
			$accion = $a;
		}
	}
	if ( ! $accion || ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return;
	}
	$id = cl_ev_id( isset( $_POST['ev'] ) ? (int) $_POST['ev'] : 0 );
	if ( ! $id || ! isset( $_POST['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_clnonce'] ), 'cl_ev_' . $accion . '_' . $id ) ) {
		return;
	}
	$vuelta = cl_ev_url( $id, 'vista' );
	$lista  = ! empty( $_POST['volver'] ) && 'lista' === $_POST['volver'];

	if ( 'borrar' === $accion ) {
		// A la papelera (el admin la puede recuperar desde wp-admin). Las fotos no se tocan.
		$emp_b = cl_ev_emp_de( $id );
		cl_ev_aviso_admin( $id, 'Experiencia borrada por el emprendedor (está en la papelera)' );
		wp_trash_post( $id );
		wp_safe_redirect( add_query_arg( 'borrada', 1, cl_ev_url_lista( $emp_b ) ) );
		exit;
	}

	if ( 'ocultar' === $accion ) {
		if ( 'publish' === get_post_status( $id ) ) {
			update_post_meta( $id, '_cl_ev_aprobada', 1 );
			wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
			cl_ev_aviso_admin( $id, 'Experiencia ocultada por el emprendedor' );
		}
		wp_safe_redirect( $lista ? add_query_arg( 'oculta', $id, cl_ev_url_lista( cl_ev_emp_de( $id ) ) ) : add_query_arg( 'oculta', 1, $vuelta ) );
		exit;
	}

	if ( 'republicar' === $accion ) {
		if ( ! cl_ev_oculta( $id ) ) {
			wp_safe_redirect( $vuelta );
			exit;
		}
		list( $oblig ) = cl_ev_chequeo( $id );
		foreach ( $oblig as $o ) {
			if ( ! $o[1] ) {
				wp_safe_redirect( add_query_arg( 'faltan', 1, $vuelta ) );
				exit;
			}
		}
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		cl_ev_meta( $id );
		cl_ev_aviso_admin( $id, 'Experiencia publicada de nuevo por el emprendedor' );
		wp_safe_redirect( $lista ? add_query_arg( 'publicada', $id, cl_ev_url_lista( cl_ev_emp_de( $id ) ) ) : add_query_arg( 'publicada', 1, $vuelta ) );
		exit;
	}

	// Duplicar
	$emp = cl_ev_emp_de( $id );
	if ( ! $emp ) {
		wp_safe_redirect( $vuelta );
		exit;
	}
	if ( count( cl_ev_de_emp( $emp ) ) >= CL_EV_MAX ) {
		wp_safe_redirect( add_query_arg( 'error', 'max', cl_ev_url_lista( $emp ) ) );
		exit;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		$clave = 'cl_ev_nuevos_' . get_current_user_id();
		$n     = (int) get_transient( $clave );
		if ( $n >= 5 ) {
			wp_safe_redirect( add_query_arg( 'error', 'muchos', cl_ev_url_lista( $emp ) ) );
			exit;
		}
		set_transient( $clave, $n + 1, DAY_IN_SECONDS );
	}
	$nuevo = wp_insert_post( wp_slash( array(
		'post_type'    => 'evento',
		'post_status'  => 'draft',
		'post_title'   => get_post_field( 'post_title', $id ),
		'post_content' => get_post_field( 'post_content', $id ),
		'post_excerpt' => get_post_field( 'post_excerpt', $id ),
		'post_author'  => (int) get_post_field( 'post_author', $id ),
	) ), true );
	if ( is_wp_error( $nuevo ) ) {
		wp_safe_redirect( add_query_arg( 'error', 'crear', cl_ev_url_lista( $emp ) ) );
		exit;
	}
	// Datos e imágenes: se copian todos los campos menos los internos. Las imágenes se comparten
	// (no se duplican); «CL – Panel: Fotos» no borra una imagen mientras la use otra ficha.
	$saltar = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_cl_ev_aprobada', '_cl_ev_orden', '_cl_ev_fin', '_encloseme', '_pingme' );
	foreach ( get_post_meta( $id ) as $k => $valores ) {
		if ( in_array( $k, $saltar, true ) || 0 === strpos( $k, '_cl_ev_' ) ) {
			continue;
		}
		foreach ( $valores as $v ) {
			add_post_meta( $nuevo, $k, maybe_unserialize( $v ) );
		}
	}
	foreach ( get_object_taxonomies( 'evento' ) as $tax ) {
		$t = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $t ) && $t ) {
			wp_set_object_terms( $nuevo, array_map( 'intval', $t ), $tax, false );
		}
	}
	// Sin fecha: la pone el emprendedor. Si era «todo el año», sigue así.
	$k = cl_ev_keys();
	update_field( $k['fecha_del_evento'], '', $nuevo );
	update_field( $k['fecha_de_finalizacion'], '', $nuevo );
	update_field( $k['mas_de_un_dia'], 'No', $nuevo );
	cl_ev_sincronizar( $nuevo, $emp );
	cl_ev_meta( $nuevo );
	cl_ev_aviso_admin( $nuevo, 'Experiencia duplicada (borrador)' );
	wp_safe_redirect( add_query_arg( 'copiada', 1, cl_ev_url( $nuevo, 'datos' ) ) );
	exit;
}, 1 );

add_action( 'transition_post_status', function ( $nuevo, $viejo, $post ) {
	if ( 'evento' !== $post->post_type || 'publish' !== $nuevo || 'pending' !== $viejo ) {
		return;
	}
	$autor = get_userdata( (int) $post->post_author );
	if ( ! $autor || ! function_exists( 'cl_es_emprendedor' ) || ! cl_es_emprendedor( $autor ) ) {
		return;
	}
	wp_mail(
		$autor->user_email,
		'¡Tu experiencia ya está publicada en Caminos Locales!',
		'Hola, ' . $autor->display_name . ":\n\n" .
		'«' . $post->post_title . '» ya está publicada en Caminos Locales:' . "\n" . get_permalink( $post ) . "\n\n" .
		'Podés editarla desde tu panel: ' . cl_ev_pag( CL_EV_PAG_LISTA ) . "\n\n" .
		"Gracias por sumarte.\nCaminos Locales"
	);
}, 10, 3 );

// Vista previa del borrador.
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || empty( $_GET['cl_vista'] ) || empty( $_GET['p'] ) || ! is_user_logged_in() ) {
		return;
	}
	$id = (int) $_GET['p'];
	if ( 'evento' !== get_post_type( $id ) ) {
		return;
	}
	if ( current_user_can( 'manage_options' ) || (int) get_post_field( 'post_author', $id ) === get_current_user_id() ) {
		$q->set( 'post_status', array( 'publish', 'draft', 'pending' ) );
	}
} );
add_action( 'wp_body_open', function () {
	if ( ! is_singular( 'evento' ) ) {
		return;
	}
	if ( ! empty( $_GET['cl_vista'] ) && 'publish' !== get_post_status() ) {
		echo '<div style="position:sticky;top:0;z-index:99999;background:#fdf0d5;color:#5c3a00;padding:10px 16px;font:700 15px/1.4 system-ui,sans-serif;text-align:center">Vista previa: esta experiencia todavía no está publicada. <a href="' . esc_url( cl_ev_url( get_the_ID(), 'vista' ) ) . '" style="color:#1f4645">← Volver al panel</a></div>';
	}
} );

// Botones de la tarjeta en «Mis experiencias».
function cl_ev_acciones_lista( $e ) {
	$btn = function ( $accion, $texto, $confirma = '' ) use ( $e ) {
		$h  = '<form method="post" class="cle-acc-f"><input type="hidden" name="cl_ev_' . $accion . '" value="1"><input type="hidden" name="ev" value="' . (int) $e . '"><input type="hidden" name="volver" value="lista">';
		$h .= wp_nonce_field( 'cl_ev_' . $accion . '_' . $e, '_clnonce', false, false );
		return $h . '<button type="submit" class="cle-acc-b' . ( 'borrar' === $accion ? ' cle-borrar' : '' ) . '"' . ( $confirma ? ' data-confirma="' . esc_attr( $confirma ) . '"' : '' ) . '>' . esc_html( $texto ) . '</button></form>';
	};
	$h      = '';
	$pasado = cl_ev_pasado( $e );
	if ( 'publish' === get_post_status( $e ) && ! $pasado ) {
		$h .= $btn( 'ocultar', 'Ocultar', '¿Seguro? Tocá de nuevo' );
	}
	if ( cl_ev_oculta( $e ) && ! $pasado ) {
		$h .= $btn( 'republicar', 'Volver a publicar' );
	}
	if ( count( cl_ev_de_emp( cl_ev_emp_de( $e ) ) ) < CL_EV_MAX ) {
		$h .= $btn( 'duplicar', 'Duplicar' );
	}
	$h .= $btn( 'borrar', 'Borrar', '¿Borrar? Tocá de nuevo' );
	return $h ? '<div class="cle-acc">' . $h . '</div>' : '';
}

// Doble toque para confirmar (botones con data-confirma). También lo usa «CL – Panel: Alojamientos».
if ( ! function_exists( 'cl_panel_confirma_js' ) ) :
function cl_panel_confirma_js() {
	static $hecho = false;
	if ( $hecho ) {
		return '';
	}
	$hecho = true;
	return '<script>document.addEventListener("click",function(ev){var b=ev.target.closest("button[data-confirma]");if(!b)return;if(b.classList.contains("cl-seguro"))return;ev.preventDefault();b.dataset.txt=b.textContent;b.textContent=b.dataset.confirma;b.classList.add("cl-seguro");setTimeout(function(){b.classList.remove("cl-seguro");b.textContent=b.dataset.txt;},4000);});</script>';
}
endif;

// ---------- Estilos comunes del panel de eventos ----------

function cl_ev_css() {
	static $hecho = false;
	if ( $hecho ) {
		return '';
	}
	$hecho = true;
	return '<style>
.cle{display:flex;flex-direction:column;gap:14px;font-family:"Source Sans 3",system-ui,sans-serif;color:#23302f}
.cle h1{margin:0;font-family:Montserrat,sans-serif;font-size:26px;color:#1f4645}
.cle-back{color:#1f4645;font-weight:700;text-decoration:none}
.cle-p{margin:0;font-size:15px;line-height:1.45;color:#3d4b49}
.cle-h2{margin:10px 0 0;font-family:Montserrat,sans-serif;font-size:17px;color:#1f4645}
.cle-card{background:#fff;border-radius:16px;padding:12px;display:flex;gap:14px;align-items:center;box-shadow:0 1px 2px rgba(31,70,69,.08);text-decoration:none;color:inherit}
a.cle-card:hover{box-shadow:0 3px 10px rgba(31,70,69,.16)}
.cle-foto{width:84px;height:84px;border-radius:12px;background:#eeeae1 center/cover no-repeat;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;color:#7a8583}
.cle-info{flex-grow:1;min-width:0;display:flex;flex-direction:column;gap:5px}
.cle-t{font-family:Montserrat,sans-serif;font-weight:700;font-size:16px;color:#1f4645}
.cle-s{font-size:13px;color:#5b6a68}
.cle-f{font-size:14px;font-weight:700;color:#9a6055}
.cle-go{font-size:20px;color:#9a6055;flex-shrink:0}
.cle-pasado{opacity:.7}
.cle-nuevo{background:#fbfaf6;border:2px dashed #9fb3ae;border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:10px}
.cle-nuevo label{font-weight:700}
.cle-nuevo input[type=text]{height:48px;border:1px solid #c9c2b3;border-radius:10px;padding:0 14px;font-size:16px;background:#fff;color:#23302f;width:100%;box-sizing:border-box}
.cle .cle-btn{min-height:50px;border:0;border-radius:12px;background:#1f4645;color:#fff;font-weight:700;font-size:16px;cursor:pointer;font-family:inherit;width:100%}
.cle-msg{border-radius:12px;padding:12px 14px;font-size:15px;line-height:1.45}
.cle-info-msg{background:#e3ece9;color:#1d3b3a}.cle-err{background:#fbe3df;color:#8a2a1f;font-weight:700}
.cle-nav{display:flex;gap:8px;flex-wrap:wrap}
.cle-nav a{min-height:40px;padding:0 12px;border-radius:20px;background:#f1ede4;color:#3d4b49;font-size:14px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center}
.cle-nav a.on{background:#9a6055;color:#fff;font-weight:700}
.cle-item{background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(31,70,69,.08)}
.cle-item .cle-card,.cle-item a.cle-card:hover{box-shadow:none;background:transparent}
.cle-item:hover{box-shadow:0 3px 10px rgba(31,70,69,.16)}
.cle-acc{display:flex;gap:8px;flex-wrap:wrap;padding:0 12px 12px 110px}
.cle-acc-f{margin:0}
.cle .cle-acc-b{min-height:36px;padding:0 14px;border:1px solid #c9c2b3;border-radius:18px;background:#fff;color:#1f4645;font:600 14px/1 "Source Sans 3",system-ui,sans-serif;cursor:pointer}
.cle .cle-acc-b:hover{background:#f1ede4}
.cle .cle-acc-b.cle-borrar{color:#8a2a1f}
.cle .cle-acc-b.cl-seguro{background:#fbe3df;border-color:#e2a59a;color:#8a2a1f;font-weight:700}
@media (max-width:480px){.cle-acc{padding-left:12px}}
.cle-help{border:1.5px dashed #b9c6c3;border-radius:16px;padding:16px;background:#fbfaf6;display:flex;flex-direction:column;gap:10px}
.cle-wa{min-height:48px;border-radius:12px;background:#1f4645;color:#fff!important;text-decoration:none;display:flex;align-items:center;justify-content:center;font-weight:700}
</style>';
}

// ---------- [cl_panel_eventos] — lista ----------

add_shortcode( 'cl_panel_eventos', function () {
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return '';
	}
	$emp      = cl_panel_emp_id();
	$es_admin = current_user_can( 'manage_options' );
	$panel    = home_url( '/panel/' ) . ( $es_admin && $emp ? '?emp=' . $emp : '' );
	ob_start();
	echo cl_ev_css(); // phpcs:ignore
	echo '<div class="cle"><a class="cle-back" href="' . esc_url( $panel ) . '">← Mi panel</a><h1>Mis experiencias</h1>';
	if ( ! $emp ) {
		echo '<p class="cle-p">' . ( $es_admin ? 'Elegí un emprendimiento en <a href="' . esc_url( home_url( '/panel/' ) ) . '">Mi panel</a> (carga asistida).' : 'Todavía no tenés un emprendimiento cargado. Escribinos y lo creamos juntos.' ) . '</p></div>';
		return ob_get_clean();
	}
	$errores = array(
		'nombre' => 'Escribí el nombre de la experiencia.',
		'max'    => 'Llegaste al máximo de ' . CL_EV_MAX . ' experiencias. Si necesitás más, escribinos.',
		'muchos' => 'Creaste varias experiencias hoy. Probá mañana o escribinos.',
		'crear'  => 'No se pudo crear la experiencia. Probá de nuevo.',
	);
	if ( isset( $_GET['error'], $errores[ $_GET['error'] ] ) ) {
		echo '<div class="cle-msg cle-err">' . esc_html( $errores[ sanitize_key( $_GET['error'] ) ] ) . '</div>';
	}
	if ( ! empty( $_GET['oculta'] ) && cl_ev_id( (int) $_GET['oculta'] ) ) {
		echo '<div class="cle-msg cle-info-msg">«' . esc_html( get_the_title( (int) $_GET['oculta'] ) ) . '» ya no se ve en la web. Podés volver a publicarla cuando quieras.</div>';
	}
	if ( ! empty( $_GET['borrada'] ) ) {
		echo '<div class="cle-msg cle-info-msg">La experiencia se borró. Si fue un error, escribinos y la recuperamos.</div>';
	}
	if ( ! empty( $_GET['publicada'] ) && cl_ev_id( (int) $_GET['publicada'] ) ) {
		echo '<div class="cle-msg cle-info-msg">«' . esc_html( get_the_title( (int) $_GET['publicada'] ) ) . '» se ve de nuevo en la web.</div>';
	}
	echo cl_panel_confirma_js(); // phpcs:ignore
	echo '<p class="cle-p">Caminatas guiadas, talleres, cabalgatas, retiros, ferias. Las que tienen fecha dejan de mostrarse cuando pasan; las de todo el año o a coordinar quedan siempre.</p>';

	$ids     = cl_ev_de_emp( $emp );
	$activos = array_values( array_filter( $ids, function ( $e ) {
		return ! cl_ev_pasado( $e );
	} ) );
	$pasados = array_values( array_diff( $ids, $activos ) );
	$card    = function ( $e ) {
		list( $oblig ) = cl_ev_chequeo( $e );
		$faltan = array();
		foreach ( $oblig as $o ) {
			if ( ! $o[1] && 'Fecha que no haya pasado' !== $o[0] ) {
				$faltan[] = strtolower( $o[0] );
			}
		}
		$h  = '<a class="cle-card' . ( cl_ev_pasado( $e ) ? ' cle-pasado' : '' ) . '" href="' . esc_url( cl_ev_url( $e, 'datos' ) ) . '">';
		$h .= cl_ev_img_html( $e, 'thumbnail', 'cle-foto', 'Sin foto' );
		$h .= '<div class="cle-info"><div class="cle-t">' . esc_html( get_the_title( $e ) ) . '</div><div class="cle-f">' . cl_ev_cuando_corto( $e ) . '</div>';
		$h .= '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">' . cl_ev_chip( $e ) . ( $faltan ? '<span class="cle-s">Falta: ' . esc_html( implode( ', ', $faltan ) ) . '</span>' : '' ) . '</div></div>';
		$h .= '<span class="cle-go" aria-hidden="true">→</span></a>';
		return '<div class="cle-item">' . $h . cl_ev_acciones_lista( $e ) . '</div>';
	};
	foreach ( $activos as $e ) {
		echo $card( $e ); // phpcs:ignore
	}
	if ( ! $activos ) {
		echo '<p class="cle-p">No tenés experiencias próximas.</p>';
	}
	if ( count( $ids ) < CL_EV_MAX ) {
		echo '<form class="cle-nuevo" method="post"><label for="cle-nombre">Agregar una experiencia</label>';
		echo '<input id="cle-nombre" type="text" name="nombre" maxlength="100" required placeholder="Nombre. Ej: Caminata de luna llena">';
		echo '<input type="hidden" name="cl_ev_nuevo" value="1"><input type="hidden" name="emp" value="' . (int) $emp . '">';
		wp_nonce_field( 'cl_ev_nuevo_' . $emp, '_clnonce', false );
		echo '<button type="submit" class="cle-btn">+ Crear y completar</button>';
		echo '<span class="cle-s">' . ( cl_sin_revision() ? 'Se crea como borrador: nadie la ve hasta que la publiques desde «Ver cómo queda».' : 'Se crea como borrador: nadie la ve hasta que la envíes a revisión y la publiquemos.' ) . '</span></form>';
	}
	if ( $pasados ) {
		echo '<h2 class="cle-h2">Ya pasaron</h2><p class="cle-p" style="font-size:14px">Ya no se ven en la web. Para repetir una, tocá <strong>Duplicar</strong> y ponele la fecha nueva.</p>';
		foreach ( $pasados as $e ) {
			echo $card( $e ); // phpcs:ignore
		}
	}
	echo '<div class="cle-help"><strong style="color:#1f4645">¿Necesitás una mano?</strong><span class="cle-p">Mandanos los datos y las fotos de la experiencia por WhatsApp y la cargamos con vos.</span>';
	echo '<a class="cle-wa" href="' . esc_url( 'https://wa.me/59899247249?text=' . rawurlencode( 'Hola, quiero publicar una experiencia en Caminos Locales (' . get_the_title( $emp ) . ')' ) ) . '" target="_blank" rel="noopener">Escribinos por WhatsApp</a></div></div>';
	return ob_get_clean();
} );

// {echo:cl_ev_pi('dato')} — tarjeta «Mis experiencias» de Mi panel (página 22812, en Bricks).
// muestra: "1" si corresponde (rubro Actividades o ya hay alguna) · txt · accion · url.
function cl_ev_pi( $dato ) {
	$emp = function_exists( 'cl_panel_emp_id' ) ? cl_panel_emp_id() : 0;
	if ( ! $emp ) {
		return '';
	}
	$ids = cl_ev_de_emp( $emp );
	switch ( $dato ) {
		case 'muestra':
			return ( $ids || 'Sí' === get_post_meta( $emp, 'publica_eventos', true ) ) ? '1' : '';
		case 'url':
			return esc_url( cl_ev_url_lista( $emp ) );
		case 'accion':
			return $ids ? 'Ver y editar →' : 'Agregar →';
		case 'txt':
			if ( ! $ids ) {
				return 'Todavía no cargaste ninguna';
			}
			$pub  = count( array_filter( $ids, function ( $e ) {
				return ! cl_ev_pasado( $e ) && 'publish' === get_post_status( $e );
			} ) );
			$otra = count( $ids ) - $pub;
			$t    = $pub . ' publicada' . ( 1 === $pub ? '' : 's' );
			return esc_html( $t . ( $otra ? ' · ' . $otra . ' en borrador o ' . ( 1 === $otra ? 'pasada' : 'pasadas' ) : '' ) );
	}
	return '';
}
add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_ev_pi' ) );
} );

// Mi panel viejo (shortcode [cl_panel_inicio]): la tarjeta "Mis eventos" pasa a «Mis experiencias». Queda mientras exista el shortcode.
add_filter( 'do_shortcode_tag', function ( $out, $tag ) {
	if ( 'cl_panel_inicio' !== $tag || false === strpos( $out, 'Mis eventos' ) ) {
		return $out;
	}
	$emp = function_exists( 'cl_panel_emp_id' ) ? cl_panel_emp_id() : 0;
	if ( ! $emp ) {
		return $out;
	}
	if ( ! cl_ev_pi( 'muestra' ) ) {
		$card = '';
	} else {
		$card = '<a class="clp-card" href="' . cl_ev_pi( 'url' ) . '"><div class="clp-t">Mis experiencias</div><div class="clp-s">' . cl_ev_pi( 'txt' ) . '</div><div class="clp-row"><span></span><span class="clp-go">' . cl_ev_pi( 'accion' ) . '</span></div></a>';
	}
	return preg_replace( '#<a class="clp-card" href="\#" aria-disabled="true">\s*<div class="clp-t">Mis eventos</div>.*?</a>#s', $card, $out, 1 );
}, 11, 2 );

// ---------- [cl_panel_ev_cabecera] ----------

add_shortcode( 'cl_panel_ev_cabecera', function () {
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return '';
	}
	$id = cl_ev_id();
	ob_start();
	echo cl_ev_css(); // phpcs:ignore
	echo '<div class="cle">';
	if ( ! $id ) {
		echo '<a class="cle-back" href="' . esc_url( cl_ev_url_lista( cl_panel_emp_id() ) ) . '">← Mis experiencias</a><div class="cle-msg cle-err">No encontramos esta experiencia. Volvé a Mis experiencias y elegila de la lista.</div></div>';
		return ob_get_clean();
	}
	$emp  = cl_ev_emp_de( $id );
	$paso = isset( $_GET['paso'] ) && '' !== $_GET['paso'] ? sanitize_key( $_GET['paso'] ) : 'datos';
	echo '<a class="cle-back" href="' . esc_url( cl_ev_url_lista( $emp ) ) . '">← Mis experiencias</a>';
	echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><strong style="font-family:Montserrat,sans-serif;font-size:17px;color:#1f4645">' . esc_html( get_the_title( $id ) ) . '</strong>' . cl_ev_chip( $id ) . '</div>'; // phpcs:ignore
	$muestra = cl_ev_en_otro_lugar( $id ) ? 'su teléfono y contacto' : 'su teléfono, mapa y contacto';
	echo '<div class="cle-msg cle-info-msg" style="font-size:14px">Lo organiza <strong>' . esc_html( $emp ? get_the_title( $emp ) : '—' ) . '</strong>. La ficha muestra ' . esc_html( $muestra ) . '.</div>';
	if ( ! empty( $_GET['copiada'] ) ) {
		echo '<div class="cle-msg" style="background:#dcebe4;color:#174a3a"><strong>Listo, es una copia. ✓</strong> Poné la fecha nueva, revisá los datos y las fotos, y ' . ( cl_sin_revision() ? 'publicala' : 'enviala a revisión' ) . ' desde «Ver cómo queda».</div>';
	}
	if ( cl_ev_oculta( $id ) ) {
		echo '<div class="cle-msg" style="background:#f1ede4;color:#4a4335">Esta experiencia está <strong>oculta</strong>: no se ve en la web. Para mostrarla de nuevo, entrá a «Ver cómo queda».</div>';
	}
	echo '<nav class="cle-nav" aria-label="Pasos">';
	foreach ( array( 'datos' => 'Datos', 'fotos' => 'Fotos', 'vista' => 'Ver cómo queda' ) as $k => $t ) {
		echo '<a href="' . esc_url( cl_ev_url( $id, $k ) ) . '"' . ( $k === $paso ? ' class="on" aria-current="step"' : '' ) . '>' . esc_html( $t ) . '</a>';
	}
	echo '</nav></div>';
	return ob_get_clean();
} );

// Formulario: fecha y hora con el selector del celular; se muestran solo los campos del "Cuándo" elegido.
add_action( 'wp_footer', function () {
	if ( ! is_page() || ! function_exists( 'cl_panel_es_pagina' ) || ! cl_panel_es_pagina() || ! cl_ev_id() ) {
		return;
	}
	?>
<script>
(function(){
	var f=document.querySelector('form.brxe-form input[name="form-field-clevti"]');f=f&&f.form;if(!f)return;
	function el(n){return f.querySelector('[name="form-field-'+n+'"]');}
	function grupo(n){var e=el(n);return e&&e.closest('.form-group');}
	[['clevfi','date'],['clevff','date'],['clevhi','time'],['clevhf','time']].forEach(function(p){var e=el(p[0]);if(e){try{e.type=p[1];}catch(x){}}});
	function modo(){var r=f.querySelector('input[name^="form-field-clevmo"]:checked');return r?r.value:'';}
	function ver(){
		var m=modo();
		var mostrar={clevfi:m==='unico'||m==='varios',clevff:m==='varios',clevhi:m==='unico'||m==='varios',clevhf:m==='unico'||m==='varios'};
		Object.keys(mostrar).forEach(function(n){var g=grupo(n);if(g){g.style.display=mostrar[n]?'':'none';}});
		var fi=grupo('clevfi');if(fi){var l=fi.querySelector('label');if(l){l.firstChild.nodeValue=m==='varios'?'Empieza el':'Fecha';}}
		var ra=el('clevra');if(ra){ra.placeholder=m==='todo'?'Ej: Todos los sábados · Grupos de 6 o más, a coordinar':'Ej: Cupos limitados · Se suspende si llueve';}
	}
	f.querySelectorAll('input[name^="form-field-clevmo"]').forEach(function(r){r.addEventListener('change',ver);});
	ver();

	// «Lugar» y «Mapa» solo si la experiencia es en otro lugar.
	function g2(n){var e=f.querySelector('[name^="form-field-'+n+'"]');return e&&e.closest('.form-group');}
	function hg(id){var e=f.querySelector('[data-cl="'+id+'"]');return e&&e.closest('.form-group');}
	function donde(){
		var r=f.querySelector('input[name^="form-field-clevdo"]:checked'),otro=r&&r.value==='otro';
		[g2('clevlu'),hg('clhevl'),g2('clevub'),hg('clhevm')].forEach(function(g){if(g){g.style.display=otro?'':'none';}});
		var he=hg('clhdoe');if(he){he.style.display=otro?'none':'';}
	}
	f.querySelectorAll('input[name^="form-field-clevdo"]').forEach(function(r){r.addEventListener('change',donde);});
	donde();

	// Categorías plegadas, con las elegidas a la vista.
	var gc=g2('clevca');
	if(gc){
		var det=document.createElement('details');det.className='clp-mas';
		var sum=document.createElement('summary');det.appendChild(sum);
		gc.parentNode.insertBefore(det,gc);det.appendChild(gc);
		function res(){
			var n=[].map.call(gc.querySelectorAll('input:checked'),function(c){var l=(c.id&&f.querySelector('label[for="'+c.id+'"]'))||c.parentNode;return l.textContent.trim();});
			sum.textContent=(n.length?'Categorías: '+n.join(', '):'Categorías: las de tu emprendimiento')+(det.open?' ▴':' · cambiar ▾');
		}
		det.addEventListener('toggle',res);gc.addEventListener('change',res);res();
	}
})();
</script>
	<?php
}, 30 );

// ---------- [cl_panel_ev_vista] ----------

add_shortcode( 'cl_panel_ev_vista', function () {
	$id = cl_ev_id();
	if ( ! $id ) {
		return '';
	}
	$estado = get_post_status( $id );
	$emp    = cl_ev_emp_de( $id );
	list( $oblig, $suger ) = cl_ev_chequeo( $id );
	$faltan = array_filter( $oblig, function ( $o ) {
		return ! $o[1];
	} );
	$breve  = trim( (string) get_post_meta( $id, 'breve_descripcion', true ) );
	$precio = trim( (string) get_post_meta( $id, 'precio_de_la_actividad', true ) );
	ob_start();
	?>
<style>
.clv{display:flex;flex-direction:column;gap:20px;font-family:'Source Sans 3',system-ui,sans-serif;color:#23302f}
.clv-card{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(31,70,69,.12)}
.clv-foto{aspect-ratio:5/4;background:#eeeae1 center/cover no-repeat;display:flex;align-items:center;justify-content:center;color:#7a8583;font-size:14px}
.clv-body{padding:16px;display:flex;flex-direction:column;gap:6px}
.clv-lugar{font-size:13px;font-weight:700;color:#9a6055;text-transform:uppercase;letter-spacing:.3px}
.clv-tit{font-family:Montserrat,sans-serif;font-weight:700;font-size:20px;color:#1f4645;margin:0}
.clv-link{display:flex;align-items:center;justify-content:center;min-height:48px;border-radius:12px;border:1px solid #1f4645;color:#1f4645;font-weight:700;text-decoration:none;background:#fff}
.clv-box{background:#fff;border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:4px}
.clv-h{font-family:Montserrat,sans-serif;font-weight:700;font-size:17px;color:#1f4645;margin:0 0 6px}
.clv-row{display:flex;align-items:center;gap:10px;min-height:40px;font-size:15px}
.clv-i{width:24px;height:24px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0}
.clv-ok{background:#dcebe4;color:#174a3a}.clv-no{background:#fbe3df;color:#8a2a1f}.clv-opc{background:#fdf0d5;color:#6b4300}
.clv-row a{margin-left:auto;color:#9a6055;font-weight:700;text-decoration:none;white-space:nowrap}
.clv-msg{border-radius:12px;padding:14px 16px;font-size:15px;line-height:1.45}
.clv-info{background:#e3ece9;color:#1f4645}.clv-warn{background:#fdf0d5;color:#5c3a00}.clv-err{background:#fbe3df;color:#8a2a1f;font-weight:700}
.clv .clv-btn{width:100%;min-height:52px;border:0;border-radius:12px;background:#9a6055;color:#fff;font-weight:700;font-size:17px;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;padding:0 16px!important;box-sizing:border-box}.clv .clv-btn:hover{background:#7f4c43}
.clv .clv-btn[disabled]{background:#c9c2b3;cursor:not-allowed}
.clv-opc-form{display:flex;flex-direction:column;gap:6px;padding:8px 0}
.clv-opc-form+.clv-opc-form{border-top:1px solid #ece7dc}
.clv .clv-btn2{min-height:46px;border-radius:12px;border:1px solid #1f4645;background:#fff;color:#1f4645;font-weight:700;font-size:16px;cursor:pointer;font-family:inherit;padding:0 16px!important}
.clv .clv-btn2:hover{background:#eef3f1}
.clv .clv-btn2.clv-borrar{border-color:#c98b80;color:#8a2a1f}
.clv .clv-btn2.cl-seguro{background:#fbe3df;border-color:#e2a59a;color:#8a2a1f}
.clv-nota{font-size:14px;line-height:1.4;color:#5b6a68}
</style>
<div class="clv">
	<?php if ( ! empty( $_GET['listo'] ) && 'publish' === $estado ) : ?>
		<div class="clv-msg clv-info"><strong>¡Listo, tu experiencia ya está publicada! ✓</strong><br>Ya se ve en Caminos Locales. Podés seguir cambiándola cuando quieras.</div>
	<?php elseif ( ! empty( $_GET['enviado'] ) ) : ?>
		<div class="clv-msg clv-info"><strong>¡Listo, la enviaste! ✓</strong><br>Tu experiencia está en revisión. Te avisamos por email cuando esté publicada, normalmente en 1 o 2 días.</div>
	<?php elseif ( ! empty( $_GET['publicada'] ) && 'publish' === $estado ) : ?>
		<div class="clv-msg clv-info"><strong>¡Listo! ✓</strong> Tu experiencia se ve de nuevo en la web.</div>
	<?php elseif ( ! empty( $_GET['oculta'] ) && 'draft' === $estado ) : ?>
		<div class="clv-msg clv-info"><strong>Listo, la ocultaste. ✓</strong> Ya no se ve en la web. Podés volver a publicarla cuando quieras.</div>
	<?php elseif ( ! empty( $_GET['faltan'] ) ) : ?>
		<div class="clv-msg clv-err">Todavía falta completar lo marcado con ✕.</div>
	<?php endif; ?>
	<?php if ( cl_ev_pasado( $id ) ) : ?>
		<div class="clv-msg clv-warn">Esta experiencia ya pasó y no se ve en la web. Para repetirla, poné una fecha nueva en Datos.</div>
	<?php endif; ?>
	<div>
		<div style="font-size:14px;font-weight:700;color:#5b6a68;margin-bottom:8px">Así se ve en el listado de experiencias</div>
		<div class="clv-card">
			<?php echo cl_ev_img_html( $id, 'medium_large', 'clv-foto', 'Sin foto principal ni afiche' ); // phpcs:ignore ?>
			<div class="clv-body">
				<div class="clv-lugar"><?php echo cl_ev_cuando_corto( $id ); // phpcs:ignore ?></div>
				<h2 class="clv-tit"><?php echo esc_html( get_the_title( $id ) ); ?></h2>
				<?php if ( $breve ) : ?><div style="font-size:15px;color:#3d4b49"><?php echo esc_html( $breve ); ?></div><?php endif; ?>
				<?php if ( $precio ) : ?><div style="font-size:15px;font-weight:700;color:#1f4645"><?php echo esc_html( $precio ); ?></div><?php endif; ?>
			</div>
		</div>
	</div>
	<a class="clv-link" href="<?php echo esc_url( cl_ev_url_vista( $id ) ); ?>" target="_blank" rel="noopener">Ver la ficha completa ↗</a>
	<div class="clv-box">
		<h3 class="clv-h">Antes de publicar</h3>
		<?php foreach ( $oblig as $o ) : ?>
			<div class="clv-row"><span class="clv-i <?php echo $o[1] ? 'clv-ok' : 'clv-no'; ?>"><?php echo $o[1] ? '✓' : '✕'; ?></span><?php echo esc_html( $o[0] ); ?><?php if ( ! $o[1] ) : ?><a href="<?php echo esc_url( cl_ev_url( $id, $o[2] ) ); ?>">Completar</a><?php endif; ?></div>
		<?php endforeach; ?>
		<?php foreach ( $suger as $o ) : ?>
			<?php if ( ! $o[1] ) : ?>
				<div class="clv-row"><span class="clv-i clv-opc">!</span><?php echo esc_html( $o[0] ); ?> <span style="color:#7a8583;font-size:14px">(opcional)</span><a href="<?php echo esc_url( cl_ev_url( $id, $o[2] ) ); ?>">Agregar</a></div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<?php if ( cl_ev_oculta( $id ) ) : ?>
		<div class="clv-msg clv-warn">Está oculta: no se ve en la web. <?php echo cl_sin_revision() ? 'La podés volver a publicar al momento.' : 'Como ya la revisamos, la podés volver a publicar al momento.'; ?></div>
		<form method="post">
			<input type="hidden" name="cl_ev_republicar" value="1">
			<input type="hidden" name="ev" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'cl_ev_republicar_' . $id, '_clnonce', false ); ?>
			<button type="submit" class="clv-btn"<?php echo $faltan ? ' disabled' : ''; ?>>Volver a publicar</button>
		</form>
	<?php elseif ( 'draft' === $estado || ( 'pending' === $estado && cl_sin_revision() ) ) : ?>
		<?php if ( cl_sin_revision() ) : ?>
			<div class="clv-msg clv-warn">Cuando completes lo obligatorio, publicala: se ve en la web al momento.</div>
		<?php else : ?>
			<div class="clv-msg clv-warn">Cuando la envíes, la revisamos y la publicamos. Te avisamos por email, normalmente en 1 o 2 días.</div>
		<?php endif; ?>
		<form method="post">
			<input type="hidden" name="cl_ev_enviar" value="1">
			<input type="hidden" name="ev" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'cl_ev_enviar_' . $id, '_clnonce', false ); ?>
			<button type="submit" class="clv-btn"<?php echo $faltan ? ' disabled' : ''; ?>><?php echo cl_sin_revision() ? 'Publicar' : 'Enviar a revisión'; ?></button>
		</form>
	<?php elseif ( 'pending' === $estado ) : ?>
		<?php if ( empty( $_GET['enviado'] ) ) : ?>
			<div class="clv-msg clv-info">Tu experiencia está en revisión. Te avisamos por email cuando esté publicada. Mientras tanto podés seguir editándola.</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="clv-msg clv-info">Tu experiencia está publicada: lo que guardás se ve en la web al momento.</div>
	<?php endif; ?>
	<div class="clv-box">
		<h3 class="clv-h">Más opciones</h3>
		<?php if ( 'publish' === $estado ) : ?>
			<form method="post" class="clv-opc-form">
				<input type="hidden" name="cl_ev_ocultar" value="1">
				<input type="hidden" name="ev" value="<?php echo (int) $id; ?>">
				<?php wp_nonce_field( 'cl_ev_ocultar_' . $id, '_clnonce', false ); ?>
				<button type="submit" class="clv-btn2">Ocultar de la web</button>
				<span class="clv-nota">Si se suspende o se llenó el cupo. No se borra nada y la podés volver a publicar.</span>
			</form>
		<?php endif; ?>
		<form method="post" class="clv-opc-form">
			<input type="hidden" name="cl_ev_duplicar" value="1">
			<input type="hidden" name="ev" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'cl_ev_duplicar_' . $id, '_clnonce', false ); ?>
			<button type="submit" class="clv-btn2">Duplicar</button>
			<span class="clv-nota">Crea una copia en borrador con los mismos textos y fotos, sin fecha. Ideal si la actividad se repite.</span>
		</form>
		<form method="post" class="clv-opc-form">
			<input type="hidden" name="cl_ev_borrar" value="1">
			<input type="hidden" name="ev" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'cl_ev_borrar_' . $id, '_clnonce', false ); ?>
			<button type="submit" class="clv-btn2 clv-borrar" data-confirma="¿Seguro? Tocá de nuevo para borrar">Borrar</button>
			<span class="clv-nota">Se borra de la web y de tu panel. Si fue un error, escribinos y la recuperamos.</span>
		</form>
		<?php echo cl_panel_confirma_js(); // phpcs:ignore ?>
	</div>
	<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<a class="clv-link" href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>">Revisar y publicar en wp-admin (solo admin)</a>
	<?php endif; ?>
</div>
	<?php
	return ob_get_clean();
} );

// ---------- Fotos del evento (principal + 4) ----------

function cl_ev_fotos_slots() {
	$s    = array(
		'principal' => array( 'meta' => '_thumbnail_id', 'label' => 'Foto principal' ),
		'afiche'    => array( 'meta' => 'afiche', 'key' => 'field_6ac9071ec7d82', 'label' => 'Afiche' ),
	);
	$keys = array( 'field_670ffdc2c359a', 'field_670ffdf1c359b', 'field_670ffdfcc359c', 'field_670ffe07c359d' );
	foreach ( $keys as $i => $k ) {
		$s[ 'g' . ( $i + 1 ) ] = array( 'meta' => 'galeria_eventos_foto' . ( $i + 1 ), 'key' => $k, 'label' => 'Foto ' . ( $i + 1 ) );
	}
	return $s;
}

function cl_ev_foto_leer( $id, $slot ) {
	$s = cl_ev_fotos_slots();
	$v = get_post_meta( $id, $s[ $slot ]['meta'], true );
	return ( is_numeric( $v ) && (int) $v > 0 && wp_attachment_is_image( (int) $v ) ) ? (int) $v : 0;
}

function cl_ev_foto_guardar( $id, $slot, $att ) {
	if ( 'principal' === $slot ) {
		return $att ? set_post_thumbnail( $id, $att ) : delete_post_thumbnail( $id );
	}
	$s = cl_ev_fotos_slots();
	update_post_meta( $id, $s[ $slot ]['meta'], $att ? (int) $att : '' );
	update_post_meta( $id, '_' . $s[ $slot ]['meta'], $s[ $slot ]['key'] );
	if ( 'afiche' === $slot ) {
		return true;
	}
	if ( ! get_post_meta( $id, '_galeria_eventos', true ) ) {
		update_post_meta( $id, 'galeria_eventos', '' );
		update_post_meta( $id, '_galeria_eventos', 'field_61b6697fb0974' );
	}
	return true;
}

function cl_ev_foto_compactar( $id ) {
	$fotos = cl_ev_galeria( $id );
	for ( $i = 1; $i <= 4; $i++ ) {
		cl_ev_foto_guardar( $id, 'g' . $i, isset( $fotos[ $i - 1 ] ) ? $fotos[ $i - 1 ] : 0 );
	}
}

add_action( 'wp_ajax_cl_foto_ev', function () {
	$error = function ( $msg ) {
		wp_send_json( array( 'ok' => false, 'msg' => $msg ) );
	};
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() || ! function_exists( 'cl_fotos_quizas_borrar' ) ) {
		$error( 'Tu sesión se cerró. Volvé a entrar y probá de nuevo.' );
	}
	$id = cl_ev_id( isset( $_POST['ev'] ) ? (int) $_POST['ev'] : 0 );
	if ( ! $id || ! check_ajax_referer( 'cl_fotos_ev_' . $id, 'nonce', false ) ) {
		$error( 'No se pudo guardar. Recargá la página y probá de nuevo.' );
	}
	$slot  = isset( $_POST['slot'] ) ? sanitize_key( $_POST['slot'] ) : '';
	$slots = cl_ev_fotos_slots();
	if ( ! isset( $slots[ $slot ] ) ) {
		$error( 'Lugar de foto desconocido.' );
	}
	$op    = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	$vieja = cl_ev_foto_leer( $id, $slot );
	$emp   = cl_ev_emp_de( $id );

	if ( 'quitar' === $op ) {
		// Publicada: siempre tiene que quedar foto principal o afiche.
		if ( 'publish' === get_post_status( $id ) ) {
			if ( 'principal' === $slot && ! cl_ev_afiche( $id ) ) {
				$error( 'La foto principal no se puede quitar de una experiencia publicada: tocá Cambiar para poner otra, o subí un afiche.' );
			}
			if ( 'afiche' === $slot && ! get_post_thumbnail_id( $id ) ) {
				$error( 'El afiche no se puede quitar: es la única imagen de la experiencia. Tocá Cambiar, o subí una foto principal.' );
			}
		}
		cl_ev_foto_guardar( $id, $slot, 0 );
		if ( 'principal' !== $slot && 'afiche' !== $slot ) {
			cl_ev_foto_compactar( $id );
		}
		if ( $vieja ) {
			cl_fotos_quizas_borrar( $vieja );
		}
		wp_send_json( array( 'ok' => true, 'id' => 0, 'url' => '', 'recargar' => true ) );
	}
	if ( 'subir' !== $op || empty( $_FILES['foto'] ) ) {
		$error( 'No llegó ninguna foto.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$slug   = $emp ? sanitize_title( get_post_field( 'post_name', $emp ) ) : '';
	$slug   = $slug ? $slug : 'emp-' . (int) $emp;
	$dir_fn = function ( $d ) use ( $slug ) {
		$d['subdir'] = '/emprendimientos/' . $slug;
		$d['path']   = $d['basedir'] . $d['subdir'];
		$d['url']    = $d['baseurl'] . $d['subdir'];
		return $d;
	};
	$nombre_fn = function ( $file ) {
		if ( empty( $file['error'] ) && ! preg_match( '/^cl-[a-z0-9]{8}\./', $file['name'] ) ) {
			$ext          = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			$file['name'] = 'cl-' . strtolower( wp_generate_password( 8, false ) ) . '.' . ( $ext ? $ext : 'jpg' );
		}
		return $file;
	};
	$es_emp = function_exists( 'cl_es_emprendedor' ) && cl_es_emprendedor();
	if ( ! $es_emp ) {
		add_filter( 'upload_dir', $dir_fn, 20 );
		add_filter( 'wp_handle_upload_prefilter', $nombre_fn, 20 );
	}
	$texto = get_the_title( $id ) . ' — ' . $slots[ $slot ]['label'];
	$att   = media_handle_upload( 'foto', $id, array( 'post_title' => $texto ) );
	if ( ! $es_emp ) {
		remove_filter( 'upload_dir', $dir_fn, 20 );
		remove_filter( 'wp_handle_upload_prefilter', $nombre_fn, 20 );
	}
	if ( is_wp_error( $att ) ) {
		$error( $att->get_error_message() );
	}
	update_post_meta( $att, '_cl_panel_foto', 1 );
	update_post_meta( $att, '_wp_attachment_image_alt', $texto );
	if ( $emp && function_exists( 'cl_carpeta_asignar_foto' ) && function_exists( 'cl_carpeta_de_emprendimiento' ) ) {
		cl_carpeta_asignar_foto( $att, cl_carpeta_de_emprendimiento( $emp ) );
	}
	$dueno = (int) get_post_field( 'post_author', $id );
	if ( $dueno && $dueno !== (int) get_post_field( 'post_author', $att ) ) {
		wp_update_post( array( 'ID' => $att, 'post_author' => $dueno ) );
	}
	cl_ev_foto_guardar( $id, $slot, $att );
	if ( 'principal' !== $slot && 'afiche' !== $slot ) {
		cl_ev_foto_compactar( $id );
	}
	if ( $vieja && $vieja !== $att ) {
		cl_fotos_quizas_borrar( $vieja );
	}
	wp_send_json( array( 'ok' => true, 'id' => $att, 'url' => function_exists( 'cl_fotos_url' ) ? cl_fotos_url( $att ) : wp_get_attachment_url( $att ), 'recargar' => ! $vieja && ( 'principal' !== $slot || 'publish' === get_post_status( $id ) ) ) );
} );

add_shortcode( 'cl_panel_ev_fotos', function () {
	$id = cl_ev_id();
	if ( ! $id ) {
		return '';
	}
	$publicado = 'publish' === get_post_status( $id );
	$tarjeta   = function ( $slot, $label, $ayuda = '' ) use ( $id, $publicado ) {
		$att = cl_ev_foto_leer( $id, $slot );
		$url = $att ? ( function_exists( 'cl_fotos_url' ) ? cl_fotos_url( $att ) : wp_get_attachment_url( $att ) ) : '';
		// Publicada: no se puede quitar la única imagen que queda (foto principal o afiche).
		$sin = ( $publicado && ( ( 'principal' === $slot && ! cl_ev_afiche( $id ) ) || ( 'afiche' === $slot && ! get_post_thumbnail_id( $id ) ) ) ) ? ' data-sinquitar="1"' : '';
		$h   = '<div class="clf-slot" data-slot="' . esc_attr( $slot ) . '"' . $sin . '><div class="clf-lbl">' . esc_html( $label ) . '</div>';
		$h  .= $ayuda ? '<div class="clf-ayuda">' . esc_html( $ayuda ) . '</div>' : '';
		$h  .= '<div class="clf-img">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '<span class="clf-vacio">' . ( 'principal' === $slot ? 'Sin foto' : ( 'afiche' === $slot ? 'Sin afiche' : '+ Agregar foto' ) ) . '</span>' ) . '<div class="clf-estado" hidden></div></div>';
		$h  .= '<div class="clf-btns"><label class="clf-btn clf-subir"><input type="file" accept="image/*" hidden><span>' . ( $url ? 'Cambiar' : 'Subir foto' ) . '</span></label>';
		$h  .= '<button type="button" class="clf-btn clf-quitar"' . ( ( $url && ! $sin ) ? '' : ' hidden' ) . '>Quitar</button></div><div class="clf-error" hidden></div></div>';
		return $h;
	};
	$n = count( cl_ev_galeria( $id ) );
	ob_start();
	?>
<style>
.clf{display:flex;flex-direction:column;gap:22px;font-family:'Source Sans 3',system-ui,sans-serif;color:#23302f}
.clf-sec{display:flex;flex-direction:column;gap:10px}
.clf-h{font-family:Montserrat,sans-serif;font-weight:700;font-size:18px;color:#1f4645;margin:0}
.clf-p{font-size:15px;line-height:1.45;color:#3d4b49;margin:0}
.clf-grilla{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.clf-slot{background:#fff;border-radius:14px;padding:12px;display:flex;flex-direction:column;gap:8px;box-shadow:0 1px 2px rgba(31,70,69,.08)}
.clf-lbl{font-weight:700;font-size:15px}
.clf-ayuda{font-size:14px;line-height:1.4;color:#5b6a68}
.clf-img{position:relative;aspect-ratio:4/3;border-radius:10px;overflow:hidden;background:#eeeae1;display:flex;align-items:center;justify-content:center}
.clf-img img{width:100%;height:100%;object-fit:cover;display:block}
.clf-slot[data-slot="afiche"] .clf-img{aspect-ratio:4/5;max-width:280px}
.clf-slot[data-slot="afiche"] .clf-img img{object-fit:contain}
.clf-vacio{font-size:14px;color:#7a8583}
.clf-estado{position:absolute;inset:auto 0 0 0;background:rgba(31,70,69,.88);color:#fff;font-size:14px;font-weight:700;padding:8px 10px;text-align:center}
.clf-btns{display:flex;gap:8px;flex-wrap:wrap}
.clf-btn{flex:1 1 auto;min-height:44px;border-radius:10px;border:1px solid #1f4645;background:#fff;color:#1f4645;font-weight:700;font-size:15px;display:inline-flex;align-items:center;justify-content:center;padding:0 12px;cursor:pointer;font-family:inherit}
.clf-subir{background:#1f4645;color:#fff}
.clf-quitar{border-color:#c9c2b3;color:#6b3a2c}
.clf-quitar.clf-seguro{background:#fbe3df;border-color:#8a2a1f;color:#8a2a1f}
.clf-error{background:#fbe3df;color:#8a2a1f;border-radius:10px;padding:8px 10px;font-size:14px;font-weight:700}
.clf-ocupado .clf-btn{opacity:.5;pointer-events:none}
</style>
<div class="clf" data-ev="<?php echo (int) $id; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cl_fotos_ev_' . $id ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
	<p class="clf-p">Subí las fotos tal como salen del celular: nosotros las achicamos. Cada foto se guarda sola, apenas termina de subir. Si todavía no la hiciste, usá fotos de una edición anterior o del lugar.</p>
	<div class="clf-sec"><?php echo $tarjeta( 'principal', 'Foto principal', 'La que aparece en el listado. Mejor con personas haciendo la actividad. Si no tenés, alcanza con el afiche.' ); // phpcs:ignore ?></div>
	<div class="clf-sec"><?php echo $tarjeta( 'afiche', 'Afiche (opcional)', 'Si ya tenés el afiche, por ejemplo el de Instagram, subilo acá: se ve completo en la ficha. Si no hay foto principal, se usa también en el listado. La fecha, el lugar y el precio cargalos igual en Datos: el texto del afiche no lo lee Google.' ); // phpcs:ignore ?></div>
	<div class="clf-sec">
		<h2 class="clf-h">Más fotos</h2>
		<p class="clf-p">Hasta 4, opcionales.</p>
		<div class="clf-grilla">
			<?php
			for ( $i = 1; $i <= min( 4, $n + 1 ); $i++ ) {
				echo $tarjeta( 'g' . $i, $i <= $n ? 'Foto ' . $i : 'Nueva foto' ); // phpcs:ignore
			}
			?>
		</div>
	</div>
</div>
<script>
(function(){
var raiz=document.currentScript.previousElementSibling;if(!raiz||!raiz.classList.contains('clf'))return;
var AJAX=raiz.dataset.ajax,EV=raiz.dataset.ev,NONCE=raiz.dataset.nonce;
function achicar(file){
	return new Promise(function(ok){
		var tipo=(file.type||'').toLowerCase();
		if(!/^image\/(jpeg|png|webp)$/.test(tipo)||!window.createImageBitmap||file.size<=600*1024){ok(file);return;}
		createImageBitmap(file,{imageOrientation:'from-image'}).then(function(bmp){
			var max=1920,w=bmp.width,h=bmp.height,k=Math.min(1,max/Math.max(w,h));
			var c=document.createElement('canvas');c.width=Math.round(w*k);c.height=Math.round(h*k);
			c.getContext('2d').drawImage(bmp,0,0,c.width,c.height);
			c.toBlob(function(b){ok(b&&b.size<file.size?new File([b],'foto.jpg',{type:'image/jpeg'}):file);},'image/jpeg',0.85);
		}).catch(function(){ok(file);});
	});
}
function enviar(slot,datos,progreso){
	return new Promise(function(ok){
		var x=new XMLHttpRequest();x.open('POST',AJAX);
		if(progreso&&x.upload){x.upload.onprogress=function(e){if(e.lengthComputable)progreso(Math.round(e.loaded/e.total*100));};}
		x.onload=function(){var r;try{r=JSON.parse(x.responseText);}catch(e){r={ok:false,msg:'El servidor no respondió bien. Probá de nuevo.'};}ok(r);};
		x.onerror=function(){ok({ok:false,msg:'Se cortó la conexión. Probá de nuevo cuando tengas mejor señal.'});};
		datos.append('action','cl_foto_ev');datos.append('ev',EV);datos.append('nonce',NONCE);datos.append('slot',slot);
		x.send(datos);
	});
}
raiz.querySelectorAll('.clf-slot').forEach(function(el){
	var slot=el.dataset.slot,input=el.querySelector('input[type=file]'),txt=el.querySelector('.clf-subir span'),
		quitar=el.querySelector('.clf-quitar'),caja=el.querySelector('.clf-img'),estado=el.querySelector('.clf-estado'),err=el.querySelector('.clf-error');
	function mostrar(url){
		var v=caja.querySelector('img,.clf-vacio');if(v)v.remove();
		var n;if(url){n=document.createElement('img');n.src=url;n.alt='';}else{n=document.createElement('span');n.className='clf-vacio';n.textContent='Sin foto';}
		caja.insertBefore(n,estado);txt.textContent=url?'Cambiar':'Subir foto';quitar.hidden=!url||el.dataset.sinquitar==='1';
	}
	function ocupado(s,m){el.classList.toggle('clf-ocupado',s);estado.hidden=!s;estado.textContent=m||'';}
	function falla(m){err.textContent=m;err.hidden=false;}
	function listo(r){ocupado(false);if(!r.ok){falla(r.msg);return;}if(r.recargar){ocupado(true,'Listo ✓');location.reload();return;}mostrar(r.url);}
	input.addEventListener('change',function(){
		var f=input.files&&input.files[0];input.value='';if(!f)return;err.hidden=true;
		ocupado(true,'Preparando…');
		achicar(f).then(function(archivo){
			var d=new FormData();d.append('op','subir');d.append('foto',archivo,archivo.name||'foto.jpg');
			return enviar(slot,d,function(p){estado.textContent=p<100?'Subiendo… '+p+' %':'Optimizando…';});
		}).then(listo);
	});
	var tQ;
	quitar.addEventListener('click',function(){
		if(!quitar.classList.contains('clf-seguro')){quitar.classList.add('clf-seguro');quitar.textContent='¿Seguro? Tocá de nuevo';clearTimeout(tQ);tQ=setTimeout(function(){quitar.classList.remove('clf-seguro');quitar.textContent='Quitar';},4000);return;}
		clearTimeout(tQ);quitar.classList.remove('clf-seguro');quitar.textContent='Quitar';err.hidden=true;ocupado(true,'Quitando…');
		var d=new FormData();d.append('op','quitar');
		enviar(slot,d).then(listo);
	});
});
})();
</script>
	<?php
	return ob_get_clean();
} );

// ---------- "Próximos eventos" (ficha del emprendimiento) y "Otras propuestas" (ficha del evento) ----------

// Eventos publicados y vigentes de un emprendimiento, ordenados por fecha (los de todo el año al final).
function cl_ev_proximos_ids( $emp, $max = 6, $excluir = 0 ) {
	if ( ! $emp ) {
		return array();
	}
	return get_posts( array(
		'post_type'    => 'evento',
		'post_status'  => 'publish',
		'numberposts'  => $max,
		'fields'       => 'ids',
		'post__not_in' => $excluir ? array( (int) $excluir ) : array(),
		'meta_key'     => '_cl_ev_orden',
		'orderby'      => array( 'meta_value_num' => 'ASC', 'title' => 'ASC' ),
		'meta_query'   => array(
			array( 'key' => 'corresponde_a_emprendimiento', 'value' => '"' . (int) $emp . '"', 'compare' => 'LIKE' ),
			array( 'key' => '_cl_ev_fin', 'value' => cl_ev_hoy(), 'compare' => '>=', 'type' => 'NUMERIC' ),
		),
	) );
}

// Emprendimiento y evento a excluir según la ficha en la que se está.
function cl_ev_contexto() {
	$id = get_the_ID();
	if ( $id && 'emprendimientos' === get_post_type( $id ) ) {
		return array( $id, 0 );
	}
	if ( $id && 'evento' === get_post_type( $id ) ) {
		return array( cl_ev_emp_de( $id ), $id );
	}
	return array( 0, 0 );
}

// {echo:cl_ev_proximos_hay} → "1" si hay eventos para mostrar (en la ficha del evento: otros del mismo emprendimiento).
function cl_ev_proximos_hay() {
	list( $emp, $excluir ) = cl_ev_contexto();
	return cl_ev_proximos_ids( $emp, 1, $excluir ) ? '1' : '';
}

// {echo:cl_ev_donde} → "Senderismo Aiguá · Aiguá" (quién organiza y dónde), para las tarjetas de la Agenda.
function cl_ev_donde( $post_id = 0 ) {
	$id  = absint( $post_id ) ? absint( $post_id ) : get_the_ID();
	$emp = ( $id && 'evento' === get_post_type( $id ) ) ? cl_ev_emp_de( $id ) : 0;
	if ( ! $emp ) {
		return '';
	}
	if ( cl_ev_en_otro_lugar( $id ) && '' !== trim( (string) get_post_meta( $id, 'lugar', true ) ) ) {
		return esc_html( trim( (string) get_post_meta( $id, 'lugar', true ) ) );
	}
	$lugar = trim( (string) get_post_meta( $emp, 'localidad', true ) );
	if ( '' === $lugar ) {
		$reg   = wp_get_object_terms( $id, 'region', array( 'fields' => 'names' ) );
		$lugar = is_wp_error( $reg ) ? '' : implode( ', ', $reg );
	}
	return esc_html( get_the_title( $emp ) . ( '' !== $lugar ? ' · ' . $lugar : '' ) );
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_ev_proximos_hay', 'cl_ev_donde' ) );
} );

// [cl_ev_proximos] — tarjetas chicas: foto, fecha corta y nombre.
add_shortcode( 'cl_ev_proximos', function () {
	list( $emp, $excluir ) = cl_ev_contexto();
	$ids = cl_ev_proximos_ids( $emp, 6, $excluir );
	if ( ! $ids ) {
		return '';
	}
	$h  = '<style>.clpe{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px;width:100%}@media(min-width:768px){.clpe{grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px}}';
	$h .= '.clpe a{display:flex;flex-direction:column;background:#fff;border:1px solid #d9d4c7;border-radius:14px;overflow:hidden;text-decoration:none;color:#23302f;transition:box-shadow .2s,transform .2s}';
	$h .= '.clpe a:hover{box-shadow:0 4px 14px rgba(31,70,69,.15);transform:translateY(-2px)}';
	$h .= '.clpe-f{aspect-ratio:5/4;background:#eeeae1 center/cover no-repeat}';
	$h .= '.clpe-b{padding:10px 12px 12px;display:flex;flex-direction:column;gap:4px}';
	$h .= '.clpe-d{font-size:13px;font-weight:700;color:#9a6055;text-transform:uppercase;letter-spacing:.3px}';
	$h .= '.clpe-t{font-size:16px;font-weight:600;line-height:1.3;color:#1f4645}</style><div class="clpe">';
	foreach ( $ids as $e ) {
		$h   .= '<a href="' . esc_url( get_permalink( $e ) ) . '">' . cl_ev_img_html( $e, 'medium_large', 'clpe-f' );
		$h   .= '<div class="clpe-b"><div class="clpe-d">' . cl_ev_cuando_corto( $e ) . '</div><div class="clpe-t">' . esc_html( get_the_title( $e ) ) . '</div></div></a>';
	}
	return $h . '</div>';
} );

