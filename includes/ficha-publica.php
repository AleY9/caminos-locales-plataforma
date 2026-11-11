<?php
/**
 * Emprendimiento sin página propia (1.1.13).
 *
 * Para emprendimientos que solo ofrecen alojamiento y no quieren mostrar más que sus cabañas.
 * El emprendimiento sigue publicado (es el dueño de los alojamientos y guarda contacto, porteras
 * y equipamiento, que la ficha del alojamiento sigue mostrando), pero:
 * - no tiene página: quien entra a su dirección va a su primer alojamiento (si no tiene, 404);
 *   la red (emprendedores y admin) la sigue viendo, para «Ver cómo queda»;
 * - no aparece en los listados de Emprendimientos ni de Inicio, y no se indexa;
 * - {echo:cl_emprendimiento_url} queda vacío y {echo:cl_emprendimiento_enlace} da el nombre sin enlace;
 * - el panel le pide solo nombre, localidad, mapa y contacto.
 *
 * Lo elige el emprendedor en «¿Qué ofrecés?» (campo clfpub → ficha_publica: Sí / No, por defecto Sí).
 * Solo cuenta si tiene Alojamiento y no Actividades y experiencias.
 */

defined( 'ABSPATH' ) || exit;

function cl_emp_sin_pagina( $id ) {
	$id = (int) $id;
	if ( ! $id || 'emprendimientos' !== get_post_type( $id ) ) {
		return false;
	}
	if ( 'No' !== get_post_meta( $id, 'ficha_publica', true ) ) {
		return false;
	}
	return has_term( 'alojamiento', 'rubro', $id ) && ! has_term( 'actividades-y-experiencias', 'rubro', $id );
}

// ---------- Meta oculta para filtrar los listados: _cl_sin_pagina = 1/0 ----------

function cl_emp_sin_pagina_sync( $id ) {
	if ( 'emprendimientos' === get_post_type( $id ) ) {
		update_post_meta( $id, '_cl_sin_pagina', cl_emp_sin_pagina( $id ) ? '1' : '0' );
	}
}

add_action( 'set_object_terms', function ( $id, $terms, $tt_ids, $taxonomy ) {
	if ( 'rubro' === $taxonomy ) {
		cl_emp_sin_pagina_sync( $id );
	}
}, 10, 4 );
add_action( 'updated_post_meta', 'cl_emp_sin_pagina_meta', 10, 3 );
add_action( 'added_post_meta', 'cl_emp_sin_pagina_meta', 10, 3 );
function cl_emp_sin_pagina_meta( $mid, $id, $key ) {
	if ( 'ficha_publica' === $key ) {
		cl_emp_sin_pagina_sync( $id );
	}
}
add_action( 'save_post_emprendimientos', function ( $id ) {
	if ( ! wp_is_post_revision( $id ) ) {
		cl_emp_sin_pagina_sync( $id );
	}
}, 30 );

// Una vez por versión: recalcula todos.
add_action( 'init', function () {
	if ( get_option( 'cl_sin_pagina_sync' ) === CLP_VERSION ) {
		return;
	}
	$ids = get_posts( array( 'post_type' => 'emprendimientos', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		cl_emp_sin_pagina_sync( $id );
	}
	update_option( 'cl_sin_pagina_sync', CLP_VERSION, false );
}, 31 );

// ---------- Campo ACF ----------

add_action( 'acf/include_fields', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	acf_add_local_field_group( array(
		'key'      => 'group_cl_ficha_publica',
		'title'    => 'Página del emprendimiento',
		'fields'   => array(
			array(
				'key'           => 'field_cl_ficha_publica',
				'name'          => 'ficha_publica',
				'label'         => 'Página propia',
				'type'          => 'radio',
				'choices'       => array( 'Sí' => 'Sí, con su propia página', 'No' => 'No, solo se ven sus alojamientos' ),
				'default_value' => 'Sí',
				'instructions'  => 'Solo cuenta si tiene Alojamiento y no Actividades y experiencias. Con «No», la dirección del emprendimiento lleva a su primer alojamiento.',
			),
		),
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'emprendimientos' ) ) ),
		'position' => 'side',
	) );
} );

