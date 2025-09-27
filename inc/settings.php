<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_menu', function(){
    add_options_page('Real Estate Pro','Real Estate Pro','manage_options','rep-settings','rep_render_settings_page');
});

add_action('admin_init', function(){
    register_setting('rep_settings_group','rep_settings', array(
        'sanitize_callback'=>function($opts){
            $o = is_array($opts)?$opts:array();
            $o['currency_symbol']   = isset($opts['currency_symbol']) ? $opts['currency_symbol'] : '€';
            $o['currency_position'] = isset($opts['currency_position']) && in_array($opts['currency_position'],array('before','after'),true) ? $opts['currency_position'] : 'after';
            $o['currency_decimals'] = intval( isset($opts['currency_decimals']) ? $opts['currency_decimals'] : 0 );
            $o['thousands_sep']     = isset($opts['thousands_sep']) ? $opts['thousands_sep'] : '.';
            $o['decimal_sep']       = isset($opts['decimal_sep']) ? $opts['decimal_sep'] : ',';
            $o['email_global']      = sanitize_email( isset($opts['email_global']) ? $opts['email_global'] : '' );
            $o['require_featured']  = !empty($opts['require_featured']) ? '1' : '0';
            $o['privacy_url']       = esc_url_raw( isset($opts['privacy_url']) ? $opts['privacy_url'] : '' );

            $o['mobilia_url'] = esc_url_raw( isset($opts['mobilia_url']) ? $opts['mobilia_url'] : '' );
            $o['mobilia_params'] = array(
                'descripcionesHtml' => !empty($opts['mobilia_params']['descripcionesHtml']),
                'mostrarAlias'      => !empty($opts['mobilia_params']['mostrarAlias']),
                'fotosAmpliada'     => !empty($opts['mobilia_params']['fotosAmpliada']),
                'marcaAgua'         => intval( isset($opts['mobilia_params']['marcaAgua']) ? $opts['mobilia_params']['marcaAgua'] : 1 ),
            );
            $o['mobilia_batch_size'] = max(5, intval( isset($opts['mobilia_batch_size']) ? $opts['mobilia_batch_size'] : 20 ));
            return $o;
        }
    ));
});

function rep_render_settings_page(){
    $o = get_option('rep_settings',array());
    ?>
    <div class="wrap">
      <h1>Real Estate Pro – Ajustes</h1>
      <form method="post" action="options.php">
        <?php settings_fields('rep_settings_group'); ?>
        <h2>Formato</h2>
        <table class="form-table">
          <tr><th>Divisa</th><td>
            <input type="text" name="rep_settings[currency_symbol]" value="<?php echo esc_attr($o['currency_symbol']??'€'); ?>" size="2"/>
            <select name="rep_settings[currency_position]">
              <option value="before" <?php selected($o['currency_position']??'after','before'); ?>>Antes</option>
              <option value="after"  <?php selected($o['currency_position']??'after','after'); ?>>Después</option>
            </select>
            &nbsp;Decimales:
            <input type="number" name="rep_settings[currency_decimals]" value="<?php echo esc_attr($o['currency_decimals']??0); ?>" min="0" max="2" style="width:60px"/>
            &nbsp;Miles:
            <input type="text" name="rep_settings[thousands_sep]" value="<?php echo esc_attr($o['thousands_sep']??'.'); ?>" size="1"/>
            &nbsp;Decim:
            <input type="text" name="rep_settings[decimal_sep]" value="<?php echo esc_attr($o['decimal_sep']??','); ?>" size="1"/>
          </td></tr>
          <tr><th>Email global</th><td>
            <input type="email" class="regular-text" name="rep_settings[email_global]" value="<?php echo esc_attr($o['email_global']??''); ?>"/>
          </td></tr>
          <tr><th>Política de privacidad (URL)</th><td>
            <input type="url" class="regular-text" name="rep_settings[privacy_url]" value="<?php echo esc_attr($o['privacy_url']??''); ?>" placeholder="/politica-de-privacidad"/>
          </td></tr>
          <tr><th>Imagen destacada obligatoria</th><td>
            <input type="checkbox" name="rep_settings[require_featured]" value="1" <?php checked($o['require_featured']??'0','1'); ?>/>
          </td></tr>
        </table>

        <h2>Mobilia – Sincronización</h2>
        <table class="form-table">
          <tr><th>URL XML</th><td>
            <input type="url" class="regular-text code" name="rep_settings[mobilia_url]" value="<?php echo esc_attr($o['mobilia_url']??''); ?>"/>
          </td></tr>
          <tr><th>Parámetros</th><td>
            <label><input type="checkbox" name="rep_settings[mobilia_params][descripcionesHtml]" <?php checked($o['mobilia_params']['descripcionesHtml']??false); ?>/> descripcionesHtml</label><br/>
            <label><input type="checkbox" name="rep_settings[mobilia_params][mostrarAlias]" <?php checked($o['mobilia_params']['mostrarAlias']??false); ?>/> mostrarAlias</label><br/>
            <label><input type="checkbox" name="rep_settings[mobilia_params][fotosAmpliada]" <?php checked($o['mobilia_params']['fotosAmpliada']??false); ?>/> fotosAmpliada</label><br/>
            Marca de agua: <input type="number" name="rep_settings[mobilia_params][marcaAgua]" value="<?php echo esc_attr($o['mobilia_params']['marcaAgua']??1); ?>" min="0" max="1"/>
          </td></tr>
          <tr><th>Lote</th><td>
            <input type="number" name="rep_settings[mobilia_batch_size]" value="<?php echo esc_attr($o['mobilia_batch_size']??20); ?>" min="5" max="200"/>
            <p class="description">Nº de inmuebles a procesar por ejecución (para evitar timeouts).</p>
          </td></tr>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
}