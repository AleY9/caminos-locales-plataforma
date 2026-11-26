<?php
/**
 * Ubicación privada (1.4.2).
 *
 * En el paso Ubicación del panel, el emprendedor elige si muestra la ubicación exacta en la web
 * (radio clubpu del formulario pqgjw0: «Sí, mostrar mapa y cómo llegar» · «No, la paso por WhatsApp al confirmar
 * la reserva»). Por defecto, Sí. Con No se guarda la meta _cl_ubicacion_privada = 1 y:
 *
 * - En las páginas públicas se leen vacíos el mapa, la dirección y «Cómo llegar» del emprendimiento, y el mapa propio
 *   de sus alojamientos. Así no aparecen en ninguna ficha (emprendimiento, alojamientos, experiencias que ocurren en
 *   el emprendimiento) ni el botón «Cómo llegar». La localidad y la región se siguen viendo.
 *   En wp-admin, el panel, los correos y las tareas programadas se ven siempre (mismo criterio que el contacto oculto).
 * - {echo:cl_ubicacion_aviso} da «La ubicación exacta y cómo llegar se pasan por WhatsApp al confirmar la reserva.»
 *   en esas fichas (o '' si la ubicación es pública). En Bricks: un texto con condición «no vacío».
 * - En el panel no se piden: se ocultan el mapa y la dirección (paso Ubicación, al elegir No) y «Cómo llegar»
 *   (paso Llegar y estar), y «Ver cómo queda» y el progreso dejan de sugerirlos.
 * - Las experiencias «En otro lugar» no cambian: su lugar y su mapa son propios.
 */

defined( 'ABSPATH' ) || exit;

function cl_ubicacion_campos() {
	return array( 'ubicacion', 'direccion_o_punto_de_encuentro', 'como_llegar' );
}

function cl_ubicacion_es_privada( $emp_id ) {
	return $emp_id && '1' === (string) get_post_meta( (int) $emp_id, '_cl_ubicacion_privada', true );
}

// Emprendimiento al que corresponde una ficha (sin pedir que esté publicado).
function cl_ubicacion_emp_de( $post_id ) {
	$post_id = (int) $post_id;
	$tipo    = get_post_type( $post_id );
	if ( 'emprendimientos' === $tipo ) {
		return $post_id;
	}
	if ( 'alojamiento' === $tipo ) {
		$rel   = get_post_meta( $post_id, 'emprendimiento_correspondiente', true );
		$first = is_array( $rel ) ? reset( $rel ) : $rel;
		$id    = absint( is_object( $first ) ? $first->ID : $first );
		return ( $id && 'emprendimientos' === get_post_type( $id ) ) ? $id : 0;
	}
	if ( 'evento' === $tipo && function_exists( 'cl_ev_emp_de' ) ) {
		if ( function_exists( 'cl_ev_en_otro_lugar' ) && cl_ev_en_otro_lugar( $post_id ) ) {
			return 0; // Su lugar es propio.
		}
		return cl_ev_emp_de( $post_id );
	}
	return 0;
}

// En las páginas públicas, el mapa, la dirección y «Cómo llegar» se leen vacíos.
add_filter( 'get_post_metadata', function ( $valor, $id, $clave, $single ) {
	static $campos = null;
	if ( null === $campos ) {
		$campos = cl_ubicacion_campos();
	}
	if ( 'ubicacion_alojamiento' === $clave ) {
		if ( 'alojamiento' !== get_post_type( $id ) || ! function_exists( 'cl_contacto_filtrar' ) || ! cl_contacto_filtrar() ) {
			return $valor;
		}
		$emp = cl_ubicacion_emp_de( $id );
	} elseif ( in_array( $clave, $campos, true ) ) {
		if ( 'emprendimientos' !== get_post_type( $id ) || ! function_exists( 'cl_contacto_filtrar' ) || ! cl_contacto_filtrar() ) {
			return $valor;
		}
		$emp = (int) $id;
	} else {
		return $valor;
	}
	if ( ! cl_ubicacion_es_privada( $emp ) ) {
		return $valor;
	}
	return $single ? array( '' ) : array();
}, 10, 4 );

// {echo:cl_ubicacion_aviso} → aviso para el visitante, o ''.
function cl_ubicacion_aviso( $post_id = 0 ) {
	$post_id = absint( $post_id ) ? absint( $post_id ) : get_the_ID();
	if ( ! $post_id || ! cl_ubicacion_es_privada( cl_ubicacion_emp_de( $post_id ) ) ) {
		return '';
	}
	return 'La ubicación exacta y cómo llegar se pasan por WhatsApp al confirmar la reserva.';
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_ubicacion_aviso' ) );
} );

// Guarda la elección desde el panel (valor del radio clubpu: Sí / No). Devuelve true si cambió.
function cl_ubicacion_guardar( $emp_id, $valor ) {
	$valor   = is_array( $valor ) ? reset( $valor ) : $valor;
	$valor   = sanitize_text_field( (string) $valor );
	$privada = 'No' === $valor;
	if ( ! in_array( $valor, array( 'Sí', 'No' ), true ) || $privada === cl_ubicacion_es_privada( $emp_id ) ) {
		return false;
	}
	if ( $privada ) {
		update_post_meta( $emp_id, '_cl_ubicacion_privada', '1' );
	} else {
		delete_post_meta( $emp_id, '_cl_ubicacion_privada' );
	}
	return true;
}

// Panel: con No no se piden el mapa, la dirección ni «Cómo llegar».
// En el paso Ubicación se ocultan al momento de elegir No (CSS :has()); en Llegar y estar, si ya está guardado.
// En la ficha de servicio (ficha compacta) la pregunta no se muestra: no recibe visitantes en un lugar.
add_action( 'wp_footer', function () {
	if ( ! is_page() || ! function_exists( 'cl_panel_es_pagina' ) || ! cl_panel_es_pagina() || ! function_exists( 'cl_panel_emp_id' ) ) {
		return;
	}
	$emp = cl_panel_emp_id();
	$css = '.brxe-form:has([name^="form-field-clubpu"][value="No"]:checked) .form-group:is(:has([name="form-field-clmapa"]),:has([name="form-field-cldire"]),:has(+ .form-group [name="form-field-clmapa"])){display:none!important}';
	if ( $emp && function_exists( 'cl_es_servicio' ) && cl_es_servicio( $emp ) ) {
		$css .= '.brxe-form .form-group:has([name^="form-field-clubpu"]){display:none!important}';
	}
	if ( $emp && cl_ubicacion_es_privada( $emp ) ) {
		$css .= '.brxe-form .form-group:is(:has([name="form-field-cllleg"]),:has([name="form-field-cllleg"]) + .form-group){display:none!important}';
	}
	echo '<style>' . $css . '</style>';
}, 20 );
