<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 22 «CL – Panel: Alojamientos» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — Panel: Mis alojamientos (fase 4, v6, oct. 2026)
 * v6: {echo:cl_aloj_pi('dato')} para la tarjeta «Mis alojamientos» de Mi panel hecha en Bricks (página 22812).
 * v5: «Borrar» (a la papelera, con doble toque) en cada tarjeta y en «Más opciones». Las fotos no se tocan.
 * v4: «Ocultar de la web» (publicado → borrador oculto) y «Volver a publicar» sin pasar de nuevo por revisión
 *     (solo alojamientos ya aprobados una vez). «Duplicar»: copia en borrador con textos, características,
 *     capacidad, precio y fotos, con el nombre «… (copia)». Los tres botones están en cada tarjeta de
 *     «Mis alojamientos» y en «Ver cómo queda». Foto de «Ver cómo queda» en 5:4, como los listados.
 * v3: baño del alojamiento. «Tipo de baño» pasa a ser «Inodoro» (Con descarga de agua · Seco (compostable));
 *     baño privado y agua caliente quedan en Características. Un alojamiento nuevo arranca con «Baño privado» marcado.
 * v2: "Enlace para reservar en línea" (url_reserva): Airbnb, Booking o el sitio web del emprendimiento;
 *     botón en la ficha con {echo:cl_reserva_url} y {echo:cl_reserva_texto}.
 * Code Snippets: tipo PHP, "Ejecutar en todas partes". Sin <?php.
 * Necesita "CL – Panel" (v14), "CL – Panel: Fotos" y "CL – Fichas". Activa la tarjeta "Mis alojamientos" de Mi panel.
 *
 * Páginas:
 *   /panel/alojamientos/  → [cl_panel_alojamientos] (lista + agregar)
 *   /panel/alojamiento/   → formularios de Bricks por paso: ?aloj=ID&paso=basico|como|fotos|reservas|vista
 *                           [cl_panel_aloj_cabecera], [cl_panel_aloj_fotos], [cl_panel_aloj_vista]
 *                           Valores actuales: {echo:cl_aloj_valor('campo')}
 *
 * - El alojamiento queda siempre vinculado al emprendimiento del panel: el emprendedor no lo puede cambiar.
 * - Nombre del emprendimiento, localidad, región, categorías y "Publicación: Alojamiento" se copian del
 *   emprendimiento, y se actualizan solos cuando cambian allá. Mapa y contacto se heredan (CL – Fichas).
 * - "Icono de alojamiento": solo el admin, en wp-admin.
 * - Fotos por casilla: principal + galería de 9 (galeria_de_alojamientos_foto1…9), sin huecos.
 * - Alojamiento nuevo: borrador → Enviar a revisión → lo publica el admin → email al emprendedor.
 *   Ediciones de uno publicado: se publican directo y le llega un aviso al admin.
 */

define( 'CL_ALOJ_MAX', 12 ); // alojamientos por emprendimiento

// ---------- Utilidades ----------

// Emprendimiento vinculado al alojamiento (en cualquier estado).
function cl_aloj_emp_de( $aloj ) {
	$rel   = get_post_meta( $aloj, 'emprendimiento_correspondiente', true );
	$first = is_array( $rel ) ? reset( $rel ) : $rel;
	$id    = absint( is_object( $first ) ? $first->ID : $first );
	return ( $id && 'emprendimientos' === get_post_type( $id ) ) ? $id : 0;
}

// Alojamientos de un emprendimiento (el emprendedor solo ve los suyos).
function cl_aloj_de_emp( $emp ) {
	if ( ! $emp ) {
		return array();
	}
	$args = array(
		'post_type'   => 'alojamiento',
		'post_status' => array( 'publish', 'draft', 'pending' ),
		'numberposts' => 50,
		'fields'      => 'ids',
		'orderby'     => 'title',
		'order'       => 'ASC',
		'meta_query'  => array( array( 'key' => 'emprendimiento_correspondiente', 'value' => '"' . (int) $emp . '"', 'compare' => 'LIKE' ) ),
	);
	if ( ! current_user_can( 'manage_options' ) ) {
		$args['author'] = get_current_user_id();
	}
	return array_map( 'intval', get_posts( $args ) );
}

// Alojamiento que se edita. Emprendedor: solo si es suyo y de su emprendimiento. Admin: cualquiera.
function cl_aloj_id( $pedido = null ) {
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return 0;
	}
	$id = null !== $pedido ? (int) $pedido : ( isset( $_GET['aloj'] ) ? (int) $_GET['aloj'] : 0 );
	if ( ! $id || 'alojamiento' !== get_post_type( $id ) || ! in_array( get_post_status( $id ), array( 'publish', 'draft', 'pending' ), true ) ) {
		return 0;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return $id;
	}
	$emp = cl_panel_emp_id();
	return ( $emp && (int) get_post_field( 'post_author', $id ) === get_current_user_id() && cl_aloj_emp_de( $id ) === $emp ) ? $id : 0;
}

function cl_aloj_url_lista( $emp = 0 ) {
	return home_url( '/panel/alojamientos/' ) . ( current_user_can( 'manage_options' ) && $emp ? '?emp=' . (int) $emp : '' );
}

function cl_aloj_url( $aloj, $paso = 'basico' ) {
	$args = array( 'paso' => $paso, 'aloj' => (int) $aloj );
	if ( current_user_can( 'manage_options' ) ) {
		$args['emp'] = cl_aloj_emp_de( $aloj );
	}
	return add_query_arg( $args, home_url( '/panel/alojamiento/' ) );
}

// Vista previa (también en borrador) para el dueño y el admin.
function cl_aloj_url_vista( $id ) {
	if ( 'publish' === get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	return add_query_arg( array( 'post_type' => 'alojamiento', 'p' => (int) $id, 'cl_vista' => 1 ), home_url( '/' ) );
}

// ---------- Campos ----------

function cl_aloj_alimentacion() {
	return array(
		'establecimiento con servicio de alimentación' => 'Con servicio de comidas',
		'servicio de alimentación previa solicitud'   => 'Comidas a pedido',
		'restaurante o rotisería en la zona'          => 'Restaurante o rotisería en la zona',
		'alimentos vegetarianos disponibles'          => 'Opción vegetariana',
		'alimentos veganos disponibles'               => 'Opción vegana',
		'alimentación para celíacos'                  => 'Apto celíacos',
	);
}

// Campos que el panel puede escribir en un alojamiento. Lo que no está acá no se guarda nunca.
// (Vínculo, nombre del emprendimiento, localidad, región, mapa e icono NO están: los maneja el sistema o el admin.)
function cl_aloj_campos() {
	return array(
		'tipo'                                    => array( 'tipo' => 'tax', 'tax' => 'tipo_alojamiento', 'min' => 1, 'error' => 'Elegí el tipo de alojamiento.' ),
		'capacidad'                               => array( 'tipo' => 'numero', 'max' => 200, 'error' => 'Capacidad: escribí solo el número de personas.' ),
		'capacidad_nota'                          => array( 'tipo' => 'texto', 'max' => 100 ),
		'superficie'                              => array( 'tipo' => 'numero', 'max' => 100000, 'error' => 'Superficie: escribí solo el número de metros cuadrados.' ),
		'caracteristicas'                         => array( 'tipo' => 'tax', 'tax' => 'caracteristicas', 'sin' => array_merge( array( 147, 148 ), function_exists( 'cl_especial_caract_vieja_ids' ) ? cl_especial_caract_vieja_ids() : array() ), 'vacio' => true ),
		'especial'                                => array( 'tipo' => 'especial', 'vacio' => true ), // 1.1.11: «Especial para» (por slug)
		'tipo_de_bano'                            => array( 'tipo' => 'opcion', 'opciones' => array( 'Con descarga de agua', 'Seco (compostable)' ) ), // «Inodoro»
		'alimentacion'                            => array( 'tipo' => 'opciones', 'opciones' => array_keys( cl_aloj_alimentacion() ), 'vacio' => true ),
		'servicios_disponibles_y_caracteristicas' => array( 'tipo' => 'textarea', 'max' => 2000 ),
		'precio'                                  => array( 'tipo' => 'texto', 'max' => 80 ),
		'reservas'                                => array( 'tipo' => 'textarea', 'max' => 1000 ),
		'url_reserva'                             => array( 'tipo' => 'reserva', 'max' => 500 ),
		'condiciones_del_alojamiento'             => array( 'tipo' => 'textarea', 'max' => 2000 ),
		'recomendaciones'                         => array( 'tipo' => 'textarea', 'max' => 2000 ),
		'email_del_emprendimiento'                => array( 'tipo' => 'opcion', 'opciones' => array( 'Sí', 'No' ) ),
	);
}

// Campo del formulario de Bricks → campo del alojamiento, por paso.
function cl_aloj_pasos() {
	return array(
		'aloj_basico'   => array( 'clnoma' => 'titulo', 'cltipo' => 'tipo', 'clcapa' => 'capacidad', 'clcapn' => 'capacidad_nota', 'clsupe' => 'superficie', 'cldesa' => 'contenido' ),
		'aloj_como'     => array( 'clcara' => 'caracteristicas', 'clespe' => 'especial', 'cltban' => 'tipo_de_bano', 'clalim' => 'alimentacion', 'clserv' => 'servicios_disponibles_y_caracteristicas' ),
		'aloj_reservas' => array( 'clprec' => 'precio', 'clrese' => 'reservas', 'clures' => 'url_reserva', 'clcond' => 'condiciones_del_alojamiento', 'clrecm' => 'recomendaciones', 'clmost' => 'email_del_emprendimiento' ),
	);
}

// Valor actual, en el mismo formato que devuelve cl_aloj_limpiar().
function cl_aloj_leer( $campo, $id ) {
	if ( 'titulo' === $campo ) {
		return (string) get_post_field( 'post_title', $id );
	}
	if ( 'contenido' === $campo ) {
		return cl_panel_contenido_a_texto( get_post_field( 'post_content', $id ) );
	}
	$campos = cl_aloj_campos();
	$r      = $campos[ $campo ];
	if ( 'especial' === $r['tipo'] ) {
		return function_exists( 'cl_especial_leer' ) ? cl_especial_leer( $id ) : array();
	}
	if ( 'tax' === $r['tipo'] ) {
		$ids = wp_get_object_terms( $id, $r['tax'], array( 'fields' => 'ids' ) );
		$ids = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
		if ( ! empty( $r['sin'] ) ) {
			$ids = array_values( array_diff( $ids, $r['sin'] ) );
		}
		sort( $ids );
		return $ids;
	}
	$v = get_post_meta( $id, $campo, true );
	if ( 'opciones' === $r['tipo'] ) {
		return is_array( $v ) ? array_values( array_intersect( $r['opciones'], $v ) ) : array();
	}
	if ( 'numero' === $r['tipo'] ) {
		return ( '' === $v || null === $v || false === $v ) ? '' : (string) absint( $v );
	}
	return is_scalar( $v ) ? (string) $v : '';
}

// {echo:cl_aloj_valor('campo')} — valor actual del alojamiento que se edita (texto plano).
function cl_aloj_valor( $campo ) {
	$id = cl_aloj_id();
	if ( ! $id ) {
		return '';
	}
	if ( 'id' === $campo ) {
		return (string) $id;
	}
	if ( 'titulo' !== $campo && 'contenido' !== $campo && ! array_key_exists( $campo, cl_aloj_campos() ) ) {
		return '';
	}
	$v = cl_aloj_leer( $campo, $id );
	return is_array( $v ) ? implode( ',', $v ) : (string) $v;
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	$names   = (array) $names;
	$names[] = 'cl_aloj_valor';
	return $names;
} );

