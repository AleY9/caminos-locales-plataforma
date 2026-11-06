<?php
/**
 * Avisos de cambios al admin (1.1.12).
 *
 * - Al momento (como antes): fichas, alojamientos y experiencias nuevas publicadas; ocultar y borrar;
 *   altas por invitación; cambio de nombre o de email del emprendimiento; cambios en espera de revisión.
 * - El resto (cambios en lo ya publicado, volver a publicar, duplicar) se junta en una cola
 *   (opción clp_avisos_cola) y sale en un solo correo de resumen.
 * - Modo en Herramientas → Caminos Locales (opción clp_avisos_modo): diario (por defecto, 20 h),
 *   semanal (domingos a las 20 h) o al momento (un correo por cambio, como hasta la 1.1.11).
 */

defined( 'ABSPATH' ) || exit;

function clp_avisos_modo() {
	$m = get_option( 'clp_avisos_modo', 'diario' );
	return in_array( $m, array( 'momento', 'diario', 'semanal' ), true ) ? $m : 'diario';
}

/**
 * Cambio que puede esperar al resumen.
 * $tipo: emprendimientos | alojamiento | evento. $cambios: lista de campos o de acciones.
 * $asunto / $cuerpo: el correo que se manda si el modo es «al momento».
 */
function clp_aviso_cambios( $id, $tipo, $cambios, $asunto, $cuerpo ) {
	$cambios = array_filter( array_map( 'strval', (array) $cambios ) );
	if ( ! $cambios ) {
		return;
	}
	if ( 'momento' === clp_avisos_modo() ) {
		wp_mail( get_option( 'admin_email' ), $asunto, $cuerpo );
		return;
	}
	$cola = get_option( 'clp_avisos_cola', array() );
	$cola = is_array( $cola ) ? $cola : array();
	$k    = (int) $id;
	$e    = isset( $cola[ $k ] ) && is_array( $cola[ $k ] ) ? $cola[ $k ] : array( 'cambios' => array(), 'usuarios' => array(), 'veces' => 0 );
	$e['tipo']    = $tipo;
	$e['cambios'] = array_values( array_unique( array_merge( (array) $e['cambios'], $cambios ) ) );
	$u            = wp_get_current_user();
	if ( $u && $u->exists() ) {
		$e['usuarios'] = array_values( array_unique( array_merge( (array) $e['usuarios'], array( $u->user_login ) ) ) );
	}
	$e['veces']  = (int) $e['veces'] + 1;
	$e['ultima'] = current_time( 'mysql' );
	$cola[ $k ]  = $e;
	update_option( 'clp_avisos_cola', $cola, false );
}

function clp_avisos_pendientes() {
	$cola = get_option( 'clp_avisos_cola', array() );
	return is_array( $cola ) ? count( $cola ) : 0;
}

