<?php
/**
 * Ficha mínima, contacto visible y orden de los listados (1.2.0).
 *
 * 1. Contacto visible: cada dato de contacto del emprendimiento (teléfono/WhatsApp, email, web, Instagram, Facebook)
 *    se puede ocultar de la web. Se guarda en la meta _cl_contacto_oculto (lista de campos ocultos; por defecto
 *    ninguno). En las páginas públicas esos campos se leen vacíos, así que no aparecen en ningún lado (fichas,
 *    alojamientos, experiencias, Servicios locales). En wp-admin, el panel, los correos y las tareas programadas
 *    se ven siempre. Reemplaza a «¿Publicamos tu teléfono en la web?» (telefono_publico) de la ficha compacta.
 *
 * 2. Ficha mínima («Perfil en construcción»): el emprendedor puede publicar con lo mínimo y completar después.
 *    Servicio (ficha compacta): nombre, qué ofrece, región y localidad y un contacto visible (sin foto: la tarjeta
 *    usa el ícono del rubro, {echo:cl_srv_icono_ficha}). El resto: además, foto principal y breve descripción.
 *    Mientras le falte algo de la ficha completa, _cl_en_construccion = 1 y {echo:cl_en_construccion} da 1
 *    (marca «Perfil en construcción — próximamente más información» en Bricks).
 *
 * 3. Listados: Emprendimientos (pvnicx) e Inicio (gcwygx) muestran solo fichas que cumplen el mínimo
 *    (meta _cl_sin_minimo, excluidas por ID). Orden aleatorio con semilla diaria en Emprendimientos, Alojamientos
 *    (291c69) e Inicio: el mismo orden todo el día, cambia cada día. La caché se purga a las 00:05.
 *    Experiencias sigue por fecha (las próximas primero).
 */

defined( 'ABSPATH' ) || exit;

// ======================================================================
// 1. Contacto visible
// ======================================================================

function cl_contacto_campos() {
	return array(
		'telefono_contacto' => 'Teléfono / WhatsApp',
		'email-emp'         => 'Email',
		'url_sitio_web'     => 'Sitio web',
		'instagramemp'      => 'Instagram',
		'facebookemp'       => 'Facebook',
	);
}

function cl_contacto_ocultos( $id ) {
	$v = get_post_meta( (int) $id, '_cl_contacto_oculto', true );
	return is_array( $v ) ? array_values( array_intersect( array_keys( cl_contacto_campos() ), $v ) ) : array();
}

function cl_contacto_visible( $id, $campo ) {
	return ! in_array( $campo, cl_contacto_ocultos( $id ), true );
}

// Lee un campo sin el filtro de visibilidad (para la red y para los chequeos).
function cl_meta_crudo( $id, $clave ) {
	$GLOBALS['cl_contacto_sin_filtro'] = true;
	$v = get_post_meta( (int) $id, $clave, true );
	$GLOBALS['cl_contacto_sin_filtro'] = false;
	return $v;
}

// ¿Este pedido es una página pública donde hay que ocultar?
function cl_contacto_filtrar() {
	if ( ! empty( $GLOBALS['cl_contacto_sin_filtro'] ) || ! did_action( 'wp' ) ) {
		return false; // Antes de saber qué página es (o en ajax, cron, wp-admin): no se toca nada.
	}
	if ( is_admin() || wp_doing_cron() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST && current_user_can( 'edit_others_posts' ) ) {
		return false;
	}
	if ( function_exists( 'cl_panel_es_pagina' ) && is_page() && cl_panel_es_pagina( get_queried_object_id() ) ) {
		return false; // En el panel cada uno ve sus datos.
	}
	return true;
}

add_filter( 'get_post_metadata', function ( $valor, $id, $clave, $single ) {
	static $campos = null;
	if ( null === $campos ) {
		$campos = array_keys( cl_contacto_campos() );
	}
	if ( ! in_array( $clave, $campos, true ) || ! cl_contacto_filtrar() || 'emprendimientos' !== get_post_type( $id ) ) {
		return $valor;
	}
	if ( ! in_array( $clave, cl_contacto_ocultos( $id ), true ) ) {
		return $valor;
	}
	return $single ? array( '' ) : array();
}, 10, 4 );

// Guarda la lista desde el panel: $visibles = campos marcados «Se muestra en la web». Devuelve true si cambió.
function cl_contacto_guardar( $id, $visibles ) {
	$visibles = array_intersect( array_keys( cl_contacto_campos() ), array_map( 'sanitize_text_field', (array) $visibles ) );
	$ocultos  = array_values( array_diff( array_keys( cl_contacto_campos() ), $visibles ) );
	if ( $ocultos === cl_contacto_ocultos( $id ) ) {
		return false;
	}
	update_post_meta( $id, '_cl_contacto_oculto', $ocultos );
	return true;
}

