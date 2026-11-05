<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 20 «CL – Panel» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — Panel del emprendedor (fase 4, v17.5, oct. 2026)
 * v17.5: «Mi panel» en Bricks (página 22812): {echo:cl_pi('dato')} da los datos y [cl_panel_admin_emp] el selector de
 *        carga asistida. [cl_panel_inicio] queda sin uso (se quita al pasar a plugin).
 * v17.4: la ayuda que va debajo de un campo queda pegada a él (6 px) en todos los formularios del panel.
 *        Se reconoce por el párrafo con margen negativo o con data-cl.
 * v17.3: «Cómo es llegar y estar»: pregunta opcional «Inodoro» (Con descarga de agua · Seco (compostable)) para el
 *        baño de quienes vienen por el día. Se vacía si se marca «Sin baño». No cuenta en el progreso.
 * v17.2: «Cómo es llegar y estar» queda en 3 preguntas cerradas (cómo se llega, porteras, baño) y una abierta:
 *        «Cómo llegar» pasa del paso Ubicación a este paso. Se sacan camino, km sin asfalto, auto bajo, señal,
 *        sombra y agua. «Porteras» arranca en Sí si nunca se respondió.
 * v17.1: «¿Cómo llega la gente?» admite varias opciones; baño para quienes vienen por el día (Hay baño · Sin baño);
 *        tarjeta de inscripción aclara que es informativa y no obligatoria; títulos de grupos de opciones sin mayúsculas.
 * v17: «Cómo es llegar y estar» (/panel/llegar-y-estar/): nivel 2 de condiciones, solo para quien recibe visitantes.
 *      Tarjeta en Mi panel con cuántas preguntas respondió. Todo opcional.
 * v16: «Mi inscripción y responsabilidades» (/panel/inscripcion/, privada): habilitación de MINTUR y seguro,
 *      con guía. Tarjeta nueva en Mi panel. Nunca se publica.
 * v15.1: «Temas» arranca plegado, con «Ver menos» y cuántos hay elegidos; títulos de grupos de casillas sin mayúsculas;
 *        «Mis eventos» pasa a «Mis experiencias o eventos».
 * v15: paso «Qué ofrecés»: el rubro va primero y decide qué se muestra. Formas de andar solo para Actividades;
 *      alojamiento y eventos se activan solos según el rubro; «Temas» queda plegado y opcional.
 *      Las categorías que no son Formas de andar (Alojamiento, Servicios…) no se tocan desde el panel.
 * v8: "Acceso" / "Mi panel" al final del menú principal.
 * v9: "Mi panel" sin salto de línea en el menú.
 * v10: textos largos sin encimarse; Facebook acepta @usuario; teléfono más flexible.
 * v11: paso 6 "Otros datos" (idiomas, qué visitar, recomendaciones, participación, redes/certificaciones).
 * v14: casillas obligatorias sin falso aviso de Bricks; contador de caracteres; "Siguiente"/"Anterior" guardan antes de cambiar de paso; aviso al salir con cambios sin guardar.
 * v13: noindex que sí funciona en /panel y en la vista previa (el valor lo pisaba otro plugin); en la ficha pública se ocultan las secciones vacías del acordeón.
 * v12: "Ver cómo queda" (?paso=vista): tarjeta, lista antes de publicar, enviar a revisión, vista previa del borrador, email al publicar.
 * v7: pasos Ubicación y Contacto; [cl_panel_mapa] muestra el mapa actual; "Cuenta con sitio web" automático.
 * v6: Equipamiento (Bicicletas, Canoas, Caballos) también para emprendimientos.
 * v5: paso Actividades (categorías, temas, descripción, alojamiento, eventos).
 * v4: estados en español en el selector; botón de ayuda por WhatsApp (099 247 249).
 * v3: shortcode [cl_panel_inicio] para Mi panel (tarjetas, progreso, carga asistida).
 * v2: breve descripción hasta 180 caracteres; recarga la página después de guardar.
 * Code Snippets: tipo PHP, "Ejecutar en todas partes". Sin <?php.
 * Necesita "CL – Roles del back office" (función cl_es_emprendedor).
 *
 * - /panel y sus subpáginas: solo Emprendedores y administradores. Sin índice en buscadores.
 * - /escritorio, /escritorio2 y sus subpáginas redirigen a /panel/.
 * - Al iniciar sesión, un Emprendedor va a /panel/.
 * - Valores actuales de la ficha para rellenar los formularios: {echo:cl_panel_valor('campo')}.
 * - Guardado de los formularios del panel (acción personalizada de Bricks), con controles en el servidor:
 *   dueño de la ficha, campos permitidos por paso, límites, campos sensibles en espera y aviso al admin.
 */

define( 'CL_PANEL_PAGE_ID', 22812 );          // Página "Panel" (/panel/)
define( 'CL_ESCRITORIO_IDS', array( 2470, 18999 ) ); // /escritorio y /escritorio2
// Formas de andar (categorías que elige el emprendedor): Senderismo, Cabalgatas, Talleres, Educación ambiental,
// Retiros, Gastronomía local, Observación de naturaleza, Bicicleta, Voluntariado.
define( 'CL_FORMAS_DE_ANDAR', array( 98, 88, 111, 108, 110, 116, 202, 203, 204 ) );

// ID de un término de rubro por su slug (alojamiento, actividades-y-experiencias…).
function cl_panel_rubro_id( $slug ) {
	$t = get_term_by( 'slug', $slug, 'rubro' );
	return $t ? (int) $t->term_id : 0;
}

// ¿El emprendimiento tiene ese rubro?
function cl_panel_tiene_rubro( $id, $slug ) {
	$r = cl_panel_rubro_id( $slug );
	return $r && has_term( $r, 'rubro', $id );
}

// Recibe visitantes = Alojamiento o Actividades. Los demás rubros son perfiles de servicio.
function cl_panel_recibe_visitantes( $id ) {
	return cl_panel_tiene_rubro( $id, 'alojamiento' ) || cl_panel_tiene_rubro( $id, 'actividades-y-experiencias' );
}

// Alojamiento y eventos se activan según el rubro (o si ya hay alguno cargado).
function cl_panel_modulos_segun_rubro( $id ) {
	$aloj = cl_panel_tiene_rubro( $id, 'alojamiento' ) || ( function_exists( 'cl_aloj_de_emp' ) && cl_aloj_de_emp( $id ) );
	$ev   = cl_panel_tiene_rubro( $id, 'actividades-y-experiencias' ) || ( function_exists( 'cl_ev_de_emp' ) && cl_ev_de_emp( $id ) );
	update_field( 'field_60fdc64938cac', $aloj ? 'Sí' : 'No', $id ); // ofrece_alojamiento
	update_field( 'field_67210edb742e9', $ev ? 'Sí' : 'No', $id );   // publica_eventos
}

// ---------- Utilidades ----------

// ¿La página actual es /panel o una subpágina?
function cl_panel_es_pagina( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_queried_object_id();
	if ( ! $post_id || 'page' !== get_post_type( $post_id ) ) {
		return false;
	}
	return (int) $post_id === CL_PANEL_PAGE_ID || in_array( CL_PANEL_PAGE_ID, get_post_ancestors( $post_id ), true );
}

// ¿Puede usar el panel? Emprendedor o administrador.
function cl_panel_puede_usar() {
	return is_user_logged_in() && ( current_user_can( 'manage_options' ) || ( function_exists( 'cl_es_emprendedor' ) && cl_es_emprendedor() ) );
}

// Emprendimiento que se está editando.
// Emprendedor: siempre el suyo (nunca se toma de la URL).
// Administrador: el de ?emp=ID (carga asistida); sin parámetro, ninguno.
function cl_panel_emp_id( $pedido = null ) {
	if ( ! is_user_logged_in() ) {
		return 0;
	}
	if ( current_user_can( 'manage_options' ) ) {
		$id = null !== $pedido ? (int) $pedido : ( isset( $_GET['emp'] ) ? (int) $_GET['emp'] : 0 );
		return ( $id && 'emprendimientos' === get_post_type( $id ) ) ? $id : 0;
	}
	if ( function_exists( 'cl_es_emprendedor' ) && cl_es_emprendedor() ) {
		$ids = get_posts( array(
			'post_type'   => 'emprendimientos',
			'post_status' => array( 'publish', 'draft', 'pending' ),
			'author'      => get_current_user_id(),
			'numberposts' => 1,
			'orderby'     => 'ID',
			'order'       => 'ASC',
			'fields'      => 'ids',
		) );
		return $ids ? (int) $ids[0] : 0;
	}
	return 0;
}

// Contenido con bloques → texto plano con párrafos separados por línea en blanco.
function cl_panel_contenido_a_texto( $html ) {
	$html = preg_replace( '/<!--.*?-->/s', '', (string) $html );
	$html = preg_replace( '#</p>\s*#i', "\n\n", $html );
	$html = preg_replace( '#<br\s*/?>#i', "\n", $html );
	return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
}

// Texto plano → párrafos en bloques de WordPress.
function cl_panel_texto_a_bloques( $texto ) {
	$parrafos = preg_split( "/\n\s*\n/", str_replace( "\r", '', (string) $texto ) );
	$out = array();
	foreach ( $parrafos as $p ) {
		$p = trim( $p );
		if ( '' === $p ) {
			continue;
		}
		$p     = nl2br( esc_html( $p ), false );
		$out[] = "<!-- wp:paragraph -->\n<p>{$p}</p>\n<!-- /wp:paragraph -->";
	}
	return implode( "\n\n", $out );
}


// ---------- Acceso y redirecciones ----------

add_action( 'template_redirect', function () {
	if ( ! is_page() ) {
		return;
	}
	$id = get_queried_object_id();

	// Páginas viejas → /panel/
	foreach ( CL_ESCRITORIO_IDS as $viejo ) {
		if ( $id === $viejo || in_array( $viejo, get_post_ancestors( $id ), true ) ) {
			wp_safe_redirect( get_permalink( CL_PANEL_PAGE_ID ), 301 );
			exit;
		}
	}

	if ( ! cl_panel_es_pagina( $id ) ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink( $id ) ) );
		exit;
	}
	if ( ! cl_panel_puede_usar() ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
	nocache_headers();
} );