// Nombre legible de un campo del panel (los campos llegan con su nombre interno).
function clp_aviso_campo( $c ) {
	$nombres = array(
		'titulo'                                  => 'nombre',
		'contenido'                               => 'descripción',
		'breve_descripcion'                       => 'descripción breve',
		'quienes_somos'                           => 'quiénes somos',
		'url_video_emprendimiento'                => 'video',
		'rubros'                                  => 'qué ofrecés',
		'categorias'                              => 'qué se puede hacer',
		'actividades_descripcion'                 => 'actividades',
		'ofrece_alojamiento'                      => 'ofrece alojamiento',
		'breve_descripcion_alojamiento'           => 'alojamiento (texto)',
		'publica_eventos'                         => 'publica experiencias',
		'ficha_completa'                          => 'tipo de ficha',
		'region'                                  => 'región',
		'ubicacion'                               => 'mapa',
		'direccion_o_punto_de_encuentro'          => 'dirección',
		'servicio_alcance'                        => 'hasta dónde llega',
		'servicio_km'                             => 'distancia',
		'servicio_zona_nota'                      => 'aclaración de zona',
		'telefono_contacto'                       => 'teléfono',
		'email-emp'                               => 'email',
		'url_sitio_web'                           => 'sitio web',
		'instagramemp'                            => 'Instagram',
		'facebookemp'                             => 'Facebook',
		'servicio_publico'                        => 'quién lo ve',
		'telefono_publico'                        => 'teléfono público',
		'que_visitar_actividades_zona'            => 'qué visitar en la zona',
		'recomendaciones_aclaraciones'            => 'recomendaciones',
		'redes_locales_certificaciones'           => 'redes y certificaciones',
		'habilitacion_mintur'                     => 'inscripción MINTUR',
		'seguro_vigente'                          => 'seguro',
		'seguro_vencimiento'                      => 'vencimiento del seguro',
		'llegada'                                 => 'cómo se llega',
		'traslado_nota'                           => 'traslado',
		'como_llegar'                             => 'cómo llegar',
		'bano_tipo'                               => 'baño',
		'bano_inodoro'                            => 'inodoro',
		'en_el_lugar'                             => 'qué hay en el lugar',
		'caracteristicas'                         => 'qué tiene',
		'tipo_de_bano'                            => 'inodoro',
		'alimentacion'                            => 'alimentación',
		'servicios_disponibles_y_caracteristicas' => 'servicios y detalles',
		'reservas'                                => 'cómo reservar',
		'url_reserva'                             => 'enlace de reserva',
		'condiciones_del_alojamiento'             => 'condiciones',
		'email_del_emprendimiento'                => 'formulario de consultas',
		'especial'                                => 'especial para',
		'fotos'                                   => 'fotos',
	);
	if ( isset( $nombres[ $c ] ) ) {
		return $nombres[ $c ];
	}
	return str_replace( array( '_', '-' ), ' ', $c );
}

// Arma y manda el resumen. Devuelve la cantidad de fichas incluidas.
function clp_avisos_enviar_resumen() {
	$cola = get_option( 'clp_avisos_cola', array() );
	if ( ! is_array( $cola ) || ! $cola ) {
		return 0;
	}
	$grupos = array(
		'emprendimientos' => 'Emprendimientos y servicios',
		'alojamiento'     => 'Alojamientos',
		'evento'          => 'Experiencias',
	);
	$txt = '';
	$n   = 0;
	foreach ( $grupos as $tipo => $titulo ) {
		$lineas = '';
		foreach ( $cola as $id => $e ) {
			if ( ( $e['tipo'] ?? '' ) !== $tipo ) {
				continue;
			}
			$post = get_post( (int) $id );
			if ( ! $post ) {
				continue;
			}
			$n++;
			$emp = '';
			if ( 'alojamiento' === $tipo && function_exists( 'cl_aloj_emp_de' ) ) {
				$emp = get_the_title( cl_aloj_emp_de( $id ) );
			} elseif ( 'evento' === $tipo && function_exists( 'cl_ev_emp_de' ) ) {
				$emp = get_the_title( cl_ev_emp_de( $id ) );
			}
			$estado  = 'publish' === $post->post_status ? '' : ' [' . ( 'trash' === $post->post_status ? 'en la papelera' : 'no publicada' ) . ']';
			$lineas .= '• ' . $post->post_title . ( $emp ? ' (' . $emp . ')' : '' ) . $estado . "\n";
			$lineas .= '  Cambió: ' . implode( ', ', array_map( 'clp_aviso_campo', (array) $e['cambios'] ) ) . "\n";
			$lineas .= '  Quién: ' . implode( ', ', (array) $e['usuarios'] ) . ' · ' . (int) $e['veces'] . ( 1 === (int) $e['veces'] ? ' vez' : ' veces' ) . "\n";
			if ( 'publish' === $post->post_status ) {
				$lineas .= '  Ver: ' . get_permalink( $post ) . "\n";
			}
			$lineas .= '  Editar: ' . admin_url( 'post.php?post=' . (int) $id . '&action=edit' ) . "\n\n";
		}
		if ( $lineas ) {
			$txt .= strtoupper( $titulo ) . "\n\n" . $lineas;
		}
	}
	delete_option( 'clp_avisos_cola' );
	if ( ! $n ) {
		return 0;
	}
	$periodo = 'semanal' === clp_avisos_modo() ? 'de la semana' : 'del día';
	wp_mail(
		get_option( 'admin_email' ),
		'[Caminos Locales] Resumen de cambios ' . $periodo . ' (' . $n . ( 1 === $n ? ' ficha' : ' fichas' ) . ')',
		'Cambios que hicieron los emprendedores en fichas ya publicadas, juntos en un solo correo.' . "\n" .
		'Lo importante (fichas nuevas, ocultas o borradas, cambios de nombre o email) te llega al momento.' . "\n\n" .
		$txt .
		'Para cambiar cada cuánto llega este resumen: ' . admin_url( 'tools.php?page=caminos-locales' )
	);
	return $n;
}

