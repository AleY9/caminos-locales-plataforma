<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 15 «CL – Fichas: campos ACF y funciones para Bricks - v5» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — Fichas: funciones y validaciones de campos ACF (v5, sept. 2026)
 * Code Snippets: tipo PHP, "Ejecutar en todas partes". Sin <?php.
 *
 * Etiquetas para Bricks:
 *   [cl_mapa] / {echo:cl_mapa_embed}   → iframe de Google Maps seguro
 *   {echo:cl_mapa_url}                 → URL del mapa validada (alojamiento sin mapa → el del emprendimiento)
 *   {echo:cl_mapa_titulo}              → "Ubicación del alojamiento" / "Ubicación de <emprendimiento>"
 *   {echo:cl_como_llegar_url}          → ruta en Google Maps hasta el lugar
 *   {echo:cl_tel_wa}                   → 59899247249 (https://wa.me/)
 *   {echo:cl_tel_mostrar}              → +598 99 247 249
 *   {echo:cl_instagram_url}            → https://www.instagram.com/usuario/
 *   {echo:cl_capacidad_total}          → suma de capacidad de los alojamientos del emprendimiento
 *   {echo:cl_emprendimiento_id|nombre|url} → emprendimiento vinculado a un alojamiento
 *   {echo:cl_otros_alojamientos}       → cantidad de otros alojamientos del mismo emprendimiento
 *
 * Validan y limpian al guardar: ubicacion, ubicacion_alojamiento, telefono_contacto,
 * url_sitio_web, facebookemp, instagramemp.
 */

// Permite usar las funciones en {echo:...} de Bricks.
add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_mapa_embed', 'cl_mapa_url', 'cl_tel_wa', 'cl_tel_mostrar' ) );
} );

/* ================= MAPAS ================= */

// Acepta el iframe completo o la URL; devuelve solo una URL de embed de Google Maps válida, o ''.
function cl_mapa_extraer_url( $valor ) {
	$valor = html_entity_decode( trim( (string) $valor ), ENT_QUOTES );
	if ( preg_match( '/src\s*=\s*["\']([^"\']+)["\']/i', $valor, $m ) ) {
		$valor = trim( $m[1] );
	}
	$ok = preg_match( '#^https://www\.google\.com/maps/embed\?pb=[^\s"\'<>]+$#', $valor )                      // Compartir → Insertar un mapa
		|| preg_match( '#^https://(www|maps)\.google\.com/maps\?[^\s"\'<>]*output=embed[^\s"\'<>]*$#', $valor ); // formato ?q=...&output=embed
	return $ok ? $valor : '';
}

/*
 * 1.1.1: también acepta el enlace de «Compartir» de Google Maps (lo que da el celular:
 * https://maps.app.goo.gl/…, o un enlace https://www.google.com/maps/place/…).
 * Se convierte en un mapa ?q=lat,lng&output=embed, que cl_mapa_extraer_url() ya acepta.
 * Solo se usa al guardar (puede consultar a Google para abrir el enlace corto).
 */
