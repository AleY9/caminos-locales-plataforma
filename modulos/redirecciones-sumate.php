<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 25 «CL – Redirecciones de Sumate» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * CL – Redirecciones de Sumate (v1, 29/09/2026)
 * Code Snippets: tipo PHP, "Ejecutar solo en el sitio" (front-end). Sin <?php.
 * Direcciones viejas de alta y planes → páginas nuevas (301).
 * Solo redirige si la página de destino está publicada (se puede activar antes de publicar).
 * Páginas: Sumate 28429 (/sumate/), Pedí sumarte 28427 (/pedi-sumarte/).
 * Después de activarlo: purgar LiteSpeed.
 */
add_action( 'template_redirect', function () {
	$destinos = array(
		'planes-de-suscripcion-2025' => 28429, // Sumate
		'planes'                     => 28429, // Sumate
		'registro'                   => 28427, // Pedí sumarte
	);
	$ruta = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH ), '/' );
	if ( ! isset( $destinos[ $ruta ] ) ) {
		return;
	}
	$id = $destinos[ $ruta ];
	if ( 'publish' !== get_post_status( $id ) ) {
		return;
	}
	wp_safe_redirect( get_permalink( $id ), 301 );
	exit;
}, 1 );