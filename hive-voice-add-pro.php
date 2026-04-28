<?php
/**
 * Plugin Name: HiveVoice ADD Pro
 * Version: 1.0
 * Description: Голосове додавання оголошень для HivePress
 * Author: WooQuick
 */

if (!defined('ABSPATH')) exit;

define('HVA_VERSION', '1.0');
define('HVA_FREE_LIMIT', 25);

function hva_get_listings_count() {
    $count = wp_count_posts('hp_listing');
    return $count->publish;
}

function hva_is_license_active() {
    $license_valid = get_option('hva_license_valid', false);
    $license_domain = get_option('hva_license_domain', '');
    return $license_valid && $license_domain === $_SERVER['HTTP_HOST'];
}

function hva_can_add_listing() {
    if (hva_is_license_active()) return true;
    return hva_get_listings_count() < HVA_FREE_LIMIT;
}

add_action('admin_menu', function() {
    add_menu_page('HiveVoice ADD', 'HiveVoice ADD', 'manage_options', 'hva_main', 'hva_render_gui', 'dashicons-microphone', 30);
    add_submenu_page('hva_main', 'Ліцензія', '🔑 Ліцензія', 'manage_options', 'hva_license', 'hva_render_license_page');
});

function hva_render_license_page() {
    // Аналогічно Woo версії
    echo '<div class="wrap"><h2>🔑 Ліцензія HiveVoice ADD Pro</h2></div>';
}

function hva_render_gui() {
    echo '<div class="wrap"><h2>🎤 HiveVoice ADD Pro</h2><p>Інтерфейс з голосовим введенням та списком атрибутів доступний в GitHub репозиторії.</p></div>';
}

add_action('wp_ajax_hva_save_listing', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Немає прав');
    if (!hva_can_add_listing()) wp_send_json_error('Ліміт вичерпано');
    wp_send_json_success(['id' => rand(1,999)]);
});
?>