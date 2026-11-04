<?php
/**
 * Servicios locales (1.1.0).
 *
 * Dos cosas separadas:
 * - Qué hace: tiene «rubro de servicio» si tiene algún rubro que no sea Alojamiento ni Actividades
 *   (Comida, Construcción y oficios, Guía local, Servicios para alojamientos, Transporte local…).
 * - Cuánta ficha: Alojamiento y Actividades tienen siempre ficha completa. Los demás arrancan con
 *   ficha compacta y pueden elegir «ficha completa» en el paso «Qué ofrecés» (campo ficha_completa).
 *
 * - Directorio «Servicios locales»: todo el que tenga rubro de servicio (con ficha compacta o completa).
 *   Los de ficha completa llevan el sello «Ficha completa →». Clase de Bricks del bucle: cl-loop-servicios.
 *   Lista del panel: clase cl-loop-prestadores (incluye los que se muestran solo a la red).
 *   Filtros: [cl_servicios_filtros] (?zona=slug-de-región&tipo=slug-de-rubro).
 * - Ficha compacta: plantilla de Bricks «Single Servicio». Solo ella sale de los listados de turismo.
 * - Visibilidad (solo ficha compacta): Comida siempre al público; los demás eligen «al público» o
 *   «solo a la red». Teléfono: en ficha compacta, si el prestador lo elige; a la red, siempre.
 */

defined( 'ABSPATH' ) || exit;

// ---------- Rubros y tipo de ficha ----------

function cl_srv_rubros_turismo() {
	return array( 'alojamiento', 'actividades-y-experiencias' );
}

function cl_srv_rubros( $id ) {
	if ( ! $id || 'emprendimientos' !== get_post_type( $id ) ) {
		return array();
	}
	$slugs = wp_get_object_terms( $id, 'rubro', array( 'fields' => 'slugs' ) );
	return is_wp_error( $slugs ) ? array() : $slugs;
}

// Tiene algún rubro de servicio: aparece en «Servicios locales».
function cl_srv_tiene_rubro_servicio( $id ) {
	return (bool) array_diff( cl_srv_rubros( $id ), cl_srv_rubros_turismo() );
}

// Ficha compacta: solo rubros de servicio y no eligió la ficha completa.
function cl_es_servicio( $id ) {
	$r = cl_srv_rubros( $id );
	if ( ! $r || array_intersect( $r, cl_srv_rubros_turismo() ) ) {
		return false;
	}
	return 'Sí' !== get_post_meta( $id, 'ficha_completa', true );
}

// Con rubro de servicio y ficha completa (por turismo o por elección): lleva el sello en el directorio.
function cl_srv_con_ficha_completa( $id ) {
	return cl_srv_tiene_rubro_servicio( $id ) && ! cl_es_servicio( $id );
}

function cl_srv_es_comida( $id ) {
	return has_term( 'comida', 'rubro', $id );
}

// Aparece en el directorio público.
function cl_srv_es_publico( $id ) {
	if ( ! cl_srv_tiene_rubro_servicio( $id ) ) {
		return false;
	}
	if ( ! cl_es_servicio( $id ) ) {
		return true; // La ficha completa ya es pública.
	}
	return cl_srv_es_comida( $id ) || 'No' !== get_post_meta( $id, 'servicio_publico', true );
}

// Metas ocultas para filtrar rápido (1/0):
// _cl_servicio = ficha compacta · _cl_en_servicios = rubro de servicio · _cl_servicio_pub = en el directorio público.
function cl_srv_sincronizar( $id ) {
	if ( 'emprendimientos' !== get_post_type( $id ) ) {
		return;
	}
	update_post_meta( $id, '_cl_servicio', cl_es_servicio( $id ) ? '1' : '0' );
	update_post_meta( $id, '_cl_en_servicios', cl_srv_tiene_rubro_servicio( $id ) ? '1' : '0' );
	update_post_meta( $id, '_cl_servicio_pub', cl_srv_es_publico( $id ) ? '1' : '0' );
}

