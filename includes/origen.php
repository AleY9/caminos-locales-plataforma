<?php
/**
 * De dónde llegan las solicitudes, y «¿Cómo conociste Caminos Locales?» (1.4.6).
 *
 * 1) Sin preguntar nada en «Pedí sumarte»: cuando alguien entra al sitio, un script anota en su navegador
 *    (localStorage cl_origen, 60 días) de dónde vino: el sitio que lo trajo (Instagram, Facebook, Google…),
 *    utm_source o ?desde= si el enlace lo trae, o «directo». Manda el primer origen externo; «directo» se reemplaza si
 *    después llega desde otro sitio. No guarda nada de la persona y no se manda a ningún lado hasta que envía el
 *    formulario: en «Pedí sumarte» se suma un campo oculto cl_origen, que el plugin guarda en la solicitud
 *    (_cl_solicitud['origen'] y ['origen_pag']) y muestra en el aviso («Llegó desde»).
 *    Ojo: desde la app de WhatsApp (y otras apps) muchas veces no se sabe de dónde viene: queda «directo».
 * 2) Pregunta opcional en el panel, al final de «Ver cómo queda» del emprendimiento: «¿Cómo conociste Caminos
 *    Locales?» (lista). Se guarda al momento (admin-ajax cl_como_conocio) en la meta _cl_como_conocio.
 * El recuadro de carga asistida de Mi panel muestra las dos cosas.
 */

defined( 'ABSPATH' ) || exit;

// ---------- 1) Origen de la visita ----------

add_action( 'wp_footer', function () {
	if ( is_admin() || is_feed() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
		return;
	}
	?>
<script>(function(){try{
var K='cl_origen',ls=window.localStorage,prev=null,now=Date.now();
try{prev=JSON.parse(ls.getItem(K)||'null');}catch(e){}
if(prev&&(!prev.t||now-prev.t>60*864e5)){prev=null;}
var q=new URLSearchParams(location.search),o=(q.get('utm_source')||q.get('desde')||'').toLowerCase().replace(/[^a-z0-9._ -]/g,'').slice(0,40);
if(!o&&q.get('igshid')){o='instagram';}
if(!o&&document.referrer){try{var h=new URL(document.referrer).hostname.replace(/^www\./,'');if(h&&h!==location.hostname.replace(/^www\./,'')){o=h;}}catch(e){}}
if(o?(!prev||prev.o==='directo'):!prev){prev={o:o||'directo',t:now,p:location.pathname.slice(0,80)};ls.setItem(K,JSON.stringify(prev));}
var c=document.querySelector('[name^="form-field-psempr"]'),f=c&&c.form;
if(f&&!f.querySelector('[name="cl_origen"]')){var i=document.createElement('input');i.type='hidden';i.name='cl_origen';i.value=prev.o+'|'+prev.p;f.appendChild(i);}
}catch(e){}})();</script>
	<?php
}, 50 );

// Nombre legible de un origen ('instagram.com', 'l.facebook.com', 'directo'…).
function cl_origen_nombre( $o ) {
	$o = strtolower( trim( (string) $o ) );
	$m = array(
		'instagram' => 'Instagram',
		'facebook'  => 'Facebook',
		'fb.'       => 'Facebook',
		'whatsapp'  => 'WhatsApp',
		'wa.me'     => 'WhatsApp',
		'google'    => 'Google',
		'bing'      => 'Bing',
		'duckduckgo' => 'DuckDuckGo',
		'yahoo'     => 'Yahoo',
		'chatgpt'   => 'ChatGPT',
		'openai'    => 'ChatGPT',
		'claude.ai' => 'Claude',
		'linkedin'  => 'LinkedIn',
		'youtube'   => 'YouTube',
		't.co'      => 'X (Twitter)',
		'twitter'   => 'X (Twitter)',
		'tiktok'    => 'TikTok',
	);
	if ( '' === $o || 'directo' === $o ) {
		return 'Directo (escribió la dirección, un enlace guardado o una app que no dice de dónde viene, como WhatsApp)';
	}
	foreach ( $m as $clave => $nombre ) {
		if ( false !== strpos( $o, $clave ) ) {
			return $nombre;
		}
	}
	return $o; // Otro sitio: queda el dominio.
}

