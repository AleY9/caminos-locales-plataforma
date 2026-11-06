<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 28 «CL – Capacidad: términos» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — Capacidad MCP para taxonomías (v3, oct. 2026)
 * Code Snippets: tipo PHP, "Ejecutar en todas partes". Sin <?php.
 *
 * Registra la capacidad caminoslocales/update-terms, para poder poner, agregar o quitar
 * términos de las fichas por MCP, sin entrar a wp-admin.
 *
 * v2: suma la taxonomía equipamiento (emprendimientos) y saca publicacion (desactivada).
 * v3: registra con el hook de WordPress 6.9+ (wp_abilities_api_init) y con categoría propia.
 *
 * Límites puestos a propósito:
 * - Solo los tipos de ficha del sitio: emprendimientos, alojamiento, evento.
 * - Solo estas taxonomías: region, tipo_alojamiento, caracteristicas, equipamiento, category.
 * - Solo términos que YA existen: nunca crea términos nuevos.
 * - El usuario tiene que poder editar esa ficha.
 * - Si cambian las Características de un alojamiento, recalcula el campo ACF "Baño privado"
 *   (el término 134 es su origen, igual que en el panel).
 * - Si cambia la región o las categorías de un emprendimiento, resincroniza sus alojamientos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! function_exists( 'cl_terms_cpt_permitidos' ) ) {
	function cl_terms_cpt_permitidos() {
		return array( 'emprendimientos', 'alojamiento', 'evento' );
	}
}

if ( ! function_exists( 'cl_terms_tax_permitidas' ) ) {
	function cl_terms_tax_permitidas() {
		return array( 'region', 'tipo_alojamiento', 'caracteristicas', 'equipamiento', 'category', 'especial' );
	}
}

if ( ! function_exists( 'cl_terms_ejecutar' ) ) {
	/**
	 * Aplica los cambios de términos y devuelve qué quedó.
	 */
	function cl_terms_ejecutar( $input ) {
		$id     = absint( isset( $input['post_id'] ) ? $input['post_id'] : 0 );
		$modo   = isset( $input['mode'] ) ? (string) $input['mode'] : 'replace';
		$pedido = ( isset( $input['terms'] ) && is_array( $input['terms'] ) ) ? $input['terms'] : array();

		if ( ! in_array( $modo, array( 'replace', 'add', 'remove' ), true ) ) {
			$modo = 'replace';
		}

		$tipo = $id ? get_post_type( $id ) : '';
		if ( ! $id || ! in_array( $tipo, cl_terms_cpt_permitidos(), true ) ) {
			return new WP_Error( 'cl_terms_post', 'La ficha no existe o no es de un tipo permitido.' );
		}
		if ( ! $pedido ) {
			return new WP_Error( 'cl_terms_vacio', 'No se indicó ninguna taxonomía en "terms".' );
		}

		$guardado = array();
		$errores  = array();
		$cambio   = false;

		foreach ( $pedido as $tax_bruta => $ids ) {
			$tax = sanitize_key( (string) $tax_bruta );

			if ( ! in_array( $tax, cl_terms_tax_permitidas(), true ) ) {
				$errores[ $tax ] = 'Taxonomía no permitida.';
				continue;
			}
			if ( ! taxonomy_exists( $tax ) || ! is_object_in_taxonomy( $tipo, $tax ) ) {
				$errores[ $tax ] = 'La taxonomía no aplica a este tipo de ficha.';
				continue;
			}

			$ids   = is_array( $ids ) ? $ids : array( $ids );
			$lista = array();
			foreach ( $ids as $t ) {
				$t = absint( $t );
				if ( $t && get_term( $t, $tax ) instanceof WP_Term ) {
					$lista[] = $t;
				} else {
					$errores[ $tax . ':' . $t ] = 'El término no existe en esta taxonomía.';
				}
			}
			$lista = array_values( array_unique( $lista ) );

			$actuales = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
			$actuales = is_wp_error( $actuales ) ? array() : array_map( 'absint', $actuales );

			if ( 'add' === $modo ) {
				$final = array_values( array_unique( array_merge( $actuales, $lista ) ) );
			} elseif ( 'remove' === $modo ) {
				$final = array_values( array_diff( $actuales, $lista ) );
			} else {
				$final = $lista;
			}

			$a = $actuales;
			$b = $final;
			sort( $a );
			sort( $b );
			if ( $a === $b ) {
				$guardado[ $tax ] = array( 'sin_cambios' => true, 'terminos' => $final );
				continue;
			}

			$r = wp_set_object_terms( $id, $final, $tax, false );
			if ( is_wp_error( $r ) ) {
				$errores[ $tax ] = $r->get_error_message();
				continue;
			}
			$guardado[ $tax ] = array( 'sin_cambios' => false, 'terminos' => $final );
			$cambio           = true;
		}

		// "Baño privado" (término 134) es el origen del campo ACF del mismo nombre.
		if ( 'alojamiento' === $tipo && isset( $guardado['caracteristicas'] ) && function_exists( 'update_field' ) ) {
			$car = wp_get_object_terms( $id, 'caracteristicas', array( 'fields' => 'ids' ) );
			$car = is_wp_error( $car ) ? array() : array_map( 'absint', $car );
			$bp  = in_array( 134, $car, true ) ? 'Sí' : 'No';
			update_field( 'field_60e346f428a1b', $bp, $id );
			$guardado['bano_privado_acf'] = $bp;
		}

		// La región y las categorías del emprendimiento bajan a sus alojamientos y eventos.
		if ( $cambio && 'emprendimientos' === $tipo
			&& ( isset( $guardado['region'] ) || isset( $guardado['category'] ) )
			&& function_exists( 'cl_aloj_sync_encolar' ) ) {
			cl_aloj_sync_encolar( $id );
		}

		clean_post_cache( $id );

		return array(
			'post_id'  => $id,
			'tipo'     => $tipo,
			'modo'     => $modo,
			'guardado' => $guardado,
			'errores'  => $errores,
		);
	}
}

