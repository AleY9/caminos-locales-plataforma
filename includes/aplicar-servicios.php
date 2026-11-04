<?php
/**
 * Aplicar servicios (1.1.0): pasa a producción lo armado en el staging el 03–04/11/2026.
 *
 * - Crea (si no existen) las páginas /invitacion/, /servicios-locales/ y /panel/prestadores/, las plantillas
 *   de Bricks «Single Servicio» y «Servicio (ventana)» y el ítem «Servicios» del menú principal.
 * - Reemplaza el diseño de Bricks de «Mi emprendimiento» (formularios del panel), «Mi panel» y «Emprendimientos».
 *
 * Seguridad (igual que «Diseño nuevo»):
 * - El paquete se descarga del staging y se comprueba con SHA-256.
 * - Antes de reemplazar una página compara cómo está acá con cómo estaba en el staging antes de los cambios.
 *   Si alguien la cambió después, no la toca y lo avisa.
 * - Guarda lo que reemplaza y lo que crea en la opción clp_servicios_previo; «Volver atrás» lo deshace.
 * Herramientas → Caminos Locales.
 */

defined( 'ABSPATH' ) || exit;

define( 'CLP_SERVICIOS_URL', 'https://staging.caminoslocales.com/wp-content/uploads/clp/servicios-2026-11-ducW7hp4xNHG.json' );
define( 'CLP_SERVICIOS_SHA256', 'c346bb37296e31a4a336a1eaea1ed8547374436247324638cb3a4e820de6dd2f' );

function clp_servicios_paquete() {
	if ( '' === CLP_SERVICIOS_URL ) {
		return new WP_Error( 'clp', 'Este plugin no trae paquete de servicios.' );
	}
	$r = wp_remote_get( CLP_SERVICIOS_URL, array( 'timeout' => 60 ) );
	if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
		return new WP_Error( 'clp', 'No se pudo descargar el paquete de servicios del staging.' );
	}
	$body = wp_remote_retrieve_body( $r );
	if ( ! hash_equals( CLP_SERVICIOS_SHA256, hash( 'sha256', $body ) ) ) {
		return new WP_Error( 'clp', 'El paquete descargado no coincide con el esperado (SHA-256). No se aplicó nada.' );
	}
	$p = json_decode( $body, true );
	return is_array( $p ) ? $p : new WP_Error( 'clp', 'El paquete no se pudo leer.' );
}

// Cambia los ID del staging por los de este sitio en un valor del paquete (JSON normalizado).
function clp_servicios_ids( $json, $mapa ) {
	foreach ( $mapa as $viejo => $nuevo ) {
		$json = str_replace(
			array( '"postId":"' . $viejo . '"', '"postId":' . $viejo . ',', '"ids":["' . $viejo . '"]', '"templateId":"' . $viejo . '"' ),
			array( '"postId":"' . $nuevo . '"', '"postId":' . $nuevo . ',', '"ids":["' . $nuevo . '"]', '"templateId":"' . $nuevo . '"' ),
			$json
		);
	}
	return $json;
}

/**
 * Revisa (y si $aplicar, aplica) el paquete. Devuelve la lista de renglones array( ok|ojo|mal, texto ).
 */
