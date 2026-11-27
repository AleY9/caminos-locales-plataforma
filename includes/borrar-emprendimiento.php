<?php
/**
 * Borrar un emprendimiento desde Mi panel, solo administradores (1.4.6).
 *
 * En el recuadro de carga asistida, con un emprendimiento elegido: «Borrar este emprendimiento…» (desplegable).
 * Paso 1: al abrirlo se ve qué se va a la papelera (la ficha, sus alojamientos y sus experiencias) y de quién es.
 * Paso 2: hay que escribir el nombre del emprendimiento para que se habilite el botón (sin importar tildes,
 * mayúsculas ni signos); el servidor lo vuelve a comprobar, junto con el permiso de administrador y el nonce.
 *
 * Todo va a la papelera de WordPress (se recupera desde wp-admin, cada ficha por separado; vuelve como borrador).
 * Se anulan las invitaciones sin usar de esa ficha. No se tocan las fotos ni la cuenta del dueño.
 * Queda registro en la meta _cl_borrado del emprendimiento (fecha, quién, IDs de alojamientos y experiencias).
 */

defined( 'ABSPATH' ) || exit;

// Lo que se borra junto con el emprendimiento: array( 'aloj' => ids, 'ev' => ids ).
function clp_borrar_emp_que( $emp_id ) {
	$emp_id = (int) $emp_id;
	$buscar = function ( $tipo, $clave ) use ( $emp_id ) {
		return array_map( 'intval', get_posts( array(
			'post_type'   => $tipo,
			'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'numberposts' => 200,
			'fields'      => 'ids',
			'meta_query'  => array(
				'relation' => 'OR',
				array( 'key' => $clave, 'value' => '"' . $emp_id . '"', 'compare' => 'LIKE' ), // relación de ACF (serializada)
				array( 'key' => $clave, 'value' => (string) $emp_id ),
			),
		) ) );
	};
	return array(
		'aloj' => $buscar( 'alojamiento', 'emprendimiento_correspondiente' ),
		'ev'   => $buscar( 'evento', 'corresponde_a_emprendimiento' ),
	);
}

function clp_borrar_emp_lista( $ids ) {
	return implode( ', ', array_map( function ( $i ) {
		return '«' . get_post_field( 'post_title', $i ) . '»';
	}, $ids ) );
}

// Nombre para comparar: sin tildes, mayúsculas, espacios ni signos (así «—» o una coma no traban la confirmación).
function clp_borrar_emp_norma( $txt ) {
	return preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( wp_specialchars_decode( (string) $txt, ENT_QUOTES ) ) ) );
}

// Desplegable para el recuadro de carga asistida.
function clp_borrar_emp_html( $emp_id ) {
	$emp_id = (int) $emp_id;
	if ( ! $emp_id || ! current_user_can( 'manage_options' ) || 'emprendimientos' !== get_post_type( $emp_id ) ) {
		return '';
	}
	$nombre = (string) get_post_field( 'post_title', $emp_id );
	$que    = clp_borrar_emp_que( $emp_id );
	$dueno  = function_exists( 'clp_inv_dueno' ) ? clp_inv_dueno( $emp_id ) : null;
	$estado = 'publish' === get_post_status( $emp_id ) ? 'publicada' : 'en borrador';

	$items   = array( '<li>La ficha «' . esc_html( $nombre ) . '» (' . $estado . ').</li>' );
	$items[] = '<li>' . ( $que['aloj'] ? esc_html( count( $que['aloj'] ) . ' ' . ( 1 === count( $que['aloj'] ) ? 'alojamiento' : 'alojamientos' ) . ': ' . clp_borrar_emp_lista( $que['aloj'] ) ) : 'Sin alojamientos.' ) . '</li>';
	$items[] = '<li>' . ( $que['ev'] ? esc_html( count( $que['ev'] ) . ' ' . ( 1 === count( $que['ev'] ) ? 'experiencia' : 'experiencias' ) . ': ' . clp_borrar_emp_lista( $que['ev'] ) ) : 'Sin experiencias.' ) . '</li>';

	$h  = '<details class="clp-borrar" style="border-top:1px solid #e9d3a4;padding-top:6px;font-size:14px;color:#3d4b49">';
	$h .= '<summary style="cursor:pointer;font-weight:700;color:#8a2a1f;min-height:36px;display:flex;align-items:center">Borrar este emprendimiento…</summary>';
	$h .= '<div style="display:flex;flex-direction:column;gap:8px;padding:4px 0 6px">';
	$h .= '<div style="font-weight:700">Se va a la papelera:</div><ul style="margin:0 0 0 18px;padding:0;line-height:1.5">' . implode( '', $items ) . '</ul>';
	if ( $dueno ) {
		$h .= '<div style="background:#fbe3df;border-radius:10px;padding:8px 10px;color:#8a2a1f"><strong>Ojo: es de ' . esc_html( $dueno->display_name ) . '</strong> (' . esc_html( $dueno->user_email ) . '). Su cuenta no se borra: se queda sin ficha.</div>';
	}
	$h .= '<div>Se recupera desde wp-admin → Papelera (cada ficha por separado; vuelve como borrador). Las fotos no se borran y las invitaciones sin usar de esta ficha se anulan.</div>';
	$h .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;flex-direction:column;gap:8px">';
	$h .= '<input type="hidden" name="action" value="clp_borrar_emp"><input type="hidden" name="emp" value="' . $emp_id . '">';
	$h .= wp_nonce_field( 'clp_borrar_emp_' . $emp_id, '_clnonce', false, false );
	$h .= '<label style="font-weight:700">Para confirmar, escribí el nombre: <span style="font-weight:400">' . esc_html( $nombre ) . '</span>';
	$h .= '<input type="text" name="clp_confirma" autocomplete="off" data-nombre="' . esc_attr( clp_borrar_emp_norma( $nombre ) ) . '" style="display:block;width:100%;box-sizing:border-box;margin-top:4px;height:44px;border:1px solid #c9a76a;border-radius:10px;padding:0 12px;font-size:16px;background:#fff"></label>';
	$h .= '<button type="submit" disabled style="min-height:44px;border-radius:10px;border:0;background:#8a2a1f;color:#fff;font-weight:700;font-size:15px;padding:0 16px;cursor:pointer;align-self:flex-start;opacity:.45">Mandar todo a la papelera</button>';
	$h .= '</form></div></details>';
	$h .= '<script>(function(){var d=document.currentScript.previousElementSibling;if(!d)return;var i=d.querySelector("[name=clp_confirma]"),b=d.querySelector("button");'
		. 'var n=function(t){return t.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"").replace(/[^a-z0-9]/g,"");};'
		. 'i.addEventListener("input",function(){var ok=i.dataset.nombre!==""&&n(i.value)===i.dataset.nombre;b.disabled=!ok;b.style.opacity=ok?1:.45;});})();</script>';
	return $h;
}

