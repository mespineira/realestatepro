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
// FORMULARIO "VENDE TU INMUEBLE"
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
// NUEVO FORMULARIO "VALORA TU INMUEBLE"
// ========================================================================

function rep_render_valuation_form(){
    // Array de extras
    $extras = [
        'ascensor' => ['icon' => 'fa-caret-square-up', 'label' => 'Ascensor'],
        'garaje' => ['icon' => 'fa-car', 'label' => 'Garaje'],
        'terraza' => ['icon' => 'fa-umbrella-beach', 'label' => 'Terraza'],
        'exterior' => ['icon' => 'fa-sun', 'label' => 'Exterior'],
        'amueblado' => ['icon' => 'fa-couch', 'label' => 'Amueblado'],
        'trastero' => ['icon' => 'fa-box-open', 'label' => 'Trastero'],
        'jardin' => ['icon' => 'fa-tree', 'label' => 'Jardín'],
        'piscina' => ['icon' => 'fa-swimming-pool', 'label' => 'Piscina'],
        'calefaccion' => ['icon' => 'fa-fire', 'label' => 'Calefacción'],
    ];

    ob_start(); ?>
    <div id="rep-valuation-form-wrapper">
        <form class="rep-valuation-form" method="post" action="">
            <?php wp_nonce_field('rep_send_valuation_lead','rep_valuation_nonce'); ?>
            <input type="hidden" name="rep_valuation_action" value="1"/>

            <!-- Step 1: Property Identification -->
            <div class="form-step" data-step="1">
                <h3 class="step-title">1. Introduce los datos del inmueble a valorar</h3>
                <div class="rep-field"><label>Tengo la referencia catastral</label><input type="text" name="val_refcat" placeholder="Ej: 1234567AB1234C0001DE"></div>
                <div class="rep-field"><label>Tengo la dirección del inmueble</label><input type="text" name="val_address" placeholder="Ej: Calle Principal 1, Madrid"></div>
                <div class="rep-field"><label>Tengo el enlace del inmueble en Idealista/Fotocasa</label><input type="url" name="val_link" placeholder="https://..."></div>
                <p class="step-note">Al menos uno de los campos es obligatorio.</p>
                <div class="form-navigation"><button type="button" class="btn-next ge-btn">Siguiente</button></div>
            </div>

            <!-- Step 2: Property Details -->
            <div class="form-step" data-step="2" style="display:none;">
                <h3 class="step-title">2. Introduce los detalles y extras del inmueble</h3>
                <div class="rep-grid-2">
                    <div class="rep-field"><label>Tipo de Inmueble</label><select name="val_type"><option>Piso</option><option>Casa</option><option>Local</option><option>Solar</option></select></div>
                    <div class="rep-field"><label>Subtipo de Inmueble</label><input type="text" name="val_subtype" placeholder="Ej: Ático, Adosado..."></div>
                    <div class="rep-field"><label>Estado del inmueble</label><select name="val_state"><option>Sin especificar</option><option>A reformar</option><option>Buen estado</option><option>A estrenar / Obra nueva</option></select></div>
                    <div class="rep-field"><label>Operación</label><select name="val_operation"><option>Venta</option><option>Alquiler</option></select></div>
                    <div class="rep-field"><label>Superficie (m²)</label><input type="number" name="val_m2"></div>
                    <div class="rep-field"><label>Habitaciones</label><input type="number" name="val_rooms"></div>
                    <div class="rep-field"><label>Baños</label><input type="number" name="val_baths"></div>
                    <div class="rep-field"><label>Precio estimado (No influye en la valoración)</label><input type="number" name="val_price" placeholder="€"></div>
                </div>
                <h4 class="extras-title">Selecciona los extras del inmueble</h4>
                <div class="extras-grid">
                    <?php foreach ($extras as $key => $details): ?>
                        <button type="button" class="btn-extra ge-btn ge-btn--sm ge-btn--outline-white" data-extra="<?php echo $key; ?>">
                            <i class="fas <?php echo $details['icon']; ?>"></i> <?php echo $details['label']; ?>
                        </button>
                        <input type="hidden" name="val_extras[<?php echo $key; ?>]" value="0">
                    <?php endforeach; ?>
                </div>
                <div class="form-navigation"><button type="button" class="btn-prev ge-btn ge-btn--outline-white">Anterior</button><button type="button" class="btn-next ge-btn">Siguiente</button></div>
            </div>

            <!-- Step 3: Motivation -->
            <div class="form-step" data-step="3" style="display:none;">
                <h3 class="step-title">3. Cuéntanos algo más</h3>
                <div class="rep-field"><label>Motivo de la valoración</label><select name="val_reason"><option>Elige motivo</option><option>Soy el propietario y quiero venderlo</option><option>Me interesa comprarlo para mi</option><option>Me interesa comprarlo para invertir</option><option>Me interesa alquilarlo</option><option>Soy el inquilino</option><option>Soy el propietario</option><option>Solo estoy curioseando</option></select></div>
                <div class="rep-field"><label>¿Cuando tienes planeado comprar/vender?</label><select name="val_timeline"><option>Lo antes posible</option><option>No tengo un plazo aproximado</option><option>En menos de 6 meses</option><option>En más de 6 meses</option></select></div>
                <div class="form-navigation"><button type="button" class="btn-prev ge-btn ge-btn--outline-white">Anterior</button><button type="button" class="btn-next ge-btn">Siguiente</button></div>
            </div>

            <!-- Step 4: Contact Info -->
            <div class="form-step" data-step="4" style="display:none;">
                <h3 class="step-title">4. Déjanos tus datos para ofrecerte un informe de valoración gratis</h3>
                <div class="rep-field"><label>Tu nombre*</label><input type="text" name="rep_name" required></div>
                <div class="rep-field"><label>Teléfono</label><input type="tel" name="rep_phone"></div>
                <div class="rep-field"><label>Email*</label><input type="email" name="rep_email" required></div>
                <div class="rep-field"><label><input type="checkbox" name="rep_privacy" value="1" required/> Acepto el <a href="/aviso-legal">aviso legal</a> y la <a href="/politica-de-privacidad">política de privacidad</a>.</label></div>
                <div class="form-navigation"><button type="button" class="btn-prev ge-btn ge-btn--outline-white">Anterior</button><button type="submit" class="ge-btn">Valorar ahora</button></div>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('rep_valuation_form', 'rep_render_valuation_form');


// ========================================================================
// PROCESAMIENTO DE FORMULARIOS
// ========================================================================

add_action('template_redirect', function(){
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';

    // --- Procesador para el formulario de contacto de ficha ---
    if ( isset($_POST['rep_lead_action']) ) {
        // (código existente sin cambios)
    }

    // --- Procesador para el formulario "Vende tu inmueble" ---
    if ( isset($_POST['rep_sell_action']) ) {
       // (código existente sin cambios)
    }
    
    // --- Procesador para el NUEVO formulario de VALORACIÓN ---
    if ( isset($_POST['rep_valuation_action']) ) {
        if ( ! isset($_POST['rep_valuation_nonce']) || ! wp_verify_nonce($_POST['rep_valuation_nonce'],'rep_send_valuation_lead') ) wp_die('Error de seguridad.');
        if ( ! empty($_POST['website']) || rep_leads_rate_limited($ip) ) wp_die('Detección anti-spam activada.');

        $name = sanitize_text_field($_POST['rep_name'] ?? '');
        $email = sanitize_email($_POST['rep_email'] ?? '');
        $phone = sanitize_text_field($_POST['rep_phone'] ?? '');
        $priv = !empty($_POST['rep_privacy']);

        if ( !$name || !is_email($email) || !$priv ) {
            wp_redirect( add_query_arg(array('valuation_lead'=>'error'), wp_get_referer()) ); exit;
        }

        $title = "Solicitud de Valoración de $name";
        $content = "== DATOS DE CONTACTO ==\n"
                 . "Nombre: $name\n"
                 . "Email: $email\n"
                 . "Teléfono: $phone\n\n"
                 . "== IDENTIFICACIÓN DEL INMUEBLE ==\n"
                 . "Ref. Catastral: " . sanitize_text_field($_POST['val_refcat'] ?? '-') . "\n"
                 . "Dirección: " . sanitize_text_field($_POST['val_address'] ?? '-') . "\n"
                 . "Enlace: " . esc_url_raw($_POST['val_link'] ?? '-') . "\n\n"
                 . "== DETALLES DEL INMUEBLE ==\n"
                 . "Tipo: " . sanitize_text_field($_POST['val_type'] ?? '-') . "\n"
                 . "Subtipo: " . sanitize_text_field($_POST['val_subtype'] ?? '-') . "\n"
                 . "Estado: " . sanitize_text_field($_POST['val_state'] ?? '-') . "\n"
                 . "Operación: " . sanitize_text_field($_POST['val_operation'] ?? '-') . "\n"
                 . "Superficie: " . sanitize_text_field($_POST['val_m2'] ?? '-') . " m²\n"
                 . "Habitaciones: " . sanitize_text_field($_POST['val_rooms'] ?? '-') . "\n"
                 . "Baños: " . sanitize_text_field($_POST['val_baths'] ?? '-') . "\n"
                 . "Precio estimado: " . sanitize_text_field($_POST['val_price'] ?? '-') . "€\n\n"
                 . "Extras: " . implode(', ', array_keys(array_filter($_POST['val_extras'] ?? []))) . "\n\n"
                 . "== MOTIVACIÓN ==\n"
                 . "Motivo: " . sanitize_text_field($_POST['val_reason'] ?? '-') . "\n"
                 . "Plazo: " . sanitize_text_field($_POST['val_timeline'] ?? '-');

        $lead_id = wp_insert_post(array('post_type'=>'property_lead','post_status'=>'publish','post_title'=>$title,'post_content'=>$content), true);
        
        if ( !is_wp_error($lead_id) ) {
            // Guardar datos principales
            update_post_meta($lead_id, 'name', $name);
            update_post_meta($lead_id, 'email', $email);
            update_post_meta($lead_id, 'phone', $phone);
            update_post_meta($lead_id, 'ip', $ip);
            // Guardar todos los datos de la valoración
            foreach($_POST as $key => $value) {
                if (strpos($key, 'val_') === 0) {
                    if (is_array($value)) {
                        $value = array_map('sanitize_text_field', $value);
                    } else {
                        $value = sanitize_text_field($value);
                    }
                    update_post_meta($lead_id, $key, $value);
                }
            }
        }

        $to = rep_get_setting('email_global', get_option('admin_email'));
        $subject = "Nueva Solicitud de Valoración de $name";
        $headers = array('Content-Type: text/html; charset=UTF-8', 'Reply-To: '.$name.' <'.$email.'>');
        $body = wpautop($content);
        wp_mail($to, $subject, $body, $headers);

        wp_redirect( add_query_arg(array('valuation_lead'=>'ok'), wp_get_referer()) ); exit;
    }
});


// ========================================================================
// MENSAJES FLASH PARA TODOS LOS FORMULARIOS
// ========================================================================

add_action('the_content', function($content){
    $alert = '';
    // Mensaje para formulario de ficha
    if ( is_singular('property') && isset($_GET['lead']) ) {
        if ( $_GET['lead']==='ok' ) $alert = '<div class="rep-alert rep-ok">¡Gracias! Hemos recibido tu mensaje.</div>';
        else $alert = '<div class="rep-alert rep-err">No se pudo enviar. Revisa los campos y resuelve el captcha.</div>';
    }
    // Mensaje para formulario de venta
    if ( is_page_template('template-vende.php') && isset($_GET['sell_lead']) ) {
        if ( $_GET['sell_lead']==='ok' ) $alert = '<div class="rep-alert rep-ok">¡Gracias! Hemos recibido tu solicitud. Nos pondremos en contacto contigo a la brevedad.</div>';
        else $alert = '<div class="rep-alert rep-err">No se pudo enviar. Revisa los campos obligatorios y resuelve el captcha.</div>';
    }
    // Mensaje para formulario de valoración
    if ( is_page_template('template-valora.php') && isset($_GET['valuation_lead']) ) {
        if ( $_GET['valuation_lead']==='ok' ) $alert = '<div class="rep-alert rep-ok">¡Gracias por completar la solicitud! Hemos recibido tus datos y te enviaremos el informe de valoración lo antes posible.</div>';
        else $alert = '<div class="rep-alert rep-err">No se pudo enviar. Por favor, revisa que has completado todos los campos obligatorios.</div>';
    }
    return $alert . $content;
}, 5);

