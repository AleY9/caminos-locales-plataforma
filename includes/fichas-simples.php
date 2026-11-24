<?php
/**
 * Fichas simples (1.4.0): recorrido del panel según el tipo de ficha.
 *
 * Tres tipos, que salen de lo que se marca en «Qué ofrecés» (cl_panel_tipo):
 * - servicio          Ficha compacta (cl_es_servicio): comida, guía, traslados, oficios, servicios para alojamientos…
 * - solo_alojamiento  Solo Alojamiento y «No, solo mis alojamientos» (cl_emp_sin_pagina): el emprendimiento no tiene
 *                     página; lo que se ve son sus alojamientos.
 * - completa          El resto.
 *
 * Qué cambia en los dos tipos simples:
 * - Pasos: Qué ofrecés · Presentación · Ubicación · Contacto · Ver cómo queda (sin Fotos ni Otros datos).
 *   En Bricks (página 28262): los accesos clpas5 y clpas6 llevan la condición {echo:cl_pi('tipo')} == completa,
 *   las líneas «Paso N de …» usan {echo:cl_pi('pasos_total')} y «Siguiente» de Contacto tiene dos versiones.
 *   Si se entra a ?paso=fotos u ?paso=otros, redirige a Presentación.
 * - Servicio: la foto o el logo va en Presentación (elemento Shortcode [cl_panel_fotos] con condición), y también
 *   los idiomas (campos clidio y clidot del formulario 2ikuem). Una sola descripción: la breve se arma sola con
 *   la primera oración (meta _cl_breve_auto) mientras el prestador no escriba la suya.
 * - Solo alojamiento: Presentación pide solo el nombre.
 * - Para publicar alcanza con nombre, qué ofrece, región y localidad y un contacto (panel.php,
 *   cl_panel_revision_chequeo). El progreso de Mi panel cuenta lo mismo más lo sugerido que se muestra.
 *
 * Los textos visibles nuevos (introducciones de cada paso según el tipo) están en Bricks, con condiciones sobre
 * {echo:cl_pi('tipo')}. Acá solo hay reglas y el reordenamiento de campos de los formularios.
 */

defined( 'ABSPATH' ) || exit;

function cl_panel_tipo( $id ) {
	if ( function_exists( 'cl_es_servicio' ) && cl_es_servicio( $id ) ) {
		return 'servicio';
	}
	if ( function_exists( 'cl_emp_sin_pagina' ) && cl_emp_sin_pagina( $id ) ) {
		return 'solo_alojamiento';
	}
	return 'completa';
}

function cl_panel_tipo_simple( $id ) {
	return 'completa' !== cl_panel_tipo( $id );
}

// Pasos de la ficha según el tipo (claves de ?paso=).
function cl_panel_pasos_de( $id ) {
	$todos = array( 'actividades', 'presentacion', 'ubicacion', 'contacto', 'fotos', 'otros' );
	return cl_panel_tipo_simple( $id ) ? array_slice( $todos, 0, 4 ) : $todos;
}

// Página «Mi emprendimiento» (/panel/emprendimiento/): la que tiene el formulario del paso Presentación.
function cl_panel_es_pagina_emp() {
	if ( ! is_page() || ! function_exists( 'cl_panel_es_pagina' ) || ! cl_panel_es_pagina( get_queried_object_id() ) ) {
		return false;
	}
	return 'emprendimiento' === get_post_field( 'post_name', get_queried_object_id() );
}

// Pasos que no corresponden al tipo → Presentación.
add_action( 'template_redirect', function () {
	if ( empty( $_GET['paso'] ) || ! cl_panel_es_pagina_emp() || ! is_user_logged_in() ) {
		return;
	}
	$paso = sanitize_key( wp_unslash( $_GET['paso'] ) );
	$id   = cl_panel_emp_id();
	if ( ! $id || ! in_array( $paso, array( 'fotos', 'otros' ), true ) || ! cl_panel_tipo_simple( $id ) ) {
		return;
	}
	wp_safe_redirect( add_query_arg( 'paso', 'presentacion' ) );
	exit;
}, 5 );

