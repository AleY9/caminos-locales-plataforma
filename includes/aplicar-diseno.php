<?php
/**
 * Aplicar diseño (v1.0.2): pasa a producción el sistema visual armado en el staging en noviembre de 2026.
 *
 * Trae un paquete JSON del staging (comprobado con SHA-256) y aplica solo lo de diseño:
 * paleta, variables, estilos del tema, clases, componentes, CSS global, plantillas y páginas de Bricks.
 * No toca fichas, alojamientos, experiencias ni usuarios (salvo el diseño de Bricks de 2 fichas que lo tenían).
 *
 * Seguridad:
 * - Antes de reemplazar cada cosa compara cómo está en este sitio con cómo estaba en el staging antes de los
 *   cambios. Si alguien la cambió después, no la toca y lo avisa.
 * - Guarda lo que reemplaza en la opción clp_diseno_previo, y el botón «Volver atrás» lo restaura.
 * Herramientas → Caminos Locales.
 */

defined( 'ABSPATH' ) || exit;

define( 'CLP_DISENO_URL', 'https://staging.caminoslocales.com/wp-content/uploads/clp/diseno-2026-11-X0YKDq4RNBS2.json' );
define( 'CLP_DISENO_SHA256', '04e155532319108c5536fb567ffaf573268d7e4460e062e9b5dd71168b2764ba' );

// Normaliza un valor para compararlo con el del staging: JSON con la dirección del sitio como {{HOME}}.
function clp_diseno_norm( $valor ) {
	$j    = wp_json_encode( $valor );
	$home = untrailingslashit( home_url() );
	// También la dirección codificada (por ejemplo en ?redirect_to=https%3A%2F%2F...).
	return str_replace( array( rawurlencode( $home ), str_replace( '/', '\/', $home ), $home ), array( '{{HOMEENC}}', '{{HOME}}', '{{HOME}}' ), $j );
}

// Del paquete al valor real de este sitio.
function clp_diseno_valor( $json ) {
	$home = untrailingslashit( home_url() );
	return json_decode( str_replace( array( '{{HOMEENC}}', '{{HOME}}' ), array( rawurlencode( $home ), str_replace( '/', '\/', $home ) ), $json ), true );
}

function clp_diseno_paquete() {
	$r = wp_remote_get( CLP_DISENO_URL, array( 'timeout' => 60 ) );
	if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
		return new WP_Error( 'clp', 'No se pudo descargar el paquete de diseño del staging.' );
	}
	$body = wp_remote_retrieve_body( $r );
	if ( ! hash_equals( CLP_DISENO_SHA256, hash( 'sha256', $body ) ) ) {
		return new WP_Error( 'clp', 'El paquete descargado no coincide con el esperado (SHA-256). No se aplicó nada.' );
	}
	$p = json_decode( $body, true );
	return is_array( $p ) ? $p : new WP_Error( 'clp', 'El paquete no se pudo leer.' );
}

/**
 * Revisa (y si $aplicar, aplica) el paquete. Devuelve array( lista de renglones, hubo_error ).
 * Cada renglón: array( estado ok|ojo|mal, texto ).
 */
