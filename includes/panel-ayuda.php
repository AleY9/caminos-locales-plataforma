<?php
/**
 * Ayudas del panel (1.1.14).
 *
 * - El «?» con que empiezan los desplegables de ayuda («? Ideas para…») pasa a un ícono en círculo:
 *   <span class="cl-ayuda">?</span>. El estilo de .cl-ayuda está en el CSS de cada página del panel, en Bricks.
 *   Sirve para las ayudas de los formularios de Bricks y para las que arma el plugin («Qué fotos funcionan mejor»).
 * - La guía rápida de «¿Qué ofrecés?» (campo clhgui) se ve abierta si todavía no hay nada marcado.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'cl_panel_es_pagina' ) || ! is_page() || ! cl_panel_es_pagina( get_queried_object_id() ) ) {
		return;
	}
	?>
<script>
(function(){
	[].forEach.call(document.querySelectorAll('summary'), function(s){
		var t = s.firstChild;
		if (!t || t.nodeType !== 3 || !/^\s*\?\s*/.test(t.textContent)) { return; }
		t.textContent = t.textContent.replace(/^\s*\?\s*/, '');
		var i = document.createElement('span');
		i.className = 'cl-ayuda';
		i.setAttribute('aria-hidden', 'true');
		i.textContent = '?';
		s.insertBefore(i, t);
	});
	var g = document.querySelector('[data-cl="clhgui"]');
	if (g && document.querySelector('input[name^="form-field-clrubr"]') && !document.querySelector('input[name^="form-field-clrubr"]:checked')) {
		g.open = true;
	}
})();
</script>
	<?php
}, 40 );