// Categoría de la capacidad (WordPress 6.9+ la exige). Si ya existe, no la duplica.
add_action( 'wp_abilities_api_categories_init', function () {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return;
	}
	if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'caminoslocales' ) ) {
		return;
	}
	wp_register_ability_category( 'caminoslocales', array(
		'label'       => 'Caminos Locales',
		'description' => 'Capacidades propias de caminoslocales.com.',
	) );
} );

// Registro: hook de WordPress 6.9+ y, de respaldo, el del plugin Abilities API viejo.
add_action( 'wp_abilities_api_init', 'cl_terms_registrar_capacidad' );
add_action( 'abilities_api_init', 'cl_terms_registrar_capacidad' );

function cl_terms_registrar_capacidad() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}
	if ( function_exists( 'wp_has_ability' ) && wp_has_ability( 'caminoslocales/update-terms' ) ) {
		return;
	}

	wp_register_ability( 'caminoslocales/update-terms', array(
		'label'       => 'Actualizar términos de una ficha',
		'category'    => 'caminoslocales',
		'description' => 'Pone, agrega o quita términos de taxonomía en una ficha de emprendimientos, alojamiento o evento. terms: objeto { taxonomia: [IDs de términos] }. mode: "replace" reemplaza la lista (por defecto), "add" suma, "remove" quita. Taxonomías permitidas: region, tipo_alojamiento, caracteristicas (alojamiento), equipamiento (emprendimientos), category, especial (alojamiento y evento). Solo acepta términos que ya existen; nunca crea términos. Si cambian las Características de un alojamiento, recalcula el campo ACF "Baño privado". Si cambia la región o las categorías de un emprendimiento, resincroniza sus alojamientos. Devuelve los términos resultantes por taxonomía, cuáles quedaron sin cambios y los errores.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'ID de la ficha (emprendimientos, alojamiento o evento).',
				),
				'terms'   => array(
					'type'        => 'object',
					'description' => 'Objeto taxonomía => array de IDs de términos. Ej: { "region": [80], "tipo_alojamiento": [97] }.',
				),
				'mode'    => array(
					'type'        => 'string',
					'enum'        => array( 'replace', 'add', 'remove' ),
					'description' => 'replace (por defecto): la lista queda exactamente como se indica. add: suma a lo que ya hay. remove: quita los indicados.',
				),
			),
			'required'             => array( 'post_id', 'terms' ),
			'additionalProperties' => false,
		),
		'output_schema' => array( 'type' => 'object' ),
		'permission_callback' => function ( $input ) {
			$id = absint( isset( $input['post_id'] ) ? $input['post_id'] : 0 );
			if ( ! $id || ! in_array( get_post_type( $id ), cl_terms_cpt_permitidos(), true ) ) {
				return false;
			}
			return current_user_can( 'edit_post', $id );
		},
		'execute_callback' => 'cl_terms_ejecutar',
		'meta'             => array(
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array( 'destructive' => false, 'idempotent' => true ),
		),
	) );
}