function clp_diseno_procesar( $aplicar ) {
	$p = clp_diseno_paquete();
	if ( is_wp_error( $p ) ) {
		return array( array( array( 'mal', $p->get_error_message() ) ), true );
	}
	$out    = array();
	$previo = array( 'posts' => array(), 'options' => array(), 'customCss' => null, 'brf' => null, 'media' => array(), 'acf' => '' );
	$saltea = 0;

	// Opciones de Bricks.
	foreach ( $p['options'] as $o ) {
		$actual = get_option( $o['name'] );
		if ( clp_diseno_norm( $actual ) === $o['value'] ) {
			$out[] = array( 'ok', $o['name'] . ': ya está.' );
			continue;
		}
		if ( md5( clp_diseno_norm( $actual ) ) !== $o['base'] ) {
			$out[] = array( 'ojo', $o['name'] . ': cambió en este sitio después de la copia; no se toca.' );
			$saltea++;
			continue;
		}
		if ( $aplicar ) {
			$previo['options'][ $o['name'] ] = $actual;
			update_option( $o['name'], clp_diseno_valor( $o['value'] ) );
		}
		$out[] = array( 'ok', $o['name'] . ( $aplicar ? ': aplicado.' : ': se aplica.' ) );
	}

	// CSS global de Bricks.
	$gs  = get_option( 'bricks_global_settings', array() );
	$css = is_array( $gs ) ? (string) ( $gs['customCss'] ?? '' ) : '';
	if ( $css === $p['customCss']['value'] ) {
		$out[] = array( 'ok', 'CSS global: ya está.' );
	} elseif ( md5( $css ) !== $p['customCss']['base'] ) {
		$out[] = array( 'ojo', 'CSS global: cambió en este sitio; no se toca. Agregá a mano el bloque «cl-botones».' );
		$saltea++;
	} else {
		if ( $aplicar ) {
			$previo['customCss'] = $css;
			$gs                  = is_array( $gs ) ? $gs : array();
			$gs['customCss']     = $p['customCss']['value'];
			update_option( 'bricks_global_settings', $gs );
		}
		$out[] = array( 'ok', 'CSS global' . ( $aplicar ? ': aplicado.' : ': se aplica.' ) );
	}

	// Imágenes de la caminante (se suben acá; sus ID cambian).
	$mapa = array();
	if ( $aplicar ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	foreach ( $p['media'] as $m ) {
		$url  = str_replace( '{{HOME}}', 'https://staging.caminoslocales.com', $m['url'] );
		$base = basename( $url );
		$ya   = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_clp_origen', 'meta_value' => $base ) );
		if ( $ya ) {
			$mapa[ $m['staging_id'] ] = (int) $ya[0];
			continue;
		}
		if ( ! $aplicar ) {
			$out[] = array( 'ok', 'Imagen ' . $base . ': se sube.' );
			continue;
		}
		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			$out[] = array( 'mal', 'Imagen ' . $base . ': no se pudo descargar.' );
			continue;
		}
		$id = media_handle_sideload( array( 'name' => $base, 'tmp_name' => $tmp ), 0, $m['title'] );
		if ( is_wp_error( $id ) ) {
			$out[] = array( 'mal', 'Imagen ' . $base . ': ' . $id->get_error_message() );
			continue;
		}
		update_post_meta( $id, '_clp_origen', $base );
		if ( '' !== $m['alt'] ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $m['alt'] );
		}
		$mapa[ $m['staging_id'] ]  = (int) $id;
		$previo['media'][]         = (int) $id;
		$out[]                     = array( 'ok', 'Imagen ' . $base . ': subida.' );
	}

	// Plantillas y páginas de Bricks.
	foreach ( $p['posts'] as $po ) {
		$titulo = $po['title'] . ' (' . $po['id'] . ')';
		if ( ! get_post( $po['id'] ) ) {
			$out[] = array( 'ojo', $titulo . ': no existe en este sitio; no se toca.' );
			$saltea++;
			continue;
		}
		$actual = get_post_meta( $po['id'], $po['key'], true );
		$nuevo  = clp_diseno_valor( $po['value'] );
		if ( $mapa ) {
			$nuevo = clp_diseno_cambiar_media( $nuevo, $mapa );
		}
		if ( clp_diseno_norm( $actual ) === clp_diseno_norm( $nuevo ) ) {
			$out[] = array( 'ok', $titulo . ': ya está.' );
			continue;
		}
		if ( md5( clp_diseno_norm( $actual ) ) !== $po['base'] ) {
			$out[] = array( 'ojo', $titulo . ': cambió en este sitio después de la copia; no se toca.' );
			$saltea++;
			continue;
		}
		if ( $aplicar ) {
			$previo['posts'][] = array( 'id' => $po['id'], 'key' => $po['key'], 'value' => $actual );
			update_post_meta( $po['id'], $po['key'], wp_slash( $nuevo ) );
		}
		$out[] = array( 'ok', $titulo . ( $aplicar ? ': aplicado.' : ': se aplica.' ) );
	}

	// Campo ACF «¿Qué hay en el lugar?».
	if ( function_exists( 'acf_get_field' ) ) {
		if ( acf_get_field( $p['acf']['field']['key'] ) ) {
			$out[] = array( 'ok', 'Campo ACF en_el_lugar: ya está.' );
		} else {
			$grupo = acf_get_field_group( $p['acf']['group'] );
			if ( ! $grupo ) {
				$out[] = array( 'mal', 'Campo ACF en_el_lugar: no encuentro el grupo «Condiciones: Estar».' );
			} elseif ( $aplicar ) {
				$f                = $p['acf']['field'];
				$f['parent']      = $grupo['ID'];
				$creado           = acf_update_field( $f );
				$previo['acf']    = $f['key'];
				$out[]            = array( $creado ? 'ok' : 'mal', 'Campo ACF en_el_lugar: ' . ( $creado ? 'creado.' : 'no se pudo crear.' ) );
			} else {
				$out[] = array( 'ok', 'Campo ACF en_el_lugar: se crea.' );
			}
		}
	}

	// Bricksforge: apagar el Scroll Smoother (rompe el botón para subir y retrasa las imágenes).
	$brf = get_option( 'brf_activated_tools' );
	if ( is_array( $brf ) ) {
		$sin = array_values( array_diff( $brf, $p['bricksforge_quitar'] ) );
		if ( $sin === array_values( $brf ) ) {
			$out[] = array( 'ok', 'Bricksforge Scroll Smoother: ya está apagado.' );
		} else {
			if ( $aplicar ) {
				$previo['brf'] = $brf;
				update_option( 'brf_activated_tools', $sin );
			}
			$out[] = array( 'ok', 'Bricksforge Scroll Smoother' . ( $aplicar ? ': apagado.' : ': se apaga.' ) );
		}
	}

	if ( $aplicar ) {
		update_option( 'clp_diseno_previo', $previo, false );
		update_option( 'clp_diseno_aplicado', current_time( 'mysql' ), false );
		clp_diseno_purgar();
	}
	if ( $saltea ) {
		$out[] = array( 'ojo', $saltea . ' cosas no se tocaron porque cambiaron en este sitio. Pasale la lista a Claude.' );
	}
	return array( $out, false );
}

