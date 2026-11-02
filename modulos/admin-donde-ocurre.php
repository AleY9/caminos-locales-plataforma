<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 30 «CL – Admin: condiciones según ¿Dónde ocurre?» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — Experiencia(evento): mostrar los grupos de condiciones
 * solo cuando "¿Dónde ocurre?" = "En otro lugar".
 */
add_action( 'acf/input/admin_footer', function () {
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'evento' ) {
		return;
	}
	?>
	<script>
	(function ($) {
		// Grupos de condiciones que dependen de "¿Dónde ocurre?"
		
		var grupos = [
			'#acf-group_6ac639ef3aaeb', // Condiciones: Llegar
			'#acf-group_6ac6541473fca', // Condiciones: Estar
			'#acf-group_6ac66a184b20b'  // Condiciones: Salud y seguridad
		];

		function actualizar() {
			var valor = $('.acf-field[data-name="donde_ocurre"] input:checked').val();
			$(grupos.join(',')).toggle(valor === 'En otro lugar');
		}

		$(document).on('change', '.acf-field[data-name="donde_ocurre"] input', actualizar);
		acf.addAction('ready', actualizar);
	})(jQuery);
	</script>
	<?php
} );