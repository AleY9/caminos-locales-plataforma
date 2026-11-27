<?php
/**
 * Solicitudes de «Pedí sumarte» (1.2.0).
 *
 * El formulario psform de «Pedí sumarte» (página 28427) usa la acción «Personalizada» de Bricks, que resuelve este
 * archivo. Con lo que manda la persona se crea un emprendimiento en borrador, a nombre del administrador:
 * nombre, localidad, rubros de «¿Qué ofrecés?», WhatsApp, email y web o redes. Nunca se publica solo.
 * Al administrador le llega un correo con «Ver borrador», «Crear invitación» y «Descartar (spam)», y a la persona
 * el correo de confirmación que está escrito en Bricks. El formulario ya no usa la acción Email de Bricks.
 *
 * Antibots (además del honeypot de Bricks): token de un solo uso con tiempo mínimo de llenado
 * (campo oculto pstokn = {echo:cl_sol_token}) y límite de envíos por IP. La página no se guarda en caché.
 */

defined( 'ABSPATH' ) || exit;

define( 'CLP_SOL_PAGINA', 28427 );   // «Pedí sumarte»
define( 'CLP_SOL_SEGUNDOS', 8 );     // Tiempo mínimo de llenado.
define( 'CLP_SOL_POR_HORA', 3 );     // Envíos por IP y por hora.

// ---------- Token de un solo uso ----------

// {echo:cl_sol_token} → valor del campo oculto pstokn: «hora.azar.firma».
function cl_sol_token() {
	$t = (string) time();
	$r = wp_generate_password( 12, false, false );
	return $t . '.' . $r . '.' . substr( hash_hmac( 'sha256', $t . '.' . $r, wp_salt( 'nonce' ) ), 0, 24 );
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_sol_token' ) );
} );

// Devuelve '' si el token sirve, o el motivo por el que no.
function clp_sol_token_motivo( $token ) {
	$p = explode( '.', (string) $token );
	if ( 3 !== count( $p ) || ! ctype_digit( $p[0] ) ) {
		return 'sin token';
	}
	if ( ! hash_equals( substr( hash_hmac( 'sha256', $p[0] . '.' . $p[1], wp_salt( 'nonce' ) ), 0, 24 ), $p[2] ) ) {
		return 'token inválido';
	}
	$edad = time() - (int) $p[0];
	if ( $edad < CLP_SOL_SEGUNDOS ) {
		return 'demasiado rápido';
	}
	if ( $edad > DAY_IN_SECONDS ) {
		return 'token vencido';
	}
	$clave = 'cl_sol_tk_' . md5( $token );
	if ( get_transient( $clave ) ) {
		return 'token usado';
	}
	set_transient( $clave, 1, DAY_IN_SECONDS );
	return '';
}

// Sin caché: cada visita necesita su propio token.
add_action( 'template_redirect', function () {
	if ( is_page( CLP_SOL_PAGINA ) ) {
		nocache_headers();
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_control_set_nocache', 'pedí sumarte: token' );
		}
	}
} );

// ---------- Formulario ----------

