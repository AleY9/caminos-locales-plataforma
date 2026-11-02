<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 13 «Capacidad MCP para escribir campos ACF» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — capacidad MCP para escribir campos ACF en fichas.
 * Pegar en Code Snippets (tipo PHP, "Ejecutar en todas partes"). Sin la etiqueta <?php.
 *
 * v2 (24/09/2026): suma contenido principal, extracto y SEO (SiteSEO).
 *
 * Registra: caminoslocales/update-acf-fields
 * Tipos permitidos: emprendimientos, alojamiento, evento.
 * Valida cada campo contra los grupos ACF reales de ese tipo de contenido,
 * así sigue funcionando cuando se agregan o cambian campos.
 */

add_action( 'wp_abilities_api_categories_init', function () {
	wp_register_ability_category( 'caminoslocales', array(
		'label'       => 'Caminos Locales',
		'description' => 'Carga y edición de fichas de la plataforma.',
	) );
} );

add_action( 'wp_abilities_api_init', function () {
	wp_register_ability( 'caminoslocales/update-acf-fields', array(
		'label'       => 'Actualizar campos ACF de una ficha',
		'description' => 'Escribe valores de campos ACF en una entrada de emprendimientos, alojamiento o evento. '
			. 'fields: objeto { nombre_de_campo: valor }. Imagen: ID de adjunto. Grupo: objeto con nombres de subcampo. '
			. 'Relación: array de IDs. Radio/select: texto exacto de la opción. Checkbox: array de opciones. '
			. 'featured_image: ID de adjunto para la imagen destacada. post_content: HTML del bloque de contenido. '
			. 'post_excerpt: extracto. seo: { title, desc, keywords, og_title, og_desc, og_img } para SiteSEO. '
			. 'Devuelve campos guardados, sin cambios y errores.',
		'category'    => 'caminoslocales',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id'        => array( 'type' => 'integer', 'description' => 'ID de la entrada.' ),
				'fields'         => array( 'type' => 'object', 'description' => 'nombre_de_campo => valor' ),
				'featured_image' => array( 'type' => 'integer', 'description' => 'ID de adjunto (opcional).' ),
				'post_content'   => array( 'type' => 'string', 'description' => 'HTML del contenido principal (opcional).' ),
				'post_excerpt'   => array( 'type' => 'string', 'description' => 'Extracto (opcional).' ),
				'seo'            => array(
					'type'        => 'object',
					'description' => 'SiteSEO (opcional): title, desc, keywords, og_title, og_desc, og_img (URL).',
				),
			),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		),
		'output_schema' => array( 'type' => 'object' ),
		// Solo usuarios que pueden editar ESA entrada (admin/editor, o el autor de la ficha).
		'permission_callback' => function ( $input ) {
			$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
			return $id > 0 && current_user_can( 'edit_post', $id );
		},
		'execute_callback' => 'cl_ability_update_acf_fields',
		'meta' => array(
			'show_in_rest' => true,
			'annotations'  => array( 'destructive' => false, 'idempotent' => true ),
			'mcp'          => array( 'public' => true ),
		),
	) );
} );