// Servicio: la breve descripción se arma sola con la primera oración de la descripción
// (hasta que el prestador escriba la suya en wp-admin o pase a ficha completa).
function cl_panel_breve_de( $texto ) {
	$texto = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $texto ) ) );
	if ( '' === $texto ) {
		return '';
	}
	if ( preg_match( '/^(.+?[.!?])(\s|$)/u', $texto, $m ) && mb_strlen( $m[1] ) <= 180 ) {
		return rtrim( $m[1], '.' );
	}
	if ( mb_strlen( $texto ) <= 180 ) {
		return rtrim( $texto, '.' );
	}
	$corte = mb_substr( $texto, 0, 177 );
	$corte = preg_replace( '/\s+\S*$/u', '', $corte );
	return rtrim( $corte, ' ,;:.' ) . '…';
}

add_action( 'bricks/form/custom_action', function ( $form ) {
	$f = $form->get_fields();
	if ( 'presentacion' !== ( isset( $f['form-field-clpaso'] ) ? sanitize_key( $f['form-field-clpaso'] ) : '' ) ) {
		return;
	}
	if ( ! cl_panel_puede_usar() ) {
		return;
	}
	$pedido = isset( $f['form-field-clempi'] ) ? (int) $f['form-field-clempi'] : 0;
	$id     = current_user_can( 'manage_options' ) ? cl_panel_emp_id( $pedido ) : cl_panel_emp_id();
	if ( ! $id || 'servicio' !== cl_panel_tipo( $id ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) && (int) get_post_field( 'post_author', $id ) !== get_current_user_id() ) {
		return;
	}
	$actual = trim( (string) get_post_meta( $id, 'breve_descripcion', true ) );
	if ( '' !== $actual && '1' !== get_post_meta( $id, '_cl_breve_auto', true ) ) {
		return; // Tiene una breve propia: no se toca.
	}
	$breve = cl_panel_breve_de( get_post_field( 'post_content', $id ) );
	if ( $breve !== $actual ) {
		update_field( 'breve_descripcion', $breve, $id );
	}
	update_post_meta( $id, '_cl_breve_auto', '1' );
}, 20 );