// Cambia los ID y las direcciones de las imágenes del staging por las subidas acá.
function clp_diseno_cambiar_media( $v, $mapa ) {
	if ( ! is_array( $v ) ) {
		return $v;
	}
	if ( isset( $v['id'], $v['url'] ) && isset( $mapa[ (int) $v['id'] ] ) ) {
		$nuevo         = $mapa[ (int) $v['id'] ];
		$url           = wp_get_attachment_url( $nuevo );
		$v['id']       = $nuevo;
		$v['url']      = $url;
		$v['full']     = $url;
		$v['filename'] = basename( $url );
		return $v;
	}
	foreach ( $v as $k => $x ) {
		$v[ $k ] = clp_diseno_cambiar_media( $x, $mapa );
	}
	return $v;
}

function clp_diseno_purgar() {
	do_action( 'litespeed_purge_all' );
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}

function clp_diseno_volver() {
	$pr = get_option( 'clp_diseno_previo' );
	if ( ! is_array( $pr ) ) {
		return 'No hay nada para volver atrás.';
	}
	foreach ( $pr['options'] as $n => $v ) {
		update_option( $n, $v );
	}
	if ( null !== $pr['customCss'] ) {
		$gs              = get_option( 'bricks_global_settings', array() );
		$gs['customCss'] = $pr['customCss'];
		update_option( 'bricks_global_settings', $gs );
	}
	foreach ( $pr['posts'] as $po ) {
		update_post_meta( $po['id'], $po['key'], wp_slash( $po['value'] ) );
	}
	if ( null !== $pr['brf'] ) {
		update_option( 'brf_activated_tools', $pr['brf'] );
	}
	// Las imágenes y el campo ACF quedan: no molestan y pueden tener datos.
	delete_option( 'clp_diseno_previo' );
	delete_option( 'clp_diseno_aplicado' );
	clp_diseno_purgar();
	return 'Listo: el diseño volvió a como estaba antes de aplicarlo.';
}

