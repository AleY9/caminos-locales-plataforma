<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 24 «CL – Panel: Cuenta» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — Panel: Mi cuenta (v1, 28/09/2026)
 * Code Snippets: tipo PHP, "Ejecutar en todas partes". Sin <?php.
 * Necesita "CL – Panel" (cl_panel_puede_usar, CL_PANEL_PAGE_ID).
 *
 * Solo lo que Bricks no puede hacer: guardar nombre, email y contraseña del usuario.
 * La página /panel/cuenta/ (28413) y su formulario (mcform) están armados en Bricks.
 * - Cambiar email o contraseña pide la contraseña actual.
 * - WordPress avisa por email al usuario cuando cambia su email o su contraseña.
 * - Si cambia el email de la cuenta, llega un aviso al administrador.
 * - Al cambiar la contraseña se cierran las otras sesiones abiertas.
 * - Activa la tarjeta "Mi cuenta" en Mi panel.
 */

add_action( 'bricks/form/custom_action', function ( $form ) {
	$f = $form->get_fields();
	if ( ! isset( $f['form-field-clpaso'] ) || 'cuenta' !== sanitize_key( $f['form-field-clpaso'] ) ) {
		return;
	}
	$error = function ( $msg ) use ( $form ) {
		$form->set_result( array( 'action' => 'cl_cuenta', 'type' => 'error', 'message' => $msg ) );
	};
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return $error( 'Tu sesión venció. Volvé a iniciar sesión.' );
	}

	// Límite: 10 intentos cada 10 minutos (protege la contraseña actual de pruebas repetidas).
	$uid   = get_current_user_id();
	$clave = 'cl_cuenta_rl_' . $uid;
	$n     = (int) get_transient( $clave );
	if ( $n >= 10 ) {
		return $error( 'Probaste muchas veces seguidas. Esperá unos minutos y volvé a intentar.' );
	}
	set_transient( $clave, $n + 1, 10 * MINUTE_IN_SECONDS );

	$v = function ( $id ) use ( $f ) {
		$x = isset( $f[ 'form-field-' . $id ] ) ? $f[ 'form-field-' . $id ] : '';
		return is_array( $x ) ? (string) reset( $x ) : (string) $x;
	};
	$user   = get_userdata( $uid );
	$nombre = trim( sanitize_text_field( wp_unslash( $v( 'mcnomb' ) ) ) );
	$email  = trim( sanitize_email( wp_unslash( $v( 'mcmail' ) ) ) );
	$nueva  = (string) wp_unslash( $v( 'mcnuev' ) );
	$repe   = (string) wp_unslash( $v( 'mcrepe' ) );
	$actual = (string) wp_unslash( $v( 'mcactu' ) );

	if ( '' === $nombre || mb_strlen( $nombre ) > 60 ) {
		return $error( 'Escribí tu nombre (hasta 60 caracteres).' );
	}
	if ( ! is_email( $email ) || mb_strlen( $email ) > 100 ) {
		return $error( 'Ese email no parece válido. Revisalo.' );
	}

	$cambia_mail  = strtolower( $email ) !== strtolower( $user->user_email );
	$cambia_clave = '' !== $nueva || '' !== $repe;

	if ( $cambia_clave ) {
		if ( mb_strlen( $nueva ) < 8 ) {
			return $error( 'La contraseña nueva tiene que tener al menos 8 caracteres.' );
		}
		if ( $nueva !== $repe ) {
			return $error( 'Las dos contraseñas nuevas no coinciden.' );
		}
	}
	if ( $cambia_mail || $cambia_clave ) {
		if ( '' === $actual ) {
			return $error( 'Para cambiar el email o la contraseña, escribí tu contraseña actual.' );
		}
		if ( ! wp_check_password( $actual, $user->user_pass, $uid ) ) {
			return $error( 'La contraseña actual no es correcta.' );
		}
	}
	if ( $cambia_mail ) {
		$otro = email_exists( $email );
		if ( $otro && (int) $otro !== $uid ) {
			return $error( 'Ese email ya lo usa otra cuenta. Si es tuyo, escribinos y lo resolvemos.' );
		}
	}

	$datos = array(
		'ID'           => $uid,
		'first_name'   => $nombre,
		'display_name' => $nombre,
		'nickname'     => $nombre,
	);
	if ( $cambia_mail ) {
		$datos['user_email'] = $email;
	}
	if ( $cambia_clave ) {
		$datos['user_pass'] = $nueva; // wp_update_user la guarda cifrada y mantiene esta sesión abierta.
	}
	$res = wp_update_user( $datos );
	if ( is_wp_error( $res ) ) {
		return $error( 'No pudimos guardar los cambios. Probá de nuevo o escribinos.' );
	}
	if ( $cambia_clave ) {
		wp_destroy_other_sessions();
	}
	if ( $cambia_mail ) {
		wp_mail(
			get_option( 'admin_email' ),
			'Caminos Locales: cambio de email de una cuenta',
			sprintf( "La cuenta \"%s\" (%s) cambió su email de acceso:\n\nAntes: %s\nAhora: %s\n", $user->user_login, $nombre, $user->user_email, $email )
		);
	}

	$msg = 'Guardado ✓';
	if ( $cambia_mail || $cambia_clave ) {
		$msg .= ' Te mandamos un aviso por email.';
	}
	$form->set_result( array( 'action' => 'cl_cuenta', 'type' => 'success', 'message' => $msg ) );
} );

// Tarjeta "Mi cuenta" en Mi panel: de "próximamente" a enlace.
add_filter( 'do_shortcode_tag', function ( $out, $tag ) {
	if ( 'cl_panel_inicio' !== $tag || ! is_user_logged_in() ) {
		return $out;
	}
	$url  = esc_url( home_url( '/panel/cuenta/' ) );
	$mail = str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), esc_html( wp_get_current_user()->user_email ) );
	return preg_replace(
		'#<a class="clp-card" href="\#" aria-disabled="true">(\s*<div class="clp-t">Mi cuenta</div>\s*)<div class="clp-s">[^<]*</div>#u',
		'<a class="clp-card" href="' . $url . '">$1<div class="clp-s">' . $mail . '</div><div class="clp-row"><span class="clp-s">Nombre, email y contraseña</span><span class="clp-go">Editar →</span></div>',
		$out,
		1
	);
}, 10, 2 );