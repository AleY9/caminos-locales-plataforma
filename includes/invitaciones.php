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
 *
 * 1.1.9: invitación para una ficha que ya existe (carga asistida). Al crearla se elige la ficha; quien la usa crea su
 * cuenta y la ficha pasa a ser suya (con sus alojamientos y experiencias). No se crea otro emprendimiento.
 * En Bricks, el campo «Nombre del emprendimiento» (clinve) toma {echo:cl_inv_emp} como valor.
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

// 1.1.9: ficha ya cargada que la invitación entrega (0 si es una invitación común).
function clp_inv_ficha( $h ) {
	$todas = clp_inv_todas();
	$id    = $h && ! empty( $todas[ $h ]['ficha'] ) ? (int) $todas[ $h ]['ficha'] : 0;
	return ( $id && 'emprendimientos' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) ? $id : 0;
}

// Para Bricks: el nombre de la ficha ya cargada, para que el campo «Nombre del emprendimiento» venga completo.
function cl_inv_emp() {
	$t = isset( $_GET['inv'] ) ? sanitize_text_field( wp_unslash( $_GET['inv'] ) ) : ''; // phpcs:ignore
	$id = clp_inv_ficha( clp_inv_clave( $t ) );
	return $id ? get_the_title( $id ) : '';
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_inv_valida', 'cl_inv_nombre', 'cl_inv_emp' ) );
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

	$ficha = clp_inv_ficha( $h );
	if ( $ficha ) {
		// 1.1.9: la ficha ya cargada pasa a ser suya, con sus alojamientos, experiencias y fotos.
		$emp_id = $ficha;
		$datos  = array( 'ID' => $emp_id, 'post_author' => $uid );
		if ( $emp !== get_the_title( $emp_id ) ) {
			$datos['post_title'] = $emp;
		}
		wp_update_post( $datos );
		$ligados = get_posts( array(
			'post_type'   => array( 'alojamiento', 'evento' ),
			'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
			'numberposts' => 200,
			'fields'      => 'ids',
			'meta_query'  => array(
				'relation' => 'OR',
				array( 'key' => 'emprendimiento_correspondiente', 'value' => '"' . $emp_id . '"', 'compare' => 'LIKE' ),
				array( 'key' => 'corresponde_a_emprendimiento', 'value' => '"' . $emp_id . '"', 'compare' => 'LIKE' ),
			),
		) );
		foreach ( $ligados as $l ) {
			wp_update_post( array( 'ID' => (int) $l, 'post_author' => $uid ) );
		}
		foreach ( get_attached_media( 'image', $emp_id ) as $att ) {
			wp_update_post( array( 'ID' => $att->ID, 'post_author' => $uid ) );
		}
		// Email y WhatsApp: solo si la ficha no los tenía.
		if ( function_exists( 'cl_panel_escribir' ) ) {
			if ( '' === trim( (string) get_post_meta( $emp_id, 'email-emp', true ) ) ) {
				cl_panel_escribir( 'email-emp', $email, $emp_id );
			}
			if ( '' !== $tel && '' === trim( (string) get_post_meta( $emp_id, 'telefono_contacto', true ) ) ) {
				cl_panel_escribir( 'telefono_contacto', mb_substr( $tel, 0, 30 ), $emp_id );
			}
		}
	} else {
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
		'Emprendimiento: ' . $emp . ( $ficha ? ' (ficha que ya estaba cargada: ahora es suya)' : ' (borrador hasta que lo publique desde el panel)' ) . "\n" .
		'Email: ' . $email . "\n" . ( '' !== $tel ? 'WhatsApp: ' . $tel . "\n" : '' ) . "\n" .
		'Ver en wp-admin: ' . admin_url( 'post.php?post=' . $emp_id . '&action=edit' )
	);

	$form->set_result( array( 'action' => 'cl_inv', 'type' => 'success', 'message' => '¡Listo! Te llevamos a tu panel…' ) );
} );

// ---------- Herramientas → Caminos Locales: crear y ver invitaciones ----------

