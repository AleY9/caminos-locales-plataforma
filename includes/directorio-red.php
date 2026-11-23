<?php
/**
 * Directorio de la red (1.3.0).
 *
 * Página «Directorio de la red» (/panel/red/, hija de Mi panel): todos los emprendimientos y servicios publicados
 * con su persona de contacto, WhatsApp y email, para que los integrantes se contacten entre ellos. Solo para la red
 * (emprendedores y admin); para el resto el bucle sale vacío. Todo lo visual en Bricks: bucle con la clase
 * cl-loop-red y estas funciones para {echo:…}:
 *   cl_red_persona()  persona de contacto (campo persona_contacto; si está vacío, quien pidió sumarse o el nombre
 *                     de la cuenta del dueño)
 *   cl_red_tel()      teléfono con formato · cl_red_wa() enlace de WhatsApp · cl_red_email() email
 *   cl_red_lugar()    «Aiguá · Región Este» · cl_red_rubros() «Alojamiento · Comida»
 *   cl_red_ficha()    dirección de la ficha (vacío si no tiene página pública)
 *   cl_red_oculto()   1 si la ficha eligió no mostrar su contacto a la red
 *   cl_red_filtro_rubros() botones de rubro (shortcode [cl_red_filtros])
 *
 * Permiso: en el paso Contacto, casilla «Mostrar mi contacto en el directorio de la red» (campo clcred + oculto
 * clcrex), marcada por defecto. Si se desmarca: meta _cl_red_oculto = 1; la ficha sigue en el directorio (nombre,
 * rubros, lugar) pero sin persona, teléfono, WhatsApp ni email.
 *
 * Filtros por la dirección: ?rubro=slug y ?q=texto (busca en el nombre y la localidad).
 */

defined( 'ABSPATH' ) || exit;

function cl_red_visible( $id ) {
	return '1' !== (string) get_post_meta( (int) $id, '_cl_red_oculto', true );
}

// Guarda la casilla del panel. Devuelve true si cambió.
function cl_red_guardar( $id, $mostrar ) {
	$antes = cl_red_visible( $id );
	if ( $antes === (bool) $mostrar ) {
		return false;
	}
	if ( $mostrar ) {
		delete_post_meta( $id, '_cl_red_oculto' );
	} else {
		update_post_meta( $id, '_cl_red_oculto', '1' );
	}
	return true;
}

function cl_red_id( $id = 0 ) {
	return $id ? (int) $id : (int) get_the_ID();
}

// Contacto sin el filtro de la web pública, y solo si la ficha lo muestra a la red.
function cl_red_dato( $id, $clave ) {
	if ( ! cl_red_visible( $id ) || ! function_exists( 'cl_srv_es_red' ) || ! cl_srv_es_red() ) {
		return '';
	}
	return trim( (string) ( function_exists( 'cl_meta_crudo' ) ? cl_meta_crudo( $id, $clave ) : get_post_meta( $id, $clave, true ) ) );
}

function cl_red_oculto( $id = 0 ) {
	return cl_red_visible( cl_red_id( $id ) ) ? '' : '1';
}

function cl_red_persona( $id = 0 ) {
	$id = cl_red_id( $id );
	if ( ! cl_red_visible( $id ) ) {
		return '';
	}
	$p = trim( (string) get_post_meta( $id, 'persona_contacto', true ) );
	if ( '' === $p ) {
		$sol = get_post_meta( $id, '_cl_solicitud', true );
		$p   = is_array( $sol ) && ! empty( $sol['persona'] ) ? trim( (string) $sol['persona'] ) : '';
	}
	if ( '' === $p ) {
		$u = get_userdata( (int) get_post_field( 'post_author', $id ) );
		if ( $u && ! user_can( $u, 'manage_options' ) ) {
			$p = trim( $u->first_name . ' ' . $u->last_name );
			$p = '' !== $p ? $p : ( false === strpos( $u->display_name, '@' ) ? $u->display_name : '' );
			// Si la cuenta tiene el nombre del emprendimiento (no de una persona), no se repite.
			$t = remove_accents( mb_strtolower( get_the_title( $id ) ) );
			$c = remove_accents( mb_strtolower( $p ) );
			if ( '' !== $c && ( false !== strpos( $c, $t ) || false !== strpos( $t, $c ) ) ) {
				$p = '';
			}
		}
	}
	return $p;
}

function cl_red_tel( $id = 0 ) {
	$id  = cl_red_id( $id );
	$tel = cl_red_dato( $id, 'telefono_contacto' );
	return ( '' !== $tel && function_exists( 'cl_srv_tel' ) ) ? cl_srv_tel( $id ) : $tel;
}

function cl_red_wa( $id = 0 ) {
	$id = cl_red_id( $id );
	if ( '' === cl_red_dato( $id, 'telefono_contacto' ) || ! function_exists( 'cl_srv_wa' ) ) {
		return '';
	}
	$wa = cl_srv_wa( $id );
	return $wa ? add_query_arg( 'text', rawurlencode( 'Hola, te escribo desde el directorio de Caminos Locales.' ), $wa ) : '';
}