// Una vez: la ficha compacta que no publicaba el teléfono (telefono_publico ≠ Sí) pasa a tenerlo oculto.
add_action( 'init', function () {
	if ( get_option( 'cl_contacto_migrado' ) || ! function_exists( 'cl_es_servicio' ) ) {
		return;
	}
	$ids = get_posts( array( 'post_type' => 'emprendimientos', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		if ( cl_es_servicio( $id ) && 'Sí' !== get_post_meta( $id, 'telefono_publico', true ) ) {
			update_post_meta( $id, '_cl_contacto_oculto', array_values( array_unique( array_merge( cl_contacto_ocultos( $id ), array( 'telefono_contacto' ) ) ) ) );
		}
	}
	update_option( 'cl_contacto_migrado', CLP_VERSION, false );
}, 32 );

// ======================================================================
// 2. Ficha mínima
// ======================================================================

// Requisitos de la ficha mínima: array( array( etiqueta, cumple, paso ), … ).
function cl_ficha_minima_chequeo( $id ) {
	$rubros = wp_get_object_terms( $id, 'rubro', array( 'fields' => 'ids' ) );
	$region = wp_get_object_terms( $id, 'region', array( 'fields' => 'ids' ) );
	$contac = false;
	foreach ( array_keys( cl_contacto_campos() ) as $c ) {
		if ( '' !== trim( (string) cl_meta_crudo( $id, $c ) ) && cl_contacto_visible( $id, $c ) ) {
			$contac = true;
			break;
		}
	}
	$req = array(
		array( 'Nombre', '' !== trim( get_the_title( $id ) ), 'presentacion' ),
		array( '¿Qué ofrecés?', ! is_wp_error( $rubros ) && ! empty( $rubros ), 'actividades' ),
		array( 'Región y localidad', ! is_wp_error( $region ) && $region && '' !== trim( (string) get_post_meta( $id, 'localidad', true ) ), 'ubicacion' ),
		array( 'Un dato de contacto que se vea en la web', $contac, 'contacto' ),
	);
	if ( ! function_exists( 'cl_es_servicio' ) || ! cl_es_servicio( $id ) ) {
		$req[] = array( 'Foto principal', (bool) get_post_thumbnail_id( $id ), 'fotos' );
		$req[] = array( 'Breve descripción (una línea)', '' !== trim( (string) get_post_meta( $id, 'breve_descripcion', true ) ), 'presentacion' );
	}
	return $req;
}

function cl_ficha_minima_ok( $id ) {
	foreach ( cl_ficha_minima_chequeo( $id ) as $r ) {
		if ( ! $r[1] ) {
			return false;
		}
	}
	return true;
}

function cl_ficha_completa_ok( $id ) {
	if ( ! function_exists( 'cl_panel_revision_chequeo' ) ) {
		return true;
	}
	list( $oblig ) = cl_panel_revision_chequeo( $id );
	foreach ( $oblig as $o ) {
		if ( ! $o[1] ) {
			return false;
		}
	}
	return true;
}

// Metas ocultas: _cl_en_construccion (publicada sin la ficha completa) y _cl_sin_minimo (no va a los listados).
function cl_ficha_minima_sync( $id ) {
	if ( 'emprendimientos' !== get_post_type( $id ) ) {
		return;
	}
	$completa = cl_ficha_completa_ok( $id );
	update_post_meta( $id, '_cl_en_construccion', ( 'publish' === get_post_status( $id ) && ! $completa ) ? '1' : '0' );
	// La mínima de un emprendimiento ya pide foto; las compactas no van a estos listados.
	update_post_meta( $id, '_cl_sin_minimo', ( $completa || cl_ficha_minima_ok( $id ) ) ? '0' : '1' );
}

// Se recalcula una vez al final del pedido (el panel guarda muchos campos seguidos).
function cl_ficha_minima_marcar( $id ) {
	if ( 'emprendimientos' !== get_post_type( $id ) ) {
		return;
	}
	if ( empty( $GLOBALS['cl_fm_pendientes'] ) ) {
		$GLOBALS['cl_fm_pendientes'] = array();
		add_action( 'shutdown', function () {
			foreach ( array_keys( (array) $GLOBALS['cl_fm_pendientes'] ) as $p ) {
				cl_ficha_minima_sync( $p );
			}
		}, 1 );
	}
	$GLOBALS['cl_fm_pendientes'][ (int) $id ] = true;
}
add_action( 'save_post_emprendimientos', 'cl_ficha_minima_marcar', 40 );
add_action( 'transition_post_status', function ( $nuevo, $viejo, $post ) {
	cl_ficha_minima_marcar( $post->ID );
}, 10, 3 );
add_action( 'set_object_terms', function ( $id ) {
	cl_ficha_minima_marcar( $id );
} );
add_action( 'updated_post_meta', 'cl_ficha_minima_meta', 10, 3 );
add_action( 'added_post_meta', 'cl_ficha_minima_meta', 10, 3 );
add_action( 'deleted_post_meta', 'cl_ficha_minima_meta', 10, 3 );
function cl_ficha_minima_meta( $mid, $id, $clave ) {
	if ( 0 !== strpos( (string) $clave, '_cl_en_construccion' ) && 0 !== strpos( (string) $clave, '_cl_sin_minimo' ) ) {
		cl_ficha_minima_marcar( $id );
	}
}

// Una vez por versión: recalcula todos.
add_action( 'init', function () {
	if ( get_option( 'cl_ficha_minima_sync' ) === CLP_VERSION || ! function_exists( 'cl_panel_revision_chequeo' ) ) {
		return;
	}
	$ids = get_posts( array( 'post_type' => 'emprendimientos', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		cl_ficha_minima_sync( $id );
	}
	update_option( 'cl_ficha_minima_sync', CLP_VERSION, false );
}, 33 );

// ---------- Para Bricks ----------

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_en_construccion', 'cl_srv_icono_ficha' ) );
} );