// Crea una invitación y devuelve su enlace. $ficha: emprendimiento ya cargado que se entrega (1.1.9) o 0.
function clp_inv_crear( $nota, $ficha = 0 ) {
	$token = wp_generate_password( 32, false, false );
	$todas = clp_inv_todas();
	$enlace = add_query_arg( 'inv', $token, home_url( '/invitacion/' ) );
	$todas[ hash( 'sha256', $token ) ] = array(
		'nota'   => '' === $nota ? 'Sin nombre' : mb_substr( $nota, 0, 80 ),
		'creado' => time(),
		'vence'  => time() + CLP_INV_DIAS * DAY_IN_SECONDS,
		'usado'  => 0,
		'ficha'  => ( $ficha && 'emprendimientos' === get_post_type( $ficha ) ) ? (int) $ficha : 0,
		// El enlace completo se guarda solo para poder volver a copiarlo (solo administradores).
		'enlace' => $enlace,
	);
	update_option( 'clp_invitaciones', $todas, false );
	return $enlace;
}

// 1.1.10: invitación vigente (sin usar ni vencida) para una ficha, o null.
function clp_inv_de_ficha( $ficha ) {
	foreach ( clp_inv_todas() as $v ) {
		if ( ! empty( $v['ficha'] ) && (int) $v['ficha'] === (int) $ficha && ! $v['usado'] && time() <= (int) $v['vence'] ) {
			return $v;
		}
	}
	return null;
}

// 1.1.10: dueño emprendedor de una ficha (WP_User) o null si la ficha es del administrador.
function clp_inv_dueno( $ficha ) {
	$u = get_userdata( (int) get_post_field( 'post_author', $ficha ) );
	return ( $u && array_intersect( array( 'cl_emprendedor', 'cl_emprendedor_pendiente' ), (array) $u->roles ) ) ? $u : null;
}

// Mensaje de WhatsApp de una invitación.
function clp_inv_mensaje( $v ) {
	$f = ! empty( $v['ficha'] ) ? (int) $v['ficha'] : 0;
	return $f
		? 'Hola! Ya cargamos la ficha de ' . get_the_title( $f ) . ' en Caminos Locales. Con este enlace creás tu cuenta y la podés revisar y cambiar cuando quieras (sirve una sola vez): ' . $v['enlace']
		: 'Hola! Te invito a sumar tu emprendimiento a Caminos Locales. Con este enlace creás tu cuenta y cargás tu ficha (sirve una sola vez): ' . $v['enlace'];
}

// 1.1.10: desde Mi panel (carga asistida): nueva ficha a nombre del administrador e invitación para su dueño.
add_action( 'admin_post_clp_panel_admin', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'clp_panel_admin' ) ) {
		wp_die( 'Sin permiso.' );
	}
	$panel  = defined( 'CL_PANEL_PAGE_ID' ) ? get_permalink( CL_PANEL_PAGE_ID ) : home_url( '/panel/' );
	$accion = sanitize_key( $_POST['clp_accion'] ?? '' );
	if ( 'nueva' === $accion ) {
		$nombre = trim( sanitize_text_field( wp_unslash( $_POST['clp_nombre'] ?? '' ) ) );
		if ( '' === $nombre ) {
			wp_safe_redirect( $panel );
			exit;
		}
		$id = wp_insert_post( array(
			'post_type'   => 'emprendimientos',
			'post_title'  => mb_substr( $nombre, 0, 100 ),
			'post_status' => 'draft',
			'post_author' => get_current_user_id(),
		) );
		wp_safe_redirect( is_wp_error( $id ) || ! $id ? $panel : add_query_arg( 'emp', (int) $id, $panel ) );
		exit;
	}
	if ( 'invitar' === $accion ) {
		$id = (int) ( $_POST['emp'] ?? 0 );
		if ( 'emprendimientos' === get_post_type( $id ) && ! clp_inv_dueno( $id ) && ! clp_inv_de_ficha( $id ) ) {
			clp_inv_crear( get_the_title( $id ), $id );
		}
		wp_safe_redirect( add_query_arg( 'emp', $id, $panel ) . '#clp-admin' );
		exit;
	}
	wp_safe_redirect( $panel );
	exit;
} );