add_action( 'set_object_terms', function ( $id, $terms, $tt_ids, $taxonomy ) {
	if ( 'rubro' === $taxonomy ) {
		cl_srv_sincronizar( $id );
	}
}, 10, 4 );
add_action( 'updated_post_meta', 'cl_srv_meta_cambio', 10, 3 );
add_action( 'added_post_meta', 'cl_srv_meta_cambio', 10, 3 );
function cl_srv_meta_cambio( $mid, $id, $key ) {
	if ( in_array( $key, array( 'servicio_publico', 'ficha_completa' ), true ) ) {
		cl_srv_sincronizar( $id );
	}
}
add_action( 'save_post_emprendimientos', function ( $id ) {
	if ( ! wp_is_post_revision( $id ) ) {
		cl_srv_sincronizar( $id );
	}
}, 30 );

// Cuando cambia la regla: recalcula las metas de todos los emprendimientos.
define( 'CL_SRV_REGLA', '2' );
add_action( 'init', function () {
	if ( get_option( 'cl_srv_sync' ) === CLP_VERSION . '-' . CL_SRV_REGLA ) {
		return;
	}
	$ids = get_posts( array( 'post_type' => 'emprendimientos', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		cl_srv_sincronizar( $id );
	}
	update_option( 'cl_srv_sync', CLP_VERSION . '-' . CL_SRV_REGLA, false );
}, 30 );

// ---------- Campos ACF ----------

function cl_srv_alcances() {
	return array( 'Solo en mi localidad', 'Hasta cierta distancia', 'En toda la región', 'En todo el país' );
}

add_action( 'acf/include_fields', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$alc = cl_srv_alcances();
	acf_add_local_field_group( array(
		'key'      => 'group_cl_servicio',
		'title'    => 'Servicio: ficha, zona y visibilidad',
		'fields'   => array(
			array( 'key' => 'field_cl_srv_ficha', 'name' => 'ficha_completa', 'label' => 'Ficha completa', 'type' => 'radio', 'choices' => array( 'No' => 'No, ficha compacta', 'Sí' => 'Sí, ficha completa' ), 'default_value' => 'No', 'instructions' => 'Solo cuenta si no tiene Alojamiento ni Actividades (esos siempre tienen ficha completa).' ),
			array( 'key' => 'field_cl_srv_alcance', 'name' => 'servicio_alcance', 'label' => 'Hasta dónde llega', 'type' => 'select', 'choices' => array_combine( $alc, $alc ), 'allow_null' => 1 ),
			array( 'key' => 'field_cl_srv_km', 'name' => 'servicio_km', 'label' => 'Kilómetros', 'type' => 'number', 'min' => 1, 'max' => 500,
				'conditional_logic' => array( array( array( 'field' => 'field_cl_srv_alcance', 'operator' => '==', 'value' => 'Hasta cierta distancia' ) ) ) ),
			array( 'key' => 'field_cl_srv_nota', 'name' => 'servicio_zona_nota', 'label' => 'Aclaración de la zona', 'type' => 'text', 'maxlength' => 150 ),
			array( 'key' => 'field_cl_srv_publico', 'name' => 'servicio_publico', 'label' => 'Se muestra al público', 'type' => 'radio', 'choices' => array( 'Sí' => 'Sí, al público', 'No' => 'Solo a emprendedores de la red' ), 'default_value' => 'Sí', 'instructions' => 'Solo ficha compacta. Comida se muestra siempre.' ),
			array( 'key' => 'field_cl_srv_tel', 'name' => 'telefono_publico', 'label' => 'Publicar el teléfono en la web', 'type' => 'radio', 'choices' => array( 'Sí' => 'Sí', 'No' => 'No' ), 'default_value' => 'No', 'instructions' => 'Solo ficha compacta. A los emprendedores de la red se les muestra siempre.' ),
		),
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'emprendimientos' ) ) ),
		'position' => 'side',
	) );
} );

// ---------- Datos para las tarjetas y la ficha ({echo:…}) ----------

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_srv_zona', 'cl_srv_zona_nota', 'cl_srv_tipo', 'cl_srv_tel', 'cl_srv_wa', 'cl_srv_es_red', 'cl_srv_sello' ) );
} );