add_action( 'admin_post_clp_diseno', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'clp_diseno' ) ) {
		wp_die( 'Sin permiso.' );
	}
	$accion = sanitize_key( $_POST['clp_accion'] ?? '' );
	if ( 'volver' === $accion ) {
		set_transient( 'clp_diseno_resultado', array( array( array( 'ok', clp_diseno_volver() ) ), 'volver' ), 600 );
	} else {
		@set_time_limit( 300 ); // phpcs:ignore
		list( $lista ) = clp_diseno_procesar( 'aplicar' === $accion );
		set_transient( 'clp_diseno_resultado', array( $lista, $accion ), 600 );
	}
	wp_safe_redirect( admin_url( 'tools.php?page=caminos-locales#clp-diseno' ) );
	exit;
} );

// Caja en Herramientas → Caminos Locales.
function clp_diseno_caja() {
	$aplicado = get_option( 'clp_diseno_aplicado' );
	$res      = get_transient( 'clp_diseno_resultado' );
	$colores  = array( 'ok' => '#00a32a', 'ojo' => '#996800', 'mal' => '#d63638' );
	?>
	<div id="clp-diseno" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 16px;margin:16px 0;max-width:760px">
		<h2 style="margin-top:0">Diseño nuevo (noviembre 2026)</h2>
		<?php if ( $aplicado ) : ?>
			<p>Aplicado el <?php echo esc_html( $aplicado ); ?>. Si algo salió mal, podés volver al diseño anterior.</p>
		<?php else : ?>
			<p>Pasa a este sitio el sistema visual del staging: paleta, letras, botones, cabecera, pie, fichas, listados, Inicio con la caminante y las páginas del panel. Crea la pregunta «¿Qué hay en tu lugar?» y apaga el Scroll Smoother de Bricksforge. No toca fichas, alojamientos, experiencias ni usuarios.</p>
			<p><strong>Antes:</strong> respaldo con JetBackup. Primero «Revisar» (no cambia nada), después «Aplicar».</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;flex-wrap:wrap">
			<input type="hidden" name="action" value="clp_diseno">
			<?php wp_nonce_field( 'clp_diseno' ); ?>
			<?php if ( $aplicado ) : ?>
				<button class="button" name="clp_accion" value="volver" onclick="return confirm('¿Volver al diseño anterior?')">Volver atrás</button>
			<?php else : ?>
				<button class="button" name="clp_accion" value="revisar">Revisar</button>
				<button class="button button-primary" name="clp_accion" value="aplicar" onclick="return confirm('¿Aplicar el diseño nuevo? Hacé el respaldo antes.')">Aplicar diseño</button>
			<?php endif; ?>
		</form>
		<?php if ( is_array( $res ) ) : delete_transient( 'clp_diseno_resultado' ); ?>
			<h3><?php echo 'revisar' === $res[1] ? 'Revisión (no se cambió nada)' : 'Resultado'; ?></h3>
			<?php foreach ( $res[0] as $r ) : ?>
				<p style="margin:3px 0"><span style="color:<?php echo esc_attr( $colores[ $r[0] ] ); ?>;font-weight:700">●</span> <?php echo esc_html( $r[1] ); ?></p>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}
