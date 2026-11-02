<?php
// Módulo de Caminos Locales Plataforma. Antes: snippet 21 «CL – Panel: Fotos» de Code Snippets (código sin cambios).
defined( 'ABSPATH' ) || exit;

/**
 * Caminos Locales — Panel: paso Fotos (fase 4, v2, sept. 2026)
 * v2: la foto anterior se borra bien; en carga asistida la foto va a la carpeta del emprendimiento y se renombra.
 * Code Snippets: tipo PHP, "Ejecutar en todas partes". Sin <?php.
 * Necesita "CL – Panel" (cl_panel_emp_id, cl_panel_puede_usar) y "CL – Subidas de fotos".
 *
 * - Shortcode [cl_panel_fotos]: una tarjeta por foto (principal, logo, lugar, quiénes somos, actividades).
 * - Cada foto se sube y se guarda al instante en su lugar de la ficha. El navegador la achica antes
 *   de enviarla si pesa (conexiones rurales); el servidor igual la deja en 1920 px.
 * - Cambiar o Quitar borra la foto anterior de la biblioteca SOLO si la subió un emprendedor
 *   (o se subió desde el panel) y no se usa en ningún otro lado. Las fotos viejas que subió
 *   el administrador se desvinculan pero quedan en la biblioteca.
 * - Limpieza diaria: fotos subidas desde el panel hace más de 48 h que no quedaron en ninguna ficha.
 */

// Lugares de foto de la ficha. meta = clave en la base; key = campo ACF; grupo = grupo ACF (si tiene).
function cl_fotos_slots() {
	$s = array(
		'principal' => array( 'meta' => '_thumbnail_id', 'label' => 'Foto principal' ),
		'logo'      => array( 'meta' => 'logo', 'key' => 'field_60788bd57d291', 'label' => 'Logo' ),
		'quienes'   => array( 'meta' => 'foto_del_emprendimiento', 'key' => 'field_606cb589c744a', 'label' => '¿Quiénes somos?' ),
	);
	$pres = array( 'field_66fd8f9996ed1', 'field_66fd8f9996ed2', 'field_66fea9f7e3229' );
	foreach ( $pres as $i => $k ) {
		$s[ 'pres' . ( $i + 1 ) ] = array( 'meta' => 'fotos_presentacion_fotop' . ( $i + 1 ), 'key' => $k, 'grupo' => array( 'fotos_presentacion', 'field_66fd8f9996ed0' ), 'label' => 'Lugar ' . ( $i + 1 ) );
	}
	$act = array( 'field_66fafabe9cd75', 'field_66fafb339cd76', 'field_66fafb499cd77', 'field_66fafb589cd78', 'field_68de6b88d15f7', 'field_68de6b97d15f8' );
	foreach ( $act as $i => $k ) {
		$s[ 'act' . ( $i + 1 ) ] = array( 'meta' => 'fotos_actividades_foto' . ( $i + 1 ), 'key' => $k, 'grupo' => array( 'fotos_actividades', 'field_6079bfa0f0433' ), 'label' => 'Actividad ' . ( $i + 1 ) );
	}
	return $s;
}

function cl_fotos_leer( $emp_id, $slot ) {
	$s = cl_fotos_slots()[ $slot ];
	$v = get_post_meta( $emp_id, $s['meta'], true );
	return ( is_numeric( $v ) && (int) $v > 0 && wp_attachment_is_image( (int) $v ) ) ? (int) $v : 0;
}

function cl_fotos_guardar( $emp_id, $slot, $att_id ) {
	$s = cl_fotos_slots()[ $slot ];
	if ( 'principal' === $slot ) {
		return $att_id ? set_post_thumbnail( $emp_id, $att_id ) : delete_post_thumbnail( $emp_id );
	}
	update_post_meta( $emp_id, $s['meta'], $att_id ? (int) $att_id : '' );
	update_post_meta( $emp_id, '_' . $s['meta'], $s['key'] );
	if ( ! empty( $s['grupo'] ) && ! get_post_meta( $emp_id, '_' . $s['grupo'][0], true ) ) {
		update_post_meta( $emp_id, $s['grupo'][0], '' );
		update_post_meta( $emp_id, '_' . $s['grupo'][0], $s['grupo'][1] );
	}
	return true;
}

