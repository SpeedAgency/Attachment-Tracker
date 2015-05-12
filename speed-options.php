<?php
// Options Page

add_action('admin_menu', 'sp_at_create_menu');

function sp_at_create_menu(){
  // create Menu Item
  add_submenu_page('options-general.php', 'Tracker', 'File Tracker', 'administrator', __FILE__, 'sp_at_settings_page', 'dashicons-email');

  add_action('admin_init', 'register_sp_at_settings');

  wp_register_style('sp-at-settings-style', plugins_url('css/settings.css', __FILE__));
}

function register_sp_at_settings(){
  register_setting('sp-at-settings', 'sp_at_api_key');
  register_setting('sp-at-settings', 'sp_at_min');
  register_setting('sp-at-settings', 'sp_at_mailto');
}

function sp_at_settings_page(){

  wp_enqueue_style('sp-at-settings-style');

  $key = get_option('sp_at_api_key');
  $min = get_option('sp_at_min', 5);
  $mailto = get_option('sp_at_mailto', get_option('admin_email'));
  $saved = true;

  if(!$key){
    $key = generate_api_key();
    $saved = false;
  }


  ?>
  <div class="wrap">
    <h2>File Tracker</h2>

    <form method="post" action="options.php">
      <?php settings_fields('sp-at-settings'); ?>
      <?php do_settings_sections('sp-at-settings'); ?>
      <div class="form-row">
        <label for="sp_at_api_key">API Key</label>
        <div class="sp-input">
          <?php if(!$saved){
            echo '<div class="append">Please Save Changes</div>';
          }else{
            echo '<div class="append">Key Saved</div>';
          } ?>
          <div class="input-wrap">
            <input type="text" id="sp_at_api_key" name="sp_at_api_key" value="<?php echo $key; ?>" />
          </div>
        </div>
      </div>
      <div class="form-row">
          <label for="sp_at_min">Minimum downloads by an IP before triggering a notification email:</label>
          <div class="input-wrap">
              <input type="number" id="sp_at_min" name="sp_at_min" value="<?php echo $min; ?>" />
          </div>
      </div>
      <div class="form-row">
          <label for="sp_at_mailto">Email address to send notification emails to:</label>
          <div class="input-wrap">
              <input type="email" id="sp_at_mailto" name="sp_at_mailto" value="<?php echo $mailto; ?>" />
          </div>
      </div>
     <?php submit_button(); ?>
  </form>
</div>
  <?php
}


function generate_api_key(){
    $length = 25;
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}





function sp_at_track_field($form_fields, $post){

    $track = (bool) get_post_meta($post->ID, 'track', true);

    $form_fields['track'] = array(
        'label' => 'Track File Downloads',
        'input' => 'html',
        'html'  => '<label for="attachments-'.$post->ID.'-track">'.
                    '<input type="checkbox" id="attachments-'.$post->ID.'-track" name="attachments['.$post->ID.'][track]" value="1"'.($track ? ' checked="checked"' : '').' /></label>',
        'value' => $track
    );
    return $form_fields;
}

function sp_at_track_field_save($post, $attachment){
    $track = ($attachment['track'] == '1') ? '1' : '0';
    update_post_meta($post['ID'], 'track', $track);
    return $post;
}

add_filter("attachment_fields_to_edit", "sp_at_track_field", null, 2);
add_filter("attachment_fields_to_save", "sp_at_track_field_save", null, 2);
