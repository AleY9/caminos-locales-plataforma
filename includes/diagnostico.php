<?php
/**
 * Diagnóstico: wp-admin → Herramientas → Caminos Locales.
 * Lista de chequeo (bien / para revisar / para arreglar), mismo formato que Liquen Panel.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_management_page( 'Caminos Locales', 'Caminos Locales', 'manage_options', 'caminos-locales', 'clp_pagina' );
} );

add_filter( 'plugin_action_links_' . plugin_basename( CLP_FILE ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=caminos-locales' ) ) . '">Diagnóstico</a>' );
	return $links;
} );

/**
 * ¿Es un sitio de pruebas? Igual que en Liquen Panel: WP_ENVIRONMENT_TYPE, o la dirección
 * empieza con staging., dev., test., stage. o qa. Para marcarlo a mano:
 * define( 'CLP_ES_STAGING', true ); en el wp-config.php (o false para forzar producción).
 */
function clp_es_staging() {
	if ( defined( 'CLP_ES_STAGING' ) ) {
		return (bool) CLP_ES_STAGING;
	}
	if ( in_array( wp_get_environment_type(), array( 'staging', 'development', 'local' ), true ) ) {
		return true;
	}
	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	return (bool) preg_match( '/^(staging|stage|dev|test|qa)[.\-]|\.(local|test|localhost)$/', $host );
}

// Guardar el ajuste «Revisar antes de publicar».
add_action( 'admin_post_clp_revision', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'clp_revision' ) ) {
		wp_die( 'Sin permiso.' );
	}
	update_option( 'clp_con_revision', empty( $_POST['clp_con_revision'] ) ? 0 : 1, false );
	wp_safe_redirect( admin_url( 'tools.php?page=caminos-locales&guardado=1' ) );
	exit;
} );

