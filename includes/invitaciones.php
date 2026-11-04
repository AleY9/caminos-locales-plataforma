<?php
/**
 * Invitaciones (v1.1.0): enlace de un solo uso para sumarse sin pasar por la aprobación.
 *
 * Ale crea el enlace en Herramientas → Caminos Locales y lo manda por WhatsApp. Quien lo abre completa un formulario
 * corto en /invitacion/ (página de Bricks), elige su contraseña y entra directo a su panel con el emprendimiento
 * creado en borrador. El enlace vence a los 14 días y sirve una sola vez.
 *
 * El formulario de Bricks usa la acción «Personalizada» (la resuelve este archivo) y después «Redirigir» a /panel/.
 * Campo oculto clinvt = {url_parameter:inv}. Condición del formulario: {echo:cl_inv_valida} == 1.
 */

defined( 'ABSPATH' ) || exit;

define( 'CLP_INV_DIAS', 14 );

function clp_inv_todas() {
	$v = get_option( 'clp_invitaciones', array() );
	return is_array( $v ) ? $v : array();
}

// Devuelve la clave (hash) de una invitación vigente, o ''.
function clp_inv_clave( $token ) {
	$token = is_string( $token ) ? trim( $token ) : '';
	if ( strlen( $token ) < 24 ) {
		return '';
	}
	$h     = hash( 'sha256', $token );
	$todas = clp_inv_todas();
	if ( empty( $todas[ $h ] ) || ! empty( $todas[ $h ]['usado'] ) || time() > (int) $todas[ $h ]['vence'] ) {
		return '';
	}
	return $h;
}

// Para Bricks: {echo:cl_inv_valida} da 1 si el enlace de la dirección sirve.
function cl_inv_valida() {
	$t = isset( $_GET['inv'] ) ? sanitize_text_field( wp_unslash( $_GET['inv'] ) ) : ''; // phpcs:ignore
	return clp_inv_clave( $t ) ? '1' : '';
}

// Para Bricks: el nombre que Ale anotó al crear la invitación (para el saludo).
function cl_inv_nombre() {
	$t = isset( $_GET['inv'] ) ? sanitize_text_field( wp_unslash( $_GET['inv'] ) ) : ''; // phpcs:ignore
	$h = clp_inv_clave( $t );
	return $h ? (string) clp_inv_todas()[ $h ]['nota'] : '';
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_inv_valida', 'cl_inv_nombre' ) );
} );

// La página de invitación no se guarda en caché ni la indexan los buscadores.
add_action( 'template_redirect', function () {
	if ( is_page( 'invitacion' ) ) {
		nocache_headers();
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_control_set_nocache', 'invitación' );
		}
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}
} );

// ---------- Formulario (Bricks, acción personalizada) ----------

