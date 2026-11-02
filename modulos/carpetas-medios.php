<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 19 «CL – Carpetas de medios» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — Carpetas de medios por emprendimiento (v1, sept. 2026)
 * Code Snippets: tipo PHP, "Ejecutar en todas partes". Sin <?php.
 * Sin plugins de pago. Necesita "CL – Roles del back office" (para las subidas de Emprendedores).
 *
 * 1) Carpetas en la biblioteca: taxonomía "Carpetas" (Medios → Carpetas), con
 *    carpeta madre "Emprendimientos" y una hija por emprendimiento.
 *    Filtro "Todas las carpetas" en Medios (vista cuadrícula y lista) y columna en la vista lista.
 * 2) Al guardar una ficha de emprendimiento o alojamiento, sus fotos (imagen destacada,
 *    logo, fotos y galerías ACF) pasan a la carpeta del emprendimiento. Los alojamientos
 *    usan la carpeta de su emprendimiento. Solo se asignan fotos que aún no tienen carpeta.
 * 3) Las fotos que sube un Emprendedor quedan también en una carpeta real del servidor:
 *    uploads/.../emprendimientos/<slug-del-emprendimiento>/
 * 4) La primera vez que un administrador entra a wp-admin, ordena las fichas existentes.
 */

// ---------- 1) Taxonomía ----------
add_action( 'init', function () {
	register_taxonomy( 'cl_carpeta', 'attachment', array(
		'labels'                => array(
			'name'          => 'Carpetas',
			'singular_name' => 'Carpeta',
			'menu_name'     => 'Carpetas',
			'all_items'     => 'Todas las carpetas',
			'add_new_item'  => 'Añadir carpeta',
			'parent_item'   => 'Carpeta madre',
		),
		'hierarchical'          => true,
		'public'                => false,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'show_in_rest'          => false,
		'rewrite'               => false,
		'query_var'             => false,
		'update_count_callback' => '_update_generic_term_count',
		'capabilities'          => array(
			'manage_terms' => 'manage_options',
			'edit_terms'   => 'manage_options',
			'delete_terms' => 'manage_options',
			'assign_terms' => 'upload_files',
		),
	) );
}, 5 );

// Carpeta madre "Emprendimientos".
function cl_carpeta_madre() {
	$t = get_term_by( 'slug', 'emprendimientos', 'cl_carpeta' );
	if ( $t ) {
		return (int) $t->term_id;
	}
	$r = wp_insert_term( 'Emprendimientos', 'cl_carpeta', array( 'slug' => 'emprendimientos' ) );
	return is_wp_error( $r ) ? 0 : (int) $r['term_id'];
}

// Carpeta de un emprendimiento (se busca por ID guardado, así no se duplica si cambia el nombre).
function cl_carpeta_de_emprendimiento( $emp_id ) {
	$emp_id = (int) $emp_id;
	if ( ! $emp_id || 'emprendimientos' !== get_post_type( $emp_id ) ) {
		return 0;
	}
	$found = get_terms( array(
		'taxonomy'   => 'cl_carpeta',
		'hide_empty' => false,
		'number'     => 1,
		'fields'     => 'ids',
		'meta_key'   => 'cl_emprendimiento_id',
		'meta_value' => $emp_id,
	) );
	if ( ! is_wp_error( $found ) && $found ) {
		return (int) $found[0];
	}
	$madre = cl_carpeta_madre();
	if ( ! $madre ) {
		return 0;
	}
	$nombre = get_the_title( $emp_id );
	$nombre = $nombre ? $nombre : 'Emprendimiento ' . $emp_id;
	$r = wp_insert_term( $nombre, 'cl_carpeta', array(
		'parent' => $madre,
		'slug'   => 'emp-' . $emp_id,
	) );
	if ( is_wp_error( $r ) ) {
		return 0;
	}
	update_term_meta( $r['term_id'], 'cl_emprendimiento_id', $emp_id );
	return (int) $r['term_id'];
}

// Emprendimiento al que pertenece una ficha (el propio, o el vinculado si es alojamiento).
function cl_emprendimiento_de_ficha( $post_id ) {
	$type = get_post_type( $post_id );
	if ( 'emprendimientos' === $type ) {
		return (int) $post_id;
	}
	if ( 'alojamiento' === $type && function_exists( 'get_field' ) ) {
		$emp = get_field( 'emprendimiento_correspondiente', $post_id, false );
		$emp = is_array( $emp ) ? reset( $emp ) : $emp;
		return (int) $emp;
	}
	return 0;
}

// Emprendimiento del usuario (el primero que tenga a su nombre).
function cl_emprendimiento_de_usuario( $user_id ) {
	$ids = get_posts( array(
		'post_type'   => 'emprendimientos',
		'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
		'author'      => (int) $user_id,
		'numberposts' => 1,
		'orderby'     => 'ID',
		'order'       => 'ASC',
		'fields'      => 'ids',
	) );
	return $ids ? (int) $ids[0] : 0;
}