// Lee el campo oculto del formulario: array( origen, página de llegada ).
function cl_origen_de_envio() {
	$v = isset( $_POST['cl_origen'] ) ? sanitize_text_field( wp_unslash( $_POST['cl_origen'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' === $v ) {
		return array( '', '' );
	}
	list( $o, $p ) = array_pad( explode( '|', $v, 2 ), 2, '' );
	$o = mb_substr( preg_replace( '/[^a-z0-9._ -]/', '', strtolower( $o ) ), 0, 60 );
	$p = mb_substr( '/' . ltrim( (string) wp_parse_url( $p, PHP_URL_PATH ), '/' ), 0, 80 );
	return array( cl_origen_nombre( $o ), $p );
}

// ---------- 2) «¿Cómo conociste Caminos Locales?» en el panel ----------

function cl_como_conocio_opciones() {
	return array(
		'instagram'   => 'Por Instagram',
		'facebook'    => 'Por Facebook',
		'invitacion'  => 'Me escribió alguien de Caminos Locales',
		'recomendo'   => 'Me lo recomendó otra persona',
		'otro_emp'    => 'Por otro emprendimiento que está en Caminos Locales',
		'internet'    => 'Buscando en internet',
		'otro'        => 'De otra forma',
	);
}

function cl_como_conocio( $emp_id ) {
	$v = (string) get_post_meta( (int) $emp_id, '_cl_como_conocio', true );
	return array_key_exists( $v, cl_como_conocio_opciones() ) ? $v : '';
}

// Caja para el final de «Ver cómo queda» ([cl_panel_vista]).
function cl_como_conocio_html( $emp_id ) {
	$emp_id = (int) $emp_id;
	if ( ! $emp_id ) {
		return '';
	}
	$actual = cl_como_conocio( $emp_id );
	$nonce  = wp_create_nonce( 'cl_como_conocio_' . $emp_id );
	$h      = '<div class="clv-box cl-conocio" data-emp="' . $emp_id . '" data-nonce="' . esc_attr( $nonce ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '">';
	$h     .= '<label for="cl-conocio-' . $emp_id . '" class="clv-h" style="display:block">¿Cómo conociste Caminos Locales? <span style="font-family:\'Source Sans 3\',sans-serif;font-weight:400;font-size:14px;color:#7a8583">(opcional)</span></label>';
	$h     .= '<select id="cl-conocio-' . $emp_id . '" style="height:44px;border:1px solid #c9c2b3;border-radius:10px;padding:0 12px;font-size:16px;background:#fff;max-width:100%">';
	$h     .= '<option value="">Elegí una opción…</option>';
	foreach ( cl_como_conocio_opciones() as $k => $txt ) {
		$h .= '<option value="' . esc_attr( $k ) . '"' . selected( $actual, $k, false ) . '>' . esc_html( $txt ) . '</option>';
	}
	$h .= '</select><div class="cl-conocio-estado" aria-live="polite" style="font-size:14px;font-weight:700;color:#1f4645;min-height:20px"></div></div>';
	$h .= '<script>(function(){var c=document.currentScript.previousElementSibling;if(!c||!c.classList.contains("cl-conocio"))return;'
		. 'var s=c.querySelector("select"),st=c.querySelector(".cl-conocio-estado"),t;'
		. 's.addEventListener("change",function(){var d=new FormData();d.append("action","cl_como_conocio");d.append("emp",c.dataset.emp);d.append("nonce",c.dataset.nonce);d.append("v",s.value);'
		. 'st.style.color="#1f4645";st.textContent="Guardando…";'
		. 'fetch(c.dataset.ajax,{method:"POST",body:d,credentials:"same-origin"}).then(function(r){return r.json();}).then(function(r){if(r&&r.ok){st.textContent="Guardado ✓ ¡Gracias!";clearTimeout(t);t=setTimeout(function(){st.textContent="";},2500);}else{throw new Error((r&&r.msg)||"");}})'
		. '.catch(function(e){st.style.color="#8a2a1f";st.textContent=e.message||"No se pudo guardar. Probá de nuevo.";});});})();</script>';
	return $h;
}

add_action( 'wp_ajax_cl_como_conocio', function () {
	$error = function ( $msg ) {
		wp_send_json( array( 'ok' => false, 'msg' => $msg ) );
	};
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		$error( 'Tu sesión se cerró. Volvé a entrar y probá de nuevo.' );
	}
	$emp_id = cl_panel_emp_id( isset( $_POST['emp'] ) ? (int) $_POST['emp'] : 0 );
	if ( ! $emp_id || ! check_ajax_referer( 'cl_como_conocio_' . $emp_id, 'nonce', false ) ) {
		$error( 'No se pudo guardar. Recargá la página y probá de nuevo.' );
	}
	$v = isset( $_POST['v'] ) ? sanitize_key( wp_unslash( $_POST['v'] ) ) : '';
	if ( '' === $v ) {
		delete_post_meta( $emp_id, '_cl_como_conocio' );
	} elseif ( array_key_exists( $v, cl_como_conocio_opciones() ) ) {
		update_post_meta( $emp_id, '_cl_como_conocio', $v );
	} else {
		$error( 'Elegí una de las opciones.' );
	}
	wp_send_json( array( 'ok' => true ) );
} );

// Línea para el recuadro de carga asistida de Mi panel.
function cl_origen_admin( $emp_id ) {
	if ( ! $emp_id ) {
		return '';
	}
	$partes = array();
	$sol    = get_post_meta( $emp_id, '_cl_solicitud', true );
	if ( is_array( $sol ) && ! empty( $sol['origen'] ) ) {
		$partes[] = 'su solicitud llegó desde ' . esc_html( $sol['origen'] );
	}
	$c = cl_como_conocio( $emp_id );
	if ( $c ) {
		$partes[] = 'respondió «' . esc_html( cl_como_conocio_opciones()[ $c ] ) . '»';
	}
	if ( ! $partes ) {
		return '';
	}
	return '<div style="border-top:1px solid #e9d3a4;padding-top:10px;font-size:15px;color:#3d4b49"><strong>Cómo nos conoció:</strong> ' . implode( '; ', $partes ) . '.</div>';
}