function clp_servicios_procesar( $aplicar ) {
	if ( ! function_exists( 'clp_diseno_norm' ) ) {
		return array( array( 'mal', 'Falta el módulo de diseño del plugin.' ) );
	}
	$p = clp_servicios_paquete();
	if ( is_wp_error( $p ) ) {
		return array( array( 'mal', $p->get_error_message() ) );
	}
	$out    = array();
	$previo = array( 'posts' => array(), 'creados' => array(), 'menu' => 0 );
	$saltea = 0;
	$mapa   = array(); // ID del staging => ID de acá.

	// 1) Páginas y plantillas nuevas: primero se crean vacías para conocer sus ID.
	foreach ( $p['crear'] as $c ) {
		$ya = 'page' === $c['type']
			? get_page_by_path( $c['path'], OBJECT, 'page' )
			: ( get_posts( array( 'post_type' => $c['type'], 'title' => $c['title'], 'post_status' => 'any', 'numberposts' => 1 ) )[0] ?? null );
		if ( $ya ) {
			$mapa[ $c['id'] ] = (int) $ya->ID;
			$out[]            = array( 'ok', $c['title'] . ': ya existe (no se toca).' );
			continue;
		}
		if ( ! $aplicar ) {
			$out[] = array( 'ok', $c['title'] . ': se crea.' );
			continue;
		}
		$padre = 0;
		if ( ! empty( $c['parent_path'] ) ) {
			$pp = get_page_by_path( $c['parent_path'], OBJECT, 'page' );
			if ( ! $pp ) {
				$out[] = array( 'mal', $c['title'] . ': no encuentro la página madre /' . $c['parent_path'] . '/.' );
				continue;
			}
			$padre = (int) $pp->ID;
		}
		$id = wp_insert_post( array(
			'post_type'   => $c['type'],
			'post_title'  => $c['title'],
			'post_name'   => $c['slug'],
			'post_parent' => $padre,
			'post_status' => 'publish',
		), true );
		if ( is_wp_error( $id ) ) {
			$out[] = array( 'mal', $c['title'] . ': ' . $id->get_error_message() );
			continue;
		}
		$mapa[ $c['id'] ]    = (int) $id;
		$previo['creados'][] = (int) $id;
		$out[]               = array( 'ok', $c['title'] . ': creada.' );
	}

	// 2) Contenido de lo creado (con los ID ya cambiados).
	if ( $aplicar ) {
		foreach ( $p['crear'] as $c ) {
			$id = $mapa[ $c['id'] ] ?? 0;
			if ( ! $id || ! in_array( $id, $previo['creados'], true ) ) {
				continue;
			}
			foreach ( $c['meta'] as $k => $v ) {
				update_post_meta( $id, $k, wp_slash( clp_diseno_valor( clp_servicios_ids( $v, $mapa ) ) ) );
			}
		}
	}

	// 3) Páginas que ya existen: se reemplaza su diseño de Bricks si nadie las cambió.
	foreach ( $p['reemplazar'] as $po ) {
		$titulo = $po['title'] . ' (' . $po['id'] . ')';
		if ( ! get_post( $po['id'] ) ) {
			$out[] = array( 'ojo', $titulo . ': no existe en este sitio; no se toca.' );
			$saltea++;
			continue;
		}
		$actual = get_post_meta( $po['id'], $po['key'], true );
		$nuevo  = clp_diseno_valor( clp_servicios_ids( $po['value'], $mapa ) );
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

	// 4) Ítem «Servicios» del menú principal, después de «Experiencias».
	$menu = wp_get_nav_menu_object( $p['menu']['name'] );
	$pag  = get_page_by_path( $p['menu']['path'], OBJECT, 'page' );
	if ( ! $menu ) {
		$out[] = array( 'ojo', 'Menú «' . $p['menu']['name'] . '»: no existe; agregá «Servicios» a mano.' );
	} else {
		$items = wp_get_nav_menu_items( $menu );
		$ya    = false;
		$pos   = count( $items );
		foreach ( $items as $i ) {
			if ( $pag && (int) $i->object_id === (int) $pag->ID ) {
				$ya = true;
			}
			if ( $i->title === $p['menu']['after'] ) {
				$pos = (int) $i->menu_order;
			}
		}
		if ( $ya ) {
			$out[] = array( 'ok', 'Menú: «Servicios» ya está.' );
		} elseif ( ! $aplicar ) {
			$out[] = array( 'ok', 'Menú: se agrega «Servicios» después de «' . $p['menu']['after'] . '».' );
		} elseif ( ! $pag ) {
			$out[] = array( 'mal', 'Menú: no encuentro la página de servicios.' );
		} else {
			foreach ( $items as $i ) {
				if ( (int) $i->menu_order > $pos ) {
					wp_update_post( array( 'ID' => $i->ID, 'menu_order' => $i->menu_order + 1 ) );
				}
			}
			$previo['menu'] = (int) wp_update_nav_menu_item( $menu->term_id, 0, array(
				'menu-item-title'     => $p['menu']['title'],
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $pag->ID,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $pos + 1,
			) );
			$out[] = array( 'ok', 'Menú: «Servicios» agregado.' );
		}
	}

	if ( $aplicar ) {
		update_option( 'clp_servicios_previo', $previo, false );
		update_option( 'clp_servicios_aplicado', current_time( 'mysql' ), false );
		delete_option( 'cl_srv_sync' ); // Recalcula quién es servicio en el próximo pedido.
		if ( function_exists( 'clp_diseno_purgar' ) ) {
			clp_diseno_purgar();
		}
	}
	if ( $saltea ) {
		$out[] = array( 'ojo', $saltea . ' cosas no se tocaron porque cambiaron en este sitio. Pasale la lista a Claude.' );
	}
	return $out;
}

function clp_servicios_volver() {
	$pr = get_option( 'clp_servicios_previo' );
	if ( ! is_array( $pr ) ) {
		return 'No hay nada para volver atrás.';
	}
	foreach ( $pr['posts'] as $po ) {
		update_post_meta( $po['id'], $po['key'], wp_slash( $po['value'] ) );
	}
	foreach ( $pr['creados'] as $id ) {
		wp_trash_post( $id ); // A la papelera, por si acaso.
	}
	if ( $pr['menu'] ) {
		wp_delete_post( $pr['menu'], true );
	}
	delete_option( 'clp_servicios_previo' );
	delete_option( 'clp_servicios_aplicado' );
	if ( function_exists( 'clp_diseno_purgar' ) ) {
		clp_diseno_purgar();
	}
	return 'Listo: las páginas volvieron a como estaban; lo creado quedó en la papelera.';
}

add_action( 'admin_post_clp_servicios', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'clp_servicios' ) ) {
		wp_die( 'Sin permiso.' );
	}
	$accion = sanitize_key( $_POST['clp_accion'] ?? '' );
	if ( 'volver' === $accion ) {
		set_transient( 'clp_servicios_resultado', array( array( array( 'ok', clp_servicios_volver() ) ), 'volver' ), 600 );
	} else {
		@set_time_limit( 300 ); // phpcs:ignore
		set_transient( 'clp_servicios_resultado', array( clp_servicios_procesar( 'aplicar' === $accion ), $accion ), 600 );
	}
	wp_safe_redirect( admin_url( 'tools.php?page=caminos-locales#clp-servicios' ) );
	exit;
} );