// ¿Se usa esta imagen en algún lado? (ante la duda, sí: no se borra)
function cl_fotos_en_uso( $att_id ) {
	global $wpdb;
	$id   = (int) $att_id;
	$meta = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id <> %d AND ( meta_value = %s OR meta_value LIKE %s OR ( meta_key LIKE %s AND meta_value LIKE %s ) )",
		$id, (string) $id, '%"' . $id . '"%', $wpdb->esc_like( '_bricks' ) . '%', '%i:' . $id . ';%'
	) );
	if ( $meta ) {
		return true;
	}
	// Nombres exactos del archivo y sus tamaños (ej. 2026/09/cl-ab12cd34-768x512.jpg), buscados en el contenido.
	$rel   = (string) get_post_meta( $id, '_wp_attached_file', true );
	$dir   = trim( dirname( $rel ), './' );
	$files = array( wp_basename( $rel ) );
	$md    = wp_get_attachment_metadata( $id );
	if ( ! empty( $md['sizes'] ) ) {
		foreach ( $md['sizes'] as $t ) {
			$files[] = $t['file'];
		}
	}
	$where = array( 'post_content LIKE %s', 'post_content LIKE %s' );
	$args  = array( '%wp-image-' . $id . '"%', '%wp-image-' . $id . ' %' );
	foreach ( array_unique( array_filter( $files ) ) as $f ) {
		$where[] = 'post_content LIKE %s';
		$args[]  = '%' . $wpdb->esc_like( ( $dir ? $dir . '/' : '' ) . $f ) . '%';
	}
	$cont = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','attachment') AND ( " . implode( ' OR ', $where ) . ' )',
		...$args
	) );
	return $cont > 0;
}

// Solo se borran fotos subidas desde el panel o por un emprendedor.
function cl_fotos_borrable( $att_id ) {
	if ( ! $att_id || 'attachment' !== get_post_type( $att_id ) ) {
		return false;
	}
	if ( get_post_meta( $att_id, '_cl_panel_foto', true ) ) {
		return true;
	}
	$autor = get_userdata( (int) get_post_field( 'post_author', $att_id ) );
	return $autor && array_intersect( array( 'cl_emprendedor', 'cl_emprendedor_pendiente' ), (array) $autor->roles );
}

function cl_fotos_quizas_borrar( $att_id ) {
	if ( cl_fotos_borrable( $att_id ) && ! cl_fotos_en_uso( $att_id ) ) {
		wp_delete_attachment( $att_id, true );
	}
}

function cl_fotos_url( $att_id ) {
	$u = wp_get_attachment_image_url( $att_id, 'medium_large' );
	return $u ? $u : (string) wp_get_attachment_url( $att_id );
}