// Después de guardar, recargar la página para mostrar los valores guardados
// (el formulario de Bricks vuelve a los valores iniciales al enviarse).
add_action( 'wp_footer', function () {
	if ( ! is_page() || ! cl_panel_es_pagina() ) {
		return;
	}
	// Bricks aplica la "altura" del campo a toda la caja (etiqueta incluida)
	// y el textarea se desborda sobre el campo siguiente: se corrige acá.
	echo '<style>.brxe-form .form-group:has(textarea){height:auto!important}.brxe-form .form-group textarea{height:auto!important;min-height:120px;max-height:480px;field-sizing:content;resize:vertical}.brxe-form textarea[name=form-field-clmapa]{max-height:130px;font-size:14px}</style>';
	echo '<style>.clp-cnt{font-size:13px;color:#7a8583;text-align:right;margin-top:4px}.clp-cnt-casi{color:#b35c00;font-weight:700}</style>';
	// Ayuda debajo de un campo (párrafo de un campo HTML con margen negativo o data-cl): pegada al campo de arriba.
	echo '<style>.brxe-form .form-group:has(+ .form-group > p:first-child:is([style*="margin:-"],[data-cl])){padding-bottom:0!important;margin-bottom:0!important}.brxe-form .form-group > p:first-child:is([style*="margin:-"],[data-cl]){margin-top:6px!important}</style>';
	?>
<script>
(function(){
	// Guardar sin perder nada: contador de caracteres, "Siguiente" guarda antes de pasar de paso,
	// aviso del navegador si se sale con cambios sin guardar, y recarga después de guardar.
	var sucio = false, destino = null, enviando = false;
	function enForm(el){ return el && el.closest && el.closest('form.brxe-form'); }
	function marcar(e){ if (enForm(e.target)) { sucio = true; destino = null; } }
	document.addEventListener('input', marcar, true);
	document.addEventListener('change', marcar, true);
	document.addEventListener('submit', function(e){ if (e.target.matches && e.target.matches('form.brxe-form')) { enviando = true; } }, true);

	// Casillas obligatorias: Bricks marca cada casilla como obligatoria y el navegador pedía tildarlas
	// todas si no se tocaban. Con una marcada alcanza; si no hay ninguna, se exige solo la primera.
	function casillas(nombre){ return document.querySelectorAll('form.brxe-form input[type=checkbox][name="' + nombre + '"]'); }
	function ajustar(nombre){
		var g = casillas(nombre), alguna = [].some.call(g, function(c){ return c.checked; });
		[].forEach.call(g, function(c, i){ c.required = !alguna && i === 0; });
	}
	var grupos = {};
	document.querySelectorAll('form.brxe-form input[type=checkbox][required]').forEach(function(c){ grupos[c.name] = true; });
	Object.keys(grupos).forEach(function(n){
		ajustar(n);
		[].forEach.call(casillas(n), function(c){ c.addEventListener('change', function(){ ajustar(n); }); });
	});

	document.querySelectorAll('form.brxe-form input[maxlength], form.brxe-form textarea[maxlength]').forEach(function(el){
		var max = parseInt(el.getAttribute('maxlength'), 10);
		if (!max || max < 100 || el.type === 'hidden' || el.closest('.form-group').offsetHeight < 5) { return; }
		var c = document.createElement('div'); c.className = 'clp-cnt';
		el.insertAdjacentElement('afterend', c);
		function act(){ var n = el.value.length; c.textContent = n + ' / ' + max; c.classList.toggle('clp-cnt-casi', n >= max * 0.9); }
		el.addEventListener('input', act); act();
	});

	document.addEventListener('click', function(e){
		var a = e.target.closest && e.target.closest('a[href]');
		if (!a || !sucio || a.target === '_blank' || a.href.indexOf('/panel/') === -1) { return; }
		var f = [].filter.call(document.querySelectorAll('form.brxe-form'), function(x){ return x.offsetParent !== null; })[0];
		if (!f) { return; }
		e.preventDefault();
		destino = a.href;
		var b = f.querySelector('button[type=submit]');
		if (b) { b.click(); } else if (f.requestSubmit) { f.requestSubmit(); }
	}, true);

	document.addEventListener('bricks/form/success', function(e){
		var d = e.detail || {}, r = d.response || d.res || {};
		if (r.data && r.data.type === 'error') { destino = null; enviando = false; return; }
		sucio = false;
		var ir = destino; destino = null;
		setTimeout(function(){ if (ir) { location.href = ir; } else { location.reload(); } }, ir ? 700 : 1200);
	});
	document.addEventListener('bricks/form/error', function(){ destino = null; enviando = false; });

	window.addEventListener('beforeunload', function(e){ if (sucio && !enviando) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
<style>.clp-rdesc{display:block;font-size:14px;color:#5b6a68;line-height:1.35;margin-top:2px}.clp-mas{border:1px solid #e0dacd;border-radius:12px;background:#fbfaf6;padding:0 14px;margin-bottom:20px}.clp-mas>summary{min-height:48px;display:flex;align-items:center;font-weight:700;color:#1f4645;cursor:pointer}.clp-mas-tit{display:none}.clp-mas[open]>summary .clp-mas-ab,.clp-mas:not([open])>summary .clp-mas-ce{display:none}.clp-mas-menos{display:block;margin:4px 0 14px;padding:0;border:0;background:none;color:#9a6055;font-weight:700;font-size:15px;cursor:pointer;font-family:inherit}.brxe-form legend,.brxe-form .form-group>.label{text-transform:none!important;letter-spacing:0!important;font-weight:700;font-size:16px;color:#23302f;margin-bottom:6px}</style>
<script>
(function(){
	// Paso «Qué ofrecés»: el rubro decide qué preguntas se ven.
	var R = <?php echo wp_json_encode( cl_panel_rubros_js(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE ); ?>;
	var cajas = document.querySelectorAll('form.brxe-form input[name="form-field-clrubr[]"]');
	if (!cajas.length) { return; }
	var form = cajas[0].form;
	[].forEach.call(cajas, function(c){
		var d = R.desc[c.value]; if (!d) { return; }
		var l = (c.id && form.querySelector('label[for="' + c.id + '"]')) || c.parentNode;
		var s = document.createElement('span'); s.className = 'clp-rdesc'; s.textContent = d; l.appendChild(s);
	});
	function grupo(id){
		var el = form.querySelector('[name="form-field-' + id + '"], [name="form-field-' + id + '[]"], [data-cl="' + id + '"]');
		return el && el.closest('.form-group');
	}
	var porRubro = { alojamiento: ['clbral', 'clhalo'], actividades: ['clcats', 'clhact', 'clacts', 'clequi'] };
	function mostrar(){
		var sel = [].filter.call(cajas, function(c){ return c.checked; }).map(function(c){ return c.value; });
		Object.keys(porRubro).forEach(function(k){
			var on = sel.indexOf(String(R[k])) > -1;
			porRubro[k].forEach(function(id){ var g = grupo(id); if (g) { g.style.display = on ? '' : 'none'; } });
		});
	}
	[].forEach.call(cajas, function(c){ c.addEventListener('change', mostrar); });
	mostrar();
	// «Temas que te identifican»: plegado, para quien quiera ir al detalle.
	var gt = grupo('cltags');
	if (gt) {
		var det = document.createElement('details'); det.className = 'clp-mas';
		var sum = document.createElement('summary');
		sum.innerHTML = '<span class="clp-mas-ab">Ver temas que te identifican (opcional) <span class="clp-mas-n"></span> ▾</span><span class="clp-mas-ce">Temas que te identifican ▴</span>';
		det.appendChild(sum); gt.parentNode.insertBefore(det, gt); det.appendChild(gt);
		// El título propio del grupo se repetía: se oculta.
		[].forEach.call(gt.children, function(el){ if (/^(LABEL|LEGEND)$/.test(el.tagName) || el.classList.contains('label')) { el.classList.add('clp-mas-tit'); } });
		var fs = gt.querySelector('legend'); if (fs) { fs.classList.add('clp-mas-tit'); }
		var menos = document.createElement('button'); menos.type = 'button'; menos.className = 'clp-mas-menos'; menos.textContent = 'Ver menos ▴';
		menos.addEventListener('click', function(){ det.open = false; det.scrollIntoView({ block: 'nearest' }); });
		det.appendChild(menos);
		function contar(){
			var n = gt.querySelectorAll('input:checked').length;
			det.querySelector('.clp-mas-n').textContent = n ? '· ' + n + (n > 1 ? ' elegidos' : ' elegido') : '';
		}
		gt.addEventListener('change', contar); contar();
	}
})();
</script>
	<?php
} );

// «Mis eventos» → «Mis experiencias o eventos» en Mi panel y en las páginas de eventos del panel.
add_filter( 'do_shortcode_tag', function ( $out, $tag ) {
	if ( in_array( $tag, array( 'cl_panel_inicio', 'cl_panel_eventos', 'cl_panel_ev_cabecera', 'cl_panel_ev_vista' ), true ) ) {
		$out = str_replace( 'Mis eventos', 'Mis experiencias o eventos', $out );
	}
	return $out;
}, 20, 2 );

// Datos para el script del paso «Qué ofrecés»: IDs de Alojamiento y Actividades, y la descripción de cada rubro.
function cl_panel_rubros_js() {
	$desc  = array();
	$terms = get_terms( array( 'taxonomy' => 'rubro', 'hide_empty' => false ) );
	foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
		if ( '' !== trim( $t->description ) ) {
			$desc[ $t->term_id ] = wp_strip_all_tags( $t->description );
		}
	}
	return array(
		'alojamiento' => cl_panel_rubro_id( 'alojamiento' ),
		'actividades' => cl_panel_rubro_id( 'actividades-y-experiencias' ),
		'desc'        => $desc,
	);
}

// Sin índice en buscadores.
// Sin índice: /panel y la vista previa de fichas. Se ejecuta al final (otro plugin pisaba el valor)
// y además se manda el encabezado X-Robots-Tag, que ningún plugin de SEO puede cambiar.
function cl_panel_sin_indice() {
	return ( is_page() && cl_panel_es_pagina() ) || ! empty( $_GET['cl_vista'] );
}
add_filter( 'wp_robots', function ( $robots ) {
	return cl_panel_sin_indice() ? array( 'noindex' => true, 'nofollow' => true ) : $robots;
}, 99999 );
add_action( 'template_redirect', function () {
	if ( cl_panel_sin_indice() && ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}
}, 99 );

// Después de iniciar sesión, los Emprendedores van al panel.
add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
	if ( $user instanceof WP_User && function_exists( 'cl_es_emprendedor' ) && cl_es_emprendedor( $user ) ) {
		return ( $requested && false !== strpos( $requested, '/panel' ) ) ? $requested : get_permalink( CL_PANEL_PAGE_ID );
	}
	return $redirect_to;
}, 10, 3 );

// ---------- Valores actuales para los formularios ----------

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	$names   = (array) $names;
	$names[] = 'cl_panel_valor';
	$names[] = 'cl_panel_pendiente';
	return $names;
} );

// {echo:cl_panel_valor('campo')} — valor actual (texto plano) de la ficha que se edita.
function cl_panel_valor( $campo ) {
	if ( ! cl_panel_puede_usar() ) {
		return '';
	}
	$id = cl_panel_emp_id();
	if ( ! $id ) {
		return '';
	}
	switch ( $campo ) {
		case 'titulo':
			$pend = get_post_meta( $id, '_cl_pendiente', true );
			return isset( $pend['titulo']['valor'] ) ? $pend['titulo']['valor'] : get_the_title( $id );
		case 'contenido':
			return cl_panel_contenido_a_texto( get_post_field( 'post_content', $id ) );
		case 'emp_id':
			return (string) $id;
		case 'idiomas_lista':
		case 'idiomas_otro':
			$sep = cl_panel_idiomas_separar( get_field( 'idiomas', $id, false ) );
			return 'idiomas_lista' === $campo ? implode( ',', $sep[0] ) : implode( ', ', $sep[1] );
		default:
			if ( ! array_key_exists( $campo, cl_panel_campos_permitidos() ) ) {
				return '';
			}
			$v     = cl_panel_leer( $campo, $id );
			$reg   = cl_panel_campos_permitidos();
			$tipo  = $reg[ $campo ]['tipo'];
			if ( 'ficha_completa' === $campo && '' === $v ) {
				return 'No';
			}
			if ( 'servicio_publico' === $campo && '' === $v ) {
				return 'Sí'; // Servicios: arrancan visibles al público.
			}
			if ( 'porteras' === $campo && '' === $v ) {
				return 'Sí'; // Casi todos piden cerrar las porteras: arranca marcado.
			}
			if ( 'sino' === $tipo ) {
				return '1' === $v ? 'Sí' : ( '0' === $v ? 'No' : '' );
			}
			if ( 'fecha' === $tipo ) {
				return preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $v, $m ) ? $m[3] . '/' . $m[2] . '/' . $m[1] : '';
			}
			return is_array( $v ) ? implode( ',', $v ) : (string) $v;
	}
}

// Valor actual de un campo permitido, en el mismo formato que devuelve cl_panel_limpiar().
function cl_panel_leer( $campo, $id ) {
	$r = cl_panel_campos_permitidos();
	$r = isset( $r[ $campo ] ) ? $r[ $campo ] : array( 'tipo' => '' );
	if ( 'tax' === $r['tipo'] ) {
		$ids = wp_get_object_terms( $id, $r['tax'], array( 'fields' => 'ids' ) );
		$ids = is_wp_error( $ids ) ? array() : array_values( array_diff( array_map( 'intval', $ids ), array( 1 ) ) );
		if ( ! empty( $r['padre'] ) ) {
			$hijos = get_terms( array( 'taxonomy' => $r['tax'], 'hide_empty' => false, 'fields' => 'ids', 'parent' => (int) $r['padre'] ) );
			$ids   = array_values( array_intersect( $ids, is_wp_error( $hijos ) ? array() : array_map( 'intval', $hijos ) ) );
		}
		if ( ! empty( $r['solo'] ) ) {
			$ids = array_values( array_intersect( $ids, $r['solo'] ) );
		}
		sort( $ids );
		return $ids;
	}
	$v = get_field( $campo, $id, false );
	if ( 'opciones' === $r['tipo'] ) {
		$v = array_values( array_intersect( $r['opciones'], (array) maybe_unserialize( $v ) ) );
		return $v;
	}
	if ( 'html' === $r['tipo'] ) {
		return cl_panel_contenido_a_texto( (string) $v );
	}
	return is_scalar( $v ) ? (string) $v : '';
}

