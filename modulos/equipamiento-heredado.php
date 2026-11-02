<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 27 «CL - Equipamiento Heredado» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — Equipamiento heredado en la ficha del alojamiento (v1, sept. 2026)
 * Code Snippets: tipo PHP, "Ejecutar en todas partes". Sin <?php.
 * Necesita "CL – Fichas" (usa cl_emprendimiento_id()).
 *
 * El equipamiento (bicicletas, caballos, canoas) es del emprendimiento, no del alojamiento.
 * Se muestra heredado, sin copiar el dato, igual que ya se hace con el mapa, el teléfono
 * y la dirección. Así no hay que registrar la taxonomía en alojamiento ni se desincroniza.
 *
 * Para usar en Bricks, en la plantilla Single Alojamiento:
 *   {echo:cl_emp_equipamiento}      → "bicicletas y canoas" (en minúscula, para una frase)
 *   {echo:cl_emp_equipamiento_hay}  → "1" si hay algo, "" si no (para la condición del bloque)
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! function_exists( 'cl_emp_equipamiento_ids' ) ) {
	// IDs del equipamiento del emprendimiento vinculado al alojamiento actual.
	function cl_emp_equipamiento_ids( $post_id = 0 ) {
		if ( ! function_exists( 'cl_emprendimiento_id' ) || ! taxonomy_exists( 'equipamiento' ) ) {
			return array();
		}
		$emp = (int) cl_emprendimiento_id( $post_id );
		if ( ! $emp ) {
			return array();
		}
		$ids = wp_get_object_terms( $emp, 'equipamiento', array( 'fields' => 'ids' ) );
		return is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
	}
}

if ( ! function_exists( 'cl_emp_equipamiento' ) ) {
	// "bicicletas", "bicicletas y canoas", "bicicletas, caballos y canoas".
	function cl_emp_equipamiento( $post_id = 0 ) {
		$ids = cl_emp_equipamiento_ids( $post_id );
		if ( ! $ids ) {
			return '';
		}
		$terminos = get_terms( array(
			'taxonomy'   => 'equipamiento',
			'include'    => $ids,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );
		if ( is_wp_error( $terminos ) || ! $terminos ) {
			return '';
		}
		$nombres = array();
		foreach ( $terminos as $t ) {
			$nombres[] = function_exists( 'mb_strtolower' ) ? mb_strtolower( $t->name, 'UTF-8' ) : strtolower( $t->name );
		}
		if ( 1 === count( $nombres ) ) {
			return esc_html( $nombres[0] );
		}
		$ultimo = array_pop( $nombres );
		return esc_html( implode( ', ', $nombres ) . ' y ' . $ultimo );
	}
}

if ( ! function_exists( 'cl_emp_equipamiento_hay' ) ) {
	// Para la condición del bloque en Bricks: "no está vacío".
	function cl_emp_equipamiento_hay( $post_id = 0 ) {
		return cl_emp_equipamiento_ids( $post_id ) ? '1' : '';
	}
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_emp_equipamiento', 'cl_emp_equipamiento_hay' ) );
} );