// 1.1.10: bloque de Mi panel para el administrador: el procedimiento, nueva ficha e «Invitar al dueño».
function clp_panel_admin_extra( $id ) {
	$post = admin_url( 'admin-post.php' );
	$nonce = wp_nonce_field( 'clp_panel_admin', '_wpnonce', true, false );
	$btn  = 'min-height:44px;border-radius:10px;border:0;background:#1f4645;color:#fff;font-weight:700;font-size:15px;padding:0 16px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;text-decoration:none';
	$h    = '';

	// Nueva ficha.
	$h .= '<form method="post" action="' . esc_url( $post ) . '" style="display:flex;gap:8px;flex-wrap:wrap">';
	$h .= '<input type="hidden" name="action" value="clp_panel_admin"><input type="hidden" name="clp_accion" value="nueva">' . $nonce;
	$h .= '<input type="text" name="clp_nombre" required maxlength="100" placeholder="Nombre del emprendimiento nuevo" style="flex:1 1 220px;height:44px;border:1px solid #c9a76a;border-radius:10px;padding:0 12px;font-size:16px;background:#fff">';
	$h .= '<button style="' . $btn . '">+ Nueva ficha</button></form>';

	// Invitar al dueño de la ficha elegida.
	if ( $id ) {
		$dueno = clp_inv_dueno( $id );
		$h    .= '<div style="border-top:1px solid #e9d3a4;padding-top:10px;display:flex;flex-direction:column;gap:6px">';
		if ( $dueno ) {
			$h .= '<div style="font-size:15px;color:#3d4b49"><strong>Dueño:</strong> ' . esc_html( $dueno->display_name ) . ' (' . esc_html( $dueno->user_email ) . '). Ya tiene su cuenta: no hace falta invitarlo.</div>';
		} else {
			$v  = clp_inv_de_ficha( $id );
			$h .= '<div style="font-size:15px;font-weight:700;color:#6b4300">Invitar al dueño</div>';
			if ( $v ) {
				$h .= '<div style="font-size:14px;color:#3d4b49">Enlace listo, vigente hasta el ' . esc_html( wp_date( 'd/m', $v['vence'] ) ) . '. Mandalo por WhatsApp: al usarlo, el dueño crea su cuenta con su email y la ficha pasa a ser suya.</div>';
				$h .= '<input type="text" readonly value="' . esc_attr( $v['enlace'] ) . '" onclick="this.select()" style="height:40px;border:1px solid #c9a76a;border-radius:10px;padding:0 10px;font-size:13px;background:#fff">';
				$h .= '<a href="' . esc_url( 'https://wa.me/?text=' . rawurlencode( clp_inv_mensaje( $v ) ) ) . '" target="_blank" rel="noopener" style="' . $btn . ';background:#25803c;align-self:flex-start">Mandar por WhatsApp</a>';
			} else {
				$h .= '<div style="font-size:14px;color:#3d4b49">Cuando la ficha esté lista, creá el enlace y mandáselo. No hace falta su email: lo pone él al crear su cuenta.</div>';
				$h .= '<form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="clp_panel_admin"><input type="hidden" name="clp_accion" value="invitar"><input type="hidden" name="emp" value="' . (int) $id . '">' . $nonce;
				$h .= '<button style="' . $btn . '">Crear enlace de invitación</button></form>';
			}
		}
		$h .= '</div>';
	}

	// El procedimiento.
	$h .= '<details style="font-size:14px;color:#3d4b49"><summary style="cursor:pointer;font-weight:700;color:#6b4300;min-height:36px;display:flex;align-items:center">Cómo cargar una ficha por alguien</summary><ol style="margin:4px 0 6px 18px;padding:0;line-height:1.5">';
	$h .= '<li><strong>+ Nueva ficha</strong> con el nombre del emprendimiento (queda en borrador, a tu nombre).</li>';
	$h .= '<li>Completala con los pasos del panel, como lo haría el emprendedor. Si querés, publicala.</li>';
	$h .= '<li><strong>Crear enlace de invitación</strong> y <strong>Mandar por WhatsApp</strong>.</li>';
	$h .= '<li>El dueño abre el enlace, pone su nombre, email y contraseña, y la ficha pasa a ser suya, con sus alojamientos y experiencias. Te llega un correo.</li>';
	$h .= '</ol>No uses cuentas con tu email o alias: la ficha tiene que quedar a tu nombre hasta que el dueño la reclame.</details>';
	return $h;
}