function clp_diagnostico() {
	$items = array();
	$add   = function ( $grupo, $estado, $texto, $enlace = null ) use ( &$items ) {
		$items[] = compact( 'grupo', 'estado', 'texto', 'enlace' );
	};

	// Módulos.
	$M        = 'Módulos';
	$modulos  = clp_modulos();
	$cargados = (array) $GLOBALS['clp_cargados'];
	if ( '' !== $GLOBALS['clp_bloqueo'] ) {
		$lista = clp_snippets_repetidos();
		$add( $M, 'mal', 'El plugin no cargó nada: siguen activos en Code Snippets los snippets con el mismo código' . ( $lista ? ' (' . implode( ', ', $lista ) . ')' : '' ) . '. Desactivalos y el plugin toma su lugar.', admin_url( 'admin.php?page=snippets' ) );
	} else {
		$faltan = array();
		foreach ( $modulos as $archivo => $m ) {
			if ( ! in_array( $archivo, $cargados, true ) && empty( $m['admin'] ) ) {
				$faltan[] = $archivo;
			}
		}
		if ( $faltan ) {
			$add( $M, 'mal', 'No se cargaron: ' . implode( ', ', $faltan ) . '.' );
		} else {
			$add( $M, 'ok', count( $cargados ) . ' de ' . count( $modulos ) . ' módulos cargados (en wp-admin se suma el de «¿Dónde ocurre?»).' );
		}
	}

	// Publicación.
	$add( 'Publicación', 'ok', cl_sin_revision()
		? 'Los emprendedores publican sin revisión (fichas, alojamientos y experiencias). Te llega un correo con cada publicación y cada cambio.'
		: 'Los emprendedores envían a revisión: vos publicás desde wp-admin. Los cambios de nombre y email quedan en espera.' );

	// Dependencias.
	$G = 'General';
	if ( function_exists( 'acf_get_field_groups' ) ) {
		$add( $G, 'ok', 'Advanced Custom Fields activo.' );
	} else {
		$add( $G, 'mal', 'Falta Advanced Custom Fields: las fichas y el panel lo usan en todos lados.', admin_url( 'plugins.php' ) );
	}
	if ( defined( 'BRICKS_VERSION' ) ) {
		$add( $G, 'ok', 'Bricks ' . BRICKS_VERSION . ' activo.' );
	} else {
		$add( $G, 'mal', 'Bricks no está activo: las plantillas del panel y de las fichas son de Bricks.' );
	}
	if ( defined( 'CL_PANEL_PAGE_ID' ) && 'publish' !== get_post_status( CL_PANEL_PAGE_ID ) ) {
		$add( $G, 'mal', 'La página Panel (ID ' . CL_PANEL_PAGE_ID . ') no está publicada: el panel del emprendedor no se ve.' );
	}
	$auto = (array) get_site_option( 'auto_update_plugins', array() );
	if ( in_array( plugin_basename( CLP_FILE ), $auto, true ) ) {
		$add( $G, 'ok', 'Actualizaciones automáticas activadas.' );
	} else {
		$add( $G, 'ojo', 'Las actualizaciones automáticas están apagadas: las versiones nuevas hay que instalarlas a mano desde Plugins.', admin_url( 'plugins.php' ) );
	}
	if ( version_compare( PHP_VERSION, '8.1', '>=' ) ) {
		$add( $G, 'ok', 'PHP ' . PHP_VERSION . '.' );
	} else {
		$add( $G, 'ojo', 'PHP ' . PHP_VERSION . ': conviene pasar a 8.3.' );
	}

	// Seguridad: ejecutar PHP por MCP y ejecución de código de Bricks, solo en stagings.
	$S        = 'Seguridad';
	$php_mcp  = defined( 'BRICKS_ENABLE_PHP_ABILITIES' ) && BRICKS_ENABLE_PHP_ABILITIES;
	$bricks_g = get_option( 'bricks_global_settings', array() );
	$codigo   = is_array( $bricks_g ) && ! empty( $bricks_g['executeCodeEnabled'] );
	if ( clp_es_staging() ) {
		$add( $S, 'ok', 'Sitio de pruebas (staging)' . ( $php_mcp || $codigo ? ': la ejecución de PHP por MCP o de código de Bricks está activa. Acá está bien; en producción no.' : '.' ) );
		if ( '0' !== (string) get_option( 'blog_public' ) ) {
			$add( $S, 'mal', 'El staging está visible para los buscadores: marcá «Disuadir a los motores de búsqueda» en Ajustes → Lectura.', admin_url( 'options-reading.php' ) );
		}
		if ( ! has_filter( 'wp_mail' ) ) {
			$add( $S, 'ojo', 'El staging puede mandar correos a emprendedores reales. Conviene desviarlos (mu-plugin «Staging – Desviar correos»).' );
		} else {
			$add( $S, 'ok', 'Hay un filtro sobre los correos (desvío del staging).' );
		}
	} elseif ( $php_mcp ) {
		$add( $S, 'mal', 'Este sitio parece de producción y tiene activa la ejecución de PHP por MCP. Sacá la línea define( \'BRICKS_ENABLE_PHP_ABILITIES\', true ); del wp-config.php. Si es un staging, agregá define( \'CLP_ES_STAGING\', true );.' );
	} elseif ( $codigo ) {
		$add( $S, 'ojo', 'Bricks → Ajustes → «Ejecución de código» está habilitada. El sitio la usa en algunos elementos ({echo:…}); si alguna vez deja de usarla, apagala.', admin_url( 'admin.php?page=bricks-settings' ) );
	} else {
		$add( $S, 'ok', 'Sin ejecución de PHP por MCP.' );
	}

	// Caché: el panel no se puede cachear.
	$C = 'Caché';
	if ( defined( 'LSCWP_V' ) ) {
		$v = get_option( 'litespeed.conf.cache-exc', array() );
		if ( is_string( $v ) ) {
			$j = json_decode( $v, true );
			$v = is_array( $j ) ? $j : preg_split( '/\R/', $v );
		}
		$ok = false;
		foreach ( array_map( 'trim', (array) $v ) as $e ) {
			if ( 0 === strpos( $e, '^/panel' ) || 0 === strpos( $e, '/panel' ) ) {
				$ok = true;
			}
		}
		if ( $ok ) {
			$add( $C, 'ok', 'LiteSpeed Cache activo y el panel está excluido de la caché.' );
		} else {
			$add( $C, 'mal', 'LiteSpeed Cache no excluye el panel: agregá ^/panel en Caché → Excluir → «URIs a no cachear» y purgá todo.', admin_url( 'admin.php?page=litespeed-cache' ) );
		}
	} else {
		$add( $C, 'ok', 'LiteSpeed Cache no está activo.' );
	}

	return $items;
}