add_action( 'bricks/form/custom_action', function ( $form ) {
	$f = $form->get_fields();
	if ( ! isset( $f['form-field-clinvt'] ) ) {
		return; // No es el formulario de invitación.
	}
	$error = function ( $msg ) use ( $form ) {
		$form->set_result( array( 'action' => 'cl_inv', 'type' => 'error', 'message' => $msg ) );
	};

	// Límite: 10 intentos cada 10 minutos por IP.
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$clave = 'cl_inv_rl_' . md5( $ip );
	$n     = (int) get_transient( $clave );
	if ( $n >= 10 ) {
		return $error( 'Hubo demasiados intentos. Esperá unos minutos y probá de nuevo.' );
	}
	set_transient( $clave, $n + 1, 10 * MINUTE_IN_SECONDS );

	$h = clp_inv_clave( sanitize_text_field( $f['form-field-clinvt'] ) );
	if ( ! $h ) {
		return $error( 'Este enlace de invitación ya se usó o venció. Pedile uno nuevo a Caminos Locales.' );
	}
	if ( is_user_logged_in() ) {
		return $error( 'Ya tenés una sesión iniciada. Cerrala para usar esta invitación.' );
	}

	$nombre = trim( sanitize_text_field( $f['form-field-clinvn'] ?? '' ) );
	$emp    = trim( sanitize_text_field( $f['form-field-clinve'] ?? '' ) );
	$email  = sanitize_email( $f['form-field-clinvm'] ?? '' );
	$tel    = trim( sanitize_text_field( $f['form-field-clinvw'] ?? '' ) );
	$clave1 = (string) ( $f['form-field-clinvp'] ?? '' );

	if ( '' === $nombre || '' === $emp ) {
		return $error( 'Completá tu nombre y el nombre de tu emprendimiento.' );
	}
	if ( mb_strlen( $emp ) > 100 || mb_strlen( $nombre ) > 100 ) {
		return $error( 'El nombre es demasiado largo (hasta 100 caracteres).' );
	}
	if ( ! is_email( $email ) ) {
		return $error( 'Revisá el email: no parece válido.' );
	}
	if ( email_exists( $email ) ) {
		return $error( 'Ya hay una cuenta con ese email. Entrá desde «Acceso» o pedí que te recuperemos la contraseña.' );
	}
	$clave2 = (string) ( $f['form-field-clinvq'] ?? $clave1 ); // «Repetí la contraseña» (1.1.0)
	if ( $clave1 !== $clave2 ) {
		return $error( 'Las dos contraseñas no coinciden. Escribilas de nuevo.' );
	}
	if ( strlen( $clave1 ) < 8 ) {
		return $error( 'La contraseña tiene que tener al menos 8 caracteres.' );
	}

	// Usuario.
	$login = sanitize_user( strstr( $email, '@', true ), true );
	$login = '' === $login ? 'emprendedor' : $login;
	$base  = $login;
	$i     = 2;
	while ( username_exists( $login ) ) {
		$login = $base . $i++;
	}
	$partes = preg_split( '/\s+/', $nombre, 2 );
	$uid    = wp_insert_user( array(
		'user_login'   => $login,
		'user_email'   => $email,
		'user_pass'    => $clave1,
		'display_name' => $nombre,
		'first_name'   => $partes[0],
		'last_name'    => $partes[1] ?? '',
		'role'         => 'cl_emprendedor',
	) );
	if ( is_wp_error( $uid ) ) {
		return $error( 'No se pudo crear la cuenta: ' . $uid->get_error_message() );
	}

	// Emprendimiento en borrador, a su nombre.
	$emp_id = wp_insert_post( array(
		'post_type'   => 'emprendimientos',
		'post_title'  => $emp,
		'post_status' => 'draft',
		'post_author' => $uid,
	), true );
	if ( is_wp_error( $emp_id ) ) {
		return $error( 'Se creó tu cuenta, pero no tu emprendimiento. Avisanos y lo resolvemos.' );
	}
	if ( function_exists( 'cl_panel_escribir' ) ) {
		cl_panel_escribir( 'email-emp', $email, $emp_id );
		if ( '' !== $tel ) {
			cl_panel_escribir( 'telefono_contacto', mb_substr( $tel, 0, 30 ), $emp_id );
		}
	}

	// La invitación queda usada.
	$todas                    = clp_inv_todas();
	$todas[ $h ]['usado']     = time();
	$todas[ $h ]['usuario']   = (int) $uid;
	$todas[ $h ]['emprendimiento'] = (int) $emp_id;
	update_option( 'clp_invitaciones', $todas, false );

	// Entra directo.
	wp_set_current_user( $uid );
	wp_set_auth_cookie( $uid, true, is_ssl() );

	wp_mail(
		get_option( 'admin_email' ),
		'[Caminos Locales] Se sumó ' . $emp . ' (invitación)',
		$nombre . ' usó la invitación «' . $todas[ $h ]['nota'] . '» y creó su cuenta.' . "\n\n" .
		'Emprendimiento: ' . $emp . ' (borrador hasta que lo publique desde el panel)' . "\n" .
		'Email: ' . $email . "\n" . ( '' !== $tel ? 'WhatsApp: ' . $tel . "\n" : '' ) . "\n" .
		'Ver en wp-admin: ' . admin_url( 'post.php?post=' . $emp_id . '&action=edit' )
	);

	$form->set_result( array( 'action' => 'cl_inv', 'type' => 'success', 'message' => '¡Listo! Te llevamos a tu panel…' ) );
} );

// ---------- Herramientas → Caminos Locales: crear y ver invitaciones ----------