function cl_mapa_normalizar( $valor ) {
	$url = cl_mapa_extraer_url( $valor );
	if ( $url ) {
		return $url;
	}
	$valor = html_entity_decode( trim( (string) $valor ), ENT_QUOTES );
	// Al compartir desde el celular suele venir «Nombre del lugar» + el enlace: se toma el enlace.
	if ( ! preg_match( '#https?://[^\s"\'<>]+#i', $valor, $m ) ) {
		return '';
	}
	$enlace = $m[0];
	if ( ! cl_mapa_host_ok( $enlace ) ) {
		return '';
	}
	$clave = 'cl_mapa_' . md5( $enlace );
	$hecho = get_transient( $clave );
	if ( false !== $hecho ) {
		return (string) $hecho;
	}
	// Enlace corto: se siguen las redirecciones (solo hacia Google) hasta la dirección larga.
	for ( $i = 0; $i < 5 && preg_match( '#^https?://(maps\.app\.goo\.gl|goo\.gl)/#i', $enlace ); $i++ ) {
		$r = wp_safe_remote_get( $enlace, array( 'redirection' => 0, 'timeout' => 8, 'user-agent' => 'Mozilla/5.0 (compatible; CaminosLocales/1.0)' ) );
		if ( is_wp_error( $r ) ) {
			return '';
		}
		$sig = wp_remote_retrieve_header( $r, 'location' );
		if ( is_array( $sig ) ) {
			$sig = end( $sig );
		}
		if ( ! $sig || ! cl_mapa_host_ok( $sig ) ) {
			break;
		}
		$enlace = $sig;
	}
	$embed = cl_mapa_embed_desde_enlace( rawurldecode( $enlace ) );
	set_transient( $clave, $embed, $embed ? MONTH_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
	return $embed;
}

function cl_mapa_host_ok( $url ) {
	$h = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	return in_array( $h, array( 'maps.app.goo.gl', 'goo.gl', 'maps.google.com', 'google.com', 'www.google.com', 'google.com.uy', 'www.google.com.uy' ), true );
}

// Dirección larga de Google Maps → URL de embed, o ''.
function cl_mapa_embed_desde_enlace( $enlace ) {
	$q = '';
	if ( preg_match( '/!3d(-?\d{1,3}\.\d+)!4d(-?\d{1,3}\.\d+)/', $enlace, $m ) ) {          // el pin del lugar
		$q = round( (float) $m[1], 6 ) . ',' . round( (float) $m[2], 6 );
	} elseif ( preg_match( '#[?&](?:q|query|ll|center|daddr)=(-?\d{1,3}\.\d+),\s*\+?(-?\d{1,3}\.\d+)#', $enlace, $m ) ) {
		$q = round( (float) $m[1], 6 ) . ',' . round( (float) $m[2], 6 );
	} elseif ( preg_match( '#/maps/search/(-?\d{1,3}\.\d+),\s*\+?(-?\d{1,3}\.\d+)#', $enlace, $m ) ) {
		$q = round( (float) $m[1], 6 ) . ',' . round( (float) $m[2], 6 );
	} elseif ( preg_match( '#@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)#', $enlace, $m ) ) {          // centro de la vista
		$q = round( (float) $m[1], 6 ) . ',' . round( (float) $m[2], 6 );
	} elseif ( preg_match( '#/maps/place/([^/@?]+)#', $enlace, $m ) ) {                      // solo el nombre
		$q = trim( str_replace( '+', ' ', $m[1] ) );
	} elseif ( preg_match( '#[?&]q=([^&]+)#', $enlace, $m ) ) {
		$q = trim( str_replace( '+', ' ', $m[1] ) );
	}
	if ( '' === $q || mb_strlen( $q ) > 200 ) {
		return '';
	}
	return 'https://maps.google.com/maps?q=' . rawurlencode( $q ) . '&z=15&output=embed';
}

function cl_mapa_campo( $post_id ) {
	return 'alojamiento' === get_post_type( $post_id ) ? 'ubicacion_alojamiento' : 'ubicacion';
}

// Mapa propio de la ficha (sin herencia).
function cl_mapa_url_propia( $post_id = 0 ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id ) {
		return '';
	}
	return cl_mapa_extraer_url( get_post_meta( $post_id, cl_mapa_campo( $post_id ), true ) );
}

// Mapa a mostrar: el propio o, en alojamientos sin mapa, el del emprendimiento.
function cl_mapa_url( $post_id = 0 ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	$url = cl_mapa_url_propia( $post_id );
	if ( ! $url && 'alojamiento' === get_post_type( $post_id ) ) {
		$emp = cl_emprendimiento_id( $post_id );
		$url = $emp ? cl_mapa_url_propia( $emp ) : '';
	}
	return $url;
}

// {echo:cl_mapa_titulo} → "Ubicación del alojamiento" o "Ubicación de <emprendimiento>".
function cl_mapa_titulo( $post_id = 0 ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! cl_mapa_url( $post_id ) ) {
		return '';
	}
	if ( 'alojamiento' === get_post_type( $post_id ) && ! cl_mapa_url_propia( $post_id ) ) {
		return 'Ubicación de ' . cl_emprendimiento_nombre( $post_id );
	}
	return 'alojamiento' === get_post_type( $post_id ) ? 'Ubicación del alojamiento' : 'Ubicación';
}

// {echo:cl_como_llegar_url} → enlace de Google Maps con la ruta hasta el lugar del mapa, o ''.
function cl_como_llegar_url( $post_id = 0 ) {
	$url = cl_mapa_url( $post_id );
	if ( ! $url ) {
		return '';
	}
	$destino = '';
	if ( preg_match( '/!2d(-?[0-9.]+)!3d(-?[0-9.]+)/', $url, $m ) ) {        // formato embed?pb=
		$destino = round( (float) $m[2], 6 ) . ',' . round( (float) $m[1], 6 );
	} elseif ( preg_match( '/[?&]q=([^&]+)/', $url, $m ) ) {                  // formato ?q=...&output=embed
		$destino = urldecode( str_replace( '+', ' ', $m[1] ) );
	}
	return $destino ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $destino ) : '';
}

function cl_mapa_embed( $post_id = 0 ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	$url = cl_mapa_url( $post_id );
	if ( ! $url ) {
		return '';
	}
	return sprintf(
		'<iframe src="%s" title="%s" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen style="border:0;width:100%%;height:100%%"></iframe>',
		esc_url( $url ),
		esc_attr( 'Mapa de ' . get_the_title( $post_id ) )
	);
}