// ---------- Reserva en línea (Airbnb, Booking o el sitio web propio) ----------

function cl_reserva_host( $url ) {
	return preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) ) );
}

// 'airbnb' | 'booking' | 'web' | '' (no aceptado). 'web' = mismo dominio que el sitio web del emprendimiento.
function cl_reserva_tipo( $url, $emp ) {
	if ( ! $url || 'https' !== strtolower( (string) wp_parse_url( (string) $url, PHP_URL_SCHEME ) ) ) {
		return '';
	}
	$h = cl_reserva_host( $url );
	if ( preg_match( '/(^|\.)airbnb\.(com|[a-z]{2})(\.[a-z]{2})?$/', $h ) || 'abnb.me' === $h ) {
		return 'airbnb';
	}
	if ( preg_match( '/(^|\.)booking\.com$/', $h ) ) {
		return 'booking';
	}
	$web = $emp ? cl_reserva_host( (string) get_post_meta( $emp, 'url_sitio_web', true ) ) : '';
	if ( $web && ( $h === $web || substr( $h, -strlen( '.' . $web ) ) === '.' . $web ) ) {
		return 'web';
	}
	return '';
}

// {echo:cl_reserva_url} → enlace validado del alojamiento, o ''.
function cl_reserva_url( $post_id = 0 ) {
	$id = absint( $post_id ) ? absint( $post_id ) : get_the_ID();
	if ( ! $id || 'alojamiento' !== get_post_type( $id ) ) {
		return '';
	}
	$u = (string) get_post_meta( $id, 'url_reserva', true );
	return cl_reserva_tipo( $u, cl_aloj_emp_de( $id ) ) ? esc_url( $u ) : '';
}

// {echo:cl_reserva_texto} → "Reservar en Airbnb" / "Reservar en Booking" / "Reservar en su sitio web".
function cl_reserva_texto( $post_id = 0 ) {
	$id = absint( $post_id ) ? absint( $post_id ) : get_the_ID();
	$t  = $id ? cl_reserva_tipo( (string) get_post_meta( $id, 'url_reserva', true ), cl_aloj_emp_de( $id ) ) : '';
	$m  = array( 'airbnb' => 'Reservar en Airbnb', 'booking' => 'Reservar en Booking', 'web' => 'Reservar en su sitio web' );
	return isset( $m[ $t ] ) ? $m[ $t ] : '';
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_reserva_url', 'cl_reserva_texto' ) );
} );

// Limpia y valida. Devuelve array( valor, error ). valor null = no tocar.
function cl_aloj_limpiar( $campo, $valor, $aloj = 0 ) {
	$uno = function ( $v ) {
		$v = is_array( $v ) ? (string) reset( $v ) : $v;
		return is_string( $v ) ? wp_unslash( $v ) : '';
	};
	if ( 'titulo' === $campo ) {
		$v = trim( sanitize_text_field( $uno( $valor ) ) );
		return '' === $v ? array( null, 'El nombre del alojamiento no puede quedar vacío.' ) : array( mb_substr( $v, 0, 100 ), '' );
	}
	if ( 'contenido' === $campo ) {
		$v = trim( sanitize_textarea_field( $uno( $valor ) ) );
		return mb_strlen( $v ) > 1800 ? array( null, 'La descripción supera los 1800 caracteres.' ) : array( $v, '' );
	}
	$campos = cl_aloj_campos();
	if ( ! isset( $campos[ $campo ] ) ) {
		return array( null, '' );
	}
	$r = $campos[ $campo ];
	switch ( $r['tipo'] ) {
		case 'especial':
			return array( function_exists( 'cl_especial_limpiar' ) ? cl_especial_limpiar( $valor ) : null, '' );
		case 'tax':
			$validos = get_terms( array( 'taxonomy' => $r['tax'], 'hide_empty' => false, 'fields' => 'ids' ) );
			$validos = is_wp_error( $validos ) ? array() : array_map( 'intval', $validos );
			if ( ! empty( $r['sin'] ) ) {
				$validos = array_diff( $validos, $r['sin'] );
			}
			$ids = array_map( 'intval', is_array( $valor ) ? $valor : explode( ',', (string) $valor ) );
			$ids = array_values( array_intersect( array_unique( $ids ), $validos ) );
			sort( $ids );
			if ( ! empty( $r['min'] ) && count( $ids ) < $r['min'] ) {
				return array( null, $r['error'] );
			}
			return array( $ids, '' );
		case 'opciones':
			$lista = is_array( $valor ) ? $valor : explode( ',', (string) $valor );
			$lista = array_map( 'trim', array_map( 'wp_unslash', array_map( 'strval', $lista ) ) );
			return array( array_values( array_intersect( $r['opciones'], $lista ) ), '' );
		case 'opcion':
			$v = $uno( $valor );
			return in_array( $v, $r['opciones'], true ) ? array( $v, '' ) : array( null, '' );
		case 'numero':
			$v = trim( $uno( $valor ) );
			if ( '' === $v ) {
				return array( '', '' );
			}
			if ( ! preg_match( '/^\d{1,6}$/', $v ) || (int) $v > $r['max'] ) {
				return array( null, $r['error'] );
			}
			return array( (string) (int) $v, '' );
		case 'reserva':
			$v = trim( $uno( $valor ) );
			if ( '' === $v ) {
				return array( '', '' );
			}
			$v = preg_replace( '#^(https?:)?//#i', '', $v ); // se guarda siempre con https
			$v = esc_url_raw( 'https://' . $v, array( 'https' ) );
			if ( '' === $v || mb_strlen( $v ) > $r['max'] || ! cl_reserva_tipo( $v, $aloj ? cl_aloj_emp_de( $aloj ) : 0 ) ) {
				return array( null, 'El enlace para reservar tiene que ser de Airbnb, de Booking o de tu sitio web (el que cargaste en Contacto) y empezar con https://.' );
			}
			return array( $v, '' );
		case 'texto':
			$v = trim( sanitize_text_field( $uno( $valor ) ) );
			break;
		case 'textarea':
			$v = trim( sanitize_textarea_field( $uno( $valor ) ) );
			break;
		default:
			return array( null, '' );
	}
	if ( mb_strlen( $v ) > $r['max'] ) {
		return array( null, 'Un texto supera el largo máximo (' . $r['max'] . ' caracteres).' );
	}
	return array( $v, '' );
}

function cl_aloj_escribir( $campo, $valor, $id ) {
	$campos = cl_aloj_campos();
	$r      = $campos[ $campo ];
	if ( 'especial' === $r['tipo'] ) {
		cl_especial_guardar( $id, $valor );
		return;
	}
	if ( 'tax' === $r['tipo'] ) {
		wp_set_object_terms( $id, $valor, $r['tax'], false );
		if ( 'caracteristicas' === $campo ) {
			// "Baño privado" también es un campo propio que muestra la ficha.
			update_field( 'field_60e346f428a1b', in_array( 134, $valor, true ) ? 'Sí' : 'No', $id );
		}
		return;
	}
	$obj = get_field_object( $campo, $id, false, false );
	update_field( $obj && ! empty( $obj['key'] ) ? $obj['key'] : $campo, $valor, $id );
}

// ---------- Herencia del emprendimiento ----------

// Copia al alojamiento lo que viene del emprendimiento y deja fijo el vínculo.
function cl_aloj_sincronizar( $aloj, $emp, $vinculo = true ) {
	$aloj = (int) $aloj;
	$emp  = (int) $emp;
	if ( ! $aloj || ! $emp ) {
		return;
	}
	if ( $vinculo ) {
		update_field( 'field_68f2b21225d8c', array( $emp ), $aloj ); // Emprendimiento correspondiente
		// Relación inversa en el emprendimiento ("Alojamiento del emprendimiento").
		$inv = get_post_meta( $emp, 'emprendimiento-alojamiento', true );
		$inv = is_array( $inv ) ? array_map( 'strval', $inv ) : array();
		if ( ! in_array( (string) $aloj, $inv, true ) ) {
			$inv[] = (string) $aloj;
			update_field( 'field_60f0d287cf9e8', $inv, $emp );
		}
		if ( term_exists( 180, 'publicacion' ) ) {
			wp_set_object_terms( $aloj, array( 180 ), 'publicacion', true ); // Publicación: Alojamiento
		}
		$cats = wp_get_object_terms( $aloj, 'category', array( 'fields' => 'ids' ) );
		$cats = is_wp_error( $cats ) ? array() : array_diff( array_map( 'intval', $cats ), array( 1 ) );
		if ( ! $cats ) {
			$ec = wp_get_object_terms( $emp, 'category', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $ec ) && $ec ) {
				wp_set_object_terms( $aloj, array_map( 'intval', $ec ), 'category', false );
			}
		}
	}
	update_field( 'field_60f84bb85768f', (string) get_post_field( 'post_title', $emp ), $aloj ); // Nombre del emprendimiento
	update_field( 'field_60e6f8cabc2ac', mb_substr( (string) get_post_meta( $emp, 'localidad', true ), 0, 80 ), $aloj ); // Localidad
	$reg = wp_get_object_terms( $emp, 'region', array( 'fields' => 'ids' ) );
	if ( ! is_wp_error( $reg ) ) {
		wp_set_object_terms( $aloj, array_map( 'intval', $reg ), 'region', false );
	}
}

// Si cambia el nombre, la localidad o la región del emprendimiento, se actualizan sus alojamientos
// (una vez por pedido, al final).
function cl_aloj_sync_encolar( $emp ) {
	static $cola = null;
	if ( null === $cola ) {
		$cola = array();
		add_action( 'shutdown', function () use ( &$cola ) {
			foreach ( array_keys( $cola ) as $e ) {
				$ids = get_posts( array(
					'post_type'   => 'alojamiento',
					'post_status' => array( 'publish', 'draft', 'pending' ),
					'numberposts' => 50,
					'fields'      => 'ids',
					'meta_query'  => array( array( 'key' => 'emprendimiento_correspondiente', 'value' => '"' . (int) $e . '"', 'compare' => 'LIKE' ) ),
				) );
				foreach ( $ids as $a ) {
					cl_aloj_sincronizar( $a, $e, false );
				}
			}
		} );
	}
	$cola[ (int) $emp ] = 1;
}
foreach ( array( 'added_post_meta', 'updated_post_meta' ) as $cl_hook ) {
	add_action( $cl_hook, function ( $mid, $post_id, $key ) {
		if ( 'localidad' === $key && 'emprendimientos' === get_post_type( $post_id ) ) {
			cl_aloj_sync_encolar( $post_id );
		}
	}, 10, 3 );
}
add_action( 'set_object_terms', function ( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( 'region' === $taxonomy && 'emprendimientos' === get_post_type( $object_id ) ) {
		cl_aloj_sync_encolar( $object_id );
	}
}, 10, 4 );
add_action( 'post_updated', function ( $id, $despues, $antes ) {
	if ( 'emprendimientos' === $despues->post_type && $despues->post_title !== $antes->post_title ) {
		cl_aloj_sync_encolar( $id );
	}
}, 10, 3 );

// ---------- Guardado de los formularios (acción personalizada de Bricks) ----------

