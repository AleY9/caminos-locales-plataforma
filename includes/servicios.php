<?php
/**
 * Servicios locales (1.1.0).
 *
 * Un emprendimiento es «servicio» cuando tiene rubro y ninguno es Alojamiento ni Actividades
 * (Comida, Construcción y oficios, Guía, Servicios para alojamientos, Transporte local…).
 *
 * - Campos de zona (paso Ubicación) y de visibilidad (paso Contacto), solo para servicios.
 * - Comida se muestra siempre al público. Los demás eligen «al público» o «solo a la red».
 * - Directorio público: elementos de Bricks con la clase cl-loop-servicios.
 *   Lista del panel: clase cl-loop-prestadores (incluye los que se muestran solo a la red).
 *   Filtros: [cl_servicios_filtros] (?zona=slug-de-región&tipo=slug-de-rubro).
 * - Ficha: la plantilla de Bricks «Single Servicio» reemplaza a la de emprendimientos.
 * - Los servicios no salen en los listados de turismo (Emprendimientos e Inicio).
 */

defined( 'ABSPATH' ) || exit;

// ---------- Quién es servicio ----------

function cl_srv_rubros_turismo() {
	return array( 'alojamiento', 'actividades-y-experiencias' );
}

function cl_es_servicio( $id ) {
	if ( ! $id || 'emprendimientos' !== get_post_type( $id ) ) {
		return false;
	}
	$slugs = wp_get_object_terms( $id, 'rubro', array( 'fields' => 'slugs' ) );
	if ( is_wp_error( $slugs ) || ! $slugs ) {
		return false;
	}
	return ! array_intersect( $slugs, cl_srv_rubros_turismo() );
}

function cl_srv_es_comida( $id ) {
	return has_term( 'comida', 'rubro', $id );
}

// Se muestra al público: Comida siempre; los demás, salvo que elijan «solo a la red».
function cl_srv_es_publico( $id ) {
	if ( ! cl_es_servicio( $id ) ) {
		return false;
	}
	return cl_srv_es_comida( $id ) || 'No' !== get_post_meta( $id, 'servicio_publico', true );
}

// Metas ocultas para filtrar rápido: _cl_servicio (1/0) y _cl_servicio_pub (1/0).
function cl_srv_sincronizar( $id ) {
	if ( 'emprendimientos' !== get_post_type( $id ) ) {
		return;
	}
	update_post_meta( $id, '_cl_servicio', cl_es_servicio( $id ) ? '1' : '0' );
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
	if ( 'servicio_publico' === $key ) {
		cl_srv_sincronizar( $id );
	}
}
add_action( 'save_post_emprendimientos', function ( $id ) {
	if ( ! wp_is_post_revision( $id ) ) {
		cl_srv_sincronizar( $id );
	}
}, 30 );

// Una vez por versión: calcula las metas de todos los emprendimientos.
add_action( 'init', function () {
	if ( get_option( 'cl_srv_sync' ) === CLP_VERSION ) {
		return;
	}
	$ids = get_posts( array( 'post_type' => 'emprendimientos', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		cl_srv_sincronizar( $id );
	}
	update_option( 'cl_srv_sync', CLP_VERSION, false );
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
		'title'    => 'Servicio: zona y visibilidad',
		'fields'   => array(
			array( 'key' => 'field_cl_srv_alcance', 'name' => 'servicio_alcance', 'label' => 'Hasta dónde llega', 'type' => 'select', 'choices' => array_combine( $alc, $alc ), 'allow_null' => 1 ),
			array( 'key' => 'field_cl_srv_km', 'name' => 'servicio_km', 'label' => 'Kilómetros', 'type' => 'number', 'min' => 1, 'max' => 500,
				'conditional_logic' => array( array( array( 'field' => 'field_cl_srv_alcance', 'operator' => '==', 'value' => 'Hasta cierta distancia' ) ) ) ),
			array( 'key' => 'field_cl_srv_nota', 'name' => 'servicio_zona_nota', 'label' => 'Aclaración de la zona', 'type' => 'text', 'maxlength' => 150 ),
			array( 'key' => 'field_cl_srv_publico', 'name' => 'servicio_publico', 'label' => 'Se muestra al público', 'type' => 'radio', 'choices' => array( 'Sí' => 'Sí, al público', 'No' => 'Solo a emprendedores de la red' ), 'default_value' => 'Sí', 'instructions' => 'Comida se muestra siempre.' ),
			array( 'key' => 'field_cl_srv_tel', 'name' => 'telefono_publico', 'label' => 'Publicar el teléfono en la web', 'type' => 'radio', 'choices' => array( 'Sí' => 'Sí', 'No' => 'No' ), 'default_value' => 'No', 'instructions' => 'A los emprendedores de la red se les muestra siempre.' ),
		),
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'emprendimientos' ) ) ),
		'position' => 'side',
	) );
} );

