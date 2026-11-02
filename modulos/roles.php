<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 16 «CL – Roles del back office» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — Roles del back office (fase 2, v1, sept. 2026)
 * Code Snippets: tipo PHP, "Ejecutar en todas partes". Sin <?php.
 *
 * Crea dos roles:
 *   - Emprendedor pendiente (cl_emprendedor_pendiente): cuenta en revisión, no puede iniciar sesión.
 *   - Emprendedor (cl_emprendedor): solo usa el panel del sitio; sin wp-admin ni barra de administración.
 * No cambia el rol de ningún usuario existente.
 */

// Crea los roles una sola vez (se vuelve a ejecutar si cambia la versión).
add_action( 'init', function () {
	if ( '1' === get_option( 'cl_roles_version' ) ) {
		return;
	}
	remove_role( 'cl_emprendedor_pendiente' );
	remove_role( 'cl_emprendedor' );
	add_role( 'cl_emprendedor_pendiente', 'Emprendedor pendiente', array() );
	add_role( 'cl_emprendedor', 'Emprendedor', array( 'read' => true ) );
	update_option( 'cl_roles_version', '1' );
} );

// ¿El usuario tiene alguno de los roles de emprendedor? (los administradores nunca cuentan)
function cl_es_emprendedor( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	if ( ! $user instanceof WP_User || ! $user->exists() || user_can( $user, 'manage_options' ) ) {
		return false;
	}
	return (bool) array_intersect( array( 'cl_emprendedor', 'cl_emprendedor_pendiente' ), (array) $user->roles );
}

// Cuenta pendiente: no puede iniciar sesión (tampoco por contraseña de aplicación).
add_filter( 'authenticate', function ( $user ) {
	if ( $user instanceof WP_User && in_array( 'cl_emprendedor_pendiente', (array) $user->roles, true ) ) {
		return new WP_Error(
			'cl_cuenta_pendiente',
			'Tu cuenta está en revisión. Te avisamos por email cuando esté activa.'
		);
	}
	return $user;
}, 30 );

// Emprendedores fuera de wp-admin (admin-ajax sigue funcionando para los formularios).
add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || ! cl_es_emprendedor() ) {
		return;
	}
	wp_safe_redirect( home_url( '/panel/' ) );
	exit;
} );

// Sin barra de administración para emprendedores.
add_filter( 'show_admin_bar', function ( $show ) {
	return cl_es_emprendedor() ? false : $show;
} );