add_action( 'bricks/form/custom_action', function ( $form ) {
	$f    = $form->get_fields();
	$paso = isset( $f['form-field-clpaso'] ) ? sanitize_key( $f['form-field-clpaso'] ) : '';
	$mapa = cl_aloj_pasos();
	if ( ! isset( $mapa[ $paso ] ) ) {
		return; // No es un formulario de alojamiento.
	}
	$error = function ( $msg ) use ( $form ) {
		$form->set_result( array( 'action' => 'cl_aloj', 'type' => 'error', 'message' => $msg ) );
	};
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return $error( 'Tu sesión venció. Volvé a iniciar sesión.' );
	}
	// Mismo límite que el resto del panel: 30 guardados cada 10 minutos por cuenta.
	$clave = 'cl_panel_rl_' . get_current_user_id();
	$n     = (int) get_transient( $clave );
	if ( $n >= 30 ) {
		return $error( 'Guardaste muchas veces seguidas. Esperá unos minutos y probá de nuevo.' );
	}
	set_transient( $clave, $n + 1, 10 * MINUTE_IN_SECONDS );

	$aloj = cl_aloj_id( isset( $f['form-field-claloj'] ) ? (int) $f['form-field-claloj'] : 0 );
	if ( ! $aloj ) {
		return $error( 'No encontramos este alojamiento. Volvé a Mis alojamientos y entrá de nuevo.' );
	}
	$emp = cl_aloj_emp_de( $aloj );
	if ( ! $emp ) {
		return $error( 'Este alojamiento no está vinculado a un emprendimiento. Escribinos y lo revisamos.' );
	}
	$es_admin = current_user_can( 'manage_options' );
	$campos   = cl_aloj_campos();
	$cambios  = array();
	$post_upd = array();

	foreach ( $mapa[ $paso ] as $fid => $campo ) {
		$k = 'form-field-' . $fid;
		if ( ! array_key_exists( $k, $f ) ) {
			// Casillas sin ninguna marcada: el navegador no las manda → quedan vacías.
			if ( empty( $campos[ $campo ]['vacio'] ) ) {
				continue;
			}
			// «Especial para» solo se vacía si el formulario tiene la pregunta (oculto clespx).
			if ( 'especial' === $campo && ! array_key_exists( 'form-field-clespx', $f ) ) {
				continue;
			}
			$f[ $k ] = array();
		}
		list( $valor, $msg ) = cl_aloj_limpiar( $campo, $f[ $k ], $aloj );
		if ( $msg ) {
			return $error( $msg );
		}
		if ( null === $valor || $valor === cl_aloj_leer( $campo, $aloj ) ) {
			continue;
		}
		if ( 'titulo' === $campo ) {
			$post_upd['post_title'] = $valor;
		} elseif ( 'contenido' === $campo ) {
			$post_upd['post_content'] = cl_panel_texto_a_bloques( $valor );
		} else {
			cl_aloj_escribir( $campo, $valor, $aloj );
		}
		$cambios[] = $campo;
	}
	if ( $post_upd ) {
		$post_upd['ID'] = $aloj;
		wp_update_post( wp_slash( $post_upd ) );
	}
	cl_aloj_sincronizar( $aloj, $emp );

	if ( ! $es_admin && $cambios && 'publish' === get_post_status( $aloj ) ) {
		$user   = wp_get_current_user();
		$asunto = '[Caminos Locales] Cambios en ' . get_the_title( $aloj );
		$cuerpo = 'Alojamiento: ' . get_the_title( $aloj ) . "\n" .
			'Emprendimiento: ' . get_the_title( $emp ) . "\n" .
			'Usuario: ' . $user->user_login . "\n\n" .
			'Cambios publicados: ' . implode( ', ', $cambios ) . "\n\n" .
			'Ver: ' . get_permalink( $aloj ) . "\n" .
			'Editar: ' . admin_url( 'post.php?post=' . $aloj . '&action=edit' );
		if ( function_exists( 'clp_aviso_cambios' ) ) {
			clp_aviso_cambios( $aloj, 'alojamiento', $cambios, $asunto, $cuerpo ); // 1.1.12: al resumen
		} else {
			wp_mail( get_option( 'admin_email' ), $asunto, $cuerpo );
		}
	}
	$form->set_result( array( 'action' => 'cl_aloj', 'type' => 'success', 'message' => 'Guardado ✓' ) );
} );

// ---------- Agregar alojamiento ----------

add_action( 'template_redirect', function () {
	if ( empty( $_POST['cl_aloj_nuevo'] ) || ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return;
	}
	$emp = cl_panel_emp_id( isset( $_POST['emp'] ) ? (int) $_POST['emp'] : null );
	if ( ! $emp || ! isset( $_POST['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_clnonce'] ), 'cl_aloj_nuevo_' . $emp ) ) {
		return;
	}
	$volver = cl_aloj_url_lista( $emp );
	$nombre = mb_substr( trim( sanitize_text_field( wp_unslash( isset( $_POST['nombre'] ) ? (string) $_POST['nombre'] : '' ) ) ), 0, 100 );
	if ( '' === $nombre ) {
		wp_safe_redirect( add_query_arg( 'error', 'nombre', $volver ) );
		exit;
	}
	if ( count( cl_aloj_de_emp( $emp ) ) >= CL_ALOJ_MAX ) {
		wp_safe_redirect( add_query_arg( 'error', 'max', $volver ) );
		exit;
	}
	$es_admin = current_user_can( 'manage_options' );
	if ( ! $es_admin ) {
		$clave = 'cl_aloj_nuevos_' . get_current_user_id();
		$n     = (int) get_transient( $clave );
		if ( $n >= 5 ) {
			wp_safe_redirect( add_query_arg( 'error', 'muchos', $volver ) );
			exit;
		}
		set_transient( $clave, $n + 1, DAY_IN_SECONDS );
	}
	$dueno = (int) get_post_field( 'post_author', $emp );
	$id    = wp_insert_post( array(
		'post_type'   => 'alojamiento',
		'post_status' => 'draft',
		'post_title'  => $nombre,
		'post_author' => $es_admin && $dueno ? $dueno : get_current_user_id(),
	), true );
	if ( is_wp_error( $id ) ) {
		wp_safe_redirect( add_query_arg( 'error', 'crear', $volver ) );
		exit;
	}
	cl_aloj_sincronizar( $id, $emp );
	update_field( 'field_68fc22b795e2a', 'Sí', $id ); // Mostrar el formulario de consultas
	cl_aloj_escribir( 'caracteristicas', array( 134 ), $id ); // «Baño privado» marcado por defecto (se desmarca si es compartido)
	if ( 'Sí' !== get_post_meta( $emp, 'ofrece_alojamiento', true ) ) {
		update_field( 'field_60fdc64938cac', 'Sí', $emp );
	}
	wp_safe_redirect( cl_aloj_url( $id, 'basico' ) );
	exit;
}, 1 );

// ---------- Estados, chequeo y envío a revisión ----------

function cl_aloj_galeria( $id ) {
	$fotos = array();
	for ( $i = 1; $i <= 9; $i++ ) {
		$v = (int) get_post_meta( $id, 'galeria_de_alojamientos_foto' . $i, true );
		if ( $v && wp_attachment_is_image( $v ) ) {
			$fotos[] = $v;
		}
	}
	return $fotos;
}

// array( obligatorios, sugeridos ); cada uno: array( texto, ok, paso ).
function cl_aloj_chequeo( $id ) {
	$tipos = wp_get_object_terms( $id, 'tipo_alojamiento', array( 'fields' => 'ids' ) );
	$cara  = wp_get_object_terms( $id, 'caracteristicas', array( 'fields' => 'ids' ) );
	$oblig = array(
		array( 'Nombre', '' !== trim( (string) get_post_field( 'post_title', $id ) ), 'basico' ),
		array( 'Tipo de alojamiento', ! is_wp_error( $tipos ) && $tipos, 'basico' ),
		array( 'Descripción', '' !== trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ) ), 'basico' ),
		array( 'Foto principal', (bool) get_post_thumbnail_id( $id ), 'fotos' ),
	);
	$suger = array(
		array( 'Capacidad', '' !== (string) get_post_meta( $id, 'capacidad', true ), 'basico' ),
		array( 'Qué tiene', ! is_wp_error( $cara ) && $cara, 'como' ),
		array( 'Al menos 2 fotos más', count( cl_aloj_galeria( $id ) ) >= 2, 'fotos' ),
		array( 'Precio', '' !== trim( (string) get_post_meta( $id, 'precio', true ) ), 'reservas' ),
		array( 'Cómo reservar', '' !== trim( (string) get_post_meta( $id, 'reservas', true ) ), 'reservas' ),
	);
	return array( $oblig, $suger );
}

function cl_aloj_chip( $estado ) {
	$m = array(
		'publish' => array( 'Publicado', '#dcebe4', '#174a3a' ),
		'pending' => array( 'En revisión', '#fdf0d5', '#6b4300' ),
		'draft'   => array( 'Borrador', '#ececec', '#454545' ),
	);
	$e = isset( $m[ $estado ] ) ? $m[ $estado ] : $m['draft'];
	return '<span style="display:inline-block;padding:3px 9px;border-radius:999px;background:' . $e[1] . ';color:' . $e[2] . ';font-size:13px;font-weight:700">' . esc_html( $e[0] ) . '</span>';
}

function cl_aloj_aprobada( $id ) {
	return (bool) get_post_meta( $id, '_cl_aloj_aprobada', true );
}

// Oculto = estuvo publicado y el emprendedor lo sacó de la web.
function cl_aloj_oculto( $id ) {
	return 'draft' === get_post_status( $id ) && cl_aloj_aprobada( $id );
}

function cl_aloj_chip_de( $id ) {
	if ( cl_aloj_oculto( $id ) ) {
		return '<span style="display:inline-block;padding:3px 9px;border-radius:999px;background:#e6e1d6;color:#4a4335;font-size:13px;font-weight:700">Oculto</span>';
	}
	return cl_aloj_chip( get_post_status( $id ) );
}

add_action( 'template_redirect', function () {
	if ( empty( $_POST['cl_aloj_enviar'] ) || ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return;
	}
	$id = cl_aloj_id( isset( $_POST['aloj'] ) ? (int) $_POST['aloj'] : 0 );
	if ( ! $id || ! isset( $_POST['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_clnonce'] ), 'cl_aloj_enviar_' . $id ) ) {
		return;
	}
	$vuelta = cl_aloj_url( $id, 'vista' );
	$estado_antes = get_post_status( $id );
	if ( 'draft' !== $estado_antes && ! ( 'pending' === $estado_antes && cl_sin_revision() ) ) {
		wp_safe_redirect( $vuelta );
		exit;
	}
	list( $oblig ) = cl_aloj_chequeo( $id );
	foreach ( $oblig as $o ) {
		if ( ! $o[1] ) {
			wp_safe_redirect( add_query_arg( 'faltan', 1, $vuelta ) );
			exit;
		}
	}
	$user = wp_get_current_user();
	$emp  = cl_aloj_emp_de( $id );
	// v1.0.1: sin revisión (integrantes de confianza), se publica al momento y se avisa al admin.
	if ( cl_sin_revision() ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		wp_mail(
			get_option( 'admin_email' ),
			'[Caminos Locales] Alojamiento publicado: ' . get_the_title( $id ),
			'El alojamiento «' . get_the_title( $id ) . '» de ' . get_the_title( $emp ) . ' se publicó desde el panel.' . "\n" .
			'Usuario: ' . $user->user_login . "\n\n" .
			'Ver: ' . get_permalink( $id ) . "\n" .
			'Editar: ' . admin_url( 'post.php?post=' . $id . '&action=edit' ) . "\n" .
			'(El icono del alojamiento se elige en wp-admin.)'
		);
		wp_safe_redirect( add_query_arg( 'listo', 1, $vuelta ) );
		exit;
	}
	wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ) );
	wp_mail(
		get_option( 'admin_email' ),
		'[Caminos Locales] Alojamiento para revisar: ' . get_the_title( $id ),
		'El alojamiento «' . get_the_title( $id ) . '» de ' . get_the_title( $emp ) . ' se envió a revisión.' . "\n" .
		'Usuario: ' . $user->user_login . "\n\n" .
		'Vista previa: ' . cl_aloj_url_vista( $id ) . "\n" .
		'Revisar y publicar: ' . admin_url( 'post.php?post=' . $id . '&action=edit' ) . "\n" .
		'(El icono del alojamiento se elige en wp-admin.)'
	);
	wp_safe_redirect( add_query_arg( 'enviado', 1, $vuelta ) );
	exit;
}, 1 );

