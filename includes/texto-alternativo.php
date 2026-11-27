<?php
/**
 * Texto alternativo de las fotos de las fichas (1.4.6).
 *
 * Las fotos que se suben desde el panel ya traen texto alternativo. Este archivo completa las que no tienen
 * (subidas antes del panel, o asignadas desde wp-admin o por MCP en una carga asistida):
 * - foto principal: el nombre de la ficha; las demás: «Nombre de la ficha – foto 2», «– foto 3»…
 * - Solo si el texto alternativo está vacío: nunca reemplaza uno escrito.
 * - Al guardar una ficha (emprendimiento, alojamiento o experiencia) y, una sola vez, en todas las fichas
 *   (al entrar un administrador a wp-admin; opción clp_alt_146).
 */

defined( 'ABSPATH' ) || exit;

// Completa el texto alternativo vacío de las fotos de una ficha. Devuelve cuántas completó.
function cl_alt_completar( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return 0;
	}
	if ( ! in_array( get_post_type( $post_id ), array( 'emprendimientos', 'alojamiento', 'evento' ), true ) || ! function_exists( 'cl_fotos_de_ficha' ) ) {
		return 0;
	}
	$titulo = trim( wp_specialchars_decode( (string) get_post_field( 'post_title', $post_id ), ENT_QUOTES ) );
	if ( '' === $titulo ) {
		return 0;
	}
	$thumb = (int) get_post_thumbnail_id( $post_id );
	$n     = 1;
	$hecho = 0;
	foreach ( cl_fotos_de_ficha( $post_id ) as $att ) {
		if ( ! wp_attachment_is_image( $att ) ) {
			continue;
		}
		$es_principal = $att === $thumb;
		if ( ! $es_principal ) {
			$n++;
		}
		if ( '' !== trim( (string) get_post_meta( $att, '_wp_attachment_image_alt', true ) ) ) {
			continue;
		}
		update_post_meta( $att, '_wp_attachment_image_alt', $es_principal ? $titulo : $titulo . ' – foto ' . $n );
		$hecho++;
	}
	return $hecho;
}

add_action( 'acf/save_post', 'cl_alt_completar', 40 );
add_action( 'save_post', function ( $post_id ) {
	cl_alt_completar( $post_id );
}, 100 );

// Una sola vez: todas las fichas que ya están cargadas.
add_action( 'admin_init', function () {
	if ( get_option( 'clp_alt_146' ) || ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
		return;
	}
	update_option( 'clp_alt_146', current_time( 'mysql' ), false );
	$ids = get_posts( array(
		'post_type'   => array( 'emprendimientos', 'alojamiento', 'evento' ),
		'post_status' => array( 'publish', 'draft', 'pending' ),
		'numberposts' => 500,
		'fields'      => 'ids',
	) );
	$total = 0;
	foreach ( $ids as $id ) {
		$total += cl_alt_completar( $id );
	}
	update_option( 'clp_alt_146', current_time( 'mysql' ) . ' · ' . $total . ' fotos', false );
} );