// ---------- Subir / quitar (admin-ajax) ----------
add_action( 'wp_ajax_cl_foto', function () {
	$error = function ( $msg ) {
		wp_send_json( array( 'ok' => false, 'msg' => $msg ) );
	};
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		$error( 'Tu sesión se cerró. Volvé a entrar y probá de nuevo.' );
	}
	$emp_id = cl_panel_emp_id( isset( $_POST['emp'] ) ? (int) $_POST['emp'] : 0 );
	if ( ! $emp_id || ! check_ajax_referer( 'cl_fotos_' . $emp_id, 'nonce', false ) ) {
		$error( 'No se pudo guardar. Recargá la página y probá de nuevo.' );
	}
	$slot  = isset( $_POST['slot'] ) ? sanitize_key( $_POST['slot'] ) : '';
	$slots = cl_fotos_slots();
	if ( ! isset( $slots[ $slot ] ) ) {
		$error( 'Lugar de foto desconocido.' );
	}
	$op    = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	$vieja = cl_fotos_leer( $emp_id, $slot );

	if ( 'quitar' === $op ) {
		if ( 'principal' === $slot && 'publish' === get_post_status( $emp_id ) ) {
			$error( 'La foto principal no se puede quitar de una ficha publicada: tocá Cambiar para poner otra.' );
		}
		cl_fotos_guardar( $emp_id, $slot, 0 );
		if ( $vieja ) {
			cl_fotos_quizas_borrar( $vieja );
		}
		wp_send_json( array( 'ok' => true, 'id' => 0, 'url' => '' ) );
	}

	if ( 'subir' !== $op || empty( $_FILES['foto'] ) ) {
		$error( 'No llegó ninguna foto.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// Carpeta del servidor y nombre del archivo como en las subidas de emprendedores
	// (para el emprendedor ya lo hacen los otros snippets; esto cubre la carga asistida).
	$slug     = sanitize_title( get_post_field( 'post_name', $emp_id ) );
	$slug     = $slug ? $slug : 'emp-' . $emp_id;
	$dir_fn   = function ( $d ) use ( $slug ) {
		$d['subdir'] = '/emprendimientos/' . $slug;
		$d['path']   = $d['basedir'] . $d['subdir'];
		$d['url']    = $d['baseurl'] . $d['subdir'];
		return $d;
	};
	$nombre_fn = function ( $file ) {
		if ( empty( $file['error'] ) && ! preg_match( '/^cl-[a-z0-9]{8}\./', $file['name'] ) ) {
			$ext          = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			$file['name'] = 'cl-' . strtolower( wp_generate_password( 8, false ) ) . '.' . ( $ext ? $ext : 'jpg' );
		}
		return $file;
	};
	$es_emp = function_exists( 'cl_es_emprendedor' ) && cl_es_emprendedor();
	if ( ! $es_emp ) {
		add_filter( 'upload_dir', $dir_fn, 20 );
		add_filter( 'wp_handle_upload_prefilter', $nombre_fn, 20 );
	}
	$att = media_handle_upload( 'foto', $emp_id, array( 'post_title' => get_the_title( $emp_id ) . ' — ' . $slots[ $slot ]['label'] ) );
	if ( ! $es_emp ) {
		remove_filter( 'upload_dir', $dir_fn, 20 );
		remove_filter( 'wp_handle_upload_prefilter', $nombre_fn, 20 );
	}
	if ( is_wp_error( $att ) ) {
		$error( $att->get_error_message() );
	}
	update_post_meta( $att, '_cl_panel_foto', 1 );
	if ( function_exists( 'cl_carpeta_asignar_foto' ) && function_exists( 'cl_carpeta_de_emprendimiento' ) ) {
		cl_carpeta_asignar_foto( $att, cl_carpeta_de_emprendimiento( $emp_id ) );
	}
	update_post_meta( $att, '_wp_attachment_image_alt', get_the_title( $emp_id ) . ' — ' . $slots[ $slot ]['label'] );
	// Carga asistida: la foto queda a nombre del dueño de la ficha (su carpeta, sus límites).
	$dueno = (int) get_post_field( 'post_author', $emp_id );
	if ( $dueno && $dueno !== (int) get_post_field( 'post_author', $att ) ) {
		wp_update_post( array( 'ID' => $att, 'post_author' => $dueno ) );
	}
	cl_fotos_guardar( $emp_id, $slot, $att );
	if ( $vieja && $vieja !== $att ) {
		cl_fotos_quizas_borrar( $vieja );
	}
	wp_send_json( array( 'ok' => true, 'id' => $att, 'url' => cl_fotos_url( $att ) ) );
} );

// ---------- Limpieza diaria (fotos del panel que quedaron sueltas más de 48 h) ----------
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'cl_fotos_limpieza' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cl_fotos_limpieza' );
	}
} );
add_action( 'cl_fotos_limpieza', function () {
	$ids = get_posts( array(
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'numberposts' => 200,
		'fields'      => 'ids',
		'meta_key'    => '_cl_panel_foto',
		'date_query'  => array( array( 'before' => '48 hours ago' ) ),
	) );
	foreach ( $ids as $id ) {
		if ( ! cl_fotos_en_uso( $id ) ) {
			wp_delete_attachment( $id, true );
		}
	}
} );