// Pone una foto en una carpeta solo si todavía no tiene ninguna.
function cl_carpeta_asignar_foto( $att_id, $term_id ) {
	$att_id = (int) $att_id;
	if ( ! $att_id || ! $term_id || 'attachment' !== get_post_type( $att_id ) ) {
		return;
	}
	$actual = wp_get_object_terms( $att_id, 'cl_carpeta', array( 'fields' => 'ids' ) );
	if ( ! is_wp_error( $actual ) && empty( $actual ) ) {
		wp_set_object_terms( $att_id, array( (int) $term_id ), 'cl_carpeta', false );
	}
}

// IDs de fotos de una ficha: imagen destacada + campos ACF de imagen, galería y archivo (también dentro de grupos).
function cl_fotos_de_ficha( $post_id ) {
	$ids   = array();
	$thumb = get_post_thumbnail_id( $post_id );
	if ( $thumb ) {
		$ids[] = (int) $thumb;
	}
	if ( ! function_exists( 'get_field_objects' ) ) {
		return $ids;
	}
	$recoger = function ( $field, $value ) use ( &$recoger, &$ids ) {
		switch ( $field['type'] ) {
			case 'image':
			case 'file':
				if ( is_numeric( $value ) ) {
					$ids[] = (int) $value;
				}
				break;
			case 'gallery':
				foreach ( (array) $value as $v ) {
					if ( is_numeric( $v ) ) {
						$ids[] = (int) $v;
					}
				}
				break;
			case 'group':
				if ( ! empty( $field['sub_fields'] ) && is_array( $value ) ) {
					foreach ( $field['sub_fields'] as $sub ) {
						$sv = isset( $value[ $sub['key'] ] ) ? $value[ $sub['key'] ] : ( isset( $value[ $sub['name'] ] ) ? $value[ $sub['name'] ] : null );
						$recoger( $sub, $sv );
					}
				}
				break;
		}
	};
	$objs = get_field_objects( $post_id, false );
	foreach ( (array) $objs as $f ) {
		$recoger( $f, isset( $f['value'] ) ? $f['value'] : null );
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

// Ordena las fotos de una ficha.
function cl_carpeta_ordenar_ficha( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return 0;
	}
	if ( ! in_array( get_post_type( $post_id ), array( 'emprendimientos', 'alojamiento' ), true ) ) {
		return 0;
	}
	$term = cl_carpeta_de_emprendimiento( cl_emprendimiento_de_ficha( $post_id ) );
	if ( ! $term ) {
		return 0;
	}
	$fotos = cl_fotos_de_ficha( $post_id );
	foreach ( $fotos as $att ) {
		cl_carpeta_asignar_foto( $att, $term );
	}
	return count( $fotos );
}

// ---------- 2) Al guardar una ficha ----------
add_action( 'acf/save_post', 'cl_carpeta_ordenar_ficha', 30 );
add_action( 'save_post', function ( $post_id ) {
	cl_carpeta_ordenar_ficha( $post_id );
}, 99 );

// Si cambia el nombre del emprendimiento, se renombra su carpeta.
add_action( 'post_updated', function ( $post_id, $after, $before ) {
	if ( 'emprendimientos' !== $after->post_type || $after->post_title === $before->post_title ) {
		return;
	}
	$found = get_terms( array(
		'taxonomy' => 'cl_carpeta', 'hide_empty' => false, 'number' => 1, 'fields' => 'ids',
		'meta_key' => 'cl_emprendimiento_id', 'meta_value' => (int) $post_id,
	) );
	if ( ! is_wp_error( $found ) && $found ) {
		wp_update_term( (int) $found[0], 'cl_carpeta', array( 'name' => $after->post_title ) );
	}
}, 10, 3 );

// ---------- 3) Subidas de Emprendedores ----------
// Carpeta real en el servidor.
add_filter( 'upload_dir', function ( $dirs ) {
	if ( ! function_exists( 'cl_es_emprendedor' ) || ! cl_es_emprendedor() ) {
		return $dirs;
	}
	$uid  = get_current_user_id();
	$emp  = cl_emprendimiento_de_usuario( $uid );
	$slug = $emp ? get_post_field( 'post_name', $emp ) : '';
	$slug = $slug ? sanitize_title( $slug ) : 'usuario-' . $uid;
	$sub  = '/emprendimientos/' . $slug;
	$dirs['subdir'] = $sub;
	$dirs['path']   = $dirs['basedir'] . $sub;
	$dirs['url']    = $dirs['baseurl'] . $sub;
	return $dirs;
} );

// Carpeta de la biblioteca apenas se sube.
add_action( 'add_attachment', function ( $att_id ) {
	if ( ! function_exists( 'cl_es_emprendedor' ) || ! cl_es_emprendedor() ) {
		return;
	}
	$emp = cl_emprendimiento_de_usuario( get_current_user_id() );
	if ( $emp ) {
		cl_carpeta_asignar_foto( $att_id, cl_carpeta_de_emprendimiento( $emp ) );
	}
} );

// ---------- 4) Ordenar las fichas existentes (una sola vez) ----------
add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) || get_option( 'cl_carpetas_v1' ) ) {
		return;
	}
	$fichas = get_posts( array(
		'post_type'   => array( 'emprendimientos', 'alojamiento' ),
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	) );
	$n = 0;
	foreach ( $fichas as $id ) {
		$n += cl_carpeta_ordenar_ficha( $id );
	}
	update_option( 'cl_carpetas_v1', gmdate( 'Y-m-d H:i' ) . " — {$n} fotos revisadas en " . count( $fichas ) . ' fichas' );
} );