// ---------- Programación: todos los días a las 20 h (hora del sitio) ----------

add_action( 'init', function () {
	if ( wp_next_scheduled( 'clp_avisos_resumen' ) ) {
		return;
	}
	$tz   = wp_timezone();
	$prox = new DateTime( 'today 20:00', $tz );
	if ( $prox->getTimestamp() <= time() ) {
		$prox->modify( '+1 day' );
	}
	wp_schedule_event( $prox->getTimestamp(), 'daily', 'clp_avisos_resumen' );
} );

add_action( 'clp_avisos_resumen', function () {
	// Semanal: solo los domingos. Al momento: se manda lo que haya quedado en la cola al cambiar de modo.
	if ( 'semanal' === clp_avisos_modo() && 7 !== (int) wp_date( 'N' ) ) {
		return;
	}
	clp_avisos_enviar_resumen();
} );

register_deactivation_hook( CLP_FILE, function () {
	wp_clear_scheduled_hook( 'clp_avisos_resumen' );
} );

// ---------- Ajuste en Herramientas → Caminos Locales ----------

function clp_avisos_caja() {
	$modo = clp_avisos_modo();
	$pend = clp_avisos_pendientes();
	$prox = wp_next_scheduled( 'clp_avisos_resumen' );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 16px;margin:16px 0;max-width:760px">
		<input type="hidden" name="action" value="clp_avisos">
		<?php wp_nonce_field( 'clp_avisos' ); ?>
		<p style="font-size:14px;margin:0 0 8px"><strong>Avisos de cambios</strong></p>
		<label style="display:block;margin:4px 0"><input type="radio" name="clp_avisos_modo" value="diario" <?php checked( 'diario', $modo ); ?>> Resumen diario (20 h)</label>
		<label style="display:block;margin:4px 0"><input type="radio" name="clp_avisos_modo" value="semanal" <?php checked( 'semanal', $modo ); ?>> Resumen semanal (domingos, 20 h)</label>
		<label style="display:block;margin:4px 0"><input type="radio" name="clp_avisos_modo" value="momento" <?php checked( 'momento', $modo ); ?>> Al momento (un correo por cada cambio)</label>
		<p class="description">Lo importante llega siempre al momento: fichas nuevas, ocultas o borradas, altas por invitación y cambios de nombre o email. El resumen junta los cambios en fichas ya publicadas, volver a publicar y duplicar.</p>
		<p class="description">En espera para el resumen: <strong><?php echo (int) $pend; ?></strong> <?php echo 1 === $pend ? 'ficha' : 'fichas'; ?><?php echo $prox ? ' · próximo envío: ' . esc_html( wp_date( 'd/m H:i', $prox ) ) : ''; ?></p>
		<?php submit_button( 'Guardar', 'secondary', 'submit', false ); ?>
		<?php if ( $pend ) : ?>
			<button type="submit" name="clp_avisos_ya" value="1" class="button" style="margin-left:6px">Mandar el resumen ahora</button>
		<?php endif; ?>
	</form>
	<?php
}

add_action( 'admin_post_clp_avisos', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'clp_avisos' ) ) {
		wp_die( 'Sin permiso.' );
	}
	$m = isset( $_POST['clp_avisos_modo'] ) ? sanitize_key( $_POST['clp_avisos_modo'] ) : 'diario';
	update_option( 'clp_avisos_modo', in_array( $m, array( 'momento', 'diario', 'semanal' ), true ) ? $m : 'diario', false );
	if ( ! empty( $_POST['clp_avisos_ya'] ) ) {
		clp_avisos_enviar_resumen();
	}
	wp_safe_redirect( admin_url( 'tools.php?page=caminos-locales&guardado=1' ) );
	exit;
} );
