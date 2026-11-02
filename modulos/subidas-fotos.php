<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 18 «CL – Subidas de fotos optimizadas» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — Subidas de fotos optimizadas (fase 2, v1, sept. 2026)
 * Code Snippets: tipo PHP, "Ejecutar en todas partes". Sin <?php.
 *
 * Para todas las subidas de imágenes (panel del sitio y wp-admin):
 *   - La foto se achica a 1920 px como máximo (lado mayor), calidad 82, y se guarda así: no queda el original pesado.
 *   - WordPress no crea la copia "-scaled" (umbral a 1920).
 *   - Converter for Media sigue generando el WebP como hasta ahora.
 * Solo para Emprendedores:
 *   - Solo imágenes JPG, PNG, WebP, HEIC/HEIF (se comprueba el tipo real del archivo, no solo la extensión).
 *   - Hasta 25 MB por archivo al subir (después queda en unos pocos cientos de KB).
 *   - Hasta 60 fotos por día por cuenta.
 *   - El archivo se renombra (cl-xxxxxxxx.jpg) para no exponer nombres del teléfono.
 */

define( 'CL_FOTO_MAX_PX', 1920 );
define( 'CL_FOTO_CALIDAD', 82 );
define( 'CL_FOTO_MAX_MB', 25 );
define( 'CL_FOTOS_POR_DIA', 60 );

// Sin copia "-scaled": el tope ya lo pone este snippet.
add_filter( 'big_image_size_threshold', function () {
	return CL_FOTO_MAX_PX;
} );

// Calidad de las miniaturas que genera WordPress.
add_filter( 'wp_editor_set_quality', function () {
	return CL_FOTO_CALIDAD;
} );

// 1) Antes de subir: controles para Emprendedores.
add_filter( 'wp_handle_upload_prefilter', function ( $file ) {
	if ( ! function_exists( 'cl_es_emprendedor' ) || ! cl_es_emprendedor() ) {
		return $file;
	}

	$permitidos = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
		'heic'         => 'image/heic',
		'heif'         => 'image/heif',
	);
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $permitidos );
	if ( empty( $check['type'] ) || 0 !== strpos( $check['type'], 'image/' ) ) {
		$file['error'] = 'Solo se pueden subir fotos (JPG, PNG, WebP o HEIC).';
		return $file;
	}

	if ( $file['size'] > CL_FOTO_MAX_MB * MB_IN_BYTES ) {
		$file['error'] = 'La foto pesa más de ' . CL_FOTO_MAX_MB . ' MB. Probá con otra o escribinos y te ayudamos.';
		return $file;
	}

	$clave = 'cl_fotos_' . get_current_user_id() . '_' . gmdate( 'Ymd' );
	$hoy   = (int) get_transient( $clave );
	if ( $hoy >= CL_FOTOS_POR_DIA ) {
		$file['error'] = 'Llegaste al límite de ' . CL_FOTOS_POR_DIA . ' fotos por día. Podés seguir mañana.';
		return $file;
	}
	set_transient( $clave, $hoy + 1, DAY_IN_SECONDS );

	$ext          = $check['ext'] ? $check['ext'] : 'jpg';
	$file['name'] = 'cl-' . strtolower( wp_generate_password( 8, false ) ) . '.' . $ext;
	return $file;
} );

// 2) Después de subir: achicar la foto y guardarla en su lugar.
add_filter( 'wp_handle_upload', function ( $upload ) {
	if ( empty( $upload['file'] ) || empty( $upload['type'] ) || 0 !== strpos( $upload['type'], 'image/' ) ) {
		return $upload;
	}
	if ( in_array( $upload['type'], array( 'image/gif', 'image/svg+xml' ), true ) ) {
		return $upload; // GIF animados y SVG no se tocan.
	}

	$editor = wp_get_image_editor( $upload['file'] );
	if ( is_wp_error( $editor ) ) {
		// El servidor no puede procesar este formato (ej. HEIC sin soporte): se rechaza para Emprendedores.
		if ( function_exists( 'cl_es_emprendedor' ) && cl_es_emprendedor() ) {
			wp_delete_file( $upload['file'] );
			return array( 'error' => 'No pudimos procesar esa foto. Probá con otra en formato JPG.' );
		}
		return $upload;
	}

	$size = $editor->get_size();
	if ( max( $size['width'], $size['height'] ) <= CL_FOTO_MAX_PX && filesize( $upload['file'] ) < 600 * KB_IN_BYTES ) {
		return $upload; // Ya es liviana: se deja como está.
	}

	$editor->set_quality( CL_FOTO_CALIDAD );
	$editor->maybe_exif_rotate(); // Las fotos de celular quedan derechas.
	$editor->resize( CL_FOTO_MAX_PX, CL_FOTO_MAX_PX, false );

	// HEIC → JPG (los navegadores no muestran HEIC).
	$mime  = in_array( $upload['type'], array( 'image/heic', 'image/heif' ), true ) ? 'image/jpeg' : $upload['type'];
	$dest  = $upload['file'];
	if ( 'image/jpeg' === $mime && 'image/jpeg' !== $upload['type'] ) {
		$dest = preg_replace( '/\.(heic|heif)$/i', '.jpg', $dest );
	}

	$saved = $editor->save( $dest, $mime );
	if ( is_wp_error( $saved ) ) {
		return $upload;
	}
	if ( $dest !== $upload['file'] ) {
		wp_delete_file( $upload['file'] );
		$upload['url'] = str_replace( wp_basename( $upload['file'] ), wp_basename( $dest ), $upload['url'] );
	}
	$upload['file'] = $dest;
	$upload['type'] = $mime;
	return $upload;
} );