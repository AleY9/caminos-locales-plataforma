<?php
/**
 * Plugin Name:       Caminos Locales Plataforma
 * Description:       Panel del emprendedor, fichas y funciones propias de caminoslocales.com (antes, snippets de Code Snippets).
 * Version:           1.1.9
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Liquen Web
 * Text Domain:       caminos-locales-plataforma
 * License:           GPL-2.0-or-later
 * Update URI:        https://github.com/AleY9/caminos-locales-plataforma
 *
 * Diagnóstico: wp-admin → Herramientas → Caminos Locales.
 */

defined( 'ABSPATH' ) || exit;

define( 'CLP_VERSION', '1.1.9' );
define( 'CLP_FILE', __FILE__ );
define( 'CLP_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Módulos: cada uno es un snippet de Code Snippets pasado a archivo, con el mismo código.
 * Se cargan en el mismo orden en que los corría Code Snippets (prioridad 10, por ID).
 * 'snippet' es el ID que tenía en Code Snippets; 'nombre', el nombre que tenía ahí.
 * 'admin' => true: solo en wp-admin (en Code Snippets era «Solo en el área de administración»).
 */
function clp_modulos() {
	return array(
		'capacidad-mcp-acf'                => array( 'snippet' => 13, 'nombre' => 'Capacidad MCP para escribir campos ACF' ),
		'email-emprendimiento-alojamiento' => array( 'snippet' => 14, 'nombre' => 'Caminos Locales – email emprendimiento en alojamientos' ),
		'fichas'                           => array( 'snippet' => 15, 'nombre' => 'CL – Fichas: campos ACF y funciones para Bricks - v5' ),
		'roles'                            => array( 'snippet' => 16, 'nombre' => 'CL – Roles del back office' ),
		'subidas-fotos'                    => array( 'snippet' => 18, 'nombre' => 'CL – Subidas de fotos optimizadas' ),
		'carpetas-medios'                  => array( 'snippet' => 19, 'nombre' => 'CL – Carpetas de medios' ),
		'panel'                            => array( 'snippet' => 20, 'nombre' => 'CL – Panel' ),
		'panel-fotos'                      => array( 'snippet' => 21, 'nombre' => 'CL – Panel: Fotos' ),
		'panel-alojamientos'               => array( 'snippet' => 22, 'nombre' => 'CL – Panel: Alojamientos' ),
		'panel-eventos'                    => array( 'snippet' => 23, 'nombre' => 'CL – Panel: Eventos' ),
		'panel-cuenta'                     => array( 'snippet' => 24, 'nombre' => 'CL – Panel: Cuenta' ),
		'redirecciones-sumate'             => array( 'snippet' => 25, 'nombre' => 'CL – Redirecciones de Sumate' ),
		'equipamiento-heredado'            => array( 'snippet' => 27, 'nombre' => 'CL - Equipamiento Heredado' ),
		'capacidad-terminos'               => array( 'snippet' => 28, 'nombre' => 'CL – Capacidad: términos' ),
		'fotos-grupo'                      => array( 'snippet' => 29, 'nombre' => 'CL - Fotos Grupo' ),
		'admin-donde-ocurre'               => array( 'snippet' => 30, 'nombre' => 'CL – Admin: condiciones según ¿Dónde ocurre?', 'admin' => true ),
	);
}

/**
 * Snippets de Code Snippets que siguen activos y repiten un módulo (por ID o por nombre).
 * Si hay alguno, el plugin no carga nada.
 */
function clp_snippets_repetidos() {
	static $repetidos = null;
	if ( null !== $repetidos ) {
		return $repetidos;
	}
	$repetidos = array();
	global $wpdb;
	if ( ! defined( 'CODE_SNIPPETS_FILE' ) && ! class_exists( '\Code_Snippets\Plugin' ) ) {
		return $repetidos; // Code Snippets no está activo.
	}
	$tabla   = $wpdb->prefix . 'snippets';
	$antes   = $wpdb->suppress_errors( true );
	$activos = $wpdb->get_results( "SELECT id, name FROM {$tabla} WHERE active = 1", ARRAY_A ); // phpcs:ignore WordPress.DB
	$wpdb->suppress_errors( $antes );
	$ids     = array();
	$nombres = array();
	foreach ( clp_modulos() as $m ) {
		$ids[]     = (int) $m['snippet'];
		$nombres[] = $m['nombre'];
	}
	foreach ( (array) $activos as $s ) {
		if ( in_array( (int) $s['id'], $ids, true ) || in_array( $s['name'], $nombres, true ) ) {
			$repetidos[ (int) $s['id'] ] = $s['name'];
		}
	}
	return $repetidos;
}

/**
 * ¿Ya hay funciones del plugin definidas por otro lado (snippets activos)?
 * Code Snippets corre los snippets en plugins_loaded con prioridad 1; esto se mira después.
 */
function clp_funciones_repetidas() {
	foreach ( array( 'cl_panel_puede_usar', 'cl_aloj_id', 'cl_ev_id', 'cl_emprendimiento_id', 'cl_fotos_ids', 'cl_email_emprendimiento_alojamiento' ) as $f ) {
		if ( function_exists( $f ) ) {
			return $f;
		}
	}
	return '';
}

/**
 * ¿Los emprendedores publican sin revisión? (v1.0.1)
 * Por defecto sí: integrantes de confianza. Publican al momento y al admin le llega un aviso.
 * Se vuelve a pedir revisión en Herramientas → Caminos Locales (opción clp_con_revision).
 */
function cl_sin_revision() {
	return ! get_option( 'clp_con_revision' );
}

// Módulos que se cargaron en este pedido (para el Diagnóstico).
$GLOBALS['clp_cargados'] = array();
$GLOBALS['clp_bloqueo']  = '';

add_action( 'plugins_loaded', function () {
	// Si alguno de los snippets sigue activo, define las mismas funciones: no se carga nada
	// (se repetirían y daría error crítico). El sitio sigue andando con los snippets.
	$rep = clp_funciones_repetidas();
	if ( $rep || clp_snippets_repetidos() ) {
		$GLOBALS['clp_bloqueo'] = $rep ? 'función ' . $rep . '() ya definida' : 'snippets activos';
		return;
	}
	foreach ( clp_modulos() as $archivo => $m ) {
		if ( ! empty( $m['admin'] ) && ! is_admin() ) {
			continue;
		}
		require_once CLP_DIR . 'modulos/' . $archivo . '.php';
		$GLOBALS['clp_cargados'][] = $archivo;
	}
}, 5 );

// Aviso en wp-admin si el plugin no cargó porque los snippets siguen activos.
add_action( 'admin_notices', function () {
	if ( '' === $GLOBALS['clp_bloqueo'] || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$lista = clp_snippets_repetidos();
	echo '<div class="notice notice-error"><p><strong>Caminos Locales Plataforma no está funcionando:</strong> ';
	echo esc_html( 'los snippets de Code Snippets con el mismo código siguen activos, y no pueden estar los dos a la vez. Desactivá en Code Snippets: ' . ( $lista ? implode( ', ', array_map( function ( $id, $n ) {
		return $n . ' (' . $id . ')';
	}, array_keys( $lista ), $lista ) ) : 'los snippets «CL – …»' ) . '. El sitio sigue andando con los snippets.' );
	echo ' <a href="' . esc_url( admin_url( 'admin.php?page=snippets' ) ) . '">Ir a Code Snippets →</a></p></div>';
} );

require_once CLP_DIR . 'includes/diagnostico.php';
require_once CLP_DIR . 'includes/actualizaciones.php';
require_once CLP_DIR . 'includes/aplicar-diseno.php';
require_once CLP_DIR . 'includes/aplicar-servicios.php';
require_once CLP_DIR . 'includes/invitaciones.php';
require_once CLP_DIR . 'includes/servicios.php';

register_activation_hook( __FILE__, function () {
	delete_site_transient( 'update_plugins' );
} );