// Escribe un campo permitido.
function cl_panel_escribir( $campo, $valor, $id ) {
	$r = cl_panel_campos_permitidos();
	$r = $r[ $campo ];
	if ( 'tax' === $r['tipo'] ) {
		if ( ! empty( $r['padre'] ) ) {
			// Solo se reemplazan los términos de esa rama; los demás quedan como estaban.
			$todos  = wp_get_object_terms( $id, $r['tax'], array( 'fields' => 'ids' ) );
			$todos  = is_wp_error( $todos ) ? array() : array_map( 'intval', $todos );
			$hijos  = get_terms( array( 'taxonomy' => $r['tax'], 'hide_empty' => false, 'fields' => 'ids', 'parent' => (int) $r['padre'] ) );
			$hijos  = is_wp_error( $hijos ) ? array() : array_map( 'intval', $hijos );
			$valor  = array_merge( array_diff( $todos, $hijos ), $valor );
		}
		if ( ! empty( $r['solo'] ) ) {
			// Solo se reemplazan los términos de la lista; los demás (Alojamiento, Servicios…) quedan.
			$todos = wp_get_object_terms( $id, $r['tax'], array( 'fields' => 'ids' ) );
			$todos = is_wp_error( $todos ) ? array() : array_map( 'intval', $todos );
			$valor = array_merge( array_diff( $todos, $r['solo'] ), $valor );
		}
		wp_set_object_terms( $id, array_values( array_unique( $valor ) ), $r['tax'], false );
		return;
	}
	if ( 'html' === $r['tipo'] ) {
		$valor = wpautop( esc_html( $valor ) );
	}
	$obj = get_field_object( $campo, $id, false, false );
	update_field( $obj && ! empty( $obj['key'] ) ? $obj['key'] : $campo, $valor, $id );
	if ( 'url_sitio_web' === $campo ) {
		update_field( 'cuenta_con_sitio_web', '' !== $valor ? 1 : 0, $id ); // Se completa solo según haya URL.
	}
}

// Idiomas: casillas para los más comunes y un texto libre para el resto. En la ficha se guarda
// como un solo texto: "Español, Inglés, Guaraní".
function cl_panel_idiomas_conocidos() {
	return array( 'Español', 'Inglés', 'Portugués', 'Francés' );
}
function cl_panel_idiomas_separar( $texto ) {
	$conocidos = cl_panel_idiomas_conocidos();
	$lista     = array();
	$otros     = array();
	foreach ( preg_split( '/\s*(?:,|;|\/|\by\b|\be\b)\s*/iu', (string) $texto ) as $t ) {
		$t = trim( $t, " \t\n\r.-" );
		if ( '' === $t ) {
			continue;
		}
		$match = null;
		foreach ( $conocidos as $c ) {
			if ( 0 === strcasecmp( remove_accents( $c ), remove_accents( $t ) ) ) {
				$match = $c;
			}
		}
		if ( $match ) {
			$lista[] = $match;
		} else {
			$otros[] = mb_strtoupper( mb_substr( $t, 0, 1 ) ) . mb_substr( $t, 1 );
		}
	}
	return array( array_values( array_intersect( $conocidos, $lista ) ), array_values( array_unique( $otros ) ) );
}
function cl_panel_idiomas_juntar( $casillas, $otros ) {
	$casillas = is_array( $casillas ) ? $casillas : explode( ',', (string) $casillas );
	$casillas = array_values( array_intersect( cl_panel_idiomas_conocidos(), array_map( 'trim', $casillas ) ) );
	$extra    = cl_panel_idiomas_separar( sanitize_text_field( wp_unslash( (string) $otros ) ) );
	return implode( ', ', array_values( array_unique( array_merge( $casillas, $extra[0], $extra[1] ) ) ) );
}

// {echo:cl_panel_pendiente('campo')} — "1" si ese campo tiene un cambio esperando aprobación.
function cl_panel_pendiente( $campo ) {
	$id = cl_panel_emp_id();
	if ( ! $id ) {
		return '';
	}
	$pend = get_post_meta( $id, '_cl_pendiente', true );
	return isset( $pend[ $campo ] ) ? '1' : '';
}

// ---------- Reglas de guardado ----------

// «¿Qué hay en tu lugar?» (v1.0.2): las mismas opciones que el campo ACF en_el_lugar.
function cl_en_el_lugar_opciones() {
	return array( 'Agua potable', 'Sombra', 'Lugar para sentarse', 'Lugar para comer', 'Electricidad', 'Señal de celular', 'Wifi', 'Estacionamiento' );
}

// Campos que el panel puede escribir, con su tipo y límite. Lo que no está acá no se guarda nunca.
function cl_panel_campos_permitidos() {
	return array(
		// Paso Presentación
		'breve_descripcion'        => array( 'tipo' => 'texto', 'max' => 180 ),
		'quienes_somos'            => array( 'tipo' => 'textarea', 'max' => 1200 ),
		'url_video_emprendimiento' => array( 'tipo' => 'video', 'max' => 300 ),
		// Paso Qué ofrecés (slug del paso: actividades)
		'rubros'                        => array( 'tipo' => 'tax', 'tax' => 'rubro', 'min' => 1, 'error' => 'Elegí al menos una opción en «¿Qué ofrecés?».' ),
		'categorias'                    => array( 'tipo' => 'tax', 'tax' => 'category', 'solo' => CL_FORMAS_DE_ANDAR ), // obligatoria solo con Actividades
		'etiquetas'                     => array( 'tipo' => 'tax', 'tax' => 'post_tag' ),
		'equipamiento' => array( 'tipo' => 'tax', 'tax' => 'equipamiento' ),
		'actividades_descripcion'       => array( 'tipo' => 'html', 'max' => 1500 ),
		'ofrece_alojamiento'            => array( 'tipo' => 'opcion', 'opciones' => array( 'Sí', 'No' ) ),
		'breve_descripcion_alojamiento' => array( 'tipo' => 'textarea', 'max' => 600 ),
		'publica_eventos'               => array( 'tipo' => 'opcion', 'opciones' => array( 'Sí', 'No' ) ),
		'ficha_completa'                => array( 'tipo' => 'opcion', 'opciones' => array( 'Sí', 'No' ) ), // 1.1.0, solo sin Alojamiento ni Actividades
		// Paso Ubicación
		'region'                        => array( 'tipo' => 'tax', 'tax' => 'region', 'unico' => true, 'min' => 1, 'error' => 'Elegí la región.' ),
		'localidad'                     => array( 'tipo' => 'texto', 'max' => 200, 'obligatorio' => 'Escribí la localidad.' ),
		'ubicacion'                     => array( 'tipo' => 'mapa', 'max' => 3000 ),
		'direccion_o_punto_de_encuentro' => array( 'tipo' => 'textarea', 'max' => 300 ),
		// Paso Ubicación, solo servicios (1.1.0)
		'servicio_alcance'              => array( 'tipo' => 'opcion', 'opciones' => array( 'Solo en mi localidad', 'Hasta cierta distancia', 'En toda la región', 'En todo el país' ) ),
		'servicio_km'                   => array( 'tipo' => 'numero', 'min' => 1, 'max' => 500 ),
		'servicio_zona_nota'            => array( 'tipo' => 'texto', 'max' => 150 ),
		// Paso Contacto
		'telefono_contacto'             => array( 'tipo' => 'tel', 'max' => 30 ),
		'email-emp'                     => array( 'tipo' => 'email', 'max' => 100 ),
		'url_sitio_web'                 => array( 'tipo' => 'url', 'max' => 300 ),
		'instagramemp'                  => array( 'tipo' => 'instagram', 'max' => 200 ),
		'facebookemp'                   => array( 'tipo' => 'facebook', 'max' => 300 ),
		// Paso Contacto, solo servicios (1.1.0)
		'servicio_publico'              => array( 'tipo' => 'opcion', 'opciones' => array( 'Sí', 'No' ) ),
		'telefono_publico'              => array( 'tipo' => 'opcion', 'opciones' => array( 'Sí', 'No' ) ),
		// Paso Otros datos
		'idiomas'                       => array( 'tipo' => 'texto', 'max' => 200 ),
		'que_visitar_actividades_zona'  => array( 'tipo' => 'textarea', 'max' => 1200 ),
		'recomendaciones_aclaraciones'  => array( 'tipo' => 'textarea', 'max' => 1200 ),
		'titulo_participacion'          => array( 'tipo' => 'texto', 'max' => 100 ),
		'participacion'                 => array( 'tipo' => 'textarea', 'max' => 1000 ),
		'titulo_redes_certificaciones'  => array( 'tipo' => 'texto', 'max' => 100 ),
		'redes_locales_certificaciones' => array( 'tipo' => 'texto', 'max' => 700 ),
		// Mi inscripción y responsabilidades (privado: nunca se publica)
		'habilitacion_mintur'           => array( 'tipo' => 'opcion', 'opciones' => array( 'Sí', 'En trámite', 'No', 'No sé si la necesito' ) ),
		'seguro_vigente'                => array( 'tipo' => 'sino' ),
		'seguro_vencimiento'            => array( 'tipo' => 'fecha' ),
		// Cómo es llegar y estar (nivel 2, solo quien recibe visitantes)
		'llegada'                       => array( 'tipo' => 'opciones', 'opciones' => array( 'En auto hasta el lugar', 'Punto de encuentro con traslado', 'Punto de encuentro y se sigue a pie' ) ),
		'traslado_nota'                 => array( 'tipo' => 'texto', 'max' => 200 ),
		'como_llegar'                   => array( 'tipo' => 'textarea', 'max' => 500 ),
		'porteras'                      => array( 'tipo' => 'sino' ),
		'bano_tipo'                     => array( 'tipo' => 'opcion', 'opciones' => array( 'Hay baño', 'Sin baño' ) ),
		'bano_inodoro'                  => array( 'tipo' => 'opcion', 'opciones' => array( 'Con descarga de agua', 'Seco (compostable)' ) ),
		'en_el_lugar'                   => array( 'tipo' => 'opciones', 'opciones' => cl_en_el_lugar_opciones() ),
	);
}

// Qué campos del formulario (ID de campo de Bricks) corresponden a cada paso.
function cl_panel_pasos() {
	return array(
		'presentacion' => array(
			'clnomb' => 'titulo',
			'clbrev' => 'breve_descripcion',
			'cldesc' => 'contenido',
			'clquie' => 'quienes_somos',
			'clvide' => 'url_video_emprendimiento',
		),
		'actividades'  => array(
			'clrubr' => 'rubros',
			'clcats' => 'categorias',
			'cltags' => 'etiquetas',
			'clequi' => 'equipamiento',
			'clacts' => 'actividades_descripcion',
			'clofal' => 'ofrece_alojamiento',
			'clbral' => 'breve_descripcion_alojamiento',
			'clpuev' => 'publica_eventos',
			'clfcom' => 'ficha_completa',
		),
		'ubicacion'    => array(
			'clregi' => 'region',
			'clloca' => 'localidad',
			'clmapa' => 'ubicacion',
			'cldire' => 'direccion_o_punto_de_encuentro',
			'clsalc' => 'servicio_alcance',
			'clskm'  => 'servicio_km',
			'clsnot' => 'servicio_zona_nota',
		),
		'contacto'     => array(
			'cltele' => 'telefono_contacto',
			'clmail' => 'email-emp',
			'clweb'  => 'url_sitio_web',
			'clinst' => 'instagramemp',
			'clface' => 'facebookemp',
			'clspub' => 'servicio_publico',
			'cltpub' => 'telefono_publico',
		),
		'otros'        => array(
			'clidio' => 'idiomas', // casillas + "otros idiomas" (clidot), se juntan al guardar
			'clvisi' => 'que_visitar_actividades_zona',
			'clreco' => 'recomendaciones_aclaraciones',
			'cltpar' => 'titulo_participacion',
			'clpart' => 'participacion',
			'cltred' => 'titulo_redes_certificaciones',
			'clred'  => 'redes_locales_certificaciones',
		),
		'inscripcion'  => array(
			'clhabi' => 'habilitacion_mintur',
			'clsegu' => 'seguro_vigente',
			'clsven' => 'seguro_vencimiento',
		),
		'lugar'        => array(
			'clnlle' => 'llegada',
			'clntra' => 'traslado_nota',
			'clnpor' => 'porteras',
			'clnban' => 'bano_tipo',
			'clnino' => 'bano_inodoro',
			'clnell' => 'en_el_lugar',
			'cllleg' => 'como_llegar',
		),
	);
}