add_action( 'bricks/form/custom_action', function ( $form ) {
	$f = $form->get_fields();
	if ( ! array_key_exists( 'form-field-psempr', $f ) ) {
		return; // No es «Pedí sumarte».
	}
	$settings = method_exists( $form, 'get_settings' ) ? (array) $form->get_settings() : array();
	$ok_msg   = ! empty( $settings['successMessage'] ) ? $settings['successMessage'] : 'Gracias, recibimos tu pedido. Te escribimos por WhatsApp en los próximos días.';
	$ok       = function () use ( $form, $ok_msg ) {
		$form->set_result( array( 'action' => 'cl_sol', 'type' => 'success', 'message' => $ok_msg ) );
	};
	$error = function ( $msg ) use ( $form ) {
		$form->set_result( array( 'action' => 'cl_sol', 'type' => 'error', 'message' => $msg ) );
	};

	// Honeypot (también lo mira Bricks): a un bot se le responde como si todo anduviera bien.
	if ( '' !== trim( (string) ( $f['form-field-pshony'] ?? '' ) ) ) {
		return $ok();
	}
	$motivo = clp_sol_token_motivo( sanitize_text_field( $f['form-field-pstokn'] ?? '' ) );
	if ( 'demasiado rápido' === $motivo ) {
		return $ok(); // Bot casi seguro.
	}
	if ( $motivo ) {
		return $error( 'La página quedó abierta mucho tiempo. Recargala y mandá el formulario de nuevo.' );
	}

	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip_h  = substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 16 );
	$clave = 'cl_sol_rl_' . $ip_h;
	$n     = (int) get_transient( $clave );
	if ( $n >= CLP_SOL_POR_HORA ) {
		return $error( 'Ya recibimos varios pedidos desde esta conexión. Si necesitás algo más, escribinos por WhatsApp al 099 247 249.' );
	}
	set_transient( $clave, $n + 1, HOUR_IN_SECONDS );

	$persona = mb_substr( trim( sanitize_text_field( $f['form-field-psnomb'] ?? '' ) ), 0, 100 );
	$nombre  = mb_substr( trim( sanitize_text_field( $f['form-field-psempr'] ?? '' ) ), 0, 100 );
	$local   = mb_substr( trim( sanitize_text_field( $f['form-field-psloca'] ?? '' ) ), 0, 200 );
	$tel     = mb_substr( trim( sanitize_text_field( $f['form-field-pswasa'] ?? '' ) ), 0, 30 );
	$email   = sanitize_email( $f['form-field-psmail'] ?? '' );
	$redes   = mb_substr( trim( sanitize_text_field( $f['form-field-psredes'] ?? '' ) ), 0, 300 );
	$mensaje = mb_substr( trim( sanitize_textarea_field( $f['form-field-psmens'] ?? '' ) ), 0, 2000 );
	$rubros  = array_filter( array_map( 'sanitize_text_field', (array) ( $f['form-field-psquee'] ?? array() ) ) );

	if ( '' === $nombre || '' === $persona || '' === $local || '' === $tel ) {
		return $error( 'Completá tu nombre, el del emprendimiento o servicio, la localidad y el WhatsApp.' );
	}

	// 1.4.6: de dónde llegó (campo oculto que suma includes/origen.php; sin preguntarle nada).
	list( $origen, $origen_pag ) = function_exists( 'cl_origen_de_envio' ) ? cl_origen_de_envio() : array( '', '' );

	$emp_id = clp_sol_crear_borrador( compact( 'persona', 'nombre', 'local', 'tel', 'email', 'redes', 'mensaje', 'rubros', 'ip_h', 'origen', 'origen_pag' ) );
	clp_sol_avisar( $emp_id, compact( 'persona', 'nombre', 'local', 'tel', 'email', 'redes', 'mensaje', 'rubros', 'origen', 'origen_pag' ) );
	// Si el formulario todavía tiene también la acción Email de Bricks, la confirmación ya la manda Bricks.
	if ( ! in_array( 'email', (array) ( $settings['actions'] ?? array() ), true ) ) {
		clp_sol_confirmar( $settings, $f, $email );
	}
	$ok();
} );