// Shortcode para el elemento "Shortcode" de Bricks: [cl_mapa]
add_shortcode( 'cl_mapa', function () {
	return cl_mapa_embed();
} );

// Al guardar: valida y guarda solo la URL (si pegan el iframe entero, se extrae el src).
foreach ( array( 'ubicacion', 'ubicacion_alojamiento' ) as $cl_campo_mapa ) {
	add_filter( "acf/validate_value/name={$cl_campo_mapa}", function ( $valid, $value ) {
		if ( true !== $valid || '' === trim( (string) $value ) ) {
			return $valid;
		}
		return cl_mapa_normalizar( $value )
			? $valid
			: 'Pegá el enlace de Google Maps (Compartir → Copiar enlace) o el código de Compartir → Insertar un mapa.';
	}, 10, 2 );

	add_filter( "acf/update_value/name={$cl_campo_mapa}", function ( $value ) {
		return cl_mapa_normalizar( $value );
	} );
}

/* ================= TELÉFONOS ================= */

// Devuelve el teléfono en formato internacional solo con dígitos (59899247249), o ''.
function cl_tel_digitos( $post_id = 0 ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id ) {
		return '';
	}

	// Alojamiento → teléfono del emprendimiento vinculado.
	if ( 'alojamiento' === get_post_type( $post_id ) ) {
		$post_id = (int) cl_emprendimiento_id( $post_id );
		if ( ! $post_id ) {
			return '';
		}
	}

	$tel = preg_replace( '/\D+/', '', (string) get_post_meta( $post_id, 'telefono_contacto', true ) );
	if ( '' === $tel ) {
		return '';
	}
	if ( 0 === strpos( $tel, '00' ) ) {        // 00598...
		$tel = substr( $tel, 2 );
	}
	if ( 0 === strpos( $tel, '0' ) ) {         // 099 247 249 → 59899247249
		$tel = '598' . substr( $tel, 1 );
	} elseif ( 8 === strlen( $tel ) ) {       // 99247249 o fijo 4xxx xxxx → 598...
		$tel = '598' . $tel;
	}

	return ( strlen( $tel ) >= 10 && strlen( $tel ) <= 15 ) ? $tel : '';
}

function cl_tel_wa( $post_id = 0 ) {
	return cl_tel_digitos( $post_id );
}

function cl_tel_mostrar( $post_id = 0 ) {
	$tel = cl_tel_digitos( $post_id );
	if ( ! $tel ) {
		return '';
	}
	if ( 0 === strpos( $tel, '598' ) && 11 === strlen( $tel ) ) {
		$n = substr( $tel, 3 );
		return '9' === $n[0]
			? '+598 ' . substr( $n, 0, 2 ) . ' ' . substr( $n, 2, 3 ) . ' ' . substr( $n, 5 )  // celular: +598 99 247 249
			: '+598 ' . substr( $n, 0, 4 ) . ' ' . substr( $n, 4 );                              // fijo: +598 4444 5555
	}
	return '+' . $tel;
}

// Al guardar: el teléfono tiene que tener al menos 8 dígitos (acepta +, espacios y guiones).
add_filter( 'acf/validate_value/name=telefono_contacto', function ( $valid, $value ) {
	if ( true !== $valid || '' === trim( (string) $value ) ) {
		return $valid;
	}
	return strlen( preg_replace( '/\D+/', '', (string) $value ) ) >= 8
		? $valid
		: 'Escribí el teléfono completo, por ejemplo +598 99 123 456 o 099 123 456.';
}, 10, 2 );

/* ================= URLs (sitio web y Facebook) ================= */

// Acepta "caminoslocales.com", "www.caminoslocales.com" o "https://..."; devuelve URL https válida, o false.
function cl_normalizar_url( $valor ) {
	$valor = trim( (string) $valor );
	if ( '' === $valor ) {
		return '';
	}
	if ( preg_match( '/[\s"\'<>`]/', $valor ) ) {
		return false;
	}
	if ( ! preg_match( '#^https?://#i', $valor ) ) {
		$valor = 'https://' . ltrim( $valor, '/' );
	}
	$valor = filter_var( $valor, FILTER_VALIDATE_URL );
	if ( ! $valor ) {
		return false;
	}
	$host = (string) wp_parse_url( $valor, PHP_URL_HOST );
	if ( false === strpos( $host, '.' ) || preg_match( '/[^a-z0-9.\-]/i', $host ) ) {
		return false;
	}
	return $valor;
}

foreach ( array( 'url_sitio_web', 'facebookemp' ) as $cl_campo_url ) {
	add_filter( "acf/validate_value/name={$cl_campo_url}", function ( $valid, $value ) {
		if ( true !== $valid || '' === trim( (string) $value ) ) {
			return $valid;
		}
		return false !== cl_normalizar_url( $value )
			? $valid
			: 'Escribí una dirección web válida, por ejemplo miemprendimiento.com';
	}, 10, 2 );

	add_filter( "acf/update_value/name={$cl_campo_url}", function ( $value ) {
		$url = cl_normalizar_url( $value );
		return false === $url ? '' : $url;
	} );
}

