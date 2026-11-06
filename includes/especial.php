<?php
/**
 * «Especial para» (1.1.11): Parejas · Familias · Grupos.
 *
 * - Taxonomía `especial` en alojamientos y experiencias. Es opcional y no cuenta en el progreso del panel.
 * - El panel la guarda por slug (parejas, familias, grupos), no por ID: así el formulario de Bricks
 *   es el mismo en el staging y en producción. Opciones del formulario: «parejas:Parejas» etc.
 * - Ficha pública: {echo:cl_especial_texto} → «Especial para parejas y familias» (vacío si no tiene).
 * - Filtros de Bricks: Filtro radio/casillas con fuente Taxonomía → «Especial para».
 * - La característica de alojamiento «Ideal para parejas» pasa a «Especial para: Parejas» una sola vez
 *   (opción clp_especial_1111) y el panel deja de ofrecerla en «Qué tiene».
 */

defined( 'ABSPATH' ) || exit;

function cl_especial_opciones() {
	return array(
		'parejas'  => 'Parejas',
		'familias' => 'Familias',
		'grupos'   => 'Grupos',
	);
}

add_action( 'init', function () {
	register_taxonomy( 'especial', array( 'alojamiento', 'evento' ), array(
		'labels'            => array(
			'name'          => 'Especial para',
			'singular_name' => 'Especial para',
			'menu_name'     => 'Especial para',
			'all_items'     => 'Todas',
			'edit_item'     => 'Editar',
			'add_new_item'  => 'Agregar',
			'search_items'  => 'Buscar',
		),
		'public'            => true,
		'hierarchical'      => true, // casillas en wp-admin
		'show_ui'           => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'especial-para' ),
	) );
}, 5 );

// Términos y paso de «Ideal para parejas», una sola vez.
add_action( 'init', function () {
	if ( get_option( 'clp_especial_1111' ) ) {
		return;
	}
	foreach ( cl_especial_opciones() as $slug => $nombre ) {
		if ( ! term_exists( $slug, 'especial' ) ) {
			wp_insert_term( $nombre, 'especial', array( 'slug' => $slug ) );
		}
	}
	$pasados = 0;
	foreach ( cl_especial_caract_vieja_ids() as $vieja ) {
		$ids = get_objects_in_term( $vieja, 'caracteristicas' );
		foreach ( is_wp_error( $ids ) ? array() : $ids as $id ) {
			wp_set_object_terms( (int) $id, 'parejas', 'especial', true );
			wp_remove_object_terms( (int) $id, $vieja, 'caracteristicas' );
			$pasados++;
		}
	}
	update_option( 'clp_especial_1111', array( 'fecha' => current_time( 'mysql' ), 'alojamientos' => $pasados ), false );
}, 20 );

// ID de la característica vieja «Ideal para parejas» (se busca por nombre: el ID puede variar entre sitios).
function cl_especial_caract_vieja_ids() {
	$t = get_term_by( 'name', 'Ideal para parejas', 'caracteristicas' );
	return $t ? array( (int) $t->term_id ) : array();
}

// Slugs marcados en una ficha, en el orden de cl_especial_opciones().
function cl_especial_leer( $id ) {
	$t = wp_get_object_terms( (int) $id, 'especial', array( 'fields' => 'slugs' ) );
	$t = is_wp_error( $t ) ? array() : $t;
	return array_values( array_intersect( array_keys( cl_especial_opciones() ), $t ) );
}

// Limpia lo que llega del formulario (array o «a,b»): solo slugs válidos.
function cl_especial_limpiar( $valor ) {
	$lista = is_array( $valor ) ? $valor : explode( ',', (string) $valor );
	$lista = array_map( 'sanitize_key', array_map( 'wp_unslash', array_map( 'strval', $lista ) ) );
	return array_values( array_intersect( array_keys( cl_especial_opciones() ), $lista ) );
}

function cl_especial_guardar( $id, $slugs ) {
	wp_set_object_terms( (int) $id, cl_especial_limpiar( $slugs ), 'especial', false );
}

// {echo:cl_especial_texto} — «Especial para parejas y familias» de la ficha actual. Vacío si no tiene.
function cl_especial_texto( $id = 0 ) {
	$id = $id ? (int) $id : (int) get_the_ID();
	if ( ! $id ) {
		return '';
	}
	$op    = cl_especial_opciones();
	$nomb  = array();
	foreach ( cl_especial_leer( $id ) as $s ) {
		$nomb[] = mb_strtolower( $op[ $s ] );
	}
	if ( ! $nomb ) {
		return '';
	}
	$ult = array_pop( $nomb );
	return 'Especial para ' . ( $nomb ? implode( ', ', $nomb ) . ' y ' . $ult : $ult );
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_especial_texto' ) );
} );