// Crea el borrador y devuelve su ID (0 si no se pudo; el aviso a Ale sale igual).
function clp_sol_crear_borrador( $d ) {
	$admin  = get_user_by( 'email', get_option( 'admin_email' ) );
	$autor  = $admin ? $admin->ID : 1;
	$emp_id = wp_insert_post( array(
		'post_type'   => 'emprendimientos',
		'post_title'  => $d['nombre'],
		'post_status' => 'draft',
		'post_author' => $autor,
	), true );
	if ( is_wp_error( $emp_id ) || ! function_exists( 'cl_panel_escribir' ) ) {
		return is_wp_error( $emp_id ) ? 0 : (int) $emp_id;
	}

	$sobra = array(); // Lo que no se pudo guardar en un campo va a la nota de la solicitud.
	$poner = function ( $campo, $valor ) use ( $emp_id, &$sobra ) {
		if ( '' === $valor ) {
			return;
		}
		list( $limpio, $msg ) = cl_panel_limpiar( $campo, $valor );
		if ( $msg || null === $limpio || '' === $limpio ) {
			$sobra[] = $valor;
			return;
		}
		cl_panel_escribir( $campo, $limpio, $emp_id );
	};
	$poner( 'localidad', $d['local'] );
	$poner( 'telefono_contacto', $d['tel'] );
	$poner( 'email-emp', $d['email'] );

	// «Web o redes»: Instagram, Facebook o sitio web según lo que parezca.
	$r = $d['redes'];
	if ( '' !== $r ) {
		if ( 0 === strpos( $r, '@' ) || false !== stripos( $r, 'instagram.com' ) ) {
			$poner( 'instagramemp', $r );
		} elseif ( preg_match( '#(facebook\.com|fb\.com|fb\.me)#i', $r ) ) {
			$poner( 'facebookemp', $r );
		} else {
			$poner( 'url_sitio_web', $r );
		}
	}

	// Rubros: vienen con el nombre (Bricks manda el texto de la casilla).
	$ids = array();
	foreach ( $d['rubros'] as $nombre_rubro ) {
		$t = get_term_by( 'name', $nombre_rubro, 'rubro' );
		if ( $t && ( ! function_exists( 'cl_srv_rubros_curados_ids' ) || ! in_array( (int) $t->term_id, cl_srv_rubros_curados_ids(), true ) ) ) {
			$ids[] = (int) $t->term_id;
		}
	}
	if ( $ids ) {
		wp_set_object_terms( $emp_id, $ids, 'rubro', false );
		if ( function_exists( 'cl_panel_modulos_segun_rubro' ) ) {
			cl_panel_modulos_segun_rubro( $emp_id );
		}
	}

	update_post_meta( $emp_id, '_cl_solicitud', array(
		'fecha'   => current_time( 'mysql' ),
		'persona' => $d['persona'],
		'mensaje' => $d['mensaje'],
		'sobra'   => $sobra,
		'ip'      => $d['ip_h'],
		'estado'  => 'nueva',
		'origen'     => $d['origen'] ?? '',
		'origen_pag' => $d['origen_pag'] ?? '',
	) );
	return (int) $emp_id;
}

// Fichas con el mismo nombre (para avisar posibles repetidos).
function clp_sol_parecidas( $emp_id, $nombre ) {
	$ids = get_posts( array(
		'post_type'      => 'emprendimientos',
		'post_status'    => array( 'publish', 'draft', 'pending' ),
		'title'          => $nombre,
		'posts_per_page' => 5,
		'fields'         => 'ids',
		'post__not_in'   => array( (int) $emp_id ),
	) );
	return array_map( 'intval', $ids );
}

// URL de una acción sobre la solicitud (abre una pantalla de confirmación en wp-admin).
function clp_sol_url( $emp_id, $hacer ) {
	return add_query_arg( array( 'action' => 'clp_solicitud', 'emp' => (int) $emp_id, 'hacer' => $hacer ), admin_url( 'admin-post.php' ) );
}

