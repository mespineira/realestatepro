<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** CPT de Leads */
add_action('init', function(){
  register_post_type('property_lead', array(
    'labels'=>array(
      'name'=>__('Leads Inmobiliarios','real-estate-pro'),
      'singular_name'=>__('Lead Inmobiliario','real-estate-pro')
    ),
    'public'=>false,
    'show_ui'=>true,
    'show_in_menu'=>'edit.php?post_type=property',
    'supports'=>array('title','editor','custom-fields'),
    'menu_icon'=>'dashicons-email'
  ));
});

/** Anti-spam básico */
function rep_leads_rate_limited($ip){
  $key = 'rep_leads_ip_'.md5($ip);
  if ( get_transient($key) ) return true;
  set_transient($key, 1, 60); // 1 minuto
  return false;
}
function rep_leads_make_captcha(){
  $a = rand(1,9); $b = rand(1,9);
  $sum = $a + $b;
  $token = wp_generate_uuid4();
  set_transient('rep_cap_'.$token, $sum, 15*60);
  return array('q'=>"¿Cuánto es $a + $b?", 't'=>$token);
}
function rep_leads_check_captcha($token, $answer){
  $key = 'rep_cap_'.$token;
  $exp = get_transient($key);
  delete_transient($key);
  return (string)$exp !== '' && intval($answer) === intval($exp);
}


// ========================================================================
// FORMULARIO DE CONTACTO DE FICHA DE PROPIEDAD (Existente)
// ========================================================================

function rep_render_contact_form($post_id){
  $title = get_the_title($post_id);
  $ref   = get_post_meta($post_id,'referencia',true);
  $prefill = sprintf('Quiero más información sobre «%s» con referencia: %s', $title, $ref ? $ref : '-');
  $cap = rep_leads_make_captcha();
  $privacy = rep_get_setting('privacy_url','');

  ob_start(); ?>
  <form class="rep-contact" method="post" action="">
    <?php wp_nonce_field('rep_send_lead','rep_lead_nonce'); ?>
    <input type="hidden" name="rep_lead_action" value="1"/>
    <input type="hidden" name="rep_lead_post" value="<?php echo esc_attr($post_id); ?>"/>
    <input type="hidden" name="rep_cap_token" value="<?php echo esc_attr($cap['t']); ?>"/>
    <!-- Honeypot -->
    <div style="position:absolute;left:-9999px;top:-9999px;">
      <label>Website <input type="text" name="website" autocomplete="off"/></label>
    </div>

    <div class="rep-field">
      <label>Nombre*</label>
      <input type="text" name="rep_name" required maxlength="120"/>
    </div>
    <div class="rep-field">
      <label>Email*</label>
      <input type="email" name="rep_email" required maxlength="140"/>
    </div>
    <div class="rep-field">
      <label>Teléfono</label>
      <input type="tel" name="rep_phone" pattern="[0-9+\-\s]{6,20}" maxlength="30"/>
    </div>
    <div class="rep-field">
      <label>Comentarios*</label>
      <textarea name="rep_message" rows="5" required><?php echo esc_textarea($prefill); ?></textarea>
    </div>

    <div class="rep-field">
      <label><?php echo esc_html($cap['q']); ?> *</label>
      <input type="number" name="rep_cap_answer" required min="0" max="99" style="width:120px"/>
    </div>

    <div class="rep-field">
      <label>
        <input type="checkbox" name="rep_privacy" value="1" required/>
        Acepto la <a href="<?php echo esc_url($privacy ? $privacy : home_url('/politica-de-privacidad')); ?>" target="_blank" rel="noopener">política de privacidad</a>.
      </label>
    </div>

    <div class="rep-actions">
      <button type="submit" class="button button-primary">Enviar</button>
    </div>
  </form>
  <?php
  return ob_get_clean();
}


// ========================================================================
// NUEVO FORMULARIO "VENDE TU INMUEBLE"
// ========================================================================

