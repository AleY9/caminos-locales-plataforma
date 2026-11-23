<?php
/**
 * Orden de las experiencias de un emprendimiento (1.3.0).
 *
 * Las experiencias con fecha se ordenan solas por fecha (no cambia). Las de «Todo el año o a coordinar» van
 * después, en el orden que elige el emprendedor con ↑ ↓ en «Mis experiencias». Se guarda en menu_order
 * (1, 2, 3…) y en la meta de orden que ya usan todos los listados: _cl_ev_orden = 90000000 + menu_order
 * (las de fecha tienen AAAAMMDD, así que siempre quedan antes). Antes era 99999999 para todas.
 *
 * - Nuevas, duplicadas o que pasan a «Todo el año»: al final.
 * - Una vez: se numeran las que ya existen en el orden en que se veían (por nombre).
 * - Se escribe directo en la tabla (sin wp_update_post) para no disparar avisos.
 */

defined( 'ABSPATH' ) || exit;

define( 'CL_EV_ORDEN_BASE', 90000000 );

// ¿Es de «Todo el año o a coordinar» (sin fecha)?
function cl_ev_sin_fecha( $id ) {
	return function_exists( 'cl_ev_fechas' ) && ! cl_ev_fechas( $id )['ini'];
}

// Experiencias sin fecha del emprendimiento, en orden (todos los estados y autores).
function cl_ev_orden_ids( $emp ) {
	if ( ! $emp ) {
		return array();
	}
	$ids = get_posts( array(
		'post_type'        => 'evento',
		'post_status'      => array( 'publish', 'draft', 'pending' ),
		'numberposts'      => 60,
		'fields'           => 'ids',
		'orderby'          => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'suppress_filters' => true,
		'meta_query'       => array( array( 'key' => 'corresponde_a_emprendimiento', 'value' => '"' . (int) $emp . '"', 'compare' => 'LIKE' ) ),
	) );
	return array_values( array_filter( array_map( 'intval', $ids ), 'cl_ev_sin_fecha' ) );
}

// Deja los números en 1, 2, 3… y la meta de orden en 90000001, 90000002…
function cl_ev_orden_numerar( $ids ) {
	global $wpdb;
	foreach ( array_values( $ids ) as $i => $id ) {
		if ( (int) get_post_field( 'menu_order', $id ) !== $i + 1 ) {
			$wpdb->update( $wpdb->posts, array( 'menu_order' => $i + 1 ), array( 'ID' => (int) $id ) );
			clean_post_cache( (int) $id );
		}
		$orden = (string) ( CL_EV_ORDEN_BASE + $i + 1 );
		if ( get_post_meta( $id, '_cl_ev_orden', true ) !== $orden ) {
			update_post_meta( $id, '_cl_ev_orden', $orden );
		}
	}
}

// Cuando cl_ev_meta() pone 99999999 (sin fecha): pasa al número de su lugar, o al final si no tenía.
foreach ( array( 'added_post_meta', 'updated_post_meta' ) as $cl_hook ) {
	add_action( $cl_hook, function ( $mid, $post_id, $key, $valor ) {
		if ( '_cl_ev_orden' !== $key || '99999999' !== (string) $valor || 'evento' !== get_post_type( $post_id ) ) {
			return;
		}
		$emp = function_exists( 'cl_ev_emp_de' ) ? cl_ev_emp_de( $post_id ) : 0;
		if ( ! $emp ) {
			return;
		}
		$ids = cl_ev_orden_ids( $emp );
		if ( 0 === (int) get_post_field( 'menu_order', $post_id ) ) {
			$ids = array_merge( array_values( array_diff( $ids, array( (int) $post_id ) ) ), array( (int) $post_id ) );
		}
		cl_ev_orden_numerar( $ids );
	}, 20, 4 );
}

// Una experiencia que pasa a tener fecha deja su lugar: menu_order a 0 para que, si vuelve a «Todo el año»,
// entre al final.
add_action( 'updated_post_meta', function ( $mid, $post_id, $key, $valor ) {
	if ( '_cl_ev_orden' !== $key || (int) $valor >= CL_EV_ORDEN_BASE || 'evento' !== get_post_type( $post_id ) || 0 === (int) get_post_field( 'menu_order', $post_id ) ) {
		return;
	}
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'menu_order' => 0 ), array( 'ID' => (int) $post_id ) );
	clean_post_cache( (int) $post_id );
}, 21, 4 );