function clp_sol_avisar( $emp_id, $d ) {
	$fila = function ( $k, $v ) {
		return '' === trim( (string) $v ) ? '' : '<tr><td style="padding:6px 10px;font-weight:700;vertical-align:top;color:#1f4645">' . esc_html( $k ) . '</td><td style="padding:6px 10px">' . nl2br( esc_html( $v ) ) . '</td></tr>';
	};
	$boton = function ( $txt, $url, $fondo = '#1f4645' ) {
		return '<a href="' . esc_url( $url ) . '" style="display:inline-block;margin:0 8px 8px 0;padding:10px 16px;border-radius:10px;background:' . $fondo . ';color:#fff;font-weight:700;text-decoration:none">' . esc_html( $txt ) . '</a>';
	};
	$h  = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#23302f;line-height:1.5">';
	$h .= '<p style="font-size:17px;font-weight:700;color:#1f4645">Nueva solicitud para sumarse a Caminos Locales</p>';
	$h .= '<table style="border-collapse:collapse">';
	$h .= $fila( 'Nombre', $d['persona'] ) . $fila( 'Emprendimiento o servicio', $d['nombre'] ) . $fila( 'Localidad', $d['local'] );
	$h .= $fila( 'Qué ofrece', $d['rubros'] ? implode( ', ', $d['rubros'] ) : '(no marcó nada)' );
	$h .= $fila( 'WhatsApp', $d['tel'] ) . $fila( 'Email', $d['email'] ) . $fila( 'Web o redes', $d['redes'] ) . $fila( 'Mensaje', $d['mensaje'] );
	if ( ! empty( $d['origen'] ) ) { // 1.4.6
		$h .= $fila( 'Llegó desde', $d['origen'] . ( ! empty( $d['origen_pag'] ) && '/' !== $d['origen_pag'] ? ' (entró por ' . $d['origen_pag'] . ')' : '' ) );
	}
	$h .= '</table>';
	if ( $emp_id ) {
		foreach ( clp_sol_parecidas( $emp_id, $d['nombre'] ) as $p ) {
			$h .= '<p style="background:#fdf0d5;border-radius:10px;padding:10px 12px;color:#5c3a00">Ojo: ya hay una ficha con ese nombre: <a href="' . esc_url( admin_url( 'post.php?post=' . $p . '&action=edit' ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a> (' . esc_html( get_post_status( $p ) ) . ').</p>';
		}
		$h .= '<p>Se creó un borrador con estos datos. No se publica solo.</p><p>';
		$h .= $boton( 'Ver borrador', function_exists( 'cl_panel_url_vista' ) ? cl_panel_url_vista( $emp_id ) : get_permalink( $emp_id ) );
		$h .= $boton( 'Completar en Mi panel', home_url( '/panel/?emp=' . $emp_id ) );
		$h .= $boton( 'Crear invitación', clp_sol_url( $emp_id, 'invitar' ) );
		$h .= $boton( 'Descartar (spam)', clp_sol_url( $emp_id, 'descartar' ), '#8a2a1f' );
		$h .= '</p>';
	} else {
		$h .= '<p>No se pudo crear el borrador: cargalo a mano desde Mi panel.</p>';
	}
	$h .= '<p style="font-size:13px;color:#5b6a68">Respondé este correo para escribirle directamente. Enviado desde «Pedí sumarte» de caminoslocales.com.</p></div>';

	$cab = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( is_email( $d['email'] ) ) {
		$cab[] = 'Reply-To: ' . str_replace( array( "\r", "\n", '<', '>', ',' ), '', $d['persona'] ) . ' <' . $d['email'] . '>';
	}
	wp_mail( get_option( 'admin_email' ), '[Caminos Locales] Pedido para sumarse: ' . $d['nombre'] . ' (' . $d['local'] . ')', $h, $cab );
}

// Correo de confirmación a quien pidió sumarse. El texto se edita en Bricks (formulario psform, acción Email →
// «Confirmation email»): asunto y contenido, con {{psnomb}}, {{psempr}}, etc. Remitente: «From» del formulario.
function clp_sol_confirmar( $settings, $f, $email ) {
	if ( ! is_email( $email ) || empty( $settings['confirmationEmailContent'] ) ) {
		return;
	}
	$reemplazar = function ( $texto, $html ) use ( $f ) {
		return preg_replace_callback( '/\{\{\s*([a-z0-9]+)\s*\}\}/i', function ( $m ) use ( $f, $html ) {
			$v = $f[ 'form-field-' . $m[1] ] ?? '';
			$v = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
			$v = sanitize_text_field( $v );
			return $html ? esc_html( $v ) : $v;
		}, (string) $texto );
	};
	$asunto = $reemplazar( $settings['confirmationEmailSubject'] ?? 'Recibimos tu solicitud – Caminos Locales', false );
	$cuerpo = $reemplazar( $settings['confirmationEmailContent'], true );
	$cab    = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( ! empty( $settings['fromEmail'] ) && is_email( $settings['fromEmail'] ) ) {
		$cab[] = 'From: ' . str_replace( array( "\r", "\n", '<', '>' ), '', $settings['fromName'] ?? 'Caminos Locales' ) . ' <' . $settings['fromEmail'] . '>';
	}
	wp_mail( $email, $asunto, $cuerpo, $cab );
}

// ---------- Acciones de Ale: crear invitación o descartar ----------

add_action( 'admin_post_clp_solicitud', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'No tenés permiso.' );
	}
	$emp   = isset( $_REQUEST['emp'] ) ? (int) $_REQUEST['emp'] : 0;
	$hacer = isset( $_REQUEST['hacer'] ) ? sanitize_key( $_REQUEST['hacer'] ) : '';
	if ( ! $emp || 'emprendimientos' !== get_post_type( $emp ) || ! in_array( $hacer, array( 'invitar', 'descartar' ), true ) ) {
		wp_die( 'No encontramos esa solicitud (quizás ya se descartó).' );
	}
	$nombre = get_the_title( $emp );
	$sol    = get_post_meta( $emp, '_cl_solicitud', true );
	$sol    = is_array( $sol ) ? $sol : array();
	$panel  = home_url( '/panel/?emp=' . $emp );
	$pagina = function ( $titulo, $cuerpo ) {
		wp_die( '<h1 style="font-size:22px">' . esc_html( $titulo ) . '</h1>' . $cuerpo, esc_html( $titulo ), array( 'response' => 200 ) );
	};

	// 1.2.2: una solicitud descartada está en la papelera: ni borrador ni invitación hasta restaurarla.
	if ( 'trash' === get_post_status( $emp ) ) {
		$pagina(
			'Solicitud descartada',
			'<p>' . esc_html( '«' . $nombre . '» está en la papelera, así que no se puede ver ni invitar.' ) . '</p>' .
			'<p>' . esc_html( 'Si querés retomarla, restaurala y volvé a usar el botón del correo.' ) . '</p>' .
			'<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_status=trash&post_type=emprendimientos' ) ) . '">Ir a la papelera</a></p>'
		);
	}

	// Primero se confirma (el enlace del correo no lleva nonce: los nonces son de cada sesión).
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_clnonce'] ), 'clp_sol_' . $hacer . '_' . $emp ) ) {
		$boton = 'invitar' === $hacer ? 'Crear invitación' : 'Descartar (va a la papelera)';
		$pagina(
			$nombre,
			'<p>' . esc_html( 'invitar' === $hacer
				? 'Se crea un enlace de un solo uso (vence en 14 días). Quien lo use crea su cuenta y esta ficha pasa a ser suya.'
				: 'La ficha va a la papelera de WordPress. Se puede recuperar desde wp-admin.' ) . '</p>' .
			'<form method="post"><input type="hidden" name="action" value="clp_solicitud"><input type="hidden" name="emp" value="' . (int) $emp . '"><input type="hidden" name="hacer" value="' . esc_attr( $hacer ) . '">' .
			wp_nonce_field( 'clp_sol_' . $hacer . '_' . $emp, '_clnonce', false, false ) .
			'<p><button class="button button-primary" type="submit">' . esc_html( $boton ) . '</button> <a class="button" href="' . esc_url( cl_panel_url_vista( $emp ) ) . '">Ver borrador</a> <a class="button" href="' . esc_url( $panel ) . '">Abrir en Mi panel</a></p></form>'
		);
	}

	if ( 'descartar' === $hacer ) {
		$sol['estado'] = 'descartada';
		update_post_meta( $emp, '_cl_solicitud', $sol );
		wp_trash_post( $emp );
		$pagina( 'Descartada', '<p>' . esc_html( '«' . $nombre . '» está en la papelera.' ) . '</p>' );
	}

	// Invitar: si ya hay una vigente para esta ficha, se reutiliza.
	$inv = function_exists( 'clp_inv_de_ficha' ) ? clp_inv_de_ficha( $emp ) : null;
	if ( ! $inv && function_exists( 'clp_inv_crear' ) ) {
		clp_inv_crear( ! empty( $sol['persona'] ) ? $sol['persona'] : $nombre, $emp );
		$inv = clp_inv_de_ficha( $emp );
	}
	if ( ! $inv ) {
		$pagina( 'No se pudo', '<p>No se pudo crear la invitación. Probá desde Mi panel.</p>' );
	}
	$sol['estado'] = 'invitada';
	update_post_meta( $emp, '_cl_solicitud', $sol );
	$wa = function_exists( 'cl_srv_wa' ) ? cl_srv_wa( $emp ) : '';
	$wa = $wa ? $wa : 'https://wa.me/';
	$wa = add_query_arg( 'text', rawurlencode( clp_inv_mensaje( $inv ) ), $wa );
	$pagina(
		'Invitación lista',
		'<p>' . esc_html( 'Enlace para ' . $nombre . ' (sirve una sola vez):' ) . '</p>' .
		'<p><input type="text" readonly value="' . esc_attr( $inv['enlace'] ) . '" style="width:100%" onclick="this.select()"></p>' .
		'<p><a class="button button-primary" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">Mandar por WhatsApp</a> <a class="button" href="' . esc_url( $panel ) . '">Abrir en Mi panel</a></p>'
	);
} );