// ---------- Datos para las tarjetas y la ficha ({echo:…}) ----------

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_srv_zona', 'cl_srv_zona_nota', 'cl_srv_tipo', 'cl_srv_tel', 'cl_srv_wa', 'cl_srv_es_red' ) );
} );

// «Aiguá y hasta 30 km» · «Aiguá · toda la región Este» · «Aiguá · todo el país».
function cl_srv_zona( $id = 0 ) {
	$id     = $id ? (int) $id : get_the_ID();
	$loc    = trim( (string) get_post_meta( $id, 'localidad', true ) );
	$reg    = wp_get_object_terms( $id, 'region', array( 'fields' => 'names' ) );
	$reg    = is_wp_error( $reg ) || ! $reg ? '' : $reg[0];
	$alc    = get_post_meta( $id, 'servicio_alcance', true );
	$km     = (int) get_post_meta( $id, 'servicio_km', true );
	$base   = '' !== $loc ? $loc : $reg;
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

// Rubros del servicio: «Comida · Transporte local».
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

// Quien mira es de la red (emprendedor o administrador).
function cl_srv_es_red() {
	return function_exists( 'cl_panel_puede_usar' ) && cl_panel_puede_usar() ? '1' : '';
}

// Teléfono: al público si el prestador lo eligió; a la red, siempre.
function cl_srv_tel( $id = 0 ) {
	$id  = $id ? (int) $id : get_the_ID();
	$tel = trim( (string) get_post_meta( $id, 'telefono_contacto', true ) );
	if ( '' === $tel ) {
		return '';
	}
	return ( cl_srv_es_red() || 'Sí' === get_post_meta( $id, 'telefono_publico', true ) ) ? $tel : '';
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

// Elemento de Bricks marcado con una clase propia (también sirve con la clase en el ID del elemento).
function cl_srv_tiene_clase( $settings, $clase ) {
	$c = isset( $settings['_cssClasses'] ) ? (string) $settings['_cssClasses'] : '';
	return in_array( $clase, preg_split( '/\s+/', $c ), true );
}

add_filter( 'bricks/posts/query_vars', function ( $qv, $settings, $element_id ) {
	$servicios   = cl_srv_tiene_clase( $settings, 'cl-loop-servicios' );
	$prestadores = cl_srv_tiene_clase( $settings, 'cl-loop-prestadores' );

	// Listados de turismo: sin servicios.
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
		array( 'key' => $servicios ? '_cl_servicio_pub' : '_cl_servicio', 'value' => '1' ),
	);

	// Tipo de servicio.
	$tipo = isset( $_GET['tipo'] ) ? sanitize_title( wp_unslash( $_GET['tipo'] ) ) : '';
	if ( $tipo && ! in_array( $tipo, cl_srv_rubros_turismo(), true ) && term_exists( $tipo, 'rubro' ) ) {
		$qv['tax_query'] = array( array( 'taxonomy' => 'rubro', 'field' => 'slug', 'terms' => $tipo ) );
	}

	// Zona: los de esa región, más los que llegan a todo el país o tienen región Nacional.
	$zona = isset( $_GET['zona'] ) ? sanitize_title( wp_unslash( $_GET['zona'] ) ) : '';
	if ( $zona && term_exists( $zona, 'region' ) ) {
		$base = array( 'post_type' => 'emprendimientos', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => $qv['meta_query'] );
		$en_region = get_posts( $base + array( 'tax_query' => array( array( 'taxonomy' => 'region', 'field' => 'slug', 'terms' => array_unique( array( $zona, 'nacional' ) ) ) ) ) );
		$pais      = get_posts( array_merge( $base, array( 'meta_query' => array( 'relation' => 'AND', $qv['meta_query'][0], array( 'key' => 'servicio_alcance', 'value' => 'En todo el país' ) ) ) ) );
		$ids             = array_values( array_unique( array_merge( $en_region, $pais ) ) );
		$qv['post__in']  = $ids ? $ids : array( 0 );
	}
	return $qv;
}, 10, 3 );

// Filtros: [cl_servicios_filtros]. Dos listas que recargan la página al cambiar.
add_shortcode( 'cl_servicios_filtros', function () {
	$zona = isset( $_GET['zona'] ) ? sanitize_title( wp_unslash( $_GET['zona'] ) ) : '';
	$tipo = isset( $_GET['tipo'] ) ? sanitize_title( wp_unslash( $_GET['tipo'] ) ) : '';
	$nac  = get_term_by( 'slug', 'nacional', 'region' );
	$regs = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => false, 'exclude' => $nac ? array( (int) $nac->term_id ) : array() ) );
	$rubs = get_terms( array( 'taxonomy' => 'rubro', 'hide_empty' => false ) );
	ob_start();
	?>
	<form class="cl-srv-filtros" method="get" action="">
		<label class="cl-srv-filtro"><span>Zona</span>
			<select name="zona" onchange="this.form.submit()">
				<option value="">Todo el país</option>
				<?php foreach ( (array) $regs as $t ) : ?>
					<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $zona, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="cl-srv-filtro"><span>Tipo de servicio</span>
			<select name="tipo" onchange="this.form.submit()">
				<option value="">Todos</option>
				<?php foreach ( (array) $rubs as $t ) : ?>
					<?php if ( in_array( $t->slug, cl_srv_rubros_turismo(), true ) ) { continue; } ?>
					<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $tipo, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<noscript><button type="submit">Filtrar</button></noscript>
	</form>
	<style>.cl-srv-filtros{display:flex;flex-wrap:wrap;gap:12px 16px}.cl-srv-filtro{display:flex;flex-direction:column;gap:4px;font-size:var(--cl-texto-s,15px);color:#3d4b49;font-weight:600;min-width:200px;flex:1 1 200px}.cl-srv-filtro select{min-height:44px;border:1px solid #1f4645;border-radius:12px;padding:0 12px;background:#fff;font:inherit;font-weight:400;color:#23302f}</style>
	<?php
	return ob_get_clean();
} );