// Campos sensibles: el cambio queda en espera hasta que el admin lo aprueba.
function cl_panel_campos_sensibles() {
	return array( 'titulo' => 'Nombre del emprendimiento', 'email-emp' => 'Email del emprendimiento' );
}

// Limpia y valida un valor. Devuelve array( valor, error ).
function cl_panel_limpiar( $campo, $valor ) {
	$reglas = cl_panel_campos_permitidos();
	if ( isset( $reglas[ $campo ] ) && 'tax' === $reglas[ $campo ]['tipo'] ) {
		$r       = $reglas[ $campo ];
		$args    = array( 'taxonomy' => $r['tax'], 'hide_empty' => false, 'fields' => 'ids' );
		if ( ! empty( $r['padre'] ) ) {
			$args['parent'] = (int) $r['padre'];
		}
		$validos = get_terms( $args );
		$validos = is_wp_error( $validos ) ? array() : array_map( 'intval', $validos );
		if ( ! empty( $r['solo'] ) ) {
			$validos = array_values( array_intersect( $validos, $r['solo'] ) );
		}
		$ids     = array_map( 'intval', is_array( $valor ) ? $valor : explode( ',', (string) $valor ) );
		$ids     = array_values( array_diff( array_intersect( array_unique( $ids ), $validos ), array( 1 ) ) );
		sort( $ids );
		if ( ! empty( $r['unico'] ) ) {
			$ids = array_slice( $ids, 0, 1 );
		}
		if ( ! empty( $r['min'] ) && count( $ids ) < $r['min'] ) {
			return array( null, $r['error'] );
		}
		return array( $ids, '' );
	}
	if ( isset( $reglas[ $campo ] ) && 'opciones' === $reglas[ $campo ]['tipo'] ) {
		// Varias casillas: se guardan las válidas, en el orden de la lista.
		$r        = $reglas[ $campo ];
		$elegidas = is_array( $valor ) ? array_map( 'wp_unslash', $valor ) : explode( ',', (string) $valor );
		return array( array_values( array_intersect( $r['opciones'], array_map( 'trim', $elegidas ) ) ), '' );
	}
	$valor = is_array( $valor ) ? (string) reset( $valor ) : $valor;
	$valor = is_string( $valor ) ? wp_unslash( $valor ) : '';
	if ( 'titulo' === $campo ) {
		$v = trim( sanitize_text_field( $valor ) );
		if ( '' === $v ) {
			return array( null, 'El nombre del emprendimiento no puede quedar vacío.' );
		}
		return array( mb_substr( $v, 0, 100 ), '' );
	}
	if ( 'contenido' === $campo ) {
		$v = trim( sanitize_textarea_field( $valor ) );
		if ( mb_strlen( $v ) > 1800 ) {
			return array( null, 'La descripción supera los 1800 caracteres.' );
		}
		return array( $v, '' );
	}
	if ( ! isset( $reglas[ $campo ] ) ) {
		return array( null, '' );
	}
	$r = $reglas[ $campo ];
	switch ( $r['tipo'] ) {
		case 'texto':
			$v = trim( sanitize_text_field( $valor ) );
			break;
		case 'textarea':
		case 'html':
			$v = trim( sanitize_textarea_field( $valor ) );
			break;
		case 'opcion':
			$v = in_array( $valor, $r['opciones'], true ) ? $valor : '';
			return array( '' === $v ? null : $v, '' );
		case 'numero':
			$v = trim( str_replace( ',', '.', sanitize_text_field( $valor ) ) );
			if ( '' === $v ) {
				return array( '', '' );
			}
			if ( ! is_numeric( $v ) || (float) $v < $r['min'] || (float) $v > $r['max'] ) {
				return array( null, 'Revisá el número: tiene que estar entre ' . $r['min'] . ' y ' . $r['max'] . '.' );
			}
			return array( (string) ( 0 + $v ), '' );
		case 'sino':
			// Casilla verdadero/falso de ACF: se guarda 1 o 0.
			if ( 'Sí' === $valor ) {
				return array( '1', '' );
			}
			return array( 'No' === $valor ? '0' : null, '' );
		case 'fecha':
			// dd/mm/aaaa (o aaaa-mm-dd) → aaaammdd, como guarda ACF.
			$v = trim( sanitize_text_field( $valor ) );
			if ( '' === $v ) {
				return array( '', '' );
			}
			$a = 0;
			$mes = 0;
			$d = 0;
			if ( preg_match( '#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $v, $m ) ) {
				$d   = (int) $m[1];
				$mes = (int) $m[2];
				$a   = (int) $m[3];
			} elseif ( preg_match( '#^(\d{4})-(\d{2})-(\d{2})$#', $v, $m ) ) {
				$a   = (int) $m[1];
				$mes = (int) $m[2];
				$d   = (int) $m[3];
			}
			if ( ! checkdate( $mes, $d, $a ) ) {
				return array( null, 'La fecha no es válida. Escribila así: 31/03/2027.' );
			}
			return array( sprintf( '%04d%02d%02d', $a, $mes, $d ), '' );
		case 'mapa':
			$v = trim( $valor );
			if ( '' !== $v ) {
				$v = function_exists( 'cl_mapa_normalizar' ) ? cl_mapa_normalizar( $v ) : '';
				if ( '' === $v ) {
					return array( null, 'No pudimos leer el mapa. En Google Maps buscá el lugar, tocá Compartir → Copiar enlace y pegalo acá. Desde la compu también sirve Compartir → Insertar un mapa.' );
				}
			}
			return array( $v, '' );
		case 'tel':
			$v = trim( sanitize_text_field( $valor ) );
			$dig = preg_replace( '/\D+/', '', $v );
			if ( '' !== $v && ( strlen( $dig ) < 8 || strlen( $dig ) > 15 ) ) {
				return array( null, 'Escribí el teléfono completo, por ejemplo 099 123 456 o +598 99 123 456.' );
			}
			break;
		case 'email':
			$v = trim( sanitize_email( $valor ) );
			if ( '' !== trim( $valor ) && ! is_email( $v ) ) {
				return array( null, 'El email no parece válido. Revisalo.' );
			}
			break;
		case 'url':
			$v = trim( $valor );
			if ( '' !== $v && ! preg_match( '#^https?://#i', $v ) ) {
				$v = 'https://' . $v;
			}
			$v = esc_url_raw( $v, array( 'http', 'https' ) );
			if ( '' !== trim( $valor ) && ( '' === $v || ! wp_http_validate_url( $v ) ) ) {
				return array( null, 'La dirección del sitio web no es válida.' );
			}
			break;
		case 'instagram':
			$v = trim( sanitize_text_field( $valor ) );
			if ( '' !== $v ) {
				if ( preg_match( '#instagram\.com/([A-Za-z0-9._]+)#i', $v, $m ) ) {
					$v = $m[1];
				}
				$v = ltrim( $v, '@' );
				if ( ! preg_match( '/^[A-Za-z0-9._]{1,30}$/', $v ) ) {
					return array( null, 'Instagram: escribí tu usuario (ej: @miemprendimiento) o pegá el enlace del perfil.' );
				}
				$v = '@' . $v;
			}
			break;
		case 'facebook':
			$v = trim( $valor );
			if ( '' !== $v ) {
				// @usuario o usuario solo: se arma el enlace a la página.
				if ( preg_match( '/^@?([A-Za-z0-9.]{5,50})$/', $v, $m ) ) {
					$v = 'https://www.facebook.com/' . $m[1];
				}
				if ( ! preg_match( '#^https?://#i', $v ) ) {
					$v = 'https://' . $v;
				}
				$v = esc_url_raw( $v, array( 'https', 'http' ) );
				if ( ! preg_match( '#^https?://([a-z0-9-]+\.)?(facebook\.com|fb\.com|fb\.me)/#i', $v ) ) {
					return array( null, 'Facebook: escribí tu usuario (ej: @miemprendimiento) o pegá el enlace de tu página.' );
				}
			}
			break;
		case 'video':
			$v = trim( esc_url_raw( $valor, array( 'https' ) ) );
			if ( '' !== $v && ! preg_match( '#^https://(www\.)?(youtube\.com|youtu\.be|vimeo\.com)/#i', $v ) ) {
				return array( null, 'El video tiene que ser un enlace de YouTube o Vimeo.' );
			}
			break;
		default:
			return array( null, '' );
	}
	if ( ! empty( $r['obligatorio'] ) && '' === $v ) {
		return array( null, $r['obligatorio'] );
	}
	if ( mb_strlen( $v ) > $r['max'] ) {
		return array( null, 'Un texto supera el largo máximo (' . $r['max'] . ' caracteres).' );
	}
	return array( $v, '' );
}

// ---------- Guardado ----------

add_action( 'bricks/form/custom_action', function ( $form ) {
	$f    = $form->get_fields();
	$paso = isset( $f['form-field-clpaso'] ) ? sanitize_key( $f['form-field-clpaso'] ) : '';
	$mapa = cl_panel_pasos();
	if ( ! isset( $mapa[ $paso ] ) ) {
		return; // No es un formulario del panel.
	}

	// Idiomas: casillas + texto libre → un solo valor.
	if ( 'otros' === $paso && ( isset( $f['form-field-clidio'] ) || isset( $f['form-field-clidot'] ) ) ) {
		$f['form-field-clidio'] = cl_panel_idiomas_juntar(
			isset( $f['form-field-clidio'] ) ? $f['form-field-clidio'] : array(),
			isset( $f['form-field-clidot'] ) ? $f['form-field-clidot'] : ''
		);
	}

	$error = function ( $msg ) use ( $form ) {
		$form->set_result( array( 'action' => 'cl_panel', 'type' => 'error', 'message' => $msg ) );
	};

	if ( ! cl_panel_puede_usar() ) {
		return $error( 'Tu sesión venció. Volvé a iniciar sesión.' );
	}

	// Límite: 30 guardados cada 10 minutos por cuenta.
	$clave = 'cl_panel_rl_' . get_current_user_id();
	$n     = (int) get_transient( $clave );
	if ( $n >= 30 ) {
		return $error( 'Guardaste muchas veces seguidas. Esperá unos minutos y probá de nuevo.' );
	}
	set_transient( $clave, $n + 1, 10 * MINUTE_IN_SECONDS );

	// Ficha: el servidor decide cuál (nunca se confía en lo que manda el navegador).
	$pedido = isset( $f['form-field-clempi'] ) ? (int) $f['form-field-clempi'] : 0;
	$emp_id = current_user_can( 'manage_options' ) ? cl_panel_emp_id( $pedido ) : cl_panel_emp_id();
	if ( ! $emp_id ) {
		return $error( 'No encontramos tu emprendimiento. Escribinos y lo revisamos.' );
	}
	$es_admin = current_user_can( 'manage_options' );
	if ( ! $es_admin && (int) get_post_field( 'post_author', $emp_id ) !== get_current_user_id() ) {
		return $error( 'No tenés permiso para editar esta ficha.' );
	}

	// Con Actividades y experiencias, hace falta al menos una Forma de andar.
	$con_rubro = 'actividades' === $paso && array_key_exists( 'form-field-clrubr', $f );
	if ( $con_rubro ) {
		list( $rub ) = cl_panel_limpiar( 'rubros', $f['form-field-clrubr'] );
		$act         = cl_panel_rubro_id( 'actividades-y-experiencias' );
		if ( $rub && $act && in_array( $act, $rub, true ) ) {
			list( $fda ) = cl_panel_limpiar( 'categorias', isset( $f['form-field-clcats'] ) ? $f['form-field-clcats'] : array() );
			if ( empty( $fda ) ) {
				return $error( 'Elegí al menos una opción en «¿Qué se puede hacer en tu lugar?».' );
			}
		}
	}

	$sensibles  = cl_panel_campos_sensibles();
	$pendientes = get_post_meta( $emp_id, '_cl_pendiente', true );
	$pendientes = is_array( $pendientes ) ? $pendientes : array();
	$cambios    = array();
	$en_espera  = array();
	$post_upd   = array();

	foreach ( $mapa[ $paso ] as $fid => $campo ) {
		if ( ! array_key_exists( 'form-field-' . $fid, $f ) ) {
			continue;
		}
		list( $valor, $msg ) = cl_panel_limpiar( $campo, $f[ 'form-field-' . $fid ] );
		if ( $msg ) {
			return $error( $msg );
		}
		if ( null === $valor ) {
			continue;
		}

		// Valor actual, para saber si cambió.
		if ( 'titulo' === $campo ) {
			$actual = get_the_title( $emp_id );
		} elseif ( 'contenido' === $campo ) {
			$actual = cl_panel_contenido_a_texto( get_post_field( 'post_content', $emp_id ) );
		} else {
			$actual = cl_panel_leer( $campo, $emp_id );
		}
		if ( $valor === $actual ) {
			if ( isset( $pendientes[ $campo ] ) && ! $es_admin ) {
				unset( $pendientes[ $campo ] ); // Volvió al valor publicado: se cancela lo pendiente.
			}
			continue;
		}

		// Campo sensible de un emprendedor: queda en espera.
		// Solo en fichas publicadas y si ya había un valor (en una ficha nueva se carga directo).
		if ( isset( $sensibles[ $campo ] ) && ! $es_admin && ! cl_sin_revision() && 'publish' === get_post_status( $emp_id ) && '' !== $actual ) {
			$pendientes[ $campo ] = array( 'valor' => $valor, 'fecha' => current_time( 'mysql' ), 'usuario' => get_current_user_id() );
			$en_espera[]          = $sensibles[ $campo ];
			continue;
		}

		if ( 'titulo' === $campo ) {
			$post_upd['post_title'] = $valor;
			unset( $pendientes['titulo'] );
		} elseif ( 'contenido' === $campo ) {
			$post_upd['post_content'] = cl_panel_texto_a_bloques( $valor );
		} else {
			cl_panel_escribir( $campo, $valor, $emp_id );
		}
		$cambios[] = $campo;
	}

	if ( $post_upd ) {
		$post_upd['ID'] = $emp_id;
		wp_update_post( wp_slash( $post_upd ) );
	}
	update_post_meta( $emp_id, '_cl_pendiente', $pendientes );
	// Sin baño para quienes vienen por el día: el inodoro no corresponde.
	if ( 'lugar' === $paso && 'Sin baño' === cl_panel_leer( 'bano_tipo', $emp_id ) && '' !== cl_panel_leer( 'bano_inodoro', $emp_id ) ) {
		cl_panel_escribir( 'bano_inodoro', '', $emp_id );
	}
	if ( $con_rubro ) {
		cl_panel_modulos_segun_rubro( $emp_id );
	}

	// Aviso al admin cuando un emprendedor cambia una ficha publicada o deja algo en espera.
	if ( ! $es_admin && 'inscripcion' !== $paso && ( $en_espera || ( $cambios && 'publish' === get_post_status( $emp_id ) ) ) ) {
		$user = wp_get_current_user();
		$txt  = 'Emprendimiento: ' . get_the_title( $emp_id ) . "\n";
		$txt .= 'Usuario: ' . $user->user_login . "\n\n";
		if ( $cambios ) {
			$txt .= 'Cambios publicados: ' . implode( ', ', $cambios ) . "\n";
		}
		if ( $en_espera ) {
			$txt .= 'Esperan tu aprobación: ' . implode( ', ', $en_espera ) . "\n";
		}
		$txt .= "\nEditar: " . admin_url( 'post.php?post=' . $emp_id . '&action=edit' );
		wp_mail( get_option( 'admin_email' ), '[Caminos Locales] Cambios en ' . get_the_title( $emp_id ), $txt );
	}

	$msg = 'Guardado ✓';
	if ( $en_espera ) {
		$msg .= ' El cambio de ' . strtolower( implode( ' y ', $en_espera ) ) . ' queda en revisión.';
	}
	$form->set_result( array( 'action' => 'cl_panel', 'type' => 'success', 'message' => $msg ) );
} );

// ---------- Aprobación de campos sensibles (wp-admin, ficha del emprendimiento) ----------

add_action( 'add_meta_boxes_emprendimientos', function ( $post ) {
	$pend = get_post_meta( $post->ID, '_cl_pendiente', true );
	if ( empty( $pend ) || ! is_array( $pend ) ) {
		return;
	}
	add_meta_box( 'cl_pendientes', 'Cambios esperando aprobación', function ( $post ) use ( $pend ) {
		$nombres = cl_panel_campos_sensibles();
		wp_nonce_field( 'cl_aprobar_' . $post->ID, 'cl_aprobar_nonce' );
		echo '<p>Pedidos desde el panel del emprendedor. Mientras no los apruebes, la ficha muestra el valor actual.</p>';
		foreach ( $pend as $campo => $d ) {
			$label = isset( $nombres[ $campo ] ) ? $nombres[ $campo ] : $campo;
			echo '<div style="border:1px solid #ddd;padding:8px;margin-bottom:8px">';
			echo '<strong>' . esc_html( $label ) . '</strong><br>Nuevo valor: <code>' . esc_html( $d['valor'] ) . '</code><br><small>' . esc_html( $d['fecha'] ) . '</small><br>';
			echo '<label><input type="radio" name="cl_aprobar[' . esc_attr( $campo ) . ']" value="" checked> Dejar pendiente</label> ';
			echo '<label><input type="radio" name="cl_aprobar[' . esc_attr( $campo ) . ']" value="si"> Aprobar</label> ';
			echo '<label><input type="radio" name="cl_aprobar[' . esc_attr( $campo ) . ']" value="no"> Rechazar</label>';
			echo '</div>';
		}
		echo '<p><small>Se aplica al hacer clic en Actualizar.</small></p>';
	}, 'emprendimientos', 'side', 'high' );
} );

add_action( 'save_post_emprendimientos', function ( $post_id ) {
	if ( empty( $_POST['cl_aprobar'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_POST['cl_aprobar_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cl_aprobar_nonce'] ) ), 'cl_aprobar_' . $post_id ) ) {
		return;
	}
	$pend = get_post_meta( $post_id, '_cl_pendiente', true );
	if ( ! is_array( $pend ) ) {
		return;
	}
	$decisiones = array_map( 'sanitize_key', wp_unslash( (array) $_POST['cl_aprobar'] ) );
	foreach ( $decisiones as $campo => $d ) {
		if ( ! isset( $pend[ $campo ] ) || '' === $d ) {
			continue;
		}
		if ( 'si' === $d ) {
			$valor = $pend[ $campo ]['valor'];
			if ( 'titulo' === $campo ) {
				remove_all_actions( 'save_post_emprendimientos' ); // Evita repetir este mismo guardado.
				wp_update_post( array( 'ID' => $post_id, 'post_title' => $valor ) );
			} else {
				update_field( $campo, $valor, $post_id );
			}
		}
		unset( $pend[ $campo ] );
	}
	update_post_meta( $post_id, '_cl_pendiente', $pend );
}, 20 );