// Caja en Herramientas → Caminos Locales.
function clp_servicios_caja() {
	if ( '' === CLP_SERVICIOS_URL ) {
		return;
	}
	$aplicado = get_option( 'clp_servicios_aplicado' );
	$res      = get_transient( 'clp_servicios_resultado' );
	$colores  = array( 'ok' => '#00a32a', 'ojo' => '#996800', 'mal' => '#d63638' );
	?>
	<div id="clp-servicios" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 16px;margin:16px 0;max-width:760px">
		<h2 style="margin-top:0">Invitaciones y servicios (1.1.0)</h2>
		<?php if ( $aplicado ) : ?>
			<p>Aplicado el <?php echo esc_html( $aplicado ); ?>. Si algo salió mal, podés volver atrás.</p>
		<?php else : ?>
			<p>Pasa a este sitio lo del staging: la página de la invitación, «Servicios locales» (con su ventana y su ficha compacta), la lista de prestadores del panel, el panel con «Qué ofrecés» primero y la ficha compacta, Mi panel ordenado, el texto de Emprendimientos y «Servicios» en el menú. No toca fichas, alojamientos, experiencias ni usuarios.</p>
			<p><strong>Antes:</strong> respaldo con JetBackup. Primero «Revisar» (no cambia nada), después «Aplicar».</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;flex-wrap:wrap">
			<input type="hidden" name="action" value="clp_servicios">
			<?php wp_nonce_field( 'clp_servicios' ); ?>
			<?php if ( $aplicado ) : ?>
				<button class="button" name="clp_accion" value="volver" onclick="return confirm('¿Volver atrás? Las páginas nuevas van a la papelera.')">Volver atrás</button>
			<?php else : ?>
				<button class="button" name="clp_accion" value="revisar">Revisar</button>
				<button class="button button-primary" name="clp_accion" value="aplicar" onclick="return confirm('¿Aplicar invitaciones y servicios? Hacé el respaldo antes.')">Aplicar</button>
			<?php endif; ?>
		</form>
		<?php if ( is_array( $res ) ) : delete_transient( 'clp_servicios_resultado' ); ?>
			<h3><?php echo 'revisar' === $res[1] ? 'Revisión (no se cambió nada)' : 'Resultado'; ?></h3>
			<?php foreach ( $res[0] as $r ) : ?>
				<p style="margin:3px 0"><span style="color:<?php echo esc_attr( $colores[ $r[0] ] ); ?>;font-weight:700">●</span> <?php echo esc_html( $r[1] ); ?></p>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}