// ---------- Shortcode [cl_panel_fotos] ----------
add_shortcode( 'cl_panel_fotos', function () {
	if ( ! function_exists( 'cl_panel_puede_usar' ) || ! cl_panel_puede_usar() ) {
		return '';
	}
	$emp_id = cl_panel_emp_id();
	if ( ! $emp_id ) {
		return '<p>Elegí un emprendimiento en <a href="/panel/">Mi panel</a>.</p>';
	}
	$publicada = 'publish' === get_post_status( $emp_id );

	$tarjeta = function ( $slot, $label, $ayuda = '', $grande = false ) use ( $emp_id, $publicada ) {
		$id  = cl_fotos_leer( $emp_id, $slot );
		$url = $id ? cl_fotos_url( $id ) : '';
		$sin_quitar = ( 'principal' === $slot && $publicada ) ? ' data-sinquitar="1"' : '';
		$h  = '<div class="clf-slot' . ( $grande ? ' clf-grande' : '' ) . '" data-slot="' . esc_attr( $slot ) . '"' . $sin_quitar . '>';
		$h .= '<div class="clf-lbl">' . esc_html( $label ) . '</div>';
		if ( $ayuda ) {
			$h .= '<div class="clf-ayuda">' . esc_html( $ayuda ) . '</div>';
		}
		$h .= '<div class="clf-img">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '<span class="clf-vacio">Sin foto</span>' ) . '<div class="clf-estado" hidden></div></div>';
		$h .= '<div class="clf-btns"><label class="clf-btn clf-subir"><input type="file" accept="image/*" hidden><span>' . ( $url ? 'Cambiar' : 'Subir foto' ) . '</span></label>';
		$h .= '<button type="button" class="clf-btn clf-quitar"' . ( ( $url && ! $sin_quitar ) ? '' : ' hidden' ) . '>Quitar</button></div>';
		$h .= '<div class="clf-error" hidden></div></div>';
		return $h;
	};

	$grilla = function ( $pref, $n, $label ) use ( $tarjeta ) {
		$h = '<div class="clf-grilla">';
		for ( $i = 1; $i <= $n; $i++ ) {
			$h .= $tarjeta( $pref . $i, $label . ' ' . $i );
		}
		return $h . '</div>';
	};

	ob_start();
	?>
<style>
.clf{display:flex;flex-direction:column;gap:22px;font-family:'Source Sans 3',system-ui,sans-serif;color:#23302f}
.clf-sec{display:flex;flex-direction:column;gap:10px}
.clf-h{font-family:Montserrat,sans-serif;font-weight:700;font-size:18px;color:#1f4645;margin:0}
.clf-p{font-size:15px;line-height:1.45;color:#3d4b49;margin:0}
.clf-grilla{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
@media(min-width:560px){.clf-grilla{grid-template-columns:repeat(3,minmax(0,1fr))}}
.clf-slot{background:#fff;border-radius:14px;padding:12px;display:flex;flex-direction:column;gap:8px;box-shadow:0 1px 2px rgba(31,70,69,.08)}
.clf-lbl{font-weight:700;font-size:15px}
.clf-ayuda{font-size:14px;line-height:1.4;color:#5b6a68}
.clf-img{position:relative;aspect-ratio:4/3;border-radius:10px;overflow:hidden;background:#eeeae1;display:flex;align-items:center;justify-content:center}
.clf-img img{width:100%;height:100%;object-fit:cover;display:block}
.clf-vacio{font-size:14px;color:#7a8583}
.clf-estado{position:absolute;inset:auto 0 0 0;background:rgba(31,70,69,.88);color:#fff;font-size:14px;font-weight:700;padding:8px 10px;text-align:center}
.clf-btns{display:flex;gap:8px;flex-wrap:wrap}
.clf-btn{flex:1 1 auto;min-height:44px;border-radius:10px;border:1px solid #1f4645;background:#fff;color:#1f4645;font-weight:700;font-size:15px;display:inline-flex;align-items:center;justify-content:center;padding:0 12px;cursor:pointer;font-family:inherit}
.clf-subir{background:#1f4645;color:#fff}
.clf-quitar{border-color:#c9c2b3;color:#6b3a2c}
.clf-quitar.clf-seguro{background:#fbe3df;border-color:#8a2a1f;color:#8a2a1f}
.clf-error{background:#fbe3df;color:#8a2a1f;border-radius:10px;padding:8px 10px;font-size:14px;font-weight:700}
.clf-ocupado .clf-btn{opacity:.5;pointer-events:none}
.clf details{background:#fbfaf6;border:1px solid #e0dacd;border-radius:12px;padding:0 14px}
.clf summary{min-height:44px;display:flex;align-items:center;font-weight:700;color:#1f4645;cursor:pointer}
.clf details div{padding:0 0 14px;font-size:14px;line-height:1.45;color:#3d4b49}
</style>
<div class="clf" data-emp="<?php echo (int) $emp_id; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cl_fotos_' . $emp_id ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
	<p class="clf-p">Subí las fotos tal como salen del celular: nosotros las achicamos y optimizamos. Cada foto se guarda sola, apenas termina de subir.</p>
	<details><summary>? Qué fotos funcionan mejor</summary><div><ul>
		<li><strong>Horizontales</strong> y con luz natural (mañana temprano o atardecer).</li>
		<li><strong>Personas haciendo cosas</strong>: caminando, cocinando, en un taller. Transmiten más que un lugar vacío.</li>
		<li>Sin textos, marcos ni logos encima, y que no sean capturas de pantalla.</li>
		<li>Fotos propias o con permiso de quien las sacó.</li>
	</ul></div></details>

	<div class="clf-sec">
		<?php echo $tarjeta( 'principal', 'Foto principal', 'La portada de tu ficha y de la tarjeta del listado. La que mejor muestre el lugar.', true ); // phpcs:ignore ?>
	</div>

	<div class="clf-sec">
		<h2 class="clf-h">El lugar</h2>
		<p class="clf-p">Paisajes, espacios, detalles. Hasta 3.</p>
		<?php echo $grilla( 'pres', 3, 'Lugar' ); // phpcs:ignore ?>
	</div>

	<div class="clf-sec">
		<h2 class="clf-h">Actividades</h2>
		<p class="clf-p">Lo que se vive con ustedes. Hasta 6.</p>
		<?php echo $grilla( 'act', 6, 'Actividad' ); // phpcs:ignore ?>
	</div>

	<div class="clf-sec">
		<h2 class="clf-h">Ustedes</h2>
		<div class="clf-grilla">
			<?php echo $tarjeta( 'quienes', '¿Quiénes somos?', 'Las personas detrás del lugar.' ); // phpcs:ignore ?>
			<?php echo $tarjeta( 'logo', 'Logo', 'Si tienen. Opcional.' ); // phpcs:ignore ?>
		</div>
	</div>
</div>
<script>
(function(){
var raiz=document.currentScript.previousElementSibling;if(!raiz||!raiz.classList.contains('clf'))return;
var AJAX=raiz.dataset.ajax,EMP=raiz.dataset.emp,NONCE=raiz.dataset.nonce;
function achicar(file,slot){
	return new Promise(function(ok){
		var tipo=(file.type||'').toLowerCase();
		var sirve=/^image\/(jpeg|png|webp)$/.test(tipo)&&window.createImageBitmap&&file.size>600*1024;
		if(!sirve||(slot==='logo'&&tipo==='image/png')){ok(file);return;}
		createImageBitmap(file,{imageOrientation:'from-image'}).then(function(bmp){
			var max=1920,w=bmp.width,h=bmp.height,k=Math.min(1,max/Math.max(w,h));
			var c=document.createElement('canvas');c.width=Math.round(w*k);c.height=Math.round(h*k);
			c.getContext('2d').drawImage(bmp,0,0,c.width,c.height);
			c.toBlob(function(b){ok(b&&b.size<file.size?new File([b],'foto.jpg',{type:'image/jpeg'}):file);},'image/jpeg',0.85);
		}).catch(function(){ok(file);});
	});
}
function enviar(slot,datos,progreso){
	return new Promise(function(ok){
		var x=new XMLHttpRequest();x.open('POST',AJAX);
		if(progreso&&x.upload){x.upload.onprogress=function(e){if(e.lengthComputable)progreso(Math.round(e.loaded/e.total*100));};}
		x.onload=function(){var r;try{r=JSON.parse(x.responseText);}catch(e){r={ok:false,msg:'El servidor no respondió bien. Probá de nuevo.'};}ok(r);};
		x.onerror=function(){ok({ok:false,msg:'Se cortó la conexión. Probá de nuevo cuando tengas mejor señal.'});};
		datos.append('action','cl_foto');datos.append('emp',EMP);datos.append('nonce',NONCE);datos.append('slot',slot);
		x.send(datos);
	});
}
raiz.querySelectorAll('.clf-slot').forEach(function(el){
	var slot=el.dataset.slot,input=el.querySelector('input[type=file]'),txt=el.querySelector('.clf-subir span'),
		quitar=el.querySelector('.clf-quitar'),caja=el.querySelector('.clf-img'),estado=el.querySelector('.clf-estado'),err=el.querySelector('.clf-error');
	function mostrar(url){
		var v=caja.querySelector('img,.clf-vacio');if(v)v.remove();
		var n;if(url){n=document.createElement('img');n.src=url;n.alt='';}else{n=document.createElement('span');n.className='clf-vacio';n.textContent='Sin foto';}
		caja.insertBefore(n,estado);txt.textContent=url?'Cambiar':'Subir foto';quitar.hidden=!url||el.dataset.sinquitar==='1';
	}
	function ocupado(s,m){el.classList.toggle('clf-ocupado',s);estado.hidden=!s;estado.textContent=m||'';}
	function falla(m){err.textContent=m;err.hidden=false;}
	input.addEventListener('change',function(){
		var f=input.files&&input.files[0];input.value='';if(!f)return;err.hidden=true;
		ocupado(true,'Preparando…');
		achicar(f,slot).then(function(archivo){
			var d=new FormData();d.append('op','subir');d.append('foto',archivo,archivo.name||'foto.jpg');
			return enviar(slot,d,function(p){estado.textContent=p<100?'Subiendo… '+p+' %':'Optimizando…';});
		}).then(function(r){ocupado(false);if(r.ok){mostrar(r.url);}else{falla(r.msg);}});
	});
	var tQ;
	quitar.addEventListener('click',function(){
		if(!quitar.classList.contains('clf-seguro')){quitar.classList.add('clf-seguro');quitar.textContent='¿Seguro? Tocá de nuevo';clearTimeout(tQ);tQ=setTimeout(function(){quitar.classList.remove('clf-seguro');quitar.textContent='Quitar';},4000);return;}
		clearTimeout(tQ);quitar.classList.remove('clf-seguro');quitar.textContent='Quitar';err.hidden=true;ocupado(true,'Quitando…');
		var d=new FormData();d.append('op','quitar');
		enviar(slot,d).then(function(r){ocupado(false);if(r.ok){mostrar('');}else{falla(r.msg);}});
	});
});
})();
</script>
	<?php
	return ob_get_clean();
} );