// ---------- Mapa actual de la ficha: [cl_panel_mapa] ----------

add_shortcode( 'cl_panel_mapa', function () {
	if ( ! cl_panel_puede_usar() ) {
		return '';
	}
	$id  = cl_panel_emp_id();
	$url = ( $id && function_exists( 'cl_mapa_url_propia' ) ) ? cl_mapa_url_propia( $id ) : '';
	if ( ! $url ) {
		return '<div style="border:1px dashed #9fb3ae;border-radius:12px;background:#dfe7e3;padding:24px;text-align:center;font-size:15px;color:#3d4b49">Todavía no hay mapa. Cuando pegues el código y guardes, lo vas a ver acá.</div>';
	}
	return '<div style="font-weight:700;margin:0 0 8px">Así se ve tu mapa ahora</div><div style="height:260px;border-radius:12px;overflow:hidden"><iframe src="' . esc_url( $url ) . '" title="Mapa" loading="lazy" referrerpolicy="no-referrer-when-downgrade" style="border:0;width:100%;height:100%"></iframe></div>';
} );

// ---------- Mi panel: [cl_panel_inicio] ----------

// "?emp=ID" para los enlaces cuando el admin está en carga asistida.
function cl_panel_qs( $emp_id ) {
	return current_user_can( 'manage_options' ) && $emp_id ? '?emp=' . (int) $emp_id : '';
}

// Qué le falta a la ficha. Devuelve array( hechos, total, faltan[] ).
function cl_panel_progreso( $id ) {
	$chk = array(
		'nombre'                 => get_the_title( $id ),
		'breve descripción'      => get_field( 'breve_descripcion', $id, false ),
		'descripción'            => trim( wp_strip_all_tags( get_post_field( 'post_content', $id ) ) ),
		'quiénes somos'          => get_field( 'quienes_somos', $id, false ),
		'actividades'            => get_field( 'actividades_descripcion', $id, false ),
		'foto principal'         => get_post_thumbnail_id( $id ),
		'localidad'              => get_field( 'localidad', $id, false ),
		'mapa'                   => get_field( 'ubicacion', $id, false ),
		'cómo llegar'            => get_field( 'como_llegar', $id, false ),
		'teléfono o email'       => get_field( 'telefono_contacto', $id, false ) . get_field( 'email-emp', $id, false ),
	);
	// Solo cuenta lo que corresponde al rubro.
	if ( ! cl_panel_tiene_rubro( $id, 'actividades-y-experiencias' ) ) {
		unset( $chk['actividades'] );
	}
	if ( ! cl_panel_recibe_visitantes( $id ) ) {
		unset( $chk['cómo llegar'] );
	}
	// Ficha compacta (1.1.0): sin «Quiénes somos»; la foto puede ser el logo.
	if ( function_exists( 'cl_es_servicio' ) && cl_es_servicio( $id ) ) {
		unset( $chk['quiénes somos'] );
		$chk = array( 'foto o logo' => $chk['foto principal'] ) + $chk;
		unset( $chk['foto principal'] );
	}
	$faltan = array();
	foreach ( $chk as $k => $v ) {
		if ( empty( $v ) ) {
			$faltan[] = $k;
		}
	}
	return array( count( $chk ) - count( $faltan ), count( $chk ), $faltan );
}

// Cuántas preguntas de «Cómo es llegar y estar» están respondidas. Devuelve array( respondidas, total ).
function cl_panel_lugar_progreso( $id ) {
	$campos = array_values( cl_panel_pasos()['lugar'] );
	$campos = array_diff( $campos, array( 'traslado_nota', 'bano_inodoro', 'en_el_lugar' ) );
	$hechas = 0;
	foreach ( $campos as $c ) {
		$v = cl_panel_leer( $c, $id );
		$hechas += ( is_array( $v ) ? count( $v ) > 0 : '' !== (string) $v ) ? 1 : 0;
	}
	return array( $hechas, count( $campos ) );
}

function cl_panel_chip( $estado ) {
	$m = array(
		'publish' => array( 'Publicada', '#dcebe4', '#174a3a' ),
		'pending' => array( 'En revisión', '#fdf0d5', '#6b4300' ),
		'draft'   => array( 'Borrador', '#ececec', '#454545' ),
	);
	$e = isset( $m[ $estado ] ) ? $m[ $estado ] : $m['draft'];
	return '<span class="clp-chip" style="background:' . $e[1] . ';color:' . $e[2] . '">' . esc_html( $e[0] ) . '</span>';
}

