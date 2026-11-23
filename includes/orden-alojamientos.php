<?php
/**
 * Orden de los alojamientos de un emprendimiento (1.3.0).
 *
 * El emprendedor elige cuál se ve primero con los botones ↑ ↓ de «Mis alojamientos». El orden se guarda en el
 * campo nativo menu_order (1, 2, 3…). Bricks ordena por menu_order los listados del emprendimiento (Single
 * Emprendimientos: zhynze y yxyjhi; Single Alojamiento: «Más alojamientos»). Lo usa también la redirección de
 * los emprendimientos sin página propia (va al primero).
 *
 * - Alojamientos nuevos o duplicados: van al final.
 * - Una vez: se numeran los que ya existen por fecha de creación (el primero que se cargó queda primero).
 * - Se escribe directo en la tabla (sin wp_update_post) para no disparar avisos ni sincronizaciones.
 */

defined( 'ABSPATH' ) || exit;

// Alojamientos del emprendimiento en orden (todos los estados y autores).
function cl_aloj_orden_ids( $emp ) {
	if ( ! $emp ) {
		return array();
	}
	return array_map( 'intval', get_posts( array(
		'post_type'        => 'alojamiento',
		'post_status'      => array( 'publish', 'draft', 'pending' ),
		'numberposts'      => 50,
		'fields'           => 'ids',
		'orderby'          => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
		'suppress_filters' => true,
		'meta_query'       => array( array( 'key' => 'emprendimiento_correspondiente', 'value' => '"' . (int) $emp . '"', 'compare' => 'LIKE' ) ),
	) ) );
}

// Deja los números en 1, 2, 3… según el orden de la lista.
function cl_aloj_orden_numerar( $ids ) {
	global $wpdb;
	foreach ( array_values( $ids ) as $i => $id ) {
		if ( (int) get_post_field( 'menu_order', $id ) !== $i + 1 ) {
			$wpdb->update( $wpdb->posts, array( 'menu_order' => $i + 1 ), array( 'ID' => (int) $id ) );
			clean_post_cache( (int) $id );
		}
	}
}

// Alojamiento nuevo o duplicado (menu_order 0): al final de su emprendimiento.
foreach ( array( 'added_post_meta', 'updated_post_meta' ) as $cl_hook ) {
	add_action( $cl_hook, function ( $mid, $post_id, $key ) {
		if ( 'emprendimiento_correspondiente' !== $key || 'alojamiento' !== get_post_type( $post_id ) || 0 !== (int) get_post_field( 'menu_order', $post_id ) ) {
			return;
		}
		$emp = function_exists( 'cl_aloj_emp_de' ) ? cl_aloj_emp_de( $post_id ) : 0;
		if ( ! $emp ) {
			return;
		}
		$otros = array_values( array_diff( cl_aloj_orden_ids( $emp ), array( (int) $post_id ) ) );
		cl_aloj_orden_numerar( array_merge( $otros, array( (int) $post_id ) ) );
	}, 20, 3 );
}

// Una vez: numerar los que ya existen, por fecha de creación.
add_action( 'init', function () {
	if ( get_option( 'cl_aloj_orden_migrado' ) || ! function_exists( 'cl_aloj_emp_de' ) ) {
		return;
	}
	$emps = array();
	foreach ( get_posts( array( 'post_type' => 'alojamiento', 'post_status' => array( 'publish', 'draft', 'pending' ), 'numberposts' => -1, 'fields' => 'ids' ) ) as $a ) {
		$e = cl_aloj_emp_de( $a );
		if ( $e ) {
			$emps[ $e ] = 1;
		}
	}
	foreach ( array_keys( $emps ) as $e ) {
		cl_aloj_orden_numerar( cl_aloj_orden_ids( $e ) );
	}
	update_option( 'cl_aloj_orden_migrado', CLP_VERSION, false );
}, 34 );

// Subir o bajar desde «Mis alojamientos».
add_action( 'template_redirect', function () {
	if ( empty( $_POST['cl_aloj_mover'] ) || ! function_exists( 'cl_aloj_id' ) ) {
		return;
	}
	$id = cl_aloj_id( isset( $_POST['aloj'] ) ? (int) $_POST['aloj'] : 0 );
	if ( ! $id || ! isset( $_POST['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_clnonce'] ), 'cl_aloj_mover_' . $id ) ) {
		return;
	}
	$emp = cl_aloj_emp_de( $id );
	$ids = cl_aloj_orden_ids( $emp );
	$pos = array_search( $id, $ids, true );
	$a   = 'arriba' === $_POST['cl_aloj_mover'] ? $pos - 1 : $pos + 1;
	if ( false !== $pos && isset( $ids[ $a ] ) ) {
		$ids[ $pos ] = $ids[ $a ];
		$ids[ $a ]   = $id;
		cl_aloj_orden_numerar( $ids );
		// LiteSpeed: la ficha del emprendimiento y las de sus alojamientos («Más alojamientos»).
		foreach ( array_merge( array( $emp ), $ids ) as $p ) {
			do_action( 'litespeed_purge_post', $p );
		}
	}
	wp_safe_redirect( add_query_arg( 'movido', $id, cl_aloj_url_lista( $emp ) ) . '#cla-' . $id );
	exit;
}, 1 );

// Botones ↑ ↓ de una tarjeta (vacío si el emprendimiento tiene un solo alojamiento).
function cl_aloj_orden_botones( $a ) {
	$ids = cl_aloj_orden_ids( cl_aloj_emp_de( $a ) );
	$pos = array_search( (int) $a, $ids, true );
	if ( count( $ids ) < 2 || false === $pos ) {
		return '';
	}
	$btn = function ( $dir, $txt, $label, $activo ) use ( $a ) {
		if ( ! $activo ) {
			return '<span class="cla-acc-b cla-orden cla-orden-no" aria-hidden="true">' . $txt . '</span>';
		}
		return '<form method="post" class="cla-acc-f"><input type="hidden" name="cl_aloj_mover" value="' . $dir . '"><input type="hidden" name="aloj" value="' . (int) $a . '">'
			. wp_nonce_field( 'cl_aloj_mover_' . $a, '_clnonce', false, false )
			. '<button type="submit" class="cla-acc-b cla-orden" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '">' . $txt . '</button></form>';
	};
	return $btn( 'arriba', '↑', 'Subir: se muestra antes', $pos > 0 ) . $btn( 'abajo', '↓', 'Bajar: se muestra después', $pos < count( $ids ) - 1 );
}

function cl_aloj_orden_css() {
	return '<style>.cla .cla-acc-b.cla-orden{min-width:40px;padding:0;font-size:18px;display:inline-flex;align-items:center;justify-content:center}.cla .cla-orden-no{opacity:.35;cursor:default}.cla-item:target{box-shadow:0 0 0 2px #b9d37f}</style>';
}

// Texto de arriba de la lista, solo con dos alojamientos o más.
function cl_aloj_orden_ayuda( $emp ) {
	if ( count( cl_aloj_orden_ids( $emp ) ) < 2 ) {
		return '';
	}
	return cl_aloj_orden_css() . '<p class="cla-p">El primero de la lista es el que se ve primero en tu ficha. Cambiá el orden con ↑ ↓.</p>';
}