// Al publicar un alojamiento que estaba en revisión, se le avisa al emprendedor.
add_action( 'transition_post_status', function ( $nuevo, $viejo, $post ) {
	if ( 'alojamiento' !== $post->post_type || 'publish' !== $nuevo || 'pending' !== $viejo ) {
		return;
	}
	$autor = get_userdata( (int) $post->post_author );
	if ( ! $autor || ! function_exists( 'cl_es_emprendedor' ) || ! cl_es_emprendedor( $autor ) ) {
		return;
	}
	wp_mail(
		$autor->user_email,
		'¡Tu alojamiento ya está publicado en Caminos Locales!',
		'Hola, ' . $autor->display_name . ":\n\n" .
		'«' . $post->post_title . '» ya se ve en Caminos Locales:' . "\n" . get_permalink( $post ) . "\n\n" .
		'Podés editarlo cuando quieras desde tu panel: ' . home_url( '/panel/alojamientos/' ) . "\n\n" .
		"Gracias por sumarte.\nCaminos Locales"
	);
}, 10, 3 );

// Todo alojamiento que se publica queda marcado como aprobado.
add_action( 'transition_post_status', function ( $nuevo, $viejo, $post ) {
	if ( 'alojamiento' === $post->post_type && 'publish' === $nuevo ) {
		update_post_meta( $post->ID, '_cl_aloj_aprobada', 1 );
	}
}, 5, 3 );

// Aviso al admin (ocultar, volver a publicar, duplicar).
function cl_aloj_aviso_admin( $id, $que ) {
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}
	$user   = wp_get_current_user();
	$emp    = cl_aloj_emp_de( $id );
	$asunto = '[Caminos Locales] ' . $que . ': ' . get_the_title( $id );
	$cuerpo = 'Alojamiento: ' . get_the_title( $id ) . "\nEmprendimiento: " . get_the_title( $emp ) . "\nUsuario: " . $user->user_login . "\n\n" . $que . ".\n\nVer: " . cl_aloj_url_vista( $id ) . "\nEditar: " . admin_url( 'post.php?post=' . $id . '&action=edit' );
	// 1.1.12: ocultar y borrar al momento; volver a publicar y duplicar, al resumen.
	if ( function_exists( 'clp_aviso_cambios' ) && preg_match( '/duplicad|de nuevo/i', $que ) ) {
		clp_aviso_cambios( $id, 'alojamiento', array( mb_strtolower( preg_replace( '/^Alojamiento |\s*por el emprendedor.*$/u', '', $que ) ) ), $asunto, $cuerpo );
		return;
	}
	wp_mail( get_option( 'admin_email' ), $asunto, $cuerpo );
}

// Ocultar, volver a publicar y duplicar (desde la lista o desde «Ver cómo queda»).
add_action( 'template_redirect', function () {
	$accion = '';
	foreach ( array( 'ocultar', 'republicar', 'duplicar', 'borrar' ) as $a ) {
		if ( ! empty( $_POST[ 'cl_aloj_' . $a ] ) ) {
			$accion = $a;
		}
	}
	if ( ! $accion || ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return;
	}
	$id = cl_aloj_id( isset( $_POST['aloj'] ) ? (int) $_POST['aloj'] : 0 );
	if ( ! $id || ! isset( $_POST['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_clnonce'] ), 'cl_aloj_' . $accion . '_' . $id ) ) {
		return;
	}
	$emp    = cl_aloj_emp_de( $id );
	$vuelta = cl_aloj_url( $id, 'vista' );
	$lista  = ! empty( $_POST['volver'] ) && 'lista' === $_POST['volver'];

	if ( 'borrar' === $accion ) {
		// A la papelera (el admin la puede recuperar desde wp-admin). Las fotos no se tocan.
		$emp_b = cl_aloj_emp_de( $id );
		cl_aloj_aviso_admin( $id, 'Alojamiento borrado por el emprendedor (está en la papelera)' );
		wp_trash_post( $id );
		wp_safe_redirect( add_query_arg( 'borrado', 1, cl_aloj_url_lista( $emp_b ) ) );
		exit;
	}

	if ( 'ocultar' === $accion ) {
		if ( 'publish' === get_post_status( $id ) ) {
			update_post_meta( $id, '_cl_aloj_aprobada', 1 );
			wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
			cl_aloj_aviso_admin( $id, 'Alojamiento ocultado por el emprendedor' );
		}
		wp_safe_redirect( $lista ? add_query_arg( 'oculto', $id, cl_aloj_url_lista( $emp ) ) : add_query_arg( 'oculto', 1, $vuelta ) );
		exit;
	}

	if ( 'republicar' === $accion ) {
		if ( ! cl_aloj_oculto( $id ) ) {
			wp_safe_redirect( $vuelta );
			exit;
		}
		list( $oblig ) = cl_aloj_chequeo( $id );
		foreach ( $oblig as $o ) {
			if ( ! $o[1] ) {
				wp_safe_redirect( add_query_arg( 'faltan', 1, $vuelta ) );
				exit;
			}
		}
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		cl_aloj_aviso_admin( $id, 'Alojamiento publicado de nuevo por el emprendedor' );
		wp_safe_redirect( $lista ? add_query_arg( 'publicado', $id, cl_aloj_url_lista( $emp ) ) : add_query_arg( 'publicado', 1, $vuelta ) );
		exit;
	}

	// Duplicar
	if ( ! $emp ) {
		wp_safe_redirect( $vuelta );
		exit;
	}
	if ( count( cl_aloj_de_emp( $emp ) ) >= CL_ALOJ_MAX ) {
		wp_safe_redirect( add_query_arg( 'error', 'max', cl_aloj_url_lista( $emp ) ) );
		exit;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		$clave = 'cl_aloj_nuevos_' . get_current_user_id();
		$n     = (int) get_transient( $clave );
		if ( $n >= 5 ) {
			wp_safe_redirect( add_query_arg( 'error', 'muchos', cl_aloj_url_lista( $emp ) ) );
			exit;
		}
		set_transient( $clave, $n + 1, DAY_IN_SECONDS );
	}
	$nuevo = wp_insert_post( wp_slash( array(
		'post_type'    => 'alojamiento',
		'post_status'  => 'draft',
		'post_title'   => mb_substr( get_post_field( 'post_title', $id ), 0, 90 ) . ' (copia)',
		'post_content' => get_post_field( 'post_content', $id ),
		'post_excerpt' => get_post_field( 'post_excerpt', $id ),
		'post_author'  => (int) get_post_field( 'post_author', $id ),
	) ), true );
	if ( is_wp_error( $nuevo ) ) {
		wp_safe_redirect( add_query_arg( 'error', 'crear', cl_aloj_url_lista( $emp ) ) );
		exit;
	}
	// Datos e imágenes: se copian todos los campos menos los internos. Las imágenes se comparten
	// (no se duplican); «CL – Panel: Fotos» no borra una imagen mientras la use otra ficha.
	$saltar = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_encloseme', '_pingme' );
	foreach ( get_post_meta( $id ) as $k => $valores ) {
		if ( in_array( $k, $saltar, true ) || 0 === strpos( $k, '_cl_aloj_' ) ) {
			continue;
		}
		foreach ( $valores as $v ) {
			add_post_meta( $nuevo, $k, maybe_unserialize( $v ) );
		}
	}
	foreach ( get_object_taxonomies( 'alojamiento' ) as $tax ) {
		$t = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $t ) && $t ) {
			wp_set_object_terms( $nuevo, array_map( 'intval', $t ), $tax, false );
		}
	}
	cl_aloj_sincronizar( $nuevo, $emp );
	cl_aloj_aviso_admin( $nuevo, 'Alojamiento duplicado (borrador)' );
	wp_safe_redirect( add_query_arg( 'copiado', 1, cl_aloj_url( $nuevo, 'basico' ) ) );
	exit;
}, 1 );

// Botones de la tarjeta en «Mis alojamientos».
function cl_aloj_acciones_lista( $a ) {
	$btn = function ( $accion, $texto, $confirma = '' ) use ( $a ) {
		$h  = '<form method="post" class="cla-acc-f"><input type="hidden" name="cl_aloj_' . $accion . '" value="1"><input type="hidden" name="aloj" value="' . (int) $a . '"><input type="hidden" name="volver" value="lista">';
		$h .= wp_nonce_field( 'cl_aloj_' . $accion . '_' . $a, '_clnonce', false, false );
		return $h . '<button type="submit" class="cla-acc-b' . ( 'borrar' === $accion ? ' cla-borrar' : '' ) . '"' . ( $confirma ? ' data-confirma="' . esc_attr( $confirma ) . '"' : '' ) . '>' . esc_html( $texto ) . '</button></form>';
	};
	$h = '';
	if ( 'publish' === get_post_status( $a ) ) {
		$h .= $btn( 'ocultar', 'Ocultar', '¿Seguro? Tocá de nuevo' );
	}
	if ( cl_aloj_oculto( $a ) ) {
		$h .= $btn( 'republicar', 'Volver a publicar' );
	}
	if ( count( cl_aloj_de_emp( cl_aloj_emp_de( $a ) ) ) < CL_ALOJ_MAX ) {
		$h .= $btn( 'duplicar', 'Duplicar' );
	}
	$h .= $btn( 'borrar', 'Borrar', '¿Borrar? Tocá de nuevo' );
	return $h ? '<div class="cla-acc">' . $h . '</div>' : '';
}

// Doble toque para confirmar (botones con data-confirma). También lo usa «CL – Panel: Mis experiencias».
if ( ! function_exists( 'cl_panel_confirma_js' ) ) :
function cl_panel_confirma_js() {
	static $hecho = false;
	if ( $hecho ) {
		return '';
	}
	$hecho = true;
	return '<script>document.addEventListener("click",function(ev){var b=ev.target.closest("button[data-confirma]");if(!b)return;if(b.classList.contains("cl-seguro"))return;ev.preventDefault();b.dataset.txt=b.textContent;b.textContent=b.dataset.confirma;b.classList.add("cl-seguro");setTimeout(function(){b.classList.remove("cl-seguro");b.textContent=b.dataset.txt;},4000);});</script>';
}
endif;

