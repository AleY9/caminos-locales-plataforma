<?php
/**
 * Títulos sin punto final (1.3.2).
 *
 * Al guardar, se saca el punto final del nombre de emprendimientos, alojamientos y experiencias, y de la
 * localidad (desde el panel, wp-admin, MCP o cualquier otro lado). Así nadie tiene que leer una ayuda más.
 * No se toca lo que termina en «...», «…» o «etc.».
 */

defined( 'ABSPATH' ) || exit;

function cl_sin_punto_final( $texto ) {
	if ( ! is_string( $texto ) ) {
		return $texto;
	}
	$t = rtrim( $texto );
	if ( '.' !== substr( $t, -1 ) || '..' === substr( $t, -2 ) || preg_match( '/\betc\.$/iu', $t ) ) {
		return $texto;
	}
	return rtrim( substr( $t, 0, -1 ) );
}

add_filter( 'wp_insert_post_data', function ( $data ) {
	if ( in_array( $data['post_type'] ?? '', array( 'emprendimientos', 'alojamiento', 'evento' ), true ) && isset( $data['post_title'] ) ) {
		$data['post_title'] = cl_sin_punto_final( $data['post_title'] );
	}
	return $data;
} );

// Pasa por update_post_meta y por ACF (sanitize_meta).
add_filter( 'sanitize_post_meta_localidad', 'cl_sin_punto_final' );