/* ================= INSTAGRAM ================= */

// Acepta "@usuario", "usuario" o el enlace de Instagram; devuelve "usuario", o ''.
function cl_instagram_usuario( $valor ) {
	$valor = trim( (string) $valor );
	if ( preg_match( '#instagram\.com/([A-Za-z0-9._]{1,30})#i', $valor, $m ) ) {
		$valor = $m[1];
	}
	$valor = ltrim( $valor, '@' );
	return preg_match( '/^[A-Za-z0-9._]{1,30}$/', $valor ) ? $valor : '';
}

// {echo:cl_instagram_url} → https://www.instagram.com/usuario/
function cl_instagram_url( $post_id = 0 ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	$usuario = cl_instagram_usuario( get_post_meta( $post_id, 'instagramemp', true ) );
	return $usuario ? 'https://www.instagram.com/' . $usuario . '/' : '';
}

add_filter( 'acf/validate_value/name=instagramemp', function ( $valid, $value ) {
	if ( true !== $valid || '' === trim( (string) $value ) ) {
		return $valid;
	}
	return cl_instagram_usuario( $value ) ? $valid : 'Escribí tu usuario de Instagram, por ejemplo @miemprendimiento';
}, 10, 2 );

add_filter( 'acf/update_value/name=instagramemp', function ( $value ) {
	$usuario = cl_instagram_usuario( $value );
	return $usuario ? '@' . $usuario : '';
} );

/* ================= CAPACIDAD TOTAL ================= */

// {echo:cl_capacidad_total} → suma de "capacidad" de los alojamientos publicados del emprendimiento (o '').
function cl_capacidad_total( $post_id = 0 ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id ) {
		return '';
	}
	$ids = get_posts( array(
		'post_type'      => 'alojamiento',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array(
				'key'     => 'emprendimiento_correspondiente',
				'value'   => '"' . $post_id . '"', // ID exacto dentro del array guardado
				'compare' => 'LIKE',
			),
		),
	) );
	$total = 0;
	foreach ( $ids as $id ) {
		$total += absint( get_post_meta( $id, 'capacidad', true ) );
	}
	return $total > 0 ? (string) $total : '';
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array(
		'cl_instagram_url', 'cl_capacidad_total',
		'cl_emprendimiento_id', 'cl_emprendimiento_nombre', 'cl_emprendimiento_url', 'cl_otros_alojamientos',
		'cl_mapa_titulo', 'cl_como_llegar_url',
	) );
} );

/* ================= ALOJAMIENTO → EMPRENDIMIENTO ================= */

// {echo:cl_emprendimiento_id} → ID del emprendimiento vinculado al alojamiento (solo si está publicado), o ''.
function cl_emprendimiento_id( $post_id = 0 ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( ! $post_id || 'alojamiento' !== get_post_type( $post_id ) ) {
		return '';
	}
	$rel   = get_post_meta( $post_id, 'emprendimiento_correspondiente', true );
	$first = is_array( $rel ) ? reset( $rel ) : $rel;
	$id    = absint( is_object( $first ) ? $first->ID : $first );
	return ( $id && 'emprendimientos' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) ? (string) $id : '';
}

// {echo:cl_emprendimiento_nombre} → nombre del emprendimiento vinculado.
function cl_emprendimiento_nombre( $post_id = 0 ) {
	$id = cl_emprendimiento_id( $post_id );
	return $id ? get_the_title( $id ) : '';
}

// {echo:cl_emprendimiento_url} → enlace a la ficha del emprendimiento vinculado.
function cl_emprendimiento_url( $post_id = 0 ) {
	$id = cl_emprendimiento_id( $post_id );
	if ( $id && function_exists( 'cl_emp_sin_pagina' ) && cl_emp_sin_pagina( $id ) ) {
		return ''; // 1.1.13: sin página propia.
	}
	return $id ? (string) get_permalink( $id ) : '';
}

// {echo:cl_otros_alojamientos} → cantidad de OTROS alojamientos publicados del mismo emprendimiento (o '').
function cl_otros_alojamientos( $post_id = 0 ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	$emp = cl_emprendimiento_id( $post_id );
	if ( ! $emp ) {
		return '';
	}
	$ids = get_posts( array(
		'post_type'      => 'alojamiento',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'post__not_in'   => array( $post_id ),
		'meta_query'     => array(
			array(
				'key'     => 'emprendimiento_correspondiente',
				'value'   => '"' . $emp . '"',
				'compare' => 'LIKE',
			),
		),
	) );
	return count( $ids ) ? (string) count( $ids ) : '';
}