function rep_render_sell_form(){
    $cap = rep_leads_make_captcha();
    $privacy = rep_get_setting('privacy_url','');
    ob_start(); ?>
    <form class="rep-contact rep-sell-form" method="post" action="">
        <?php wp_nonce_field('rep_send_sell_lead','rep_sell_lead_nonce'); ?>
        <input type="hidden" name="rep_sell_action" value="1"/>
        <input type="hidden" name="rep_cap_token" value="<?php echo esc_attr($cap['t']); ?>"/>
        <!-- Honeypot -->
        <div style="position:absolute;left:-9999px;top:-9999px;">
          <label>Website <input type="text" name="website" autocomplete="off"/></label>
        </div>

        <div class="rep-field"><label>Nombre*</label><input type="text" name="rep_name" required /></div>
        <div class="rep-field"><label>Email*</label><input type="email" name="rep_email" required /></div>
        <div class="rep-field"><label>Teléfono</label><input type="tel" name="rep_phone" /></div>

        <div class="rep-field">
            <label>Tipo de Inmueble*</label>
            <select name="rep_prop_type" required>
                <option value="">-- Selecciona --</option>
                <option value="Piso">Piso</option>
                <option value="Casa">Casa</option>
                <option value="Terreno">Terreno</option>
                <option value="Otro">Otro</option>
            </select>
        </div>
        <div class="rep-field"><label>Superficie (m²)</label><input type="number" name="rep_prop_m2" /></div>
        <div class="rep-field"><label>Ciudad*</label><input type="text" name="rep_prop_city" required /></div>
        
        <div class="rep-field" style="grid-column: 1 / -1;"><label>Comentarios</label><textarea name="rep_message" rows="5"></textarea></div>

        <div class="rep-field"><label><?php echo esc_html($cap['q']); ?> *</label><input type="number" name="rep_cap_answer" required style="width:120px"/></div>
        
        <div class="rep-field" style="grid-column: 1 / -1;">
            <label><input type="checkbox" name="rep_privacy" value="1" required/> Acepto la <a href="<?php echo esc_url($privacy ? $privacy : home_url('/politica-de-privacidad')); ?>" target="_blank" rel="noopener">política de privacidad</a>.</label>
        </div>
        
        <div class="rep-actions" style="grid-column: 1 / -1;"><button type="submit" class="button button-primary">Enviar solicitud</button></div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('rep_contact', 'rep_render_sell_form');


// ========================================================================
// PROCESAMIENTO DE AMBOS FORMULARIOS
// ========================================================================

add_action('template_redirect', function(){
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';

    // --- Procesador para el formulario de contacto de ficha ---
    if ( isset($_POST['rep_lead_action']) ) {
        if ( ! isset($_POST['rep_lead_nonce']) || ! wp_verify_nonce($_POST['rep_lead_nonce'],'rep_send_lead') ) wp_die('Error de seguridad.');
        if ( ! empty($_POST['website']) || rep_leads_rate_limited($ip) ) wp_die('Detección anti-spam activada.');
        
        $post_id = intval($_POST['rep_lead_post'] ?? 0);
        if ( $post_id <= 0 || get_post_type($post_id)!=='property' ) wp_die('Propiedad no válida.');

        $name = sanitize_text_field($_POST['rep_name'] ?? '');
        $email = sanitize_email($_POST['rep_email'] ?? '');
        $phone = sanitize_text_field($_POST['rep_phone'] ?? '');
        $msg = wp_kses_post($_POST['rep_message'] ?? '');
        $okcap = rep_leads_check_captcha(sanitize_text_field($_POST['rep_cap_token'] ?? ''), intval($_POST['rep_cap_answer'] ?? -1));
        $priv = !empty($_POST['rep_privacy']);

        if ( !$name || !is_email($email) || !$msg || !$okcap || !$priv ) {
            wp_redirect( add_query_arg(array('lead'=>'error'), get_permalink($post_id)) ); exit;
        }

        $title = 'Lead – '. wp_strip_all_tags( get_the_title($post_id) );
        $lead_id = wp_insert_post(array('post_type'=>'property_lead','post_status'=>'publish','post_title'=>$title,'post_content'=>$msg), true);
        
        if ( !is_wp_error($lead_id) ) {
            update_post_meta($lead_id, 'property_id', $post_id);
            update_post_meta($lead_id, 'name', $name);
            update_post_meta($lead_id, 'email', $email);
            update_post_meta($lead_id, 'phone', $phone);
            update_post_meta($lead_id, 'ip', $ip);
        }

        $to = rep_get_setting('email_global', get_option('admin_email'));
        $ref = get_post_meta($post_id,'referencia',true);
        $subject = sprintf('[Consulta Web] %s (Ref: %s)', get_the_title($post_id), $ref ?: '-');
        $headers = array('Content-Type: text/html; charset=UTF-8', 'Reply-To: '.$name.' <'.$email.'>');
        $body = wpautop(sprintf("Propiedad: %s\nReferencia: %s\n\nNombre: %s\nEmail: %s\nTeléfono: %s\n\nMensaje:\n%s\n\nIP: %s", get_permalink($post_id), $ref ?: '-', $name, $email, $phone, wp_strip_all_tags($msg), $ip));
        wp_mail($to, $subject, $body, $headers);

        wp_redirect( add_query_arg(array('lead'=>'ok'), get_permalink($post_id)) ); exit;
    }

    // --- Procesador para el formulario "Vende tu inmueble" ---
    if ( isset($_POST['rep_sell_action']) ) {
        if ( ! isset($_POST['rep_sell_lead_nonce']) || ! wp_verify_nonce($_POST['rep_sell_lead_nonce'],'rep_send_sell_lead') ) wp_die('Error de seguridad.');
        if ( ! empty($_POST['website']) || rep_leads_rate_limited($ip) ) wp_die('Detección anti-spam activada.');
        
        $name = sanitize_text_field($_POST['rep_name'] ?? '');
        $email = sanitize_email($_POST['rep_email'] ?? '');
        $phone = sanitize_text_field($_POST['rep_phone'] ?? '');
        $prop_type = sanitize_text_field($_POST['rep_prop_type'] ?? '');
        $prop_m2 = sanitize_text_field($_POST['rep_prop_m2'] ?? '');
        $prop_city = sanitize_text_field($_POST['rep_prop_city'] ?? '');
        $msg = wp_kses_post($_POST['rep_message'] ?? '');
        $okcap = rep_leads_check_captcha(sanitize_text_field($_POST['rep_cap_token'] ?? ''), intval($_POST['rep_cap_answer'] ?? -1));
        $priv = !empty($_POST['rep_privacy']);

        if ( !$name || !is_email($email) || !$prop_type || !$prop_city || !$okcap || !$priv ) {
            wp_redirect( add_query_arg(array('sell_lead'=>'error'), wp_get_referer()) ); exit;
        }

        $title = sprintf('Solicitud Venta: %s en %s', $prop_type, $prop_city);
        $content = "Datos del solicitante:\n- Nombre: $name\n- Email: $email\n- Teléfono: $phone\n\n"
                 . "Datos del inmueble:\n- Tipo: $prop_type\n- Superficie: $prop_m2 m²\n- Ciudad: $prop_city\n\n"
                 . "Comentarios adicionales:\n" . ($msg ?: '(Ninguno)');

        $lead_id = wp_insert_post(array('post_type'=>'property_lead','post_status'=>'publish','post_title'=>$title,'post_content'=>$content), true);
        
        if ( !is_wp_error($lead_id) ) {
            update_post_meta($lead_id, 'name', $name);
            update_post_meta($lead_id, 'email', $email);
            update_post_meta($lead_id, 'phone', $phone);
            update_post_meta($lead_id, 'property_type_sell', $prop_type);
            update_post_meta($lead_id, 'surface_sell', $prop_m2);
            update_post_meta($lead_id, 'city_sell', $prop_city);
            update_post_meta($lead_id, 'ip', $ip);
        }

        $to = rep_get_setting('email_global', get_option('admin_email'));
        $subject = sprintf('[Solicitud Venta] %s en %s', $prop_type, $prop_city);
        $headers = array('Content-Type: text/html; charset=UTF-8', 'Reply-To: '.$name.' <'.$email.'>');
        $body = wpautop($content);
        wp_mail($to, $subject, $body, $headers);

        wp_redirect( add_query_arg(array('sell_lead'=>'ok'), wp_get_referer()) ); exit;
    }
});


// ========================================================================
// MENSAJES FLASH PARA AMBOS FORMULARIOS
// ========================================================================

add_action('the_content', function($content){
    $alert = '';
    // Mensaje para formulario de ficha
    if ( is_singular('property') && isset($_GET['lead']) ) {
        if ( $_GET['lead']==='ok' ) {
            $alert = '<div class="rep-alert rep-ok">¡Gracias! Hemos recibido tu mensaje.</div>';
        } else {
            $alert = '<div class="rep-alert rep-err">No se pudo enviar. Revisa los campos y resuelve el captcha.</div>';
        }
    }
    // Mensaje para formulario de venta
    if ( is_page_template('template-vende.php') && isset($_GET['sell_lead']) ) {
        if ( $_GET['sell_lead']==='ok' ) {
            $alert = '<div class="rep-alert rep-ok">¡Gracias! Hemos recibido tu solicitud. Nos pondremos en contacto contigo a la brevedad.</div>';
        } else {
            $alert = '<div class="rep-alert rep-err">No se pudo enviar. Revisa los campos obligatorios y resuelve el captcha.</div>';
        }
    }
    return $alert . $content;
}, 5);