// «Aiguá y hasta 30 km» · «Aiguá · toda la región Este» · «Aiguá · todo el país».
function cl_srv_zona( $id = 0 ) {
	$id   = $id ? (int) $id : get_the_ID();
	$loc  = trim( (string) get_post_meta( $id, 'localidad', true ) );
	$reg  = wp_get_object_terms( $id, 'region', array( 'fields' => 'names' ) );
	$reg  = is_wp_error( $reg ) || ! $reg ? '' : $reg[0];
	$alc  = get_post_meta( $id, 'servicio_alcance', true );
	$km   = (int) get_post_meta( $id, 'servicio_km', true );
	$base = '' !== $loc ? $loc : $reg;
	switch ( $alc ) {
		case 'Hasta cierta distancia':
			return $km ? trim( $base . ' y hasta ' . $km . ' km' ) : $base;
		case 'En toda la región':
			return $reg ? ( $loc ? $loc . ' · toda la región ' . $reg : 'Toda la región ' . $reg ) : $base;
		case 'En todo el país':
			return $base ? $base . ' · todo el país' : 'Todo el país';
		default:
			return $base;
	}
}

function cl_srv_zona_nota( $id = 0 ) {
	$id = $id ? (int) $id : get_the_ID();
	return (string) get_post_meta( $id, 'servicio_zona_nota', true );
}

// Rubros de servicio: «Comida · Guía local».
function cl_srv_tipo( $id = 0 ) {
	$id    = $id ? (int) $id : get_the_ID();
	$terms = wp_get_object_terms( $id, 'rubro' );
	if ( is_wp_error( $terms ) ) {
		return '';
	}
	$n = array();
	foreach ( $terms as $t ) {
		if ( 'otro' !== $t->slug && ! in_array( $t->slug, cl_srv_rubros_turismo(), true ) ) {
			$n[] = $t->name;
		}
	}
	return implode( ' · ', $n );
}

// Sello de la tarjeta: «Ficha completa →» si tiene perfil de emprendimiento.
function cl_srv_sello( $id = 0 ) {
	$id = $id ? (int) $id : get_the_ID();
	return cl_srv_con_ficha_completa( $id ) ? 'Ficha completa →' : '';
}

// Quien mira es de la red (emprendedor o administrador).
function cl_srv_es_red() {
	return function_exists( 'cl_panel_puede_usar' ) && cl_panel_puede_usar() ? '1' : '';
}

// Teléfono: ficha completa, siempre (ya se publica en su ficha); compacta, si lo eligió; a la red, siempre.
function cl_srv_tel( $id = 0 ) {
	$id  = $id ? (int) $id : get_the_ID();
	$tel = trim( (string) get_post_meta( $id, 'telefono_contacto', true ) );
	if ( '' === $tel ) {
		return '';
	}
	$publico = ! cl_es_servicio( $id ) || 'Sí' === get_post_meta( $id, 'telefono_publico', true );
	return ( $publico || cl_srv_es_red() ) ? $tel : '';
}

// Enlace de WhatsApp para el teléfono visible (vacío si no hay).
function cl_srv_wa( $id = 0 ) {
	$d = preg_replace( '/\D+/', '', cl_srv_tel( $id ) );
	if ( '' === $d ) {
		return '';
	}
	if ( 0 === strpos( $d, '0' ) ) {
		$d = '598' . substr( $d, 1 ); // 099 123 456 → 59899123456
	} elseif ( 8 === strlen( $d ) && '9' === $d[0] ) {
		$d = '598' . $d;
	}
	return 'https://wa.me/' . $d;
}

// ---------- Listados ----------

function cl_srv_tiene_clase( $settings, $clase ) {
	$c = isset( $settings['_cssClasses'] ) ? (string) $settings['_cssClasses'] : '';
	return in_array( $clase, preg_split( '/\s+/', $c ), true );
}

