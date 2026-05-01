<?php
/**
 * Plugin Name: HiveVoice ADD Pro
 * Version: 1.0
 * Description: Голосове додавання оголошень для HivePress
 * Author: portallcomua
 * GitHub Plugin URI: https://github.com/portallcomua/hive-voice-add-pro
 */

if (!defined('ABSPATH')) exit;

define('HVA_VERSION', '1.0');
define('HVA_FREE_LIMIT', 25);
define('HVA_SHOP_URL', 'https://uaserver.pp.ua/product/hivevoice-add-pro/');

// ==================== АВТООНОВЛЕННЯ ====================
add_filter('pre_set_site_transient_update_plugins', function($transient) {
    if (empty($transient->checked)) return $transient;
    $plugin_slug = plugin_basename(__FILE__);
    $response = wp_remote_get("https://api.github.com/repos/portallcomua/hive-voice-add-pro/releases/latest");
    if (is_wp_error($response)) return $transient;
    $release = json_decode(wp_remote_retrieve_body($response));
    if (isset($release->tag_name)) {
        $latest = ltrim($release->tag_name, 'v');
        if (version_compare(HVA_VERSION, $latest, '<')) {
            $transient->response[$plugin_slug] = (object) [
                'slug' => dirname($plugin_slug),
                'plugin' => $plugin_slug,
                'new_version' => $latest,
                'url' => $release->html_url,
                'package' => $release->zipball_url,
            ];
        }
    }
    return $transient;
});

// ==================== МОНЕТИЗАЦІЯ ====================
function hva_get_count() { return (int) get_option('hva_operations', 0); }
function hva_inc() { update_option('hva_operations', hva_get_count() + 1); }
function hva_can() { return get_option('hva_license') ? true : hva_get_count() < HVA_FREE_LIMIT; }
function hva_remaining() { return max(0, HVA_FREE_LIMIT - hva_get_count()); }

add_action('admin_menu', function() {
    add_menu_page('HiveVoice ADD', 'HiveVoice ADD', 'manage_options', 'hva_main', 'hva_page', 'dashicons-microphone', 31);
    add_submenu_page('hva_main', 'Ліцензія', '🔑 Ліцензія', 'manage_options', 'hva_license', 'hva_license_page');
});

function hva_page() { echo '<div class="wrap"><h1>🐝 HiveVoice ADD Pro</h1><p>Голосове додавання оголошень. Ліміт: ' . hva_remaining() . ' / ' . HVA_FREE_LIMIT . '</p></div>'; }

function hva_license_page() { ?>
    <div class="wrap"><h1>🔑 Ліцензія HiveVoice ADD Pro</h1>
    <?php if (get_option('hva_license')): ?>
        <div class="notice notice-success"><p>✅ Активна</p></div>
    <?php else: ?>
        <div class="notice notice-warning"><p>⚠️ Безкоштовно: <?php echo hva_remaining(); ?> / <?php echo HVA_FREE_LIMIT; ?></p>
        <form method="post"><?php wp_nonce_field('hva_lic'); ?>
            <input name="license_key" placeholder="Ключ"><button type="submit" name="activate_lic">🔑 Активувати</button>
        </form>
        <p><a href="<?php echo HVA_SHOP_URL; ?>" target="_blank">💰 Придбати PRO (599 грн / $29)</a></p>
    <?php endif; ?>
    </div><?php
}

add_action('admin_init', function() {
    if (isset($_POST['activate_lic']) && wp_verify_nonce($_POST['hva_lic'], 'hva_lic')) {
        if (strlen(sanitize_text_field($_POST['license_key'])) >= 16) update_option('hva_license', true);
        else echo '<div class="notice notice-error"><p>❌ Невірний ключ</p></div>';
    }
});
?>