// Aviso después de borrar (una sola vez).
function clp_borrar_emp_aviso() {
	$clave = 'clp_borrado_' . get_current_user_id();
	$msg   = get_transient( $clave );
	if ( ! $msg ) {
		return '';
	}
	delete_transient( $clave );
	return '<div style="background:#e3ece9;color:#1f4645;border-radius:12px;padding:12px 14px;font-size:15px;margin-bottom:10px">' . esc_html( $msg ) . '</div>';
}

add_action( 'admin_post_clp_borrar_emp', function () {
	$emp_id = isset( $_POST['emp'] ) ? (int) $_POST['emp'] : 0;
	if ( ! current_user_can( 'manage_options' ) || ! $emp_id || ! isset( $_POST['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_clnonce'] ), 'clp_borrar_emp_' . $emp_id ) ) {
		wp_die( 'Sin permiso.', 403 );
	}
	$panel = defined( 'CL_PANEL_PAGE_ID' ) ? get_permalink( CL_PANEL_PAGE_ID ) : home_url( '/panel/' );
	if ( 'emprendimientos' !== get_post_type( $emp_id ) || 'trash' === get_post_status( $emp_id ) ) {
		wp_die( 'Ese emprendimiento no existe o ya está en la papelera.' );
	}
	$nombre   = (string) get_post_field( 'post_title', $emp_id );
	$confirma = isset( $_POST['clp_confirma'] ) ? sanitize_text_field( wp_unslash( $_POST['clp_confirma'] ) ) : '';
	if ( '' === clp_borrar_emp_norma( $nombre ) || clp_borrar_emp_norma( $confirma ) !== clp_borrar_emp_norma( $nombre ) ) {
		wp_die( 'El nombre no coincide: no se borró nada. <a href="' . esc_url( add_query_arg( 'emp', $emp_id, $panel ) ) . '">Volver</a>' );
	}

	$que = clp_borrar_emp_que( $emp_id );
	update_post_meta( $emp_id, '_cl_borrado', array(
		'fecha'   => current_time( 'mysql' ),
		'usuario' => wp_get_current_user()->user_login,
		'aloj'    => $que['aloj'],
		'ev'      => $que['ev'],
	) );
	foreach ( array_merge( $que['aloj'], $que['ev'] ) as $id ) {
		wp_trash_post( $id );
	}
	$sol = get_post_meta( $emp_id, '_cl_solicitud', true );
	if ( is_array( $sol ) ) {
		$sol['estado'] = 'borrada';
		update_post_meta( $emp_id, '_cl_solicitud', $sol );
	}
	wp_trash_post( $emp_id );

	// Invitaciones sin usar de esta ficha.
	if ( function_exists( 'clp_inv_todas' ) ) {
		$todas = clp_inv_todas();
		foreach ( $todas as $k => $v ) {
			if ( ! empty( $v['ficha'] ) && (int) $v['ficha'] === $emp_id && empty( $v['usado'] ) ) {
				unset( $todas[ $k ] );
			}
		}
		update_option( 'clp_invitaciones', $todas, false );
	}

	$n   = count( $que['aloj'] ) + count( $que['ev'] );
	$msg = '«' . $nombre . '» está en la papelera' . ( $n ? ', con ' . ( count( $que['aloj'] ) ? count( $que['aloj'] ) . ' ' . ( 1 === count( $que['aloj'] ) ? 'alojamiento' : 'alojamientos' ) : '' ) . ( count( $que['aloj'] ) && count( $que['ev'] ) ? ' y ' : '' ) . ( count( $que['ev'] ) ? count( $que['ev'] ) . ' ' . ( 1 === count( $que['ev'] ) ? 'experiencia' : 'experiencias' ) : '' ) : '' ) . '. Se recupera desde wp-admin → Papelera.';
	set_transient( 'clp_borrado_' . get_current_user_id(), $msg, 5 * MINUTE_IN_SECONDS );
	wp_safe_redirect( $panel );
	exit;
} );