add_shortcode( 'cl_panel_inicio', function () {
	if ( ! cl_panel_puede_usar() ) {
		return '';
	}
	$es_admin = current_user_can( 'manage_options' );
	$id       = cl_panel_emp_id();
	$base     = get_permalink( CL_PANEL_PAGE_ID );
	ob_start();
	?>
	<style>
	.clp{font-family:"Source Sans 3",system-ui,sans-serif;color:#23302f;display:flex;flex-direction:column;gap:18px}
	.clp h1{margin:0;font-family:Montserrat,sans-serif;font-size:26px;color:#1f4645}
	.clp-card{background:#fff;border-radius:16px;padding:18px;display:flex;flex-direction:column;gap:12px;box-shadow:0 1px 2px rgba(31,70,69,.08);text-decoration:none;color:inherit}
	a.clp-card:hover{box-shadow:0 3px 10px rgba(31,70,69,.16)}
	.clp-t{font-family:Montserrat,sans-serif;font-weight:700;font-size:17px;color:#1f4645}
	.clp-s{font-size:14px;color:#5b6a68}
	.clp-chip{display:inline-block;padding:4px 10px;border-radius:999px;font-size:13px;font-weight:700}
	.clp-row{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
	.clp-go{font-size:15px;font-weight:700;color:#9a6055}
	.clp-bar{height:10px;border-radius:5px;background:#e6e1d6;overflow:hidden}
	.clp-bar div{height:10px;background:#9a6055}
	.clp-admin{background:#fdf0d5;border:1px solid #e9d3a4;border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:8px}
	.clp-admin label{font-size:13px;font-weight:700;color:#6b4300}
	.clp-admin select{height:44px;border:1px solid #c9a76a;border-radius:10px;padding:0 12px;font-size:16px;background:#fff}
	.clp-help{border:1.5px dashed #b9c6c3;border-radius:16px;padding:18px;background:#fbfaf6;display:flex;flex-direction:column;gap:10px}
	.clp-btn{min-height:48px;border-radius:12px;background:#1f4645;color:#fff !important;text-decoration:none;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px}
	</style>
	<div class="clp">
	<?php if ( $es_admin ) :
		$todos = get_posts( array( 'post_type' => 'emprendimientos', 'post_status' => array( 'publish', 'draft', 'pending' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ); ?>
		<form class="clp-admin" method="get" action="<?php echo esc_url( $base ); ?>">
			<label for="clp-emp">Carga asistida · estás como administrador</label>
			<select id="clp-emp" name="emp" onchange="this.form.submit()">
				<option value="">Elegí un emprendimiento…</option>
				<?php foreach ( $todos as $p ) : ?>
					<option value="<?php echo (int) $p->ID; ?>" <?php selected( $id, $p->ID ); ?>><?php $est = array( 'draft' => 'Borrador', 'pending' => 'En revisión' ); echo esc_html( $p->post_title . ( isset( $est[ $p->post_status ] ) ? ' (' . $est[ $p->post_status ] . ')' : '' ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</form>
	<?php endif; ?>

	<?php if ( ! $id ) : ?>
		<div class="clp-card"><div class="clp-t"><?php echo $es_admin ? 'Elegí un emprendimiento arriba para ver su panel.' : 'Todavía no tenés un emprendimiento cargado.'; ?></div>
		<?php if ( ! $es_admin ) : ?><div class="clp-s">Escribinos y lo creamos juntos.</div><?php endif; ?></div>
	<?php else :
		list( $hechos, $total, $faltan ) = cl_panel_progreso( $id );
		$pct   = (int) round( $hechos * 100 / $total );
		$qs    = cl_panel_qs( $id );
		$aloj  = get_posts( array( 'post_type' => 'alojamiento', 'post_status' => array( 'publish', 'draft', 'pending' ), 'numberposts' => -1, 'fields' => 'ids',
			'meta_query' => array( array( 'key' => 'emprendimiento_correspondiente', 'value' => '"' . $id . '"', 'compare' => 'LIKE' ) ) ) );
		$pub   = 0;
		foreach ( $aloj as $a ) { if ( 'publish' === get_post_status( $a ) ) { $pub++; } }
		$pend  = get_post_meta( $id, '_cl_pendiente', true );
		?>
		<div><div class="clp-s">Hola, <?php echo esc_html( get_the_title( $id ) ); ?></div><h1>Mi panel</h1></div>

		<div class="clp-card">
			<div class="clp-row"><strong>Tu ficha está completa al <?php echo $pct; ?> %</strong><span class="clp-s"><?php echo $hechos . ' de ' . $total; ?></span></div>
			<div class="clp-bar"><div style="width:<?php echo $pct; ?>%"></div></div>
			<?php if ( $faltan ) : ?><div class="clp-s">Te falta: <strong><?php echo esc_html( implode( ', ', $faltan ) ); ?></strong>. Una ficha completa aparece mejor en las búsquedas.</div><?php endif; ?>
			<?php if ( ! empty( $pend ) ) : ?><div class="clp-s">Hay cambios esperando revisión de Caminos Locales.</div><?php endif; ?>
		</div>

		<a class="clp-card" href="<?php echo esc_url( $base . 'emprendimiento/' . $qs ); ?>">
			<div class="clp-t">Mi emprendimiento</div>
			<div class="clp-s"><?php echo esc_html( get_the_title( $id ) ); ?></div>
			<div class="clp-row"><?php echo cl_panel_chip( get_post_status( $id ) ); ?><span class="clp-go">Editar →</span></div>
		</a>

		<a class="clp-card" href="#" aria-disabled="true">
			<div class="clp-t">Mis alojamientos</div>
			<div class="clp-s"><?php echo count( $aloj ) ? count( $aloj ) . ' alojamiento' . ( count( $aloj ) > 1 ? 's' : '' ) . ' · ' . $pub . ' publicado' . ( 1 !== $pub ? 's' : '' ) : 'Sin alojamientos'; ?></div>
			<div class="clp-row"><span class="clp-s">Próximamente en el panel</span></div>
		</a>

		<a class="clp-card" href="#" aria-disabled="true">
			<div class="clp-t">Mis eventos</div>
			<div class="clp-row"><span class="clp-s">Próximamente en el panel</span></div>
		</a>

		<?php if ( cl_panel_recibe_visitantes( $id ) ) :
			list( $l_h, $l_t ) = cl_panel_lugar_progreso( $id ); ?>
		<a class="clp-card" href="<?php echo esc_url( $base . 'llegar-y-estar/' . $qs ); ?>">
			<div class="clp-t">Cómo es llegar y estar</div>
			<div class="clp-s">Cómo se llega, el último tramo del camino, las porteras y el baño, para que la gente sepa a qué va. Es opcional.</div>
			<div class="clp-row"><span class="clp-s"><?php echo $l_h ? esc_html( $l_h . ' de ' . $l_t . ' respondidas' ) : 'Sin empezar'; ?></span><span class="clp-go"><?php echo $l_h ? 'Ver y editar →' : 'Empezar →'; ?></span></div>
		</a>
		<?php endif; ?>

		<a class="clp-card" href="<?php echo esc_url( $base . 'inscripcion/' . $qs ); ?>">
			<div class="clp-t">Mi inscripción y responsabilidades</div>
			<div class="clp-s">Sección informativa: si te interesa inscribirte en MINTUR o contratar un seguro, te orientamos. No hace falta para estar en Caminos Locales.</div>
			<div class="clp-row"><span class="clp-s">🔒 Solo lo ven vos y Caminos Locales</span><span class="clp-go">Ver →</span></div>
		</a>

		<a class="clp-card" href="#" aria-disabled="true">
			<div class="clp-t">Mi cuenta</div>
			<div class="clp-s">Nombre, email y contraseña · próximamente</div>
		</a>

		<div class="clp-help">
			<strong style="color:#1f4645">¿Necesitás una mano?</strong>
			<div class="clp-s" style="font-size:15px">Escribinos y te ayudamos a cargar tu ficha. Si preferís, la completamos nosotros con lo que nos mandes.</div>
			<a class="clp-btn" href="<?php echo esc_url( 'https://wa.me/59899247249?text=' . rawurlencode( 'Hola, necesito ayuda con mi ficha de Caminos Locales (' . get_the_title( $id ) . ')' ) ); ?>" target="_blank" rel="noopener">Escribinos por WhatsApp</a>
		</div>
	<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
} );

// ---------- Paso final: Ver cómo queda / Enviar a revisión ----------

// Lo que tiene que estar antes de enviar a revisión (obligatorio) y lo que conviene sumar (sugerido).
function cl_panel_revision_chequeo( $id ) {
	$tel_mail = get_field( 'telefono_contacto', $id, false ) . get_field( 'email-emp', $id, false );
	$cats     = wp_get_object_terms( $id, 'category', array( 'fields' => 'ids' ) );
	$cats     = is_wp_error( $cats ) ? array() : array_intersect( array_map( 'intval', $cats ), CL_FORMAS_DE_ANDAR );
	$rubros   = wp_get_object_terms( $id, 'rubro', array( 'fields' => 'ids' ) );
	$con_act  = cl_panel_tiene_rubro( $id, 'actividades-y-experiencias' );
	$region   = wp_get_object_terms( $id, 'region', array( 'fields' => 'ids' ) );
	$act      = 0;
	for ( $i = 1; $i <= 6; $i++ ) {
		$act += get_post_meta( $id, 'fotos_actividades_foto' . $i, true ) ? 1 : 0;
	}
	$oblig = array(
		array( 'Nombre', '' !== trim( get_the_title( $id ) ), 'presentacion' ),
		array( 'Breve descripción', '' !== trim( (string) get_field( 'breve_descripcion', $id, false ) ), 'presentacion' ),
		array( 'Descripción de la propuesta', '' !== trim( wp_strip_all_tags( get_post_field( 'post_content', $id ) ) ), 'presentacion' ),
		array( '¿Qué ofrecés?', ! is_wp_error( $rubros ) && ! empty( $rubros ), 'actividades' ),
		array( 'Región y localidad', ! is_wp_error( $region ) && $region && '' !== trim( (string) get_field( 'localidad', $id, false ) ), 'ubicacion' ),
		array( 'Teléfono o email', '' !== trim( $tel_mail ), 'contacto' ),
		array( 'Foto principal', (bool) get_post_thumbnail_id( $id ), 'fotos' ),
	);
	if ( $con_act ) {
		array_splice( $oblig, 4, 0, array( array( '¿Qué se puede hacer en tu lugar?', ! empty( $cats ), 'actividades' ) ) );
	}
	$suger = array( array( 'Mapa', '' !== trim( (string) get_field( 'ubicacion', $id, false ) ), 'ubicacion' ) );
	if ( cl_panel_recibe_visitantes( $id ) ) {
		$suger[] = array( 'Cómo llegar', '' !== trim( (string) get_field( 'como_llegar', $id, false ) ), 'lugar' );
	}
	if ( $con_act ) {
		$suger[] = array( 'Fotos de actividades', $act > 0, 'fotos' );
	}
	if ( ! function_exists( 'cl_es_servicio' ) || ! cl_es_servicio( $id ) ) {
		$suger[] = array( '¿Quiénes somos?', '' !== trim( (string) get_field( 'quienes_somos', $id, false ) ), 'presentacion' );
	}
	return array( $oblig, $suger );
}

// Vista previa de la ficha para su dueño (y el admin), aunque esté en borrador o en revisión.
function cl_panel_url_vista( $id ) {
	if ( 'publish' === get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	return add_query_arg( array( 'post_type' => 'emprendimientos', 'p' => (int) $id, 'cl_vista' => 1 ), home_url( '/' ) );
}
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || empty( $_GET['cl_vista'] ) || empty( $_GET['p'] ) || ! is_user_logged_in() ) {
		return;
	}
	$id = (int) $_GET['p'];
	if ( 'emprendimientos' !== get_post_type( $id ) ) {
		return;
	}
	if ( current_user_can( 'manage_options' ) || (int) get_post_field( 'post_author', $id ) === get_current_user_id() ) {
		$q->set( 'post_status', array( 'publish', 'draft', 'pending' ) );
	}
} );
add_action( 'template_redirect', function () {
	if ( ! empty( $_GET['cl_vista'] ) ) {
		nocache_headers();
	}
}, 5 );
// Aviso arriba de la vista previa.
add_action( 'wp_body_open', function () {
	if ( empty( $_GET['cl_vista'] ) || ! is_singular( 'emprendimientos' ) || 'publish' === get_post_status() ) {
		return;
	}
	$volver = home_url( '/panel/emprendimiento/?paso=vista' . ( current_user_can( 'manage_options' ) ? '&emp=' . get_the_ID() : '' ) );
	echo '<div style="position:sticky;top:0;z-index:99999;background:#fdf0d5;color:#5c3a00;padding:10px 16px;font:700 15px/1.4 system-ui,sans-serif;text-align:center">Vista previa: tu ficha todavía no está publicada. <a href="' . esc_url( $volver ) . '" style="color:#1f4645">← Volver al panel</a></div>';
} );

// Enviar a revisión (formulario del shortcode).
add_action( 'template_redirect', function () {
	if ( empty( $_POST['cl_enviar_revision'] ) || ! cl_panel_puede_usar() ) {
		return;
	}
	$id = cl_panel_emp_id( isset( $_POST['emp'] ) ? (int) $_POST['emp'] : null );
	if ( ! $id || ! isset( $_POST['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_clnonce'] ), 'cl_enviar_' . $id ) ) {
		return;
	}
	cl_panel_publicar_o_enviar( $id );
}, 1 );

// 1.1.1: «Publicar» desde Mi panel (enlace con nonce). Si falta algo obligatorio, lleva a «Ver cómo queda».
add_action( 'template_redirect', function () {
	if ( empty( $_GET['cl_publicar'] ) || ! cl_panel_puede_usar() ) {
		return;
	}
	$id = cl_panel_emp_id( (int) $_GET['cl_publicar'] );
	if ( ! $id || (int) $_GET['cl_publicar'] !== $id || ! isset( $_GET['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_clnonce'] ), 'cl_publicar_' . $id ) ) {
		return;
	}
	cl_panel_publicar_o_enviar( $id );
}, 1 );

// 1.1.2: «Ocultar de la web» en «Ver cómo queda»: la ficha publicada vuelve a borrador.
add_action( 'template_redirect', function () {
	if ( empty( $_POST['cl_ocultar_emp'] ) || ! cl_panel_puede_usar() ) {
		return;
	}
	$id = cl_panel_emp_id( isset( $_POST['emp'] ) ? (int) $_POST['emp'] : null );
	if ( ! $id || ! isset( $_POST['_clnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_clnonce'] ), 'cl_ocultar_' . $id ) ) {
		return;
	}
	$vuelta = current_user_can( 'manage_options' ) ? home_url( '/panel/emprendimiento/?paso=vista&emp=' . $id ) : home_url( '/panel/emprendimiento/?paso=vista' . cl_panel_qs( $id ) );
	if ( 'publish' === get_post_status( $id ) ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		$user = wp_get_current_user();
		wp_mail(
			get_option( 'admin_email' ),
			'[Caminos Locales] Ficha oculta: ' . get_the_title( $id ),
			'El emprendimiento «' . get_the_title( $id ) . '» se ocultó desde el panel (quedó en borrador).' . "\n" .
			'Usuario: ' . $user->user_login . "\n\n" .
			'Editar: ' . admin_url( 'post.php?post=' . $id . '&action=edit' )
		);
	}
	wp_safe_redirect( add_query_arg( 'oculta', 1, $vuelta ) );
	exit;
}, 1 );

// URL del botón «Publicar» de Mi panel.
function cl_panel_url_publicar( $id ) {
	return add_query_arg( array( 'cl_publicar' => $id, '_clnonce' => wp_create_nonce( 'cl_publicar_' . $id ) ), get_permalink( CL_PANEL_PAGE_ID ) );
}

// Publica (sin revisión) o envía a revisión, y redirige a «Ver cómo queda» con el resultado.
function cl_panel_publicar_o_enviar( $id ) {
	$vuelta = home_url( '/panel/emprendimiento/?paso=vista' . cl_panel_qs( $id ) );
	$vuelta = current_user_can( 'manage_options' ) ? add_query_arg( 'paso', 'vista', home_url( '/panel/emprendimiento/?emp=' . $id ) ) : $vuelta;
	$estado_antes = get_post_status( $id );
	if ( 'draft' !== $estado_antes && ! ( 'pending' === $estado_antes && cl_sin_revision() ) ) {
		wp_safe_redirect( $vuelta );
		exit;
	}
	list( $oblig ) = cl_panel_revision_chequeo( $id );
	foreach ( $oblig as $o ) {
		if ( ! $o[1] ) {
			wp_safe_redirect( add_query_arg( 'faltan', 1, $vuelta ) );
			exit;
		}
	}
	$user = wp_get_current_user();
	// v1.0.1: sin revisión (integrantes de confianza), se publica al momento y se avisa al admin.
	if ( cl_sin_revision() ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		wp_mail(
			get_option( 'admin_email' ),
			'[Caminos Locales] Ficha publicada: ' . get_the_title( $id ),
			'El emprendimiento «' . get_the_title( $id ) . '» se publicó desde el panel.' . "\n" .
			'Usuario: ' . $user->user_login . "\n\n" .
			'Ver: ' . get_permalink( $id ) . "\n" .
			'Editar: ' . admin_url( 'post.php?post=' . $id . '&action=edit' )
		);
		wp_safe_redirect( add_query_arg( 'publicada', 1, $vuelta ) );
		exit;
	}
	wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ) );
	wp_mail(
		get_option( 'admin_email' ),
		'[Caminos Locales] Ficha para revisar: ' . get_the_title( $id ),
		'El emprendimiento «' . get_the_title( $id ) . '» se envió a revisión.' . "\n" .
		'Usuario: ' . $user->user_login . "\n\n" .
		'Vista previa: ' . cl_panel_url_vista( $id ) . "\n" .
		'Revisar y publicar: ' . admin_url( 'post.php?post=' . $id . '&action=edit' )
	);
	wp_safe_redirect( add_query_arg( 'enviado', 1, $vuelta ) );
	exit;
}

// Cuando el admin publica una ficha que estaba en revisión, se le avisa al emprendedor.
add_action( 'transition_post_status', function ( $nuevo, $viejo, $post ) {
	if ( 'emprendimientos' !== $post->post_type || 'publish' !== $nuevo || 'pending' !== $viejo ) {
		return;
	}
	$autor = get_userdata( (int) $post->post_author );
	if ( ! $autor || ! function_exists( 'cl_es_emprendedor' ) || ! cl_es_emprendedor( $autor ) ) {
		return;
	}
	wp_mail(
		$autor->user_email,
		'¡Tu ficha ya está publicada en Caminos Locales!',
		'Hola, ' . $autor->display_name . ":\n\n" .
		'«' . $post->post_title . '» ya se ve en Caminos Locales:' . "\n" . get_permalink( $post ) . "\n\n" .
		'Podés seguir editándola cuando quieras desde tu panel: ' . home_url( '/panel/' ) . "\n\n" .
		"Gracias por sumarte.\nCaminos Locales"
	);
}, 10, 3 );

// [cl_panel_vista] — tarjeta como se ve en el listado, lista de "Antes de publicar" y botón.
add_shortcode( 'cl_panel_vista', function () {
	if ( ! cl_panel_puede_usar() ) {
		return '';
	}
	$id = cl_panel_emp_id();
	if ( ! $id ) {
		return '<p>Elegí un emprendimiento en <a href="/panel/">Mi panel</a>.</p>';
	}
	$estado   = get_post_status( $id );
	$es_admin = current_user_can( 'manage_options' );
	$qs       = $es_admin ? '&emp=' . $id : '';
	$paso_url = function ( $paso ) use ( $qs, $id, $es_admin ) {
		if ( 'lugar' === $paso ) {
			return home_url( '/panel/llegar-y-estar/' . ( $es_admin ? '?emp=' . $id : '' ) );
		}
		return home_url( '/panel/emprendimiento/?paso=' . $paso . $qs );
	};
	list( $oblig, $suger ) = cl_panel_revision_chequeo( $id );
	$faltan_oblig = array_filter( $oblig, function ( $o ) {
		return ! $o[1];
	} );

	// Datos de la tarjeta.
	$foto    = get_the_post_thumbnail_url( $id, 'medium_large' );
	$region  = wp_get_object_terms( $id, 'region', array( 'fields' => 'names' ) );
	$lugar   = trim( implode( ' · ', array_filter( array( get_field( 'localidad', $id, false ), is_wp_error( $region ) ? '' : implode( ', ', $region ) ) ) ) );
	$cats    = wp_get_object_terms( $id, 'category', array( 'include' => CL_FORMAS_DE_ANDAR, 'number' => 3 ) );
	if ( is_wp_error( $cats ) || ! $cats ) {
		$cats = wp_get_object_terms( $id, 'rubro', array( 'number' => 3 ) );
	}
	$pend    = get_post_meta( $id, '_cl_pendiente', true );
	$pend    = is_array( $pend ) ? $pend : array();
	$nombres = cl_panel_campos_sensibles();

	ob_start();
	?>
<style>
.clv{display:flex;flex-direction:column;gap:20px;font-family:'Source Sans 3',system-ui,sans-serif;color:#23302f}
.clv-card{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(31,70,69,.12)}
.clv-foto{aspect-ratio:16/10;background:#eeeae1 center/cover no-repeat;display:flex;align-items:center;justify-content:center;color:#7a8583;font-size:14px}
.clv-body{padding:16px;display:flex;flex-direction:column;gap:6px}
.clv-lugar{font-size:13px;font-weight:700;color:#9a6055;text-transform:uppercase;letter-spacing:.3px}
.clv-tit{font-family:Montserrat,sans-serif;font-weight:700;font-size:20px;color:#1f4645;margin:0}
.clv-breve{font-size:15px;line-height:1.45;color:#3d4b49;margin:0}
.clv-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px}
.clv-chip{padding:4px 10px;border-radius:999px;background:#e3ece9;color:#1f4645;font-size:13px;font-weight:700}
.clv-link{display:flex;align-items:center;justify-content:center;min-height:48px;border-radius:12px;border:1px solid #1f4645;color:#1f4645;font-weight:700;text-decoration:none;background:#fff}
.clv-box{background:#fff;border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:4px}
.clv-h{font-family:Montserrat,sans-serif;font-weight:700;font-size:17px;color:#1f4645;margin:0 0 6px}
.clv-row{display:flex;align-items:center;gap:10px;min-height:40px;font-size:15px}
.clv-i{width:24px;height:24px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0}
.clv-ok{background:#dcebe4;color:#174a3a}.clv-no{background:#fbe3df;color:#8a2a1f}.clv-opc{background:#fdf0d5;color:#6b4300}
.clv-row a{margin-left:auto;color:#9a6055;font-weight:700;text-decoration:none;white-space:nowrap}
.clv-msg{border-radius:12px;padding:14px 16px;font-size:15px;line-height:1.45}
.clv-info{background:#e3ece9;color:#1f4645}.clv-warn{background:#fdf0d5;color:#5c3a00}.clv-err{background:#fbe3df;color:#8a2a1f;font-weight:700}
.clv .clv-btn{width:100%;min-height:52px;border:0;border-radius:12px;background:#9a6055;color:#fff;font-weight:700;font-size:17px;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;text-align:center;padding:0 16px!important;box-sizing:border-box}.clv .clv-btn:hover{background:#7f4c43}
.clv .clv-btn[disabled]{background:#c9c2b3;cursor:not-allowed}
</style>
<div class="clv">
	<?php if ( ! empty( $_GET['oculta'] ) && 'draft' === $estado ) : ?>
		<div class="clv-msg clv-info"><strong>Listo, tu ficha ya no se ve en la web.</strong><br>Quedó en borrador. Para mostrarla de nuevo, tocá «Publicar».</div>
	<?php elseif ( ! empty( $_GET['publicada'] ) && 'publish' === $estado ) : ?>
		<div class="clv-msg clv-info"><strong>¡Listo, tu ficha ya está publicada! ✓</strong><br>Ya se ve en Caminos Locales. Podés seguir cambiándola cuando quieras.</div>
	<?php elseif ( ! empty( $_GET['enviado'] ) ) : ?>
		<div class="clv-msg clv-info"><strong>¡Listo, la enviaste! ✓</strong><br>Tu ficha está en revisión. Te avisamos por email cuando esté publicada, normalmente en 1 o 2 días.</div>
	<?php elseif ( ! empty( $_GET['faltan'] ) ) : ?>
		<div class="clv-msg clv-err">Todavía falta completar lo marcado con ✕.</div>
	<?php endif; ?>

	<div>
		<div style="font-size:14px;font-weight:700;color:#5b6a68;margin-bottom:8px">Así se ve tu tarjeta en el listado</div>
		<div class="clv-card">
			<div class="clv-foto"<?php echo $foto ? ' style="background-image:url(' . esc_url( $foto ) . ')"' : ''; ?>><?php echo $foto ? '' : 'Sin foto principal'; ?></div>
			<div class="clv-body">
				<?php if ( $lugar ) : ?><div class="clv-lugar"><?php echo esc_html( $lugar ); ?></div><?php endif; ?>
				<h2 class="clv-tit"><?php echo esc_html( get_the_title( $id ) ); ?></h2>
				<p class="clv-breve"><?php echo esc_html( (string) get_field( 'breve_descripcion', $id, false ) ); ?></p>
				<?php if ( ! is_wp_error( $cats ) && $cats ) : ?>
					<div class="clv-chips"><?php foreach ( $cats as $c ) { echo '<span class="clv-chip">' . esc_html( $c->name ) . '</span>'; } ?></div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<a class="clv-link" href="<?php echo esc_url( cl_panel_url_vista( $id ) ); ?>" target="_blank" rel="noopener">Ver la ficha completa ↗</a>

	<div class="clv-box">
		<h3 class="clv-h">Antes de publicar</h3>
		<?php foreach ( $oblig as $o ) : ?>
			<div class="clv-row"><span class="clv-i <?php echo $o[1] ? 'clv-ok' : 'clv-no'; ?>"><?php echo $o[1] ? '✓' : '✕'; ?></span><?php echo esc_html( $o[0] ); ?><?php if ( ! $o[1] ) : ?><a href="<?php echo esc_url( $paso_url( $o[2] ) ); ?>">Completar</a><?php endif; ?></div>
		<?php endforeach; ?>
		<?php foreach ( $suger as $o ) : ?>
			<?php if ( ! $o[1] ) : ?>
				<div class="clv-row"><span class="clv-i clv-opc">!</span><?php echo esc_html( $o[0] ); ?> <span style="color:#7a8583;font-size:14px">(opcional)</span><a href="<?php echo esc_url( $paso_url( $o[2] ) ); ?>">Agregar</a></div>
			<?php endif; ?>
		<?php endforeach; ?>
		<?php foreach ( $pend as $campo => $p ) : ?>
			<?php if ( isset( $nombres[ $campo ] ) ) : ?>
				<div class="clv-row"><span class="clv-i clv-opc">⏳</span><?php echo esc_html( $nombres[ $campo ] . ' nuevo: queda en revisión' ); ?></div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<?php if ( 'draft' === $estado || ( 'pending' === $estado && cl_sin_revision() ) ) : ?>
		<?php if ( cl_sin_revision() ) : ?>
			<div class="clv-msg clv-warn">Cuando completes lo obligatorio, publicala: se ve en la web al momento.</div>
		<?php else : ?>
			<div class="clv-msg clv-warn">Cuando la envíes, la revisamos y la publicamos. Te avisamos por email, normalmente en 1 o 2 días.</div>
		<?php endif; ?>
		<form method="post">
			<input type="hidden" name="cl_enviar_revision" value="1">
			<input type="hidden" name="emp" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'cl_enviar_' . $id, '_clnonce', false ); ?>
			<button type="submit" class="clv-btn"<?php echo $faltan_oblig ? ' disabled' : ''; ?>><?php echo cl_sin_revision() ? 'Publicar' : 'Enviar a revisión'; ?></button>
		</form>
	<?php elseif ( 'pending' === $estado ) : ?>
		<?php if ( empty( $_GET['enviado'] ) ) : ?>
			<div class="clv-msg clv-info">Tu ficha está en revisión. Te avisamos por email cuando esté publicada. Mientras tanto podés seguir editándola.</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="clv-msg clv-info">Tu ficha está publicada: lo que guardás se ve en la web al momento.<?php echo cl_sin_revision() ? '' : ' El cambio de nombre o de email lo revisamos antes.'; ?></div>
		<?php // 1.1.2: volver a borrador desde el panel. ?>
		<div class="clv-box">
			<h3 class="clv-h">Más opciones</h3>
			<p style="font-size:15px;line-height:1.45;color:#3d4b49;margin:0 0 8px">Si querés sacarla de la web un tiempo, ocultala: queda en borrador y la volvés a publicar cuando quieras desde Mi panel.</p>
			<form method="post">
				<input type="hidden" name="cl_ocultar_emp" value="1">
				<input type="hidden" name="emp" value="<?php echo (int) $id; ?>">
				<?php wp_nonce_field( 'cl_ocultar_' . $id, '_clnonce', false ); ?>
				<button type="submit" class="clv-link" style="width:100%;cursor:pointer;font-family:inherit;font-size:16px" onclick="if(!this.dataset.ok){this.dataset.ok=1;this.textContent='¿Seguro? Tocá de nuevo';return false;}">Ocultar de la web</button>
			</form>
		</div>
	<?php endif; ?>

	<?php if ( $es_admin && 'publish' !== $estado ) : ?>
		<a class="clv-link" href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>">Revisar y publicar en wp-admin (solo admin)</a>
	<?php endif; ?>
</div>
	<?php
	return ob_get_clean();
} );

// ---------- Ficha pública (plantilla "Single Emprendimientos") ----------
// Acordeón "Qué visitar / Recomendaciones / Participación / Redes" (elemento evnwjl):
// se ocultan las secciones sin texto (los títulos traen un valor por defecto y aparecían vacías).
add_filter( 'bricks/element/settings', function ( $settings, $element ) {
	if ( empty( $element->id ) || 'evnwjl' !== $element->id || empty( $settings['accordions'] ) || ! function_exists( 'bricks_render_dynamic_data' ) ) {
		return $settings;
	}
	$post_id = get_the_ID();
	$items   = array();
	foreach ( $settings['accordions'] as $item ) {
		$contenido = isset( $item['content'] ) ? bricks_render_dynamic_data( $item['content'], $post_id ) : '';
		if ( '' !== trim( wp_strip_all_tags( (string) $contenido ) ) ) {
			$items[] = $item;
		}
	}
	$settings['accordions'] = $items;
	return $settings;
}, 10, 2 );

// =====================================================================
// v17.5 (10/10/2026): «Mi panel» en Bricks (página 22812).
// El código da los datos; Bricks arma las tarjetas.
// El shortcode [cl_panel_inicio] queda por ahora (ya no se usa en la página); se quita al pasar a plugin.
// =====================================================================

// {echo:cl_pi('dato')} — datos de Mi panel:
// tiene_emp · nombre · pct · hechos_de · faltan · pendiente · estado · estado_slug · recibe ·
// url_emp · url_lugar · url_insc · url_sug · url_cuenta · lugar_txt · lugar_accion · wa
function cl_pi( $dato ) {
	if ( ! cl_panel_puede_usar() ) {
		return '';
	}
	$id = cl_panel_emp_id();
	if ( 'tiene_emp' === $dato ) {
		return $id ? '1' : '';
	}
	if ( 'url_cuenta' === $dato ) {
		return esc_url( home_url( '/panel/cuenta/' ) );
	}
	if ( ! $id ) {
		return '';
	}
	$base = get_permalink( CL_PANEL_PAGE_ID );
	$qs   = cl_panel_qs( $id );
	switch ( $dato ) {
		case 'nombre':
			return esc_html( get_the_title( $id ) );
		case 'pct':
		case 'hechos_de':
		case 'faltan':
			list( $hechos, $total, $faltan ) = cl_panel_progreso( $id );
			if ( 'pct' === $dato ) {
				return (string) (int) round( $hechos * 100 / max( 1, $total ) );
			}
			return esc_html( 'hechos_de' === $dato ? $hechos . ' de ' . $total : implode( ', ', $faltan ) );
		case 'pendiente':
			$p = get_post_meta( $id, '_cl_pendiente', true );
			return ! empty( $p ) ? '1' : '';
		case 'estado':
		case 'estado_slug':
			$e = get_post_status( $id );
			$e = in_array( $e, array( 'publish', 'pending' ), true ) ? $e : 'draft';
			if ( 'estado_slug' === $dato ) {
				return $e;
			}
			$m = array( 'publish' => 'Publicada', 'pending' => 'En revisión', 'draft' => 'Borrador' );
			return $m[ $e ];
		case 'puede_publicar':
			// 1.1.1: botón «Publicar» junto al chip de estado (borrador, o en revisión si ya no hay revisión).
			$e = get_post_status( $id );
			return ( 'draft' === $e || ( 'pending' === $e && cl_sin_revision() ) ) ? '1' : '';
		case 'url_publicar':
			return esc_url( cl_panel_url_publicar( $id ) );
		case 'txt_publicar':
			return cl_sin_revision() ? 'Publicar' : 'Enviar a revisión';
		case 'recibe':
			return cl_panel_recibe_visitantes( $id ) ? '1' : '';
		case 'url_emp':
			return esc_url( $base . 'emprendimiento/' . $qs );
		case 'url_lugar':
			return esc_url( $base . 'llegar-y-estar/' . $qs );
		case 'url_insc':
			return esc_url( $base . 'inscripcion/' . $qs );
		case 'url_sug':
			return esc_url( $base . 'sugerencias/' . $qs );
		case 'lugar_txt':
		case 'lugar_accion':
			list( $l_h, $l_t ) = cl_panel_lugar_progreso( $id );
			if ( 'lugar_accion' === $dato ) {
				return $l_h ? 'Ver y editar →' : 'Empezar →';
			}
			return $l_h ? esc_html( $l_h . ' de ' . $l_t . ' respondidas' ) : 'Sin empezar';
		case 'wa':
			return esc_url( 'https://wa.me/59899247249?text=' . rawurlencode( 'Hola, necesito ayuda con mi ficha de Caminos Locales (' . get_the_title( $id ) . ')' ) );
	}
	return '';
}

add_filter( 'bricks/code/echo_function_names', function ( $names ) {
	return array_merge( (array) $names, array( 'cl_pi' ) );
} );

// [cl_panel_admin_emp] — selector de carga asistida (solo administradores). Es un formulario que
// recarga la página con ?emp=ID: queda en código porque es lógica de acceso, no presentación.
add_shortcode( 'cl_panel_admin_emp', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	$id    = cl_panel_emp_id();
	$todos = get_posts( array( 'post_type' => 'emprendimientos', 'post_status' => array( 'publish', 'draft', 'pending' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	$est   = array( 'draft' => 'Borrador', 'pending' => 'En revisión' );
	$h     = '<form class="clp-admin" method="get" action="' . esc_url( get_permalink( CL_PANEL_PAGE_ID ) ) . '" style="background:#fdf0d5;border:1px solid #e9d3a4;border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:8px">';
	$h    .= '<label for="clp-emp" style="font-size:13px;font-weight:700;color:#6b4300">Carga asistida · estás como administrador</label>';
	$h    .= '<select id="clp-emp" name="emp" onchange="this.form.submit()" style="height:44px;border:1px solid #c9a76a;border-radius:10px;padding:0 12px;font-size:16px;background:#fff"><option value="">Elegí un emprendimiento…</option>';
	foreach ( $todos as $p ) {
		$h .= '<option value="' . (int) $p->ID . '"' . selected( $id, $p->ID, false ) . '>' . esc_html( $p->post_title . ( isset( $est[ $p->post_status ] ) ? ' (' . $est[ $p->post_status ] . ')' : '' ) ) . '</option>';
	}
	return $h . '</select></form>';
} );

// 1.1.1: en el celular, la tira de pasos («Accesos a los pasos», Bricks #brxe-clpasn) arranca mostrando el paso actual.
add_action( 'wp_footer', function () {
	if ( ! is_page() || ! cl_panel_es_pagina( get_queried_object_id() ) ) {
		return;
	}
	?>
<script>(function(){
	var n = document.getElementById('brxe-clpasn');
	if (!n || n.scrollWidth <= n.clientWidth) { return; }
	var p = n.getAttribute('data-actual') || 'actividades', a = null;
	[].forEach.call(n.querySelectorAll('a[data-paso]'), function(x){ if (x.getAttribute('data-paso') === p) { a = x; } });
	if (a) { n.scrollLeft += a.getBoundingClientRect().left - n.getBoundingClientRect().left - (n.clientWidth - a.offsetWidth) / 2; }
})();</script>
	<?php
}, 40 );