function cl_ability_update_acf_fields( $input ) {
	$allowed = array( 'emprendimientos', 'alojamiento', 'evento' );
	$post_id = (int) $input['post_id'];
	$post    = get_post( $post_id );

	if ( ! $post || ! in_array( $post->post_type, $allowed, true ) ) {
		return new WP_Error( 'cl_invalid_post', 'La entrada no existe o su tipo no está permitido.' );
	}
	if ( ! function_exists( 'acf_get_field_groups' ) ) {
		return new WP_Error( 'cl_no_acf', 'ACF no está activo.' );
	}

	// Campos válidos para este tipo de contenido, leídos de ACF en el momento.
	$valid = array();
	foreach ( acf_get_field_groups( array( 'post_type' => $post->post_type ) ) as $group ) {
		foreach ( (array) acf_get_fields( $group['key'] ) as $field ) {
			$valid[ $field['name'] ] = $field;
		}
	}

	$saved = array(); $unchanged = array(); $errors = array();

	foreach ( (array) ( $input['fields'] ?? array() ) as $name => $value ) {
		if ( ! isset( $valid[ $name ] ) ) {
			$errors[ $name ] = 'No existe en ' . $post->post_type;
			continue;
		}
		$field = $valid[ $name ];
		$check = cl_validate_acf_value( $field, $value );
		if ( true !== $check ) {
			$errors[ $name ] = $check;
			continue;
		}
		if ( update_field( $field['key'], $value, $post_id ) ) {
			$saved[] = $name;
		} else {
			$unchanged[] = $name; // ACF devuelve false si el valor ya era el mismo.
		}
	}

	if ( ! empty( $input['featured_image'] ) ) {
		$img = (int) $input['featured_image'];
		if ( wp_attachment_is_image( $img ) ) {
			set_post_thumbnail( $post_id, $img );
			$saved[] = 'featured_image';
		} else {
			$errors['featured_image'] = 'El ID no es una imagen.';
		}
	}

	// Contenido principal y extracto.
	$post_update = array();
	if ( isset( $input['post_content'] ) ) {
		$post_update['post_content'] = wp_kses_post( $input['post_content'] );
	}
	if ( isset( $input['post_excerpt'] ) ) {
		$post_update['post_excerpt'] = sanitize_textarea_field( $input['post_excerpt'] );
	}
	if ( $post_update ) {
		$post_update['ID'] = $post_id;
		$result = wp_update_post( wp_slash( $post_update ), true );
		if ( is_wp_error( $result ) ) {
			$errors['post'] = $result->get_error_message();
		} else {
			$saved = array_merge( $saved, array_keys( array_diff_key( $post_update, array( 'ID' => 1 ) ) ) );
		}
	}

	// SEO de SiteSEO: solo estas claves.
	$seo_map = array(
		'title'    => '_siteseo_titles_title',
		'desc'     => '_siteseo_titles_desc',
		'keywords' => '_siteseo_analysis_target_kw',
		'og_title' => '_siteseo_social_fb_title',
		'og_desc'  => '_siteseo_social_fb_desc',
		'og_img'   => '_siteseo_social_fb_img',
	);
	foreach ( (array) ( $input['seo'] ?? array() ) as $key => $value ) {
		if ( ! isset( $seo_map[ $key ] ) ) {
			$errors[ 'seo.' . $key ] = 'Clave SEO no permitida.';
			continue;
		}
		$value = ( 'og_img' === $key ) ? esc_url_raw( $value ) : sanitize_text_field( $value );
		update_post_meta( $post_id, $seo_map[ $key ], $value );
		$saved[] = 'seo.' . $key;
	}

	return array(
		'post_id'   => $post_id,
		'post_type' => $post->post_type,
		'saved'     => $saved,
		'unchanged' => $unchanged,
		'errors'    => (object) $errors,
		'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
	);
}

/** Devuelve true si el valor es válido, o un texto con el problema. */
function cl_validate_acf_value( $field, $value ) {
	if ( '' === $value || null === $value ) {
		return true; // Permite vaciar un campo.
	}
	switch ( $field['type'] ) {
		case 'image':
			return wp_attachment_is_image( (int) $value ) ? true : 'El ID ' . $value . ' no es una imagen.';

		case 'group':
			if ( ! is_array( $value ) ) {
				return 'Debe ser un objeto con los subcampos.';
			}
			$subs = array();
			foreach ( (array) $field['sub_fields'] as $sub ) {
				$subs[ $sub['name'] ] = $sub;
			}
			foreach ( $value as $sub_name => $sub_value ) {
				if ( ! isset( $subs[ $sub_name ] ) ) {
					return 'Subcampo inexistente: ' . $sub_name;
				}
				$sub_check = cl_validate_acf_value( $subs[ $sub_name ], $sub_value );
				if ( true !== $sub_check ) {
					return $sub_name . ': ' . $sub_check;
				}
			}
			return true;

		case 'radio':
		case 'select':
			if ( ! empty( $field['other_choice'] ) ) {
				return true;
			}
			return array_key_exists( $value, (array) $field['choices'] ) ? true : 'Opción no válida: ' . $value;

		case 'checkbox':
			if ( ! empty( $field['allow_custom'] ) ) {
				return true;
			}
			foreach ( (array) $value as $v ) {
				if ( ! array_key_exists( $v, (array) $field['choices'] ) ) {
					return 'Opción no válida: ' . $v;
				}
			}
			return true;

		case 'relationship':
			foreach ( (array) $value as $rel_id ) {
				$rel = get_post( (int) $rel_id );
				if ( ! $rel || ( ! empty( $field['post_type'] ) && ! in_array( $rel->post_type, (array) $field['post_type'], true ) ) ) {
					return 'ID de relación no válido: ' . $rel_id;
				}
			}
			return true;

		default:
			if ( is_string( $value ) && ! empty( $field['maxlength'] ) && mb_strlen( $value ) > (int) $field['maxlength'] ) {
				return 'Supera ' . $field['maxlength'] . ' caracteres.';
			}
			return true;
	}
}