function clp_pagina() {
	$items  = clp_diagnostico();
	$cuenta = array_count_values( array_column( $items, 'estado' ) );
	$iconos = array( 'ok' => '✓', 'ojo' => '!', 'mal' => '✕' );
	$colores = array( 'ok' => '#00a32a', 'ojo' => '#996800', 'mal' => '#d63638' );
	?>
	<div class="wrap">
		<h1>Caminos Locales Plataforma <span style="font-size:13px;color:#646970">versión <?php echo esc_html( CLP_VERSION ); ?></span></h1>
		<p>Panel del emprendedor, fichas y funciones propias del sitio. Antes eran snippets de Code Snippets; el código es el mismo.</p>
		<?php if ( isset( $_GET['guardado'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success inline"><p>Guardado.</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 16px;margin:16px 0;max-width:760px">
			<input type="hidden" name="action" value="clp_revision">
			<?php wp_nonce_field( 'clp_revision' ); ?>
			<label style="font-size:14px"><input type="checkbox" name="clp_con_revision" value="1" <?php checked( ! cl_sin_revision() ); ?>> <strong>Revisar antes de publicar</strong></label>
			<p class="description">Apagado: los emprendedores publican al momento y te llega un aviso por correo (integrantes de confianza). Encendido: envían a revisión y publicás vos.</p>
			<?php submit_button( 'Guardar', 'secondary', 'submit', false ); ?>
		</form>
		<?php if ( function_exists( 'clp_avisos_caja' ) ) { clp_avisos_caja(); } ?>
		<?php clp_invitaciones_caja(); ?>
		<?php clp_servicios_caja(); ?>
		<?php clp_diseno_caja(); ?>
		<p style="font-size:15px">
			<span style="color:#00a32a">✓ <?php echo (int) ( $cuenta['ok'] ?? 0 ); ?> bien</span> ·
			<span style="color:#996800">! <?php echo (int) ( $cuenta['ojo'] ?? 0 ); ?> para revisar</span> ·
			<span style="color:#d63638">✕ <?php echo (int) ( $cuenta['mal'] ?? 0 ); ?> para arreglar</span>
		</p>
		<?php
		$grupos = array();
		foreach ( $items as $it ) {
			$grupos[ $it['grupo'] ][] = $it;
		}
		foreach ( $grupos as $g => $lista ) :
			usort( $lista, function ( $a, $b ) {
				$o = array( 'mal' => 0, 'ojo' => 1, 'ok' => 2 );
				return $o[ $a['estado'] ] <=> $o[ $b['estado'] ];
			} );
			?>
			<h2 style="margin-top:24px"><?php echo esc_html( $g ); ?></h2>
			<?php foreach ( $lista as $it ) : ?>
				<p style="display:flex;gap:10px;align-items:flex-start;margin:6px 0">
					<span style="color:<?php echo esc_attr( $colores[ $it['estado'] ] ); ?>;font-weight:700;width:16px"><?php echo esc_html( $iconos[ $it['estado'] ] ); ?></span>
					<span>
						<?php echo esc_html( $it['texto'] ); ?>
						<?php if ( $it['enlace'] && 'ok' !== $it['estado'] ) : ?>
							<a href="<?php echo esc_url( $it['enlace'] ); ?>">Ir →</a>
						<?php endif; ?>
					</span>
				</p>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</div>
	<?php
}