add_action( 'admin_post_clp_invitacion', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'clp_invitacion' ) ) {
		wp_die( 'Sin permiso.' );
	}
	$todas  = clp_inv_todas();
	$accion = sanitize_key( $_POST['clp_accion'] ?? '' );
	if ( 'crear' === $accion ) {
		$nota  = trim( sanitize_text_field( wp_unslash( $_POST['clp_nota'] ?? '' ) ) );
		$token = wp_generate_password( 32, false, false );
		$todas[ hash( 'sha256', $token ) ] = array(
			'nota'   => '' === $nota ? 'Sin nombre' : mb_substr( $nota, 0, 80 ),
			'creado' => time(),
			'vence'  => time() + CLP_INV_DIAS * DAY_IN_SECONDS,
			'usado'  => 0,
			// El enlace completo se guarda solo para poder volver a copiarlo desde esta pantalla (solo administradores).
			'enlace' => add_query_arg( 'inv', $token, home_url( '/invitacion/' ) ),
		);
	} elseif ( 'anular' === $accion ) {
		unset( $todas[ sanitize_text_field( wp_unslash( $_POST['clp_clave'] ?? '' ) ) ] );
	}
	// Se borran las vencidas o usadas hace más de 60 días.
	foreach ( $todas as $k => $v ) {
		$ref = $v['usado'] ? $v['usado'] : $v['vence'];
		if ( time() - $ref > 60 * DAY_IN_SECONDS ) {
			unset( $todas[ $k ] );
		}
	}
	update_option( 'clp_invitaciones', $todas, false );
	wp_safe_redirect( admin_url( 'tools.php?page=caminos-locales#clp-invitaciones' ) );
	exit;
} );

function clp_invitaciones_caja() {
	$todas = clp_inv_todas();
	uasort( $todas, function ( $a, $b ) {
		return $b['creado'] <=> $a['creado'];
	} );
	?>
	<div id="clp-invitaciones" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 16px;margin:16px 0;max-width:760px">
		<h2 style="margin-top:0">Invitar a sumarse</h2>
		<p>Un enlace de un solo uso que vence en <?php echo (int) CLP_INV_DIAS; ?> días. Quien lo abre crea su cuenta y entra directo a su panel, sin esperar tu aprobación. Usalo con gente de confianza.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
			<input type="hidden" name="action" value="clp_invitacion">
			<input type="hidden" name="clp_accion" value="crear">
			<?php wp_nonce_field( 'clp_invitacion' ); ?>
			<input type="text" name="clp_nota" placeholder="Para quién (ej.: Marta, cabañas en Aiguá)" maxlength="80" style="min-width:300px">
			<button class="button button-primary">Crear enlace</button>
		</form>
		<?php if ( $todas ) : ?>
			<table class="widefat striped" style="margin-top:12px">
				<thead><tr><th>Para</th><th>Estado</th><th>Enlace</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $todas as $k => $v ) :
					$vencida = ! $v['usado'] && time() > $v['vence'];
					if ( $v['usado'] ) {
						$estado = 'Usada el ' . wp_date( 'd/m H:i', $v['usado'] ) . ( ! empty( $v['emprendimiento'] ) ? ' · ' . get_the_title( $v['emprendimiento'] ) : '' );
					} elseif ( $vencida ) {
						$estado = 'Vencida';
					} else {
						$estado = 'Vigente hasta el ' . wp_date( 'd/m', $v['vence'] );
					}
					$msg = 'Hola! Te invito a sumar tu emprendimiento a Caminos Locales. Con este enlace creás tu cuenta y cargás tu ficha (sirve una sola vez): ' . $v['enlace'];
					?>
					<tr>
						<td><?php echo esc_html( $v['nota'] ); ?></td>
						<td><?php echo esc_html( $estado ); ?></td>
						<td>
							<?php if ( ! $v['usado'] && ! $vencida ) : ?>
								<input type="text" readonly value="<?php echo esc_attr( $v['enlace'] ); ?>" onclick="this.select()" style="width:100%;font-size:12px">
								<a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $msg ) ); ?>" style="margin-top:4px">Mandar por WhatsApp</a>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! $v['usado'] ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="clp_invitacion">
									<input type="hidden" name="clp_accion" value="anular">
									<input type="hidden" name="clp_clave" value="<?php echo esc_attr( $k ); ?>">
									<?php wp_nonce_field( 'clp_invitacion' ); ?>
									<button class="button-link" style="color:#b32d2e">Anular</button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