// Reordenar y simplificar los formularios según el tipo (los textos nuevos están en Bricks).
add_action( 'wp_footer', function () {
	if ( ! cl_panel_es_pagina_emp() ) {
		return;
	}
	$id = cl_panel_emp_id();
	if ( ! $id ) {
		return;
	}
	$tipo  = cl_panel_tipo( $id );
	$mapa  = trim( (string) get_post_meta( $id, 'ubicacion', true ) ) . trim( (string) get_post_meta( $id, 'direccion_o_punto_de_encuentro', true ) );
	?>
<style>.clp-fs{border:1px solid #e0dacd;border-radius:12px;background:#fbfaf6;padding:0 14px;margin:0 0 20px}.clp-fs>summary{min-height:48px;display:flex;align-items:center;font-weight:700;color:#1f4645;cursor:pointer;font-size:16px}.clp-fs>summary span{font-weight:400;color:#5b6a68;font-size:15px;margin-left:6px}.clp-fs-ayu{font-size:14px;line-height:1.45;color:#5b6a68;margin:0 0 12px}</style>
<script>
(function(){
	var T = <?php echo wp_json_encode( $tipo ); ?>, HAY_LUGAR = <?php echo $mapa ? 'true' : 'false'; ?>;
	var form = [].filter.call(document.querySelectorAll('form.brxe-form'), function(x){ return x.querySelector('[name="form-field-clpaso"]'); })[0];
	if (!form) { return; }
	var paso = (form.querySelector('[name="form-field-clpaso"]') || {}).value;
	function g(id){ var el = form.querySelector('[name="form-field-' + id + '"], [name="form-field-' + id + '[]"], [data-cl="' + id + '"]'); return el && el.closest('.form-group'); }
	function ocultar(id){ var x = g(id); if (x) { x.style.display = 'none'; [].forEach.call(x.querySelectorAll('[required]'), function(i){ i.required = false; }); } }
	function etiqueta(id, txt){ var x = g(id), l = x && x.querySelector('label'); if (l) { l.firstChild.textContent = txt; } }
	// Mete en un desplegable los grupos de la lista, en su orden.
	function plegar(ids, titulo, nota, ayuda, abierto){
		var grupos = ids.map(g).filter(Boolean);
		if (!grupos.length) { return null; }
		var d = document.createElement('details'); d.className = 'clp-fs'; d.open = !!abierto;
		var s = document.createElement('summary'); s.textContent = titulo;
		if (nota) { var n = document.createElement('span'); n.textContent = nota; s.appendChild(n); }
		d.appendChild(s);
		if (ayuda) { var p = document.createElement('p'); p.className = 'clp-fs-ayu'; p.textContent = ayuda; d.appendChild(p); }
		grupos[0].parentNode.insertBefore(d, grupos[0]);
		grupos.forEach(function(x){ d.appendChild(x); });
		return d;
	}

	if (paso === 'presentacion') {
		if (T === 'completa') {
			ocultar('clidio'); ocultar('clidot');
			var br = form.querySelector('[name="form-field-clbrev"]');
			if (br) { br.required = true; } // en Bricks ya no es obligatorio: lo exige el código solo acá
		} else {
			ocultar('clbrev');
		}
		if (T === 'servicio') {
			etiqueta('clnomb', 'Nombre (tuyo o de tu servicio)');
		}
		if (T === 'solo_alojamiento') {
			['clhdes', 'cldesc', 'clhqui', 'clquie', 'clvide', 'clidio', 'clidot'].forEach(ocultar);
			etiqueta('clnomb', 'Nombre del lugar');
		}
	}

	if (paso === 'ubicacion' && T === 'servicio') {
		// Primero la zona de trabajo; el lugar fijo (mapa y dirección), plegado y opcional.
		var zona = ['clhsrv', 'clsalc', 'clskm', 'clsnot'].map(g).filter(Boolean);
		var loca = g('clloca');
		if (loca && zona.length) {
			var ref = loca.nextSibling;
			zona.forEach(function(x){ loca.parentNode.insertBefore(x, ref); });
		}
		var d = plegar(['clhmap', 'clmapa', 'cldire'], '¿Atendés en un lugar fijo?', '(opcional)', 'Si tenés local, puesto o taller, poné la dirección y el mapa: se ven en tu ficha con el botón «Cómo llegar». Si trabajás a domicilio o vas a donde te llaman, dejalo vacío.', HAY_LUGAR);
		if (d) { var ult = zona[zona.length - 1]; if (ult && ult.parentNode === d.parentNode) { ult.parentNode.insertBefore(d, ult.nextSibling); } }
		var prev = document.getElementById('brxe-z6mnmw'); // vista previa del mapa ([cl_panel_mapa])
		if (prev && !HAY_LUGAR) { prev.style.display = 'none'; }
	}

	if (paso === 'contacto') {
		// Qué datos se muestran (web y directorio de la red): plegado, porque por defecto se muestra todo.
		plegar(['clcvis', 'clhcvi'], 'Qué datos se muestran en la web', '(todos, salvo que desmarques)', '', false);
		if (T !== 'completa') {
			// Email: la ficha simple no tiene formulario de consultas.
			[].forEach.call(form.querySelectorAll('.form-group > p'), function(p){
				if (p.textContent.indexOf('A este email llegan') === 0) { p.textContent = 'Si tu ficha ya está publicada, un cambio de email lo revisa Caminos Locales antes.'; }
			});
		}
		if (T === 'servicio') {
			// «¿Dónde aparece tu servicio?» arriba: es lo primero que decide un prestador.
			var tel = g('cltele'), q = ['clhsvc', 'clspub'].map(g).filter(Boolean);
			if (tel && q.length) { q.forEach(function(x){ tel.parentNode.insertBefore(x, tel); }); }
		}
	}
})();
</script>
	<?php
}, 35 );