function cl_red_email( $id = 0 ) {
	$m = cl_red_dato( cl_red_id( $id ), 'email-emp' );
	return is_email( $m ) ? $m : '';
}

function cl_red_lugar( $id = 0 ) {
	$id  = cl_red_id( $id );
	$loc = trim( (string) get_post_meta( $id, 'localidad', true ) );
	$reg = wp_get_object_terms( $id, 'region', array( 'fields' => 'names' ) );
	$reg = is_wp_error( $reg ) || ! $reg ? '' : 'Región ' . $reg[0];
	return implode( ' · ', array_filter( array( $loc, $reg ) ) );
}

function cl_red_rubros( $id = 0 ) {
	$t = wp_get_object_terms( cl_red_id( $id ), 'rubro', array( 'fields' => 'names' ) );
	return is_wp_error( $t ) ? '' : implode( ' · ', $t );
}

function cl_red_ficha( $id = 0 ) {
	$id = cl_red_id( $id );
	if ( function_exists( 'cl_emp_sin_pagina' ) && cl_emp_sin_pagina( $id ) ) {
		return ''; // Sin página propia (p. ej. solo sus alojamientos).
	}
	return (string) get_permalink( $id );
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_red_persona', 'cl_red_tel', 'cl_red_wa', 'cl_red_email', 'cl_red_lugar', 'cl_red_rubros', 'cl_red_ficha', 'cl_red_oculto' ) );
} );

// Bucle del directorio.
add_filter( 'bricks/posts/query_vars', function ( $qv, $settings ) {
	if ( ! function_exists( 'cl_srv_tiene_clase' ) || ! cl_srv_tiene_clase( $settings, 'cl-loop-red' ) ) {
		return $qv;
	}
	if ( ! function_exists( 'cl_srv_es_red' ) || ! cl_srv_es_red() ) {
		$qv['post__in'] = array( 0 );
		return $qv;
	}
	$qv['post_type']      = 'emprendimientos';
	$qv['post_status']    = 'publish';
	$qv['orderby']        = 'title';
	$qv['order']          = 'ASC';
	$qv['posts_per_page'] = 200;
	unset( $qv['meta_query'], $qv['tax_query'], $qv['post__not_in'] );

	$rubro = isset( $_GET['rubro'] ) ? sanitize_title( wp_unslash( $_GET['rubro'] ) ) : '';
	if ( $rubro && term_exists( $rubro, 'rubro' ) ) {
		$qv['tax_query'] = array( array( 'taxonomy' => 'rubro', 'field' => 'slug', 'terms' => $rubro ) );
	}
	$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	if ( '' !== $q ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$ids  = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'localidad'
			 WHERE p.post_type = 'emprendimientos' AND p.post_status = 'publish' AND ( p.post_title LIKE %s OR m.meta_value LIKE %s )",
			$like,
			$like
		) );
		$qv['post__in'] = $ids ? array_map( 'intval', $ids ) : array( 0 );
	}
	return $qv;
}, 20, 2 );

// [cl_red_filtros]: botones de rubro (los que tienen al menos una ficha publicada) y buscador.
add_shortcode( 'cl_red_filtros', function () {
	if ( ! function_exists( 'cl_srv_es_red' ) || ! cl_srv_es_red() ) {
		return '';
	}
	$base  = get_permalink();
	$act   = isset( $_GET['rubro'] ) ? sanitize_title( wp_unslash( $_GET['rubro'] ) ) : '';
	$q     = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$terms = get_terms( array( 'taxonomy' => 'rubro', 'hide_empty' => true ) );
	$h     = '<form class="clred-buscar" method="get" action="' . esc_url( $base ) . '">'
		. ( $act ? '<input type="hidden" name="rubro" value="' . esc_attr( $act ) . '">' : '' )
		. '<input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="Buscar por nombre o localidad" aria-label="Buscar por nombre o localidad">'
		. '<button type="submit">Buscar</button></form>';
	$h    .= '<div class="clred-chips"><a class="clred-chip' . ( $act ? '' : ' is-on' ) . '" href="' . esc_url( $q ? add_query_arg( 'q', $q, $base ) : $base ) . '">Todos</a>';
	foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
		$url = add_query_arg( array_filter( array( 'rubro' => $t->slug, 'q' => $q ) ), $base );
		$h  .= '<a class="clred-chip' . ( $act === $t->slug ? ' is-on' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $t->name ) . '</a>';
	}
	$h .= '</div>';
	$css = '<style>.clred-buscar{display:flex;gap:8px;margin:0 0 12px}.clred-buscar input{flex:1;min-height:40px;padding:0 12px;border:1px solid #c9c2b3;border-radius:20px;font:inherit}.clred-buscar button{min-height:40px;padding:0 16px;border:0;border-radius:20px;background:#2e6b5e;color:#fff;font:600 15px/1 inherit;cursor:pointer}.clred-chips{display:flex;flex-wrap:wrap;gap:8px}.clred-chip{padding:6px 12px;border:1px solid #c9c2b3;border-radius:16px;color:#1f4645;text-decoration:none;font-size:14px;background:#fff}.clred-chip.is-on{background:#1f4645;border-color:#1f4645;color:#fff}</style>';
	return $css . $h;
} );