// ---------- Página: redirige al primer alojamiento ----------

// Primer alojamiento publicado del emprendimiento (por título), o 0.
function cl_emp_primer_alojamiento( $emp ) {
	$ids = get_posts( array(
		'post_type'      => 'alojamiento',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'meta_query'     => array( array( 'key' => 'emprendimiento_correspondiente', 'value' => '"' . (int) $emp . '"', 'compare' => 'LIKE' ) ),
	) );
	return $ids ? (int) $ids[0] : 0;
}

add_action( 'template_redirect', function () {
	if ( ! is_singular( 'emprendimientos' ) ) {
		return;
	}
	$id = get_queried_object_id();
	if ( ! cl_emp_sin_pagina( $id ) || ( function_exists( 'cl_srv_es_red' ) && cl_srv_es_red() ) ) {
		return;
	}
	$aloj = cl_emp_primer_alojamiento( $id );
	if ( $aloj ) {
		wp_safe_redirect( get_permalink( $aloj ), 302 ); // 302: la elección se puede cambiar.
		exit;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}, 9 );

// Sin índice (la red sí la ve).
function cl_emp_sin_pagina_actual() {
	return is_singular( 'emprendimientos' ) && cl_emp_sin_pagina( get_queried_object_id() );
}
add_filter( 'wp_robots', function ( $robots ) {
	return cl_emp_sin_pagina_actual() ? array( 'noindex' => true, 'nofollow' => true ) : $robots;
}, 99999 );
add_action( 'template_redirect', function () {
	if ( cl_emp_sin_pagina_actual() && ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}
}, 99 );

// ---------- Listados: fuera de Emprendimientos (pvnicx) e Inicio (gcwygx) ----------

add_filter( 'bricks/posts/query_vars', function ( $qv, $settings, $element_id ) {
	if ( ! function_exists( 'cl_srv_tiene_clase' ) ) {
		return $qv;
	}
	$tipos = (array) ( $qv['post_type'] ?? array() );
	if ( ! in_array( 'emprendimientos', $tipos, true ) ) {
		return $qv;
	}
	if ( ! in_array( $element_id, array( 'pvnicx', 'gcwygx' ), true ) && ! cl_srv_tiene_clase( $settings, 'cl-loop-turismo' ) ) {
		return $qv;
	}
	$sin = array( 'relation' => 'OR', array( 'key' => '_cl_sin_pagina', 'compare' => 'NOT EXISTS' ), array( 'key' => '_cl_sin_pagina', 'value' => '1', 'compare' => '!=' ) );
	$qv['meta_query'] = empty( $qv['meta_query'] ) ? array( $sin ) : array( 'relation' => 'AND', $qv['meta_query'], $sin );
	return $qv;
}, 20, 3 );

// ---------- Nombre con o sin enlace ----------

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_emprendimiento_enlace' ) );
} );

// {echo:cl_emprendimiento_enlace} → en un alojamiento: el nombre del emprendimiento, con enlace si tiene página.
function cl_emprendimiento_enlace( $post_id = 0 ) {
	if ( ! function_exists( 'cl_emprendimiento_id' ) ) {
		return '';
	}
	$id = cl_emprendimiento_id( $post_id );
	if ( ! $id ) {
		return '';
	}
	$nombre = esc_html( get_the_title( $id ) );
	return cl_emp_sin_pagina( $id ) ? $nombre : '<a href="' . esc_url( get_permalink( $id ) ) . '">' . $nombre . '</a>';
}

// Panel: la pregunta clfpub se muestra u oculta con CSS en la página «Mi emprendimiento» (28262), en Bricks.