add_filter( 'bricks/posts/query_vars', function ( $qv, $settings, $element_id ) {
	$servicios   = cl_srv_tiene_clase( $settings, 'cl-loop-servicios' );
	$prestadores = cl_srv_tiene_clase( $settings, 'cl-loop-prestadores' );

	// Listados de turismo: sin las fichas compactas.
	if ( ! $servicios && ! $prestadores ) {
		$tipos = (array) ( $qv['post_type'] ?? array() );
		if ( in_array( 'emprendimientos', $tipos, true ) && ( in_array( $element_id, array( 'pvnicx', 'gcwygx' ), true ) || cl_srv_tiene_clase( $settings, 'cl-loop-turismo' ) ) ) {
			$sin = array( 'relation' => 'OR', array( 'key' => '_cl_servicio', 'compare' => 'NOT EXISTS' ), array( 'key' => '_cl_servicio', 'value' => '1', 'compare' => '!=' ) );
			$qv['meta_query'] = empty( $qv['meta_query'] ) ? array( $sin ) : array( 'relation' => 'AND', $qv['meta_query'], $sin );
		}
		return $qv;
	}

	if ( $prestadores && ! cl_srv_es_red() ) {
		$qv['post__in'] = array( 0 ); // La lista del panel es solo para la red.
		return $qv;
	}

	$qv['post_type']   = 'emprendimientos';
	$qv['post_status'] = 'publish';
	$qv['orderby']     = 'title';
	$qv['order']       = 'ASC';
	$qv['meta_query']  = array(
		array( 'key' => $servicios ? '_cl_servicio_pub' : '_cl_en_servicios', 'value' => '1' ),
	);

	// Tipo de servicio.
	$tipo = isset( $_GET['tipo'] ) ? sanitize_title( wp_unslash( $_GET['tipo'] ) ) : '';
	if ( $tipo && ! in_array( $tipo, cl_srv_rubros_turismo(), true ) && term_exists( $tipo, 'rubro' ) ) {
		$qv['tax_query'] = array( array( 'taxonomy' => 'rubro', 'field' => 'slug', 'terms' => $tipo ) );
	}

	// Zona: los de esa región, más los que llegan a todo el país o tienen región Nacional.
	$zona = isset( $_GET['zona'] ) ? sanitize_title( wp_unslash( $_GET['zona'] ) ) : '';
	if ( $zona && term_exists( $zona, 'region' ) ) {
		$base      = array( 'post_type' => 'emprendimientos', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => $qv['meta_query'] );
		$en_region = get_posts( $base + array( 'tax_query' => array( array( 'taxonomy' => 'region', 'field' => 'slug', 'terms' => array_unique( array( $zona, 'nacional' ) ) ) ) ) );
		$pais      = get_posts( array_merge( $base, array( 'meta_query' => array( 'relation' => 'AND', $qv['meta_query'][0], array( 'key' => 'servicio_alcance', 'value' => 'En todo el país' ) ) ) ) );
		$ids            = array_values( array_unique( array_merge( $en_region, $pais ) ) );
		$qv['post__in'] = $ids ? $ids : array( 0 );
	}
	return $qv;
}, 10, 3 );