// ---------- Ficha ----------

// Servicio que solo se muestra a la red: para el público no existe.
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
	if ( 'content' === $content_type && is_singular( 'emprendimientos' ) && cl_es_servicio( get_queried_object_id() ) && cl_srv_plantilla_id() ) {
		$active['content'] = cl_srv_plantilla_id();
	}
	return $active;
}, 10, 3 );

// ---------- Panel: los campos de servicio se ven solo si es servicio ----------

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'cl_panel_es_pagina' ) || ! is_page() || ! cl_panel_es_pagina( get_queried_object_id() ) ) {
		return;
	}
	$id = cl_panel_emp_id();
	if ( ! $id ) {
		return;
	}
	$srv    = cl_es_servicio( $id ) ? 1 : 0;
	$comida = cl_srv_es_comida( $id ) ? 1 : 0;
	?>
<script>
(function(){
	var S = <?php echo (int) $srv; ?>, C = <?php echo (int) $comida; ?>;
	function g(id){ var el = document.querySelector('form.brxe-form [name="form-field-' + id + '"], form.brxe-form [name="form-field-' + id + '[]"], form.brxe-form [data-cl="' + id + '"]'); return el && el.closest('.form-group'); }
	function ver(id, on){ var x = g(id); if (x) { x.style.display = on ? '' : 'none'; } }
	['clhsrv','clsalc','clsnot','clhsvc','cltpub'].forEach(function(id){ ver(id, S); });
	ver('clspub', S && !C);
	var alc = document.querySelectorAll('form.brxe-form [name="form-field-clsalc"]');
	function km(){ var v = ''; [].forEach.call(alc, function(r){ if (r.checked) { v = r.value; } }); ver('clskm', S && v === 'Hasta cierta distancia'); }
	[].forEach.call(alc, function(r){ r.addEventListener('change', km); });
	km();
})();
</script>
	<?php
}, 30 );