// {echo:cl_en_construccion} → 1 si la ficha se publicó como perfil en construcción.
function cl_en_construccion( $id = 0 ) {
	$id = $id ? (int) $id : get_the_ID();
	return '1' === get_post_meta( $id, '_cl_en_construccion', true ) ? '1' : '';
}

// {echo:cl_srv_icono_ficha} → ícono del rubro si la ficha no tiene foto ni imagen extra (si no, vacío).
function cl_srv_icono_ficha( $id = 0 ) {
	$id = $id ? (int) $id : get_the_ID();
	if ( ! $id || get_post_thumbnail_id( $id ) || (int) get_post_meta( $id, 'imagen_extra', true ) || ! function_exists( 'cl_srv_icono' ) ) {
		return '';
	}
	$slug    = 'otro';
	$turismo = function_exists( 'cl_srv_rubros_turismo' ) ? cl_srv_rubros_turismo() : array();
	$terms   = wp_get_object_terms( $id, 'rubro', array( 'fields' => 'slugs' ) );
	foreach ( is_wp_error( $terms ) ? array() : $terms as $s ) {
		if ( ! in_array( $s, $turismo, true ) ) {
			$slug = $s;
			break;
		}
	}
	return '<span class="cl-icono-rubro">' . str_replace( 'width="20" height="20"', 'width="48" height="48"', cl_srv_icono( $slug ) ) . '</span>';
}

// ======================================================================
// 3. Listados
// ======================================================================

// Fuera de Emprendimientos e Inicio las fichas sin el mínimo.
add_filter( 'bricks/posts/query_vars', function ( $qv, $settings, $element_id ) {
	if ( ! function_exists( 'cl_listado_excluir' ) ) {
		return $qv;
	}
	$tipos = (array) ( $qv['post_type'] ?? array() );
	if ( ! in_array( 'emprendimientos', $tipos, true ) ) {
		return $qv;
	}
	$turismo = function_exists( 'cl_srv_tiene_clase' ) && cl_srv_tiene_clase( $settings, 'cl-loop-turismo' );
	if ( ! in_array( $element_id, array( 'pvnicx', 'gcwygx' ), true ) && ! $turismo ) {
		return $qv;
	}
	return cl_listado_excluir( $qv, '_cl_sin_minimo' );
}, 21, 3 );

// Semilla del día (fecha del sitio): AAAAMMDD.
function cl_orden_semilla() {
	return (int) wp_date( 'Ymd' );
}

// Orden aleatorio diario en Emprendimientos, Alojamientos e Inicio. Experiencias sigue por fecha.
add_filter( 'bricks/posts/query_vars', function ( $qv, $settings, $element_id ) {
	if ( ! in_array( $element_id, array( 'pvnicx', '291c69', 'gcwygx' ), true ) ) {
		return $qv;
	}
	$qv['orderby'] = 'RAND(' . cl_orden_semilla() . ')';
	unset( $qv['order'], $qv['meta_key'] );
	return $qv;
}, 30, 3 );

// Purga de la caché a las 00:05 para que se vea el orden nuevo.
add_action( 'init', function () {
	if ( wp_next_scheduled( 'clp_orden_diario' ) ) {
		return;
	}
	$manana = new DateTime( 'tomorrow 00:05', wp_timezone() );
	wp_schedule_event( $manana->getTimestamp(), 'daily', 'clp_orden_diario' );
} );
add_action( 'clp_orden_diario', function () {
	do_action( 'litespeed_purge_all' );
} );