// Vista previa del borrador para su dueño y el admin.
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || empty( $_GET['cl_vista'] ) || empty( $_GET['p'] ) || ! is_user_logged_in() ) {
		return;
	}
	$id = (int) $_GET['p'];
	if ( 'alojamiento' !== get_post_type( $id ) ) {
		return;
	}
	if ( current_user_can( 'manage_options' ) || (int) get_post_field( 'post_author', $id ) === get_current_user_id() ) {
		$q->set( 'post_status', array( 'publish', 'draft', 'pending' ) );
	}
} );
add_action( 'wp_body_open', function () {
	if ( empty( $_GET['cl_vista'] ) || ! is_singular( 'alojamiento' ) || 'publish' === get_post_status() ) {
		return;
	}
	echo '<div style="position:sticky;top:0;z-index:99999;background:#fdf0d5;color:#5c3a00;padding:10px 16px;font:700 15px/1.4 system-ui,sans-serif;text-align:center">Vista previa: este alojamiento todavía no está publicado. <a href="' . esc_url( cl_aloj_url( get_the_ID(), 'vista' ) ) . '" style="color:#1f4645">← Volver al panel</a></div>';
} );

// ---------- Estilos comunes ----------

function cl_aloj_css() {
	static $hecho = false;
	if ( $hecho ) {
		return '';
	}
	$hecho = true;
	return '<style>
.cla{display:flex;flex-direction:column;gap:14px;font-family:"Source Sans 3",system-ui,sans-serif;color:#23302f}
.cla h1{margin:0;font-family:Montserrat,sans-serif;font-size:26px;color:#1f4645}
.cla-back{color:#1f4645;font-weight:700;text-decoration:none}
.cla-p{margin:0;font-size:15px;line-height:1.45;color:#3d4b49}
.cla-card{background:#fff;border-radius:16px;padding:12px;display:flex;gap:14px;align-items:center;box-shadow:0 1px 2px rgba(31,70,69,.08);text-decoration:none;color:inherit}
a.cla-card:hover{box-shadow:0 3px 10px rgba(31,70,69,.16)}
.cla-foto{width:84px;height:84px;border-radius:12px;background:#eeeae1 center/cover no-repeat;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;color:#7a8583}
.cla-info{flex-grow:1;min-width:0;display:flex;flex-direction:column;gap:6px}
.cla-t{font-family:Montserrat,sans-serif;font-weight:700;font-size:16px;color:#1f4645}
.cla-s{font-size:13px;color:#5b6a68}
.cla-go{font-size:20px;color:#9a6055;flex-shrink:0}
.cla-nuevo{background:#fbfaf6;border:2px dashed #9fb3ae;border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:10px}
.cla-nuevo label{font-weight:700}
.cla-nuevo input[type=text]{height:48px;border:1px solid #c9c2b3;border-radius:10px;padding:0 14px;font-size:16px;background:#fff;color:#23302f;width:100%;box-sizing:border-box}
.cla .cla-btn{min-height:50px;border:0;border-radius:12px;background:#1f4645;color:#fff;font-weight:700;font-size:16px;cursor:pointer;font-family:inherit;width:100%}
.cla-msg{border-radius:12px;padding:12px 14px;font-size:15px;line-height:1.45}
.cla-info-msg{background:#e3ece9;color:#1d3b3a}.cla-err{background:#fbe3df;color:#8a2a1f;font-weight:700}.cla-warn{background:#fdf0d5;color:#5c3a00}
.cla-nav{display:flex;gap:8px;flex-wrap:wrap}
.cla-nav a{min-height:40px;padding:0 12px;border-radius:20px;background:#f1ede4;color:#3d4b49;font-size:14px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center}
.cla-nav a.on{background:#9a6055;color:#fff;font-weight:700}
.cla-item{background:#fff;border-radius:16px;box-shadow:0 1px 2px rgba(31,70,69,.08)}
.cla-item .cla-card,.cla-item a.cla-card:hover{box-shadow:none;background:transparent}
.cla-item:hover{box-shadow:0 3px 10px rgba(31,70,69,.16)}
.cla-acc{display:flex;gap:8px;flex-wrap:wrap;padding:0 12px 12px 110px}
.cla-acc-f{margin:0}
.cla .cla-acc-b{min-height:36px;padding:0 14px;border:1px solid #c9c2b3;border-radius:18px;background:#fff;color:#1f4645;font:600 14px/1 "Source Sans 3",system-ui,sans-serif;cursor:pointer}
.cla .cla-acc-b:hover{background:#f1ede4}
.cla .cla-acc-b.cla-borrar{color:#8a2a1f}
.cla .cla-acc-b.cl-seguro{background:#fbe3df;border-color:#e2a59a;color:#8a2a1f;font-weight:700}
@media (max-width:480px){.cla-acc{padding-left:12px}}
.cla-help{border:1.5px dashed #b9c6c3;border-radius:16px;padding:16px;background:#fbfaf6;display:flex;flex-direction:column;gap:10px}
.cla-wa{min-height:48px;border-radius:12px;background:#1f4645;color:#fff!important;text-decoration:none;display:flex;align-items:center;justify-content:center;font-weight:700}
</style>';
}

// ---------- [cl_panel_alojamientos] — lista ----------

add_shortcode( 'cl_panel_alojamientos', function () {
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return '';
	}
	$emp      = cl_panel_emp_id();
	$es_admin = current_user_can( 'manage_options' );
	$panel    = home_url( '/panel/' ) . ( $es_admin && $emp ? '?emp=' . $emp : '' );
	ob_start();
	echo cl_aloj_css(); // phpcs:ignore
	echo '<div class="cla">';
	echo '<a class="cla-back" href="' . esc_url( $panel ) . '">← Mi panel</a>';
	echo '<h1>Mis alojamientos</h1>';
	if ( ! $emp ) {
		echo '<p class="cla-p">' . ( $es_admin ? 'Elegí un emprendimiento en <a href="' . esc_url( home_url( '/panel/' ) ) . '">Mi panel</a> (carga asistida).' : 'Todavía no tenés un emprendimiento cargado. Escribinos y lo creamos juntos.' ) . '</p></div>';
		return ob_get_clean();
	}
	$errores = array(
		'nombre' => 'Escribí el nombre del alojamiento.',
		'max'    => 'Llegaste al máximo de ' . CL_ALOJ_MAX . ' alojamientos. Si necesitás más, escribinos.',
		'muchos' => 'Creaste varios alojamientos hoy. Probá mañana o escribinos.',
		'crear'  => 'No se pudo crear el alojamiento. Probá de nuevo.',
	);
	if ( isset( $_GET['error'], $errores[ $_GET['error'] ] ) ) {
		echo '<div class="cla-msg cla-err">' . esc_html( $errores[ sanitize_key( $_GET['error'] ) ] ) . '</div>';
	}
	if ( ! empty( $_GET['oculto'] ) && cl_aloj_id( (int) $_GET['oculto'] ) ) {
		echo '<div class="cla-msg cla-info-msg">«' . esc_html( get_the_title( (int) $_GET['oculto'] ) ) . '» ya no se ve en la web. Podés volver a publicarlo cuando quieras.</div>';
	}
	if ( ! empty( $_GET['borrado'] ) ) {
		echo '<div class="cla-msg cla-info-msg">El alojamiento se borró. Si fue un error, escribinos y lo recuperamos.</div>';
	}
	if ( ! empty( $_GET['publicado'] ) && cl_aloj_id( (int) $_GET['publicado'] ) ) {
		echo '<div class="cla-msg cla-info-msg">«' . esc_html( get_the_title( (int) $_GET['publicado'] ) ) . '» se ve de nuevo en la web.</div>';
	}
	echo cl_panel_confirma_js(); // phpcs:ignore
	echo '<p class="cla-p">Cada alojamiento tiene su propia ficha y toma la ubicación, el mapa y el contacto de <strong>' . esc_html( get_the_title( $emp ) ) . '</strong>.</p>';

	$ids = cl_aloj_de_emp( $emp );
	foreach ( $ids as $a ) {
		$foto = get_the_post_thumbnail_url( $a, 'thumbnail' );
		list( $oblig ) = cl_aloj_chequeo( $a );
		$faltan = array();
		foreach ( $oblig as $o ) {
			if ( ! $o[1] ) {
				$faltan[] = strtolower( $o[0] );
			}
		}
		$nota = $faltan ? 'Falta: ' . implode( ', ', $faltan ) : ( 'publish' === get_post_status( $a ) || cl_aloj_oculto( $a ) ? '' : 'Listo para enviar' );
		echo '<div class="cla-item"><a class="cla-card" href="' . esc_url( cl_aloj_url( $a, 'basico' ) ) . '">';
		echo '<div class="cla-foto"' . ( $foto ? ' style="background-image:url(' . esc_url( $foto ) . ')"' : '' ) . '>' . ( $foto ? '' : 'Sin foto' ) . '</div>';
		echo '<div class="cla-info"><div class="cla-t">' . esc_html( get_the_title( $a ) ) . '</div>';
		echo '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">' . cl_aloj_chip_de( $a ) . ( $nota ? '<span class="cla-s">' . esc_html( $nota ) . '</span>' : '' ) . '</div></div>'; // phpcs:ignore
		echo '<span class="cla-go" aria-hidden="true">→</span></a>';
		echo cl_aloj_acciones_lista( $a ) . '</div>'; // phpcs:ignore
	}
	if ( ! $ids ) {
		echo '<p class="cla-p">Todavía no cargaste ningún alojamiento.</p>';
	}

	if ( count( $ids ) < CL_ALOJ_MAX ) {
		echo '<form class="cla-nuevo" method="post">';
		echo '<label for="cla-nombre">Agregar alojamiento</label>';
		echo '<input id="cla-nombre" type="text" name="nombre" maxlength="100" required placeholder="Nombre. Ej: Cabaña del Arroyo">';
		echo '<input type="hidden" name="cl_aloj_nuevo" value="1"><input type="hidden" name="emp" value="' . (int) $emp . '">';
		wp_nonce_field( 'cl_aloj_nuevo_' . $emp, '_clnonce', false );
		echo '<button type="submit" class="cla-btn">+ Crear y completar</button>';
		echo '<span class="cla-s">' . ( cl_sin_revision() ? 'Se crea como borrador: nadie lo ve hasta que lo publiques desde «Ver cómo queda».' : 'Se crea como borrador: nadie lo ve hasta que lo envíes a revisión y lo publiquemos.' ) . '</span>';
		echo '</form>';
	}
	echo '<div class="cla-help"><strong style="color:#1f4645">¿Necesitás una mano?</strong><span class="cla-p">Mandanos las fotos y los datos por WhatsApp y lo cargamos con vos.</span>';
	echo '<a class="cla-wa" href="' . esc_url( 'https://wa.me/59899247249?text=' . rawurlencode( 'Hola, necesito ayuda con mis alojamientos en Caminos Locales (' . get_the_title( $emp ) . ')' ) ) . '" target="_blank" rel="noopener">Escribinos por WhatsApp</a></div>';
	echo '</div>';
	return ob_get_clean();
} );

// Mi panel: la tarjeta "Mis alojamientos" (que decía "Próximamente") pasa a enlazar a la lista.
// Se muestra si el emprendimiento ofrece alojamiento o ya tiene alguno cargado.
add_filter( 'do_shortcode_tag', function ( $out, $tag ) {
	if ( 'cl_panel_inicio' !== $tag || false === strpos( $out, 'Mis alojamientos' ) ) {
		return $out;
	}
	$emp = function_exists( 'cl_panel_emp_id' ) ? cl_panel_emp_id() : 0;
	if ( ! $emp ) {
		return $out;
	}
	$ids = cl_aloj_de_emp( $emp );
	$pub = 0;
	foreach ( $ids as $a ) {
		$pub += 'publish' === get_post_status( $a ) ? 1 : 0;
	}
	if ( ! $ids && 'Sí' !== get_post_meta( $emp, 'ofrece_alojamiento', true ) ) {
		$card = '';
	} else {
		$txt  = $ids ? count( $ids ) . ' alojamiento' . ( count( $ids ) > 1 ? 's' : '' ) . ' · ' . $pub . ' publicado' . ( 1 !== $pub ? 's' : '' ) : 'Todavía no cargaste ninguno';
		$card = '<a class="clp-card" href="' . esc_url( cl_aloj_url_lista( $emp ) ) . '"><div class="clp-t">Mis alojamientos</div><div class="clp-s">' . esc_html( $txt ) . '</div><div class="clp-row"><span></span><span class="clp-go">' . ( $ids ? 'Ver y editar →' : 'Agregar →' ) . '</span></div></a>';
	}
	return preg_replace( '#<a class="clp-card" href="\#" aria-disabled="true">\s*<div class="clp-t">Mis alojamientos</div>.*?</a>#s', $card, $out, 1 );
}, 10, 2 );

// ---------- [cl_panel_aloj_cabecera] — arriba del editor ----------

add_shortcode( 'cl_panel_aloj_cabecera', function () {
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return '';
	}
	$id = cl_aloj_id();
	ob_start();
	echo cl_aloj_css(); // phpcs:ignore
	echo '<div class="cla">';
	if ( ! $id ) {
		echo '<a class="cla-back" href="' . esc_url( cl_aloj_url_lista( cl_panel_emp_id() ) ) . '">← Mis alojamientos</a>';
		echo '<div class="cla-msg cla-err">No encontramos este alojamiento. Volvé a Mis alojamientos y elegilo de la lista.</div></div>';
		return ob_get_clean();
	}
	$emp  = cl_aloj_emp_de( $id );
	$paso = isset( $_GET['paso'] ) ? sanitize_key( $_GET['paso'] ) : 'basico';
	$paso = '' === $paso ? 'basico' : $paso;
	echo '<a class="cla-back" href="' . esc_url( cl_aloj_url_lista( $emp ) ) . '">← Mis alojamientos</a>';
	echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><strong style="font-family:Montserrat,sans-serif;font-size:17px;color:#1f4645">' . esc_html( get_the_title( $id ) ) . '</strong>' . cl_aloj_chip_de( $id ) . '</div>'; // phpcs:ignore
	echo '<div class="cla-msg cla-info-msg" style="font-size:14px">Pertenece a <strong>' . esc_html( $emp ? get_the_title( $emp ) : '—' ) . '</strong>. Toma su ubicación, mapa y contacto: se cambian en <a href="' . esc_url( home_url( '/panel/emprendimiento/?paso=ubicacion' . ( current_user_can( 'manage_options' ) ? '&emp=' . $emp : '' ) ) ) . '" style="color:#1f4645;font-weight:700">Mi emprendimiento</a>.</div>';
	if ( ! empty( $_GET['copiado'] ) ) {
		echo '<div class="cla-msg" style="background:#dcebe4;color:#174a3a"><strong>Listo, es una copia. ✓</strong> Cambiale el nombre, revisá los datos y las fotos, y ' . ( cl_sin_revision() ? 'publicalo' : 'envialo a revisión' ) . ' desde «Ver cómo queda».</div>';
	}
	if ( cl_aloj_oculto( $id ) ) {
		echo '<div class="cla-msg" style="background:#f1ede4;color:#4a4335">Este alojamiento está <strong>oculto</strong>: no se ve en la web. Para mostrarlo de nuevo, entrá a «Ver cómo queda».</div>';
	}
	$pasos = array( 'basico' => 'Lo básico', 'como' => 'Cómo es', 'fotos' => 'Fotos', 'reservas' => 'Reservas', 'vista' => 'Ver cómo queda' );
	echo '<nav class="cla-nav" aria-label="Pasos">';
	foreach ( $pasos as $k => $t ) {
		echo '<a href="' . esc_url( cl_aloj_url( $id, $k ) ) . '"' . ( $k === $paso ? ' class="on" aria-current="step"' : '' ) . '>' . esc_html( $t ) . '</a>';
	}
	echo '</nav></div>';
	return ob_get_clean();
} );

// ---------- [cl_panel_aloj_vista] — ver cómo queda y enviar ----------

add_shortcode( 'cl_panel_aloj_vista', function () {
	$id = cl_aloj_id();
	if ( ! $id ) {
		return '';
	}
	$estado = get_post_status( $id );
	$emp    = cl_aloj_emp_de( $id );
	list( $oblig, $suger ) = cl_aloj_chequeo( $id );
	$faltan = array_filter( $oblig, function ( $o ) {
		return ! $o[1];
	} );
	$foto  = get_the_post_thumbnail_url( $id, 'medium_large' );
	$tipos = wp_get_object_terms( $id, 'tipo_alojamiento', array( 'fields' => 'names' ) );
	$cap   = (string) get_post_meta( $id, 'capacidad', true );
	$linea = array_filter( array( is_wp_error( $tipos ) ? '' : implode( ', ', $tipos ), '' !== $cap ? $cap . ' personas' : '' ) );
	$precio = trim( (string) get_post_meta( $id, 'precio', true ) );
	ob_start();
	?>
<style>
.clv{display:flex;flex-direction:column;gap:20px;font-family:'Source Sans 3',system-ui,sans-serif;color:#23302f}
.clv-card{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(31,70,69,.12)}
.clv-foto{aspect-ratio:5/4;background:#eeeae1 center/cover no-repeat;display:flex;align-items:center;justify-content:center;color:#7a8583;font-size:14px}
.clv-body{padding:16px;display:flex;flex-direction:column;gap:6px}
.clv-lugar{font-size:13px;font-weight:700;color:#9a6055;text-transform:uppercase;letter-spacing:.3px}
.clv-tit{font-family:Montserrat,sans-serif;font-weight:700;font-size:20px;color:#1f4645;margin:0}
.clv-link{display:flex;align-items:center;justify-content:center;min-height:48px;border-radius:12px;border:1px solid #1f4645;color:#1f4645;font-weight:700;text-decoration:none;background:#fff}
.clv-box{background:#fff;border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:4px}
.clv-h{font-family:Montserrat,sans-serif;font-weight:700;font-size:17px;color:#1f4645;margin:0 0 6px}
.clv-row{display:flex;align-items:center;gap:10px;min-height:40px;font-size:15px}
.clv-i{width:24px;height:24px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0}
.clv-ok{background:#dcebe4;color:#174a3a}.clv-no{background:#fbe3df;color:#8a2a1f}.clv-opc{background:#fdf0d5;color:#6b4300}
.clv-row a{margin-left:auto;color:#9a6055;font-weight:700;text-decoration:none;white-space:nowrap}
.clv-msg{border-radius:12px;padding:14px 16px;font-size:15px;line-height:1.45}
.clv-info{background:#e3ece9;color:#1f4645}.clv-warn{background:#fdf0d5;color:#5c3a00}.clv-err{background:#fbe3df;color:#8a2a1f;font-weight:700}
.clv .clv-btn{width:100%;min-height:52px;border:0;border-radius:12px;background:#9a6055;color:#fff;font-weight:700;font-size:17px;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;padding:0 16px!important;box-sizing:border-box}.clv .clv-btn:hover{background:#7f4c43}
.clv .clv-btn[disabled]{background:#c9c2b3;cursor:not-allowed}
.clv-opc-form{display:flex;flex-direction:column;gap:6px;padding:8px 0}
.clv-opc-form+.clv-opc-form{border-top:1px solid #ece7dc}
.clv .clv-btn2{min-height:46px;border-radius:12px;border:1px solid #1f4645;background:#fff;color:#1f4645;font-weight:700;font-size:16px;cursor:pointer;font-family:inherit;padding:0 16px!important}
.clv .clv-btn2:hover{background:#eef3f1}
.clv .clv-btn2.clv-borrar{border-color:#c98b80;color:#8a2a1f}
.clv .clv-btn2.cl-seguro{background:#fbe3df;border-color:#e2a59a;color:#8a2a1f}
.clv-nota{font-size:14px;line-height:1.4;color:#5b6a68}
</style>
<div class="clv">
	<?php if ( ! empty( $_GET['listo'] ) && 'publish' === $estado ) : ?>
		<div class="clv-msg clv-info"><strong>¡Listo, tu alojamiento ya está publicado! ✓</strong><br>Ya se ve en Caminos Locales. Podés seguir cambiándolo cuando quieras.</div>
	<?php elseif ( ! empty( $_GET['enviado'] ) ) : ?>
		<div class="clv-msg clv-info"><strong>¡Listo, lo enviaste! ✓</strong><br>Tu alojamiento está en revisión. Te avisamos por email cuando esté publicado, normalmente en 1 o 2 días.</div>
	<?php elseif ( ! empty( $_GET['publicado'] ) && 'publish' === $estado ) : ?>
		<div class="clv-msg clv-info"><strong>¡Listo! ✓</strong> Tu alojamiento se ve de nuevo en la web.</div>
	<?php elseif ( ! empty( $_GET['oculto'] ) && 'draft' === $estado ) : ?>
		<div class="clv-msg clv-info"><strong>Listo, lo ocultaste. ✓</strong> Ya no se ve en la web. Podés volver a publicarlo cuando quieras.</div>
	<?php elseif ( ! empty( $_GET['faltan'] ) ) : ?>
		<div class="clv-msg clv-err">Todavía falta completar lo marcado con ✕.</div>
	<?php endif; ?>
	<?php if ( $emp && 'publish' !== get_post_status( $emp ) ) : ?>
		<div class="clv-msg clv-warn">Tu emprendimiento todavía no está publicado. El alojamiento se ve con su ubicación y contacto cuando el emprendimiento esté publicado.</div>
	<?php endif; ?>
	<div>
		<div style="font-size:14px;font-weight:700;color:#5b6a68;margin-bottom:8px">Así se ve en el listado</div>
		<div class="clv-card">
			<div class="clv-foto"<?php echo $foto ? ' style="background-image:url(' . esc_url( $foto ) . ')"' : ''; ?>><?php echo $foto ? '' : 'Sin foto principal'; ?></div>
			<div class="clv-body">
				<?php if ( $linea ) : ?><div class="clv-lugar"><?php echo esc_html( implode( ' · ', $linea ) ); ?></div><?php endif; ?>
				<h2 class="clv-tit"><?php echo esc_html( get_the_title( $id ) ); ?></h2>
				<?php if ( $emp ) : ?><div style="font-size:15px;color:#3d4b49"><?php echo esc_html( get_the_title( $emp ) ); ?></div><?php endif; ?>
				<?php if ( $precio ) : ?><div style="font-size:15px;font-weight:700;color:#1f4645"><?php echo esc_html( $precio ); ?></div><?php endif; ?>
			</div>
		</div>
	</div>
	<a class="clv-link" href="<?php echo esc_url( cl_aloj_url_vista( $id ) ); ?>" target="_blank" rel="noopener">Ver la ficha completa ↗</a>

	<div class="clv-box">
		<h3 class="clv-h">Antes de publicar</h3>
		<?php foreach ( $oblig as $o ) : ?>
			<div class="clv-row"><span class="clv-i <?php echo $o[1] ? 'clv-ok' : 'clv-no'; ?>"><?php echo $o[1] ? '✓' : '✕'; ?></span><?php echo esc_html( $o[0] ); ?><?php if ( ! $o[1] ) : ?><a href="<?php echo esc_url( cl_aloj_url( $id, $o[2] ) ); ?>">Completar</a><?php endif; ?></div>
		<?php endforeach; ?>
		<?php foreach ( $suger as $o ) : ?>
			<?php if ( ! $o[1] ) : ?>
				<div class="clv-row"><span class="clv-i clv-opc">!</span><?php echo esc_html( $o[0] ); ?> <span style="color:#7a8583;font-size:14px">(opcional)</span><a href="<?php echo esc_url( cl_aloj_url( $id, $o[2] ) ); ?>">Agregar</a></div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<?php if ( cl_aloj_oculto( $id ) ) : ?>
		<div class="clv-msg clv-warn">Está oculto: no se ve en la web. <?php echo cl_sin_revision() ? 'Lo podés volver a publicar al momento.' : 'Como ya lo revisamos, lo podés volver a publicar al momento.'; ?></div>
		<form method="post">
			<input type="hidden" name="cl_aloj_republicar" value="1">
			<input type="hidden" name="aloj" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'cl_aloj_republicar_' . $id, '_clnonce', false ); ?>
			<button type="submit" class="clv-btn"<?php echo $faltan ? ' disabled' : ''; ?>>Volver a publicar</button>
		</form>
	<?php elseif ( 'draft' === $estado || ( 'pending' === $estado && cl_sin_revision() ) ) : ?>
		<?php if ( cl_sin_revision() ) : ?>
			<div class="clv-msg clv-warn">Cuando completes lo obligatorio, publicalo: se ve en la web al momento.</div>
		<?php else : ?>
			<div class="clv-msg clv-warn">Cuando lo envíes, lo revisamos y lo publicamos. Te avisamos por email, normalmente en 1 o 2 días.</div>
		<?php endif; ?>
		<form method="post">
			<input type="hidden" name="cl_aloj_enviar" value="1">
			<input type="hidden" name="aloj" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'cl_aloj_enviar_' . $id, '_clnonce', false ); ?>
			<button type="submit" class="clv-btn"<?php echo $faltan ? ' disabled' : ''; ?>><?php echo cl_sin_revision() ? 'Publicar' : 'Enviar a revisión'; ?></button>
		</form>
	<?php elseif ( 'pending' === $estado ) : ?>
		<?php if ( empty( $_GET['enviado'] ) ) : ?>
			<div class="clv-msg clv-info">Tu alojamiento está en revisión. Te avisamos por email cuando esté publicado. Mientras tanto podés seguir editándolo.</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="clv-msg clv-info">Tu alojamiento está publicado: lo que guardás se ve en la web al momento.</div>
	<?php endif; ?>
	<div class="clv-box">
		<h3 class="clv-h">Más opciones</h3>
		<?php if ( 'publish' === $estado ) : ?>
			<form method="post" class="clv-opc-form">
				<input type="hidden" name="cl_aloj_ocultar" value="1">
				<input type="hidden" name="aloj" value="<?php echo (int) $id; ?>">
				<?php wp_nonce_field( 'cl_aloj_ocultar_' . $id, '_clnonce', false ); ?>
				<button type="submit" class="clv-btn2">Ocultar de la web</button>
				<span class="clv-nota">Si lo cerrás por una temporada o está en arreglos. No se borra nada y lo podés volver a publicar.</span>
			</form>
		<?php endif; ?>
		<form method="post" class="clv-opc-form">
			<input type="hidden" name="cl_aloj_duplicar" value="1">
			<input type="hidden" name="aloj" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'cl_aloj_duplicar_' . $id, '_clnonce', false ); ?>
			<button type="submit" class="clv-btn2">Duplicar</button>
			<span class="clv-nota">Crea una copia en borrador con los mismos textos, características y fotos. Ideal si tenés varias cabañas o habitaciones parecidas.</span>
		</form>
		<form method="post" class="clv-opc-form">
			<input type="hidden" name="cl_aloj_borrar" value="1">
			<input type="hidden" name="aloj" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'cl_aloj_borrar_' . $id, '_clnonce', false ); ?>
			<button type="submit" class="clv-btn2 clv-borrar" data-confirma="¿Seguro? Tocá de nuevo para borrar">Borrar</button>
			<span class="clv-nota">Se borra de la web y de tu panel. Si fue un error, escribinos y lo recuperamos.</span>
		</form>
		<?php echo cl_panel_confirma_js(); // phpcs:ignore ?>
	</div>

	<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<a class="clv-link" href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>">Icono, revisar y publicar en wp-admin (solo admin)</a>
	<?php endif; ?>
</div>
	<?php
	return ob_get_clean();
} );

// ---------- Fotos del alojamiento ----------

function cl_aloj_fotos_slots() {
	$s    = array( 'principal' => array( 'meta' => '_thumbnail_id', 'label' => 'Foto principal' ) );
	$keys = array( 'field_670ffd22151f1', 'field_670ffd57151f2', 'field_670ffd6a151f3', 'field_670ffd76151f4', 'field_671915c58f280', 'field_671915db8f281', 'field_671915ec8f282', 'field_68d6970e7b83d', 'field_68d6976a28b19' );
	foreach ( $keys as $i => $k ) {
		$s[ 'g' . ( $i + 1 ) ] = array( 'meta' => 'galeria_de_alojamientos_foto' . ( $i + 1 ), 'key' => $k, 'label' => 'Foto ' . ( $i + 1 ) );
	}
	return $s;
}

function cl_aloj_foto_leer( $id, $slot ) {
	$s = cl_aloj_fotos_slots();
	$v = get_post_meta( $id, $s[ $slot ]['meta'], true );
	return ( is_numeric( $v ) && (int) $v > 0 && wp_attachment_is_image( (int) $v ) ) ? (int) $v : 0;
}

function cl_aloj_foto_guardar( $id, $slot, $att ) {
	if ( 'principal' === $slot ) {
		return $att ? set_post_thumbnail( $id, $att ) : delete_post_thumbnail( $id );
	}
	$s = cl_aloj_fotos_slots();
	update_post_meta( $id, $s[ $slot ]['meta'], $att ? (int) $att : '' );
	update_post_meta( $id, '_' . $s[ $slot ]['meta'], $s[ $slot ]['key'] );
	if ( ! get_post_meta( $id, '_galeria_de_alojamientos', true ) ) {
		update_post_meta( $id, 'galeria_de_alojamientos', '' );
		update_post_meta( $id, '_galeria_de_alojamientos', 'field_60f1b1a0c7af6' );
	}
	return true;
}

// Galería sin huecos: foto1, foto2… en orden (la ficha pública muestra la grilla en ese orden).
function cl_aloj_foto_compactar( $id ) {
	$fotos = cl_aloj_galeria( $id );
	for ( $i = 1; $i <= 9; $i++ ) {
		cl_aloj_foto_guardar( $id, 'g' . $i, isset( $fotos[ $i - 1 ] ) ? $fotos[ $i - 1 ] : 0 );
	}
}

add_action( 'wp_ajax_cl_foto_aloj', function () {
	$error = function ( $msg ) {
		wp_send_json( array( 'ok' => false, 'msg' => $msg ) );
	};
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() || ! function_exists( 'cl_fotos_quizas_borrar' ) ) {
		$error( 'Tu sesión se cerró. Volvé a entrar y probá de nuevo.' );
	}
	$id = cl_aloj_id( isset( $_POST['aloj'] ) ? (int) $_POST['aloj'] : 0 );
	if ( ! $id || ! check_ajax_referer( 'cl_fotos_aloj_' . $id, 'nonce', false ) ) {
		$error( 'No se pudo guardar. Recargá la página y probá de nuevo.' );
	}
	$slot  = isset( $_POST['slot'] ) ? sanitize_key( $_POST['slot'] ) : '';
	$slots = cl_aloj_fotos_slots();
	if ( ! isset( $slots[ $slot ] ) ) {
		$error( 'Lugar de foto desconocido.' );
	}
	$op    = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	$vieja = cl_aloj_foto_leer( $id, $slot );
	$emp   = cl_aloj_emp_de( $id );

	if ( 'quitar' === $op ) {
		if ( 'principal' === $slot && 'publish' === get_post_status( $id ) ) {
			$error( 'La foto principal no se puede quitar de un alojamiento publicado: tocá Cambiar para poner otra.' );
		}
		cl_aloj_foto_guardar( $id, $slot, 0 );
		if ( 'principal' !== $slot ) {
			cl_aloj_foto_compactar( $id );
		}
		if ( $vieja ) {
			cl_fotos_quizas_borrar( $vieja );
		}
		wp_send_json( array( 'ok' => true, 'id' => 0, 'url' => '', 'recargar' => 'principal' !== $slot ) );
	}

	if ( 'subir' !== $op || empty( $_FILES['foto'] ) ) {
		$error( 'No llegó ninguna foto.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// Carga asistida: carpeta del emprendimiento y nombre cl-xxxx (al emprendedor ya se lo hacen los otros snippets).
	$slug   = $emp ? sanitize_title( get_post_field( 'post_name', $emp ) ) : '';
	$slug   = $slug ? $slug : 'emp-' . (int) $emp;
	$dir_fn = function ( $d ) use ( $slug ) {
		$d['subdir'] = '/emprendimientos/' . $slug;
		$d['path']   = $d['basedir'] . $d['subdir'];
		$d['url']    = $d['baseurl'] . $d['subdir'];
		return $d;
	};
	$nombre_fn = function ( $file ) {
		if ( empty( $file['error'] ) && ! preg_match( '/^cl-[a-z0-9]{8}\./', $file['name'] ) ) {
			$ext          = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			$file['name'] = 'cl-' . strtolower( wp_generate_password( 8, false ) ) . '.' . ( $ext ? $ext : 'jpg' );
		}
		return $file;
	};
	$es_emp = function_exists( 'cl_es_emprendedor' ) && cl_es_emprendedor();
	if ( ! $es_emp ) {
		add_filter( 'upload_dir', $dir_fn, 20 );
		add_filter( 'wp_handle_upload_prefilter', $nombre_fn, 20 );
	}
	$texto = get_the_title( $id ) . ' — ' . $slots[ $slot ]['label'];
	$att   = media_handle_upload( 'foto', $id, array( 'post_title' => $texto ) );
	if ( ! $es_emp ) {
		remove_filter( 'upload_dir', $dir_fn, 20 );
		remove_filter( 'wp_handle_upload_prefilter', $nombre_fn, 20 );
	}
	if ( is_wp_error( $att ) ) {
		$error( $att->get_error_message() );
	}
	update_post_meta( $att, '_cl_panel_foto', 1 );
	update_post_meta( $att, '_wp_attachment_image_alt', $texto );
	if ( $emp && function_exists( 'cl_carpeta_asignar_foto' ) && function_exists( 'cl_carpeta_de_emprendimiento' ) ) {
		cl_carpeta_asignar_foto( $att, cl_carpeta_de_emprendimiento( $emp ) );
	}
	$dueno = (int) get_post_field( 'post_author', $id );
	if ( $dueno && $dueno !== (int) get_post_field( 'post_author', $att ) ) {
		wp_update_post( array( 'ID' => $att, 'post_author' => $dueno ) );
	}
	cl_aloj_foto_guardar( $id, $slot, $att );
	if ( 'principal' !== $slot ) {
		cl_aloj_foto_compactar( $id );
	}
	if ( $vieja && $vieja !== $att ) {
		cl_fotos_quizas_borrar( $vieja );
	}
	wp_send_json( array( 'ok' => true, 'id' => $att, 'url' => function_exists( 'cl_fotos_url' ) ? cl_fotos_url( $att ) : wp_get_attachment_url( $att ), 'recargar' => 'principal' !== $slot && ! $vieja ) );
} );

// [cl_panel_aloj_fotos] — principal + galería (las cargadas y una casilla libre para agregar).
add_shortcode( 'cl_panel_aloj_fotos', function () {
	$id = cl_aloj_id();
	if ( ! $id ) {
		return '';
	}
	$publicado = 'publish' === get_post_status( $id );
	$tarjeta   = function ( $slot, $label, $ayuda = '', $grande = false ) use ( $id, $publicado ) {
		$att = cl_aloj_foto_leer( $id, $slot );
		$url = $att ? ( function_exists( 'cl_fotos_url' ) ? cl_fotos_url( $att ) : wp_get_attachment_url( $att ) ) : '';
		$sin = ( 'principal' === $slot && $publicado ) ? ' data-sinquitar="1"' : '';
		$h   = '<div class="clf-slot' . ( $grande ? ' clf-grande' : '' ) . '" data-slot="' . esc_attr( $slot ) . '"' . $sin . '>';
		$h  .= '<div class="clf-lbl">' . esc_html( $label ) . '</div>';
		$h  .= $ayuda ? '<div class="clf-ayuda">' . esc_html( $ayuda ) . '</div>' : '';
		$h  .= '<div class="clf-img">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '<span class="clf-vacio">' . ( 'principal' === $slot ? 'Sin foto' : '+ Agregar foto' ) . '</span>' ) . '<div class="clf-estado" hidden></div></div>';
		$h  .= '<div class="clf-btns"><label class="clf-btn clf-subir"><input type="file" accept="image/*" hidden><span>' . ( $url ? 'Cambiar' : 'Subir foto' ) . '</span></label>';
		$h  .= '<button type="button" class="clf-btn clf-quitar"' . ( ( $url && ! $sin ) ? '' : ' hidden' ) . '>Quitar</button></div>';
		$h  .= '<div class="clf-error" hidden></div></div>';
		return $h;
	};
	$galeria = cl_aloj_galeria( $id );
	ob_start();
	?>
<style>
.clf{display:flex;flex-direction:column;gap:22px;font-family:'Source Sans 3',system-ui,sans-serif;color:#23302f}
.clf-sec{display:flex;flex-direction:column;gap:10px}
.clf-h{font-family:Montserrat,sans-serif;font-weight:700;font-size:18px;color:#1f4645;margin:0}
.clf-p{font-size:15px;line-height:1.45;color:#3d4b49;margin:0}
.clf-grilla{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
@media(min-width:560px){.clf-grilla{grid-template-columns:repeat(3,minmax(0,1fr))}}
.clf-slot{background:#fff;border-radius:14px;padding:12px;display:flex;flex-direction:column;gap:8px;box-shadow:0 1px 2px rgba(31,70,69,.08)}
.clf-lbl{font-weight:700;font-size:15px}
.clf-ayuda{font-size:14px;line-height:1.4;color:#5b6a68}
.clf-img{position:relative;aspect-ratio:4/3;border-radius:10px;overflow:hidden;background:#eeeae1;display:flex;align-items:center;justify-content:center}
.clf-img img{width:100%;height:100%;object-fit:cover;display:block}
.clf-vacio{font-size:14px;color:#7a8583}
.clf-estado{position:absolute;inset:auto 0 0 0;background:rgba(31,70,69,.88);color:#fff;font-size:14px;font-weight:700;padding:8px 10px;text-align:center}
.clf-btns{display:flex;gap:8px;flex-wrap:wrap}
.clf-btn{flex:1 1 auto;min-height:44px;border-radius:10px;border:1px solid #1f4645;background:#fff;color:#1f4645;font-weight:700;font-size:15px;display:inline-flex;align-items:center;justify-content:center;padding:0 12px;cursor:pointer;font-family:inherit}
.clf-subir{background:#1f4645;color:#fff}
.clf-quitar{border-color:#c9c2b3;color:#6b3a2c}
.clf-quitar.clf-seguro{background:#fbe3df;border-color:#8a2a1f;color:#8a2a1f}
.clf-error{background:#fbe3df;color:#8a2a1f;border-radius:10px;padding:8px 10px;font-size:14px;font-weight:700}
.clf-ocupado .clf-btn{opacity:.5;pointer-events:none}
.clf details{background:#fbfaf6;border:1px solid #e0dacd;border-radius:12px;padding:0 14px}
.clf summary{min-height:44px;display:flex;align-items:center;font-weight:700;color:#1f4645;cursor:pointer}
.clf details div{padding:0 0 14px;font-size:14px;line-height:1.45;color:#3d4b49}
</style>
<div class="clf" data-aloj="<?php echo (int) $id; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cl_fotos_aloj_' . $id ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
	<p class="clf-p">Subí las fotos tal como salen del celular: nosotros las achicamos. Cada foto se guarda sola, apenas termina de subir.</p>
	<details><summary>? Qué fotos funcionan mejor</summary><div><ul>
		<li><strong>Horizontales</strong> y con luz natural.</li>
		<li>Primero el ambiente principal y la vista; después baño, cocina y detalles (la estufa, la galería).</li>
		<li>Ordenado y con las camas hechas. Sin textos, marcos ni capturas de pantalla.</li>
		<li>Fotos propias o con permiso de quien las sacó.</li>
	</ul></div></details>
	<div class="clf-sec">
		<?php echo $tarjeta( 'principal', 'Foto principal', 'La portada del alojamiento y de su tarjeta. La que mejor lo muestre.', true ); // phpcs:ignore ?>
	</div>
	<div class="clf-sec">
		<h2 class="clf-h">Más fotos</h2>
		<p class="clf-p">Hasta 9. Se muestran en este orden.</p>
		<div class="clf-grilla">
			<?php
			$n = count( $galeria );
			for ( $i = 1; $i <= min( 9, $n + 1 ); $i++ ) {
				echo $tarjeta( 'g' . $i, $i <= $n ? 'Foto ' . $i : 'Nueva foto' ); // phpcs:ignore
			}
			?>
		</div>
	</div>
</div>
<script>
(function(){
var raiz=document.currentScript.previousElementSibling;if(!raiz||!raiz.classList.contains('clf'))return;
var AJAX=raiz.dataset.ajax,ALOJ=raiz.dataset.aloj,NONCE=raiz.dataset.nonce;
function achicar(file){
	return new Promise(function(ok){
		var tipo=(file.type||'').toLowerCase();
		if(!/^image\/(jpeg|png|webp)$/.test(tipo)||!window.createImageBitmap||file.size<=600*1024){ok(file);return;}
		createImageBitmap(file,{imageOrientation:'from-image'}).then(function(bmp){
			var max=1920,w=bmp.width,h=bmp.height,k=Math.min(1,max/Math.max(w,h));
			var c=document.createElement('canvas');c.width=Math.round(w*k);c.height=Math.round(h*k);
			c.getContext('2d').drawImage(bmp,0,0,c.width,c.height);
			c.toBlob(function(b){ok(b&&b.size<file.size?new File([b],'foto.jpg',{type:'image/jpeg'}):file);},'image/jpeg',0.85);
		}).catch(function(){ok(file);});
	});
}
function enviar(slot,datos,progreso){
	return new Promise(function(ok){
		var x=new XMLHttpRequest();x.open('POST',AJAX);
		if(progreso&&x.upload){x.upload.onprogress=function(e){if(e.lengthComputable)progreso(Math.round(e.loaded/e.total*100));};}
		x.onload=function(){var r;try{r=JSON.parse(x.responseText);}catch(e){r={ok:false,msg:'El servidor no respondió bien. Probá de nuevo.'};}ok(r);};
		x.onerror=function(){ok({ok:false,msg:'Se cortó la conexión. Probá de nuevo cuando tengas mejor señal.'});};
		datos.append('action','cl_foto_aloj');datos.append('aloj',ALOJ);datos.append('nonce',NONCE);datos.append('slot',slot);
		x.send(datos);
	});
}
raiz.querySelectorAll('.clf-slot').forEach(function(el){
	var slot=el.dataset.slot,input=el.querySelector('input[type=file]'),txt=el.querySelector('.clf-subir span'),
		quitar=el.querySelector('.clf-quitar'),caja=el.querySelector('.clf-img'),estado=el.querySelector('.clf-estado'),err=el.querySelector('.clf-error');
	function mostrar(url){
		var v=caja.querySelector('img,.clf-vacio');if(v)v.remove();
		var n;if(url){n=document.createElement('img');n.src=url;n.alt='';}else{n=document.createElement('span');n.className='clf-vacio';n.textContent='Sin foto';}
		caja.insertBefore(n,estado);txt.textContent=url?'Cambiar':'Subir foto';quitar.hidden=!url||el.dataset.sinquitar==='1';
	}
	function ocupado(s,m){el.classList.toggle('clf-ocupado',s);estado.hidden=!s;estado.textContent=m||'';}
	function falla(m){err.textContent=m;err.hidden=false;}
	function listo(r){ocupado(false);if(!r.ok){falla(r.msg);return;}if(r.recargar){ocupado(true,'Listo ✓');location.reload();return;}mostrar(r.url);}
	input.addEventListener('change',function(){
		var f=input.files&&input.files[0];input.value='';if(!f)return;err.hidden=true;
		ocupado(true,'Preparando…');
		achicar(f).then(function(archivo){
			var d=new FormData();d.append('op','subir');d.append('foto',archivo,archivo.name||'foto.jpg');
			return enviar(slot,d,function(p){estado.textContent=p<100?'Subiendo… '+p+' %':'Optimizando…';});
		}).then(listo);
	});
	var tQ;
	quitar.addEventListener('click',function(){
		if(!quitar.classList.contains('clf-seguro')){quitar.classList.add('clf-seguro');quitar.textContent='¿Seguro? Tocá de nuevo';clearTimeout(tQ);tQ=setTimeout(function(){quitar.classList.remove('clf-seguro');quitar.textContent='Quitar';},4000);return;}
		clearTimeout(tQ);quitar.classList.remove('clf-seguro');quitar.textContent='Quitar';err.hidden=true;ocupado(true,'Quitando…');
		var d=new FormData();d.append('op','quitar');
		enviar(slot,d).then(listo);
	});
});
})();
</script>
	<?php
	return ob_get_clean();
} );

// =====================================================================
// v6 (10/10/2026): tarjeta «Mis alojamientos» de Mi panel en Bricks (página 22812).
// =====================================================================

// {echo:cl_aloj_pi('dato')} — muestra ("1" si ofrece alojamiento o ya tiene alguno) · txt · accion · url.
function cl_aloj_pi( $dato ) {
	$emp = function_exists( 'cl_panel_emp_id' ) ? cl_panel_emp_id() : 0;
	if ( ! $emp ) {
		return '';
	}
	$ids = cl_aloj_de_emp( $emp );
	switch ( $dato ) {
		case 'muestra':
			return ( $ids || 'Sí' === get_post_meta( $emp, 'ofrece_alojamiento', true ) ) ? '1' : '';
		case 'url':
			return esc_url( cl_aloj_url_lista( $emp ) );
		case 'accion':
			return $ids ? 'Ver y editar →' : 'Agregar →';
		case 'txt':
			if ( ! $ids ) {
				return 'Todavía no cargaste ninguno';
			}
			$pub = 0;
			foreach ( $ids as $a ) {
				$pub += 'publish' === get_post_status( $a ) ? 1 : 0;
			}
			$n = count( $ids );
			return esc_html( $n . ' alojamiento' . ( 1 === $n ? '' : 's' ) . ' · ' . $pub . ' publicado' . ( 1 === $pub ? '' : 's' ) );
	}
	return '';
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_aloj_pi' ) );
} );