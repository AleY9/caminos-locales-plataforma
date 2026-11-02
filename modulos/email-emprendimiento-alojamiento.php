<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 14 «Caminos Locales – email emprendimiento en alojamientos» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — email del emprendimiento para el formulario de los alojamientos.
 * Pegar en Code Snippets como snippet NUEVO (tipo PHP, "Ejecutar en todas partes"). Sin <?php.
 *
 * Uso en Bricks: {echo:cl_email_emprendimiento_alojamiento}
 * Devuelve el email (campo email-emp) del emprendimiento vinculado al alojamiento
 * (campo emprendimiento_correspondiente). Si no hay, devuelve vacío.
 */

// Permite usar la función en la etiqueta dinámica {echo:...} de Bricks.
add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	$names   = (array) $names;
	$names[] = 'cl_email_emprendimiento_alojamiento';
	return $names;
} );

function cl_email_emprendimiento_alojamiento( $post_id = 0 ) {
	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	// Al enviar el formulario (AJAX), Bricks manda el ID de la página en postId.
	if ( ( ! $post_id || 'alojamiento' !== get_post_type( $post_id ) ) && isset( $_POST['postId'] ) ) {
		$post_id = absint( $_POST['postId'] );
	}
	if ( ! $post_id || 'alojamiento' !== get_post_type( $post_id ) || ! function_exists( 'get_field' ) ) {
		return '';
	}

	$rel = get_field( 'emprendimiento_correspondiente', $post_id );
	if ( empty( $rel ) ) {
		return '';
	}
	$first  = is_array( $rel ) ? reset( $rel ) : $rel;
	$emp_id = is_object( $first ) ? (int) $first->ID : (int) $first;

	$email = trim( (string) get_field( 'email-emp', $emp_id ) );
	return is_email( $email ) ? $email : '';
}