// Una vez: numerar las que ya existen.
add_action( 'init', function () {
	if ( get_option( 'cl_ev_orden_migrado' ) || ! function_exists( 'cl_ev_emp_de' ) || ! get_option( 'cl_ev_meta_v1' ) ) {
		return;
	}
	$emps = array();
	foreach ( get_posts( array( 'post_type' => 'evento', 'post_status' => array( 'publish', 'draft', 'pending' ), 'numberposts' => -1, 'fields' => 'ids' ) ) as $e ) {
		$x = cl_ev_emp_de( $e );
		if ( $x ) {
			$emps[ $x ] = 1;
		}
	}
	foreach ( array_keys( $emps ) as $x ) {
		cl_ev_orden_numerar( cl_ev_orden_ids( $x ) );
	}
	update_option( 'cl_ev_orden_migrado', CLP_VERSION, false );
}, 35 );

// Subir o bajar desde «Mis experiencias».
add_action( 'template_redirect', function () {
	if ( empty( $_POST['cl_ev_mover'] ) || ! function_exists( 'cl_ev_id' ) ) {
		return;
	}
	$id = cl_ev_id( isset( $_POST['ev'] ) ? (int) $_POST['ev'] : 0 );
	if ( ! $id || ! isset( $_POST['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_clnonce'] ), 'cl_ev_mover_' . $id ) ) {
		return;
	}
	$emp = cl_ev_emp_de( $id );
	$ids = cl_ev_orden_ids( $emp );
	$pos = array_search( $id, $ids, true );
	$a   = 'arriba' === $_POST['cl_ev_mover'] ? $pos - 1 : $pos + 1;
	if ( false !== $pos && isset( $ids[ $a ] ) ) {
		$ids[ $pos ] = $ids[ $a ];
		$ids[ $a ]   = $id;
		cl_ev_orden_numerar( $ids );
		// LiteSpeed: la ficha del emprendimiento, las de sus experiencias y la página Experiencias.
		foreach ( array_merge( array( $emp ), $ids ) as $p ) {
			do_action( 'litespeed_purge_post', $p );
		}
		do_action( 'litespeed_purge_post', 10272 );
	}
	wp_safe_redirect( add_query_arg( 'movida', $id, cl_ev_url_lista( $emp ) ) . '#cle-' . $id );
	exit;
}, 1 );

// Botones ↑ ↓ de una tarjeta: solo las de «Todo el año o a coordinar», si hay dos o más.
function cl_ev_orden_botones( $e ) {
	if ( ! cl_ev_sin_fecha( $e ) ) {
		return '';
	}
	$ids = cl_ev_orden_ids( cl_ev_emp_de( $e ) );
	$pos = array_search( (int) $e, $ids, true );
	if ( count( $ids ) < 2 || false === $pos ) {
		return '';
	}
	$btn = function ( $dir, $txt, $label, $activo ) use ( $e ) {
		if ( ! $activo ) {
			return '<span class="cle-acc-b cle-orden cle-orden-no" aria-hidden="true">' . $txt . '</span>';
		}
		return '<form method="post" class="cle-acc-f"><input type="hidden" name="cl_ev_mover" value="' . $dir . '"><input type="hidden" name="ev" value="' . (int) $e . '">'
			. wp_nonce_field( 'cl_ev_mover_' . $e, '_clnonce', false, false )
			. '<button type="submit" class="cle-acc-b cle-orden" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '">' . $txt . '</button></form>';
	};
	return $btn( 'arriba', '↑', 'Subir: se muestra antes', $pos > 0 ) . $btn( 'abajo', '↓', 'Bajar: se muestra después', $pos < count( $ids ) - 1 );
}

// Texto de arriba de la lista, solo si hay dos o más de «Todo el año o a coordinar».
function cl_ev_orden_ayuda( $emp ) {
	if ( count( cl_ev_orden_ids( $emp ) ) < 2 ) {
		return '';
	}
	return '<style>.cle .cle-acc-b.cle-orden{min-width:40px;padding:0;font-size:18px;display:inline-flex;align-items:center;justify-content:center}.cle .cle-orden-no{opacity:.35;cursor:default}.cle-item:target{box-shadow:0 0 0 2px #b9d37f}</style>'
		. '<p class="cle-p">Las que tienen fecha se ordenan solas. Las de todo el año o a coordinar se muestran en el orden de esta lista: cambialo con ↑ ↓.</p>';
}