add_action( 'admin_post_clp_invitacion', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'clp_invitacion' ) ) {
		wp_die( 'Sin permiso.' );
	}
	$todas  = clp_inv_todas();
	$accion = sanitize_key( $_POST['clp_accion'] ?? '' );
	if ( 'crear' === $accion ) {
		$nota = trim( sanitize_text_field( wp_unslash( $_POST['clp_nota'] ?? '' ) ) );
		clp_inv_crear( $nota, isset( $_POST['clp_ficha'] ) ? (int) $_POST['clp_ficha'] : 0 );
		$todas = clp_inv_todas();
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

// 1.1.9: fichas cuyo autor no es un emprendedor (las cargó el admin), para entregarlas con una invitación.
function clp_inv_fichas_sin_dueno() {
	$ids = get_posts( array(
		'post_type'   => 'emprendimientos',
		'post_status' => array( 'publish', 'draft', 'pending' ),
		'numberposts' => 200,
		'fields'      => 'ids',
		'orderby'     => 'title',
		'order'       => 'ASC',
	) );
	return array_values( array_filter( $ids, function ( $id ) {
		$u = get_userdata( (int) get_post_field( 'post_author', $id ) );
		return ! $u || ! array_intersect( array( 'cl_emprendedor', 'cl_emprendedor_pendiente' ), (array) $u->roles );
	} ) );
}

function clp_invitaciones_caja() {
	$todas = clp_inv_todas();
	uasort( $todas, function ( $a, $b ) {
		return $b['creado'] <=> $a['creado'];
	} );
	?>
	<div id="clp-invitaciones" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 16px;margin:16px 0;max-width:760px">
		<h2 style="margin-top:0">Invitar a sumarse</h2>
		<p>Un enlace de un solo uso que vence en <?php echo (int) CLP_INV_DIAS; ?> días. Quien lo abre crea su cuenta y entra directo a su panel, sin esperar tu aprobación. Usalo con gente de confianza.</p>
		<p>Si ya le cargaste la ficha, elegila en la lista: al usar el enlace, la ficha (con sus alojamientos y experiencias) pasa a ser suya y no se crea otra.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
			<input type="hidden" name="action" value="clp_invitacion">
			<input type="hidden" name="clp_accion" value="crear">
			<?php wp_nonce_field( 'clp_invitacion' ); ?>
			<input type="text" name="clp_nota" placeholder="Para quién (ej.: Marta, cabañas en Aiguá)" maxlength="80" style="min-width:300px">
			<select name="clp_ficha" style="min-width:260px">
				<option value="0">Emprendimiento nuevo</option>
				<?php foreach ( clp_inv_fichas_sin_dueno() as $fid ) : ?>
					<option value="<?php echo (int) $fid; ?>">Ficha ya cargada: <?php echo esc_html( get_the_title( $fid ) ); ?></option>
				<?php endforeach; ?>
			</select>
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
					$ficha_v = ! empty( $v['ficha'] ) ? (int) $v['ficha'] : 0;
					$msg     = clp_inv_mensaje( $v );
					?>
					<tr>
						<td><?php echo esc_html( $v['nota'] ); ?><?php echo $ficha_v ? '<br><small>Ficha: ' . esc_html( get_the_title( $ficha_v ) ) . '</small>' : ''; ?></td>
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
