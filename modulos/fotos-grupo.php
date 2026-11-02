<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 29 «CL - Fotos Grupo» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;


/**
 * Caminos Locales — {cl_fotos:grupo}
 * Devuelve las fotos cargadas en un campo "group" de ACF (foto1, foto2…)
 * para usarlas en el elemento Image Gallery de Bricks.
 * En texto o condiciones devuelve la cantidad de fotos.
 */

function cl_fotos_ids( $post_id, $grupo ) {
	$valores = get_field( $grupo, $post_id );
	if ( ! is_array( $valores ) ) {
		return [];
	}
	$ids = [];
	foreach ( $valores as $foto ) {
		if ( empty( $foto ) ) {
			continue;
		}
		if ( is_array( $foto ) && ! empty( $foto['ID'] ) ) {
			$ids[] = (int) $foto['ID'];
		} elseif ( is_numeric( $foto ) ) {
			$ids[] = (int) $foto;
		} elseif ( is_string( $foto ) ) {
			$id = attachment_url_to_postid( $foto );
			if ( $id ) {
				$ids[] = $id;
			}
		}
	}
	return $ids;
}

add_filter( 'bricks/dynamic_tags_list', function ( $tags ) {
	$tags[] = [
		'name'  => '{cl_fotos}',
		'label' => 'Fotos de un conjunto ACF (cl_fotos:grupo)',
		'group' => 'Caminos Locales',
	];
	return $tags;
} );

add_filter( 'bricks/dynamic_data/render_tag', function ( $tag, $post, $context = 'text' ) {
	if ( ! is_string( $tag ) ) {
		return $tag;
	}
	$limpio = trim( $tag, '{}' );
	if ( strpos( $limpio, 'cl_fotos:' ) !== 0 ) {
		return $tag;
	}
	$grupo   = sanitize_key( substr( $limpio, strlen( 'cl_fotos:' ) ) );
	$post_id = ( $post && isset( $post->ID ) ) ? $post->ID : get_the_ID();
	$ids     = cl_fotos_ids( $post_id, $grupo );

	return ( $context === 'image' ) ? $ids : (string) count( $ids );
}, 20, 3 );

function cl_fotos_en_texto( $content, $post, $context = 'text' ) {
	if ( ! is_string( $content ) || strpos( $content, '{cl_fotos:' ) === false ) {
		return $content;
	}
	$post_id = ( $post && isset( $post->ID ) ) ? $post->ID : get_the_ID();
	return preg_replace_callback( '/\{cl_fotos:([a-z0-9_\-]+)\}/', function ( $m ) use ( $post_id ) {
		return (string) count( cl_fotos_ids( $post_id, sanitize_key( $m[1] ) ) );
	}, $content );
}
add_filter( 'bricks/dynamic_data/render_content', 'cl_fotos_en_texto', 20, 3 );
add_filter( 'bricks/frontend/render_data', 'cl_fotos_en_texto', 20, 2 );