add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( $screen && 'upload' === $screen->id && current_user_can( 'manage_options' ) && get_option( 'cl_carpetas_v1' ) && ! get_option( 'cl_carpetas_aviso_visto' ) ) {
		echo '<div class="notice notice-success"><p><strong>CL – Carpetas:</strong> ' . esc_html( get_option( 'cl_carpetas_v1' ) ) . '. Usá el filtro "Todas las carpetas" para verlas.</p></div>';
		update_option( 'cl_carpetas_aviso_visto', 1 );
	}
} );

// ---------- Filtro en Medios ----------
// Vista lista.
add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'attachment' !== $post_type ) {
		return;
	}
	wp_dropdown_categories( array(
		'taxonomy'        => 'cl_carpeta',
		'name'            => 'cl_carpeta',
		'value_field'     => 'slug',
		'show_option_all' => 'Todas las carpetas',
		'hierarchical'    => true,
		'hide_empty'      => false,
		'selected'        => isset( $_GET['cl_carpeta'] ) ? sanitize_title( wp_unslash( $_GET['cl_carpeta'] ) ) : '',
	) );
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'attachment' !== $q->get( 'post_type' ) || empty( $_GET['cl_carpeta'] ) ) {
		return;
	}
	$q->set( 'tax_query', array( array(
		'taxonomy' => 'cl_carpeta',
		'field'    => 'slug',
		'terms'    => sanitize_title( wp_unslash( $_GET['cl_carpeta'] ) ),
	) ) );
} );

// Vista cuadrícula (y ventana de "Añadir medios").
add_filter( 'ajax_query_attachments_args', function ( $query ) {
	if ( ! empty( $_REQUEST['query']['cl_carpeta'] ) ) {
		$query['tax_query'] = array( array(
			'taxonomy' => 'cl_carpeta',
			'field'    => 'slug',
			'terms'    => sanitize_title( wp_unslash( $_REQUEST['query']['cl_carpeta'] ) ),
		) );
	}
	return $query;
} );

add_action( 'wp_enqueue_media', function () {
	if ( ! is_admin() || ! current_user_can( 'upload_files' ) ) {
		return;
	}
	$terms = get_terms( array( 'taxonomy' => 'cl_carpeta', 'hide_empty' => false, 'orderby' => 'name' ) );
	if ( is_wp_error( $terms ) ) {
		return;
	}
	$lista = array();
	foreach ( $terms as $t ) {
		$prefijo = $t->parent ? '— ' : '';
		$lista[] = array( 'slug' => $t->slug, 'name' => $prefijo . $t->name, 'parent' => (int) $t->parent );
	}
	// Madres primero, hijas debajo.
	usort( $lista, function ( $a, $b ) {
		return strcmp( ( $a['parent'] ? '1' : '0' ) . $a['name'], ( $b['parent'] ? '1' : '0' ) . $b['name'] );
	} );
	$js = 'window.clCarpetas=' . wp_json_encode( $lista ) . ';
(function($){
	if(!window.wp||!wp.media||!wp.media.view||!wp.media.view.AttachmentFilters){return;}
	var Filtro = wp.media.view.AttachmentFilters.extend({
		id: "cl-carpeta-filtro",
		createFilters: function(){
			var f = { all: { text: "Todas las carpetas", props: { cl_carpeta: "" }, priority: 1 } };
			_.each(window.clCarpetas, function(t, i){
				f[t.slug] = { text: t.name, props: { cl_carpeta: t.slug }, priority: 10 + i };
			});
			this.filters = f;
		}
	});
	var Browser = wp.media.view.AttachmentsBrowser;
	wp.media.view.AttachmentsBrowser = Browser.extend({
		createToolbar: function(){
			Browser.prototype.createToolbar.call(this);
			this.toolbar.set("clCarpetaFiltro", new Filtro({
				controller: this.controller,
				model: this.collection.props,
				priority: -75
			}).render());
		}
	});
})(jQuery);';
	wp_add_inline_script( 'media-views', $js );
} );