// Íconos de línea (24 × 24) para los tipos de servicio. Provisorios, hasta tener los propios.
function cl_srv_icono( $slug ) {
	$p = array(
		'todos'                       => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
		'comida'                      => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
		'guia'                        => '<path d="M12 3v3"/><path d="M18.5 13h-13L2 9.5 5.5 6h13L22 9.5Z"/><path d="M12 13v8"/>',
		'transporte-local'            => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>',
		'servicios-para-alojamientos' => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
		'construccion-y-oficios'      => '<path d="m15 12-8.5 8.5c-.83.83-2.17.83-3 0a2.12 2.12 0 0 1 0-3L12 9"/><path d="M17.64 15 22 10.64"/><path d="m20.91 11.7-1.25-1.25c-.6-.6-.93-1.4-.93-2.25v-.86L16.01 4.6a5.56 5.56 0 0 0-3.94-1.64H9l.92.82A6.18 6.18 0 0 1 12 8.4v1.56l2 2h2.47l2.26 1.91"/>',
		'productos-locales'           => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>',
		'otro'                        => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
	);
	$d = isset( $p[ $slug ] ) ? $p[ $slug ] : $p['otro'];
	return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

// Filtros: [cl_servicios_filtros]. Zona (lista) y tipo de servicio (botones con ícono).
add_shortcode( 'cl_servicios_filtros', function () {
	$zona = isset( $_GET['zona'] ) ? sanitize_title( wp_unslash( $_GET['zona'] ) ) : '';
	$tipo = isset( $_GET['tipo'] ) ? sanitize_title( wp_unslash( $_GET['tipo'] ) ) : '';
	$nac  = get_term_by( 'slug', 'nacional', 'region' );
	$regs = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => false, 'exclude' => $nac ? array( (int) $nac->term_id ) : array() ) );
	$rubs = get_terms( array( 'taxonomy' => 'rubro', 'hide_empty' => false ) );
	$url  = function ( $t ) use ( $zona ) {
		return esc_url( add_query_arg( array_filter( array( 'zona' => $zona, 'tipo' => $t ) ), get_permalink() ) );
	};
	ob_start();
	?>
	<div class="cl-srv-filtros">
		<form class="cl-srv-zona" method="get" action="<?php echo esc_url( get_permalink() ); ?>">
			<label><span>Zona</span>
				<select name="zona" onchange="this.form.submit()">
					<option value="">Todo el país</option>
					<?php foreach ( (array) $regs as $t ) : ?>
						<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $zona, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php if ( $tipo ) : ?><input type="hidden" name="tipo" value="<?php echo esc_attr( $tipo ); ?>"><?php endif; ?>
			<noscript><button type="submit">Filtrar</button></noscript>
		</form>
		<nav class="cl-srv-tipos" aria-label="Tipo de servicio">
			<a href="<?php echo $url( '' ); ?>" <?php echo $tipo ? '' : 'aria-current="true"'; ?>><?php echo cl_srv_icono( 'todos' ); ?><span>Todos</span></a>
			<?php foreach ( (array) $rubs as $t ) : ?>
				<?php if ( in_array( $t->slug, cl_srv_rubros_turismo(), true ) ) { continue; } ?>
				<a href="<?php echo $url( $t->slug ); ?>" <?php echo $tipo === $t->slug ? 'aria-current="true"' : ''; ?>><?php echo cl_srv_icono( $t->slug ); ?><span><?php echo esc_html( $t->name ); ?></span></a>
			<?php endforeach; ?>
		</nav>
	</div>
	<style>
	.cl-srv-filtros{display:flex;flex-direction:column;gap:14px;width:100%}
	.cl-srv-zona label{display:flex;flex-direction:column;gap:4px;font-size:var(--cl-texto-s,16px);color:#3d4b49;font-weight:600;max-width:320px}
	.cl-srv-zona select{min-height:44px;border:1px solid #1f4645;border-radius:12px;padding:0 12px;background:#fff;font:inherit;font-weight:400;color:#23302f}
	.cl-srv-tipos{display:flex;flex-wrap:wrap;gap:8px}
	.cl-srv-tipos a{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:0 14px;border:1px solid #c9c2b3;border-radius:999px;background:#fff;color:#1f4645;font-size:var(--cl-texto-s,16px);text-decoration:none;line-height:1.2}
	.cl-srv-tipos a:hover{border-color:#1f4645}
	.cl-srv-tipos a[aria-current]{background:#1f4645;border-color:#1f4645;color:#fff}
	@media (max-width:767px){.cl-srv-tipos{flex-wrap:nowrap;overflow-x:auto;margin:0 -16px;padding:0 16px 4px;scrollbar-width:none}.cl-srv-tipos a{flex:0 0 auto}.cl-srv-zona label{max-width:none}}
	</style>
	<?php
	return ob_get_clean();
} );

// ---------- Ficha ----------

// Ficha compacta que solo se muestra a la red: para el público no existe.
add_action( 'template_redirect', function () {
	if ( ! is_singular( 'emprendimientos' ) ) {
		return;
	}
	$id = get_queried_object_id();
	if ( cl_es_servicio( $id ) && ! cl_srv_es_publico( $id ) && ! cl_srv_es_red() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
} );

// Plantilla compacta: la de Bricks titulada «Single Servicio».
function cl_srv_plantilla_id() {
	static $id = null;
	if ( null === $id ) {
		$t  = get_posts( array( 'post_type' => 'bricks_template', 'post_status' => 'publish', 'title' => 'Single Servicio', 'numberposts' => 1, 'fields' => 'ids' ) );
		$id = $t ? (int) $t[0] : 0;
	}
	return $id;
}

add_filter( 'bricks/active_templates', function ( $active, $post_id, $content_type ) {
	if ( ! is_singular( 'emprendimientos' ) || ! cl_es_servicio( get_queried_object_id() ) || ! cl_srv_plantilla_id() ) {
		return $active;
	}
	$active['content'] = cl_srv_plantilla_id();
	// La ficha compacta no tiene foto grande: va la cabecera común, no la transparente.
	$cab = get_posts( array( 'post_type' => 'bricks_template', 'post_status' => 'publish', 'title' => 'Cabecera', 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $cab ) {
		$active['header'] = (int) $cab[0];
	}
	return $active;
}, 10, 3 );

// ---------- Panel ----------

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'cl_panel_es_pagina' ) || ! is_page() || ! cl_panel_es_pagina( get_queried_object_id() ) ) {
		return;
	}
	$id = cl_panel_emp_id();
	if ( ! $id ) {
		return;
	}
	$ids_turismo = array();
	foreach ( cl_srv_rubros_turismo() as $s ) {
		$t = get_term_by( 'slug', $s, 'rubro' );
		if ( $t ) {
			$ids_turismo[] = (string) $t->term_id;
		}
	}
	$en_srv  = cl_srv_tiene_rubro_servicio( $id ) ? 1 : 0;
	$compact = cl_es_servicio( $id ) ? 1 : 0;
	$comida  = cl_srv_es_comida( $id ) ? 1 : 0;
	?>
<script>
(function(){
	var EN = <?php echo (int) $en_srv; ?>, CO = <?php echo (int) $compact; ?>, C = <?php echo (int) $comida; ?>;
	var TUR = <?php echo wp_json_encode( $ids_turismo ); ?>;
	function g(id){ var el = document.querySelector('form.brxe-form [name="form-field-' + id + '"], form.brxe-form [name="form-field-' + id + '[]"], form.brxe-form [data-cl="' + id + '"]'); return el && el.closest('.form-group'); }
	function ver(id, on){ var x = g(id); if (x) { x.style.display = on ? '' : 'none'; } }
	// Ubicación: zona para todo el que aparece en Servicios.
	['clhsrv','clsalc','clsnot'].forEach(function(id){ ver(id, EN); });
	var alc = document.querySelectorAll('form.brxe-form [name="form-field-clsalc"]');
	function km(){ var v = ''; [].forEach.call(alc, function(r){ if (r.checked) { v = r.value; } }); ver('clskm', EN && v === 'Hasta cierta distancia'); }
	[].forEach.call(alc, function(r){ r.addEventListener('change', km); });
	km();
	// Contacto: visibilidad y teléfono solo con ficha compacta.
	ver('clhsvc', CO); ver('cltpub', CO); ver('clspub', CO && !C);
	// Qué ofrecés: «ficha completa» solo si no marcó Alojamiento ni Actividades.
	var cajas = document.querySelectorAll('form.brxe-form input[name="form-field-clrubr[]"]');
	if (cajas.length) {
		var f = function(){
			var sel = [].filter.call(cajas, function(c){ return c.checked; }).map(function(c){ return c.value; });
			var on = sel.length && !sel.some(function(v){ return TUR.indexOf(v) > -1; });
			ver('clhfco', on); ver('clfcom', on);
		};
		[].forEach.call(cajas, function(c){ c.addEventListener('change', f); });
		f();
	}
})();
</script>
	<?php
}, 30 );
