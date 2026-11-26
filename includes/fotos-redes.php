<?php
/**
 * Fotos en redes (1.4.5): permiso del emprendimiento para usar sus fotos en las redes de Caminos Locales.
 *
 * - Una sola decisión por emprendimiento (cubre sus alojamientos y experiencias). Meta _cl_fotos_redes: '1' sí,
 *   '0' no, vacío = todavía no la vio. _cl_fotos_redes_fecha: cuándo quedó registrada.
 * - La casilla viene marcada. Se muestra al final de las fotos del emprendimiento ([cl_panel_fotos]: paso Fotos, o
 *   Presentación en la ficha de servicio) y, si el emprendimiento no tiene página propia (solo alojamiento), al
 *   final de las fotos del alojamiento ([cl_panel_aloj_fotos]).
 * - Cuando el emprendedor (no el admin) ve la casilla sin haber decidido, queda registrado «sí» con la fecha:
 *   la vio marcada y la dejó así. Lo que carga el admin en una carga asistida no registra nada.
 * - Desmarcar o volver a marcar se guarda al momento (admin-ajax cl_fotos_redes) y va a los avisos.
 * - El admin ve el estado en el recuadro de carga asistida de Mi panel.
 * - Criterio interno (no va en la casilla): en redes no se usan fotos donde se reconozca a menores sin permiso
 *   expreso de quienes los tienen a cargo.
 */

defined( 'ABSPATH' ) || exit;

// '1', '0' o '' (sin decidir).
function cl_fotos_redes_estado( $emp_id ) {
	$v = (string) get_post_meta( (int) $emp_id, '_cl_fotos_redes', true );
	return in_array( $v, array( '1', '0' ), true ) ? $v : '';
}

// ¿Se pueden usar en redes? Sin decidir cuenta como no: hay que preguntar.
function cl_fotos_redes( $emp_id ) {
	return '1' === cl_fotos_redes_estado( $emp_id );
}

function cl_fotos_redes_guardar( $emp_id, $si ) {
	update_post_meta( (int) $emp_id, '_cl_fotos_redes', $si ? '1' : '0' );
	update_post_meta( (int) $emp_id, '_cl_fotos_redes_fecha', current_time( 'mysql' ) );
}

// La casilla, para poner dentro del contenedor .clf de las fotos.
function cl_fotos_redes_html( $emp_id ) {
	$emp_id = (int) $emp_id;
	if ( ! $emp_id ) {
		return '';
	}
	$estado = cl_fotos_redes_estado( $emp_id );
	if ( '' === $estado && ! current_user_can( 'manage_options' ) ) {
		cl_fotos_redes_guardar( $emp_id, true );
		$estado = '1';
	}
	$marcada = '0' !== $estado;
	$nonce   = wp_create_nonce( 'cl_fotos_redes_' . $emp_id );
	ob_start();
	?>
<div class="clf-redes" data-emp="<?php echo (int) $emp_id; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" style="background:#fbfaf6;border:1px solid #e0dacd;border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:6px">
	<label style="display:flex;gap:10px;align-items:flex-start;font-weight:700;font-size:15px;color:#23302f;cursor:pointer;min-height:44px">
		<input type="checkbox" <?php checked( $marcada ); ?> style="width:22px;height:22px;flex:0 0 22px;margin:1px 0 0;accent-color:#1f4645">
		<span>Caminos Locales puede usar mis fotos en sus redes sociales</span>
	</label>
	<div style="font-size:14px;line-height:1.4;color:#5b6a68">Para publicaciones propias o promocionadas (pagas) que muestran tu emprendimiento, siempre con tu nombre. Si preferís que no, desmarcala: lo podés cambiar cuando quieras.</div>
	<div class="clf-redes-estado" aria-live="polite" style="font-size:14px;font-weight:700;color:#1f4645" hidden></div>
</div>
<script>
(function(){
var c=document.currentScript.previousElementSibling;if(!c||!c.classList.contains('clf-redes'))return;
var cb=c.querySelector('input'),st=c.querySelector('.clf-redes-estado'),t;
cb.addEventListener('change',function(){
	var d=new FormData();d.append('action','cl_fotos_redes');d.append('emp',c.dataset.emp);d.append('nonce',c.dataset.nonce);d.append('si',cb.checked?'1':'0');
	st.hidden=false;st.style.color='#1f4645';st.textContent='Guardando…';
	fetch(c.dataset.ajax,{method:'POST',body:d,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(r){
		if(r&&r.ok){st.textContent='Guardado ✓';clearTimeout(t);t=setTimeout(function(){st.hidden=true;},2500);}
		else{throw new Error((r&&r.msg)||'');}
	}).catch(function(e){cb.checked=!cb.checked;st.style.color='#8a2a1f';st.textContent=e.message||'No se pudo guardar. Probá de nuevo.';});
});
})();
</script>
	<?php
	return ob_get_clean();
}

add_action( 'wp_ajax_cl_fotos_redes', function () {
	$error = function ( $msg ) {
		wp_send_json( array( 'ok' => false, 'msg' => $msg ) );
	};
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		$error( 'Tu sesión se cerró. Volvé a entrar y probá de nuevo.' );
	}
	$emp_id = cl_panel_emp_id( isset( $_POST['emp'] ) ? (int) $_POST['emp'] : 0 );
	if ( ! $emp_id || ! check_ajax_referer( 'cl_fotos_redes_' . $emp_id, 'nonce', false ) ) {
		$error( 'No se pudo guardar. Recargá la página y probá de nuevo.' );
	}
	$si    = isset( $_POST['si'] ) && '1' === $_POST['si'];
	$antes = cl_fotos_redes_estado( $emp_id );
	cl_fotos_redes_guardar( $emp_id, $si );
	if ( ( $si ? '1' : '0' ) !== $antes && ! current_user_can( 'manage_options' ) && function_exists( 'clp_aviso_cambios' ) ) {
		$nombre = get_the_title( $emp_id );
		$que    = $si ? 'Fotos en redes: sí' : 'Fotos en redes: no';
		clp_aviso_cambios(
			$emp_id,
			'emprendimientos',
			array( $que ),
			'Caminos Locales: ' . $nombre . ' — ' . $que,
			$nombre . ' cambió el permiso para usar sus fotos en redes: ' . ( $si ? 'ahora sí.' : 'ahora no.' ) . "\n\n" . get_permalink( $emp_id )
		);
	}
	wp_send_json( array( 'ok' => true ) );
} );

// Línea para el recuadro de carga asistida de Mi panel.
function cl_fotos_redes_admin( $emp_id ) {
	if ( ! $emp_id ) {
		return '';
	}
	$estado = cl_fotos_redes_estado( $emp_id );
	$fecha  = get_post_meta( $emp_id, '_cl_fotos_redes_fecha', true );
	$cuando = $fecha ? ' (' . esc_html( mysql2date( 'd/m/Y', $fecha ) ) . ')' : '';
	if ( '1' === $estado ) {
		$txt = 'Sí' . $cuando;
	} elseif ( '0' === $estado ) {
		$txt = 'No' . $cuando . '. No usar sus fotos en redes.';
	} else {
		$txt = 'Sin responder: el dueño todavía no vio la casilla. Pedí permiso antes de usarlas.';
	}
	return '<div style="border-top:1px solid #e9d3a4;padding-top:10px;font-size:15px;color:#3d4b49"><strong>Fotos en redes:</strong> ' . $txt . '</div>';
}
