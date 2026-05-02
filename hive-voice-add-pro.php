<?php
/**
 * Plugin Name: HiveVoice ADD Pro
 * Version: 2.0
 * Description: Голосове додавання оголошень для HivePress
 * Author: portallcomua
 * GitHub Plugin URI: https://github.com/portallcomua/hive-voice-add-pro
 */

if (!defined('ABSPATH')) exit;

define('HVA_VERSION', '2.0');
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

// ==================== ОТРИМАННЯ АТРИБУТІВ ====================
function hva_get_all_attributes() {
    global $wpdb;
    $attributes = [];
    
    // Отримуємо всі таксономії HivePress
    $taxonomies = $wpdb->get_col("SELECT DISTINCT taxonomy FROM {$wpdb->term_taxonomy} WHERE taxonomy LIKE 'hp_listing_%' AND taxonomy NOT IN ('hp_listing_category', 'hp_listing_tag')");
    
    foreach ($taxonomies as $tax) {
        $name = str_replace('hp_listing_', '', $tax);
        $name = str_replace('_', ' ', $name);
        $attributes[] = ucwords($name);
    }
    
    // Якщо нічого не знайдено, додаємо тестові атрибути
    if (empty($attributes)) {
        $attributes = ['Тираж', 'Рік', 'Палітура', 'Формат', 'Мова'];
    }
    
    return $attributes;
}

// ==================== МОНЕТИЗАЦІЯ ====================
function hva_get_count() { return (int) get_option('hva_operations', 0); }
function hva_inc() { update_option('hva_operations', hva_get_count() + 1); }
function hva_can() { return get_option('hva_license') ? true : hva_get_count() < HVA_FREE_LIMIT; }
function hva_remaining() { return max(0, HVA_FREE_LIMIT - hva_get_count()); }

add_action('admin_menu', function() {
    add_menu_page('HiveVoice ADD', 'HiveVoice ADD', 'manage_options', 'hva_main', 'hva_render_page', 'dashicons-microphone', 31);
    add_submenu_page('hva_main', 'Ліцензія', '🔑 Ліцензія', 'manage_options', 'hva_license', 'hva_license_page');
});

function hva_license_page() { ?>
    <div class="wrap"><h1>🔑 Ліцензія HiveVoice ADD Pro</h1>
    <?php if (get_option('hva_license')): ?>
        <div class="notice notice-success"><p>✅ Активна</p></div>
    <?php else: ?>
        <div class="notice notice-warning"><p>⚠️ Безкоштовно: <?php echo hva_remaining(); ?> / <?php echo HVA_FREE_LIMIT; ?></p>
        <form method="post"><?php wp_nonce_field('hva_lic'); ?>
            <input name="license_key" placeholder="Введіть ключ"><button type="submit" name="activate_lic">🔑 Активувати</button>
        </form>
        <p><a href="<?php echo HVA_SHOP_URL; ?>" target="_blank">💰 Придбати PRO (599 грн / $29)</a></p>
    <?php endif; ?>
    </div><?php
}

add_action('admin_init', function() {
    if (isset($_POST['activate_lic']) && wp_verify_nonce($_POST['hva_lic'], 'hva_lic')) {
        if (strlen(trim($_POST['license_key'])) >= 16) update_option('hva_license', true);
        else echo '<div class="notice notice-error"><p>❌ Невірний ключ</p></div>';
    }
});

// ==================== ГОЛОВНА СТОРІНКА ====================
function hva_render_page() {
    $attrs = hva_get_all_attributes();
    ?>
    <div class="wrap" style="max-width:800px; margin:auto; padding:20px;">
        <div style="display:flex; justify-content:space-between;">
            <h1>🐝 HiveVoice ADD Pro</h1>
            <span style="background:#2271b1; color:#fff; padding:4px 12px; border-radius:20px;">v<?php echo HVA_VERSION; ?></span>
        </div>
        
        <div style="background:<?php echo get_option('hva_license') ? '#d4edda' : '#fff3cd'; ?>; padding:15px; border-radius:10px; margin-bottom:20px; text-align:center;">
            <?php if (get_option('hva_license')): ?>
                ✅ PRO версія активна
            <?php else: ?>
                📊 Безкоштовна версія: залишилось <strong><?php echo hva_remaining(); ?></strong> з <?php echo HVA_FREE_LIMIT; ?>
                <?php if (hva_remaining() == 0): ?>
                    <br><a href="<?php echo admin_url('admin.php?page=hva_license'); ?>" style="color:#d9534f;">Придбати ліцензію →</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div style="margin-bottom:15px;">
            <a href="https://uaserver.pp.ua/readme-hive-voice-add" target="_blank" class="button">📖 Інструкція</a>
            <a href="<?php echo admin_url('admin.php?page=hva_license'); ?>" class="button">🔑 Ліцензія</a>
        </div>
        
        <video id="video" width="100%" autoplay playsinline muted style="background:#000; border-radius:10px; margin-bottom:10px; transform:scaleX(-1);"></video>
        <div id="gallery" style="display:flex; gap:5px; flex-wrap:wrap; margin-bottom:10px;"></div>
        <div style="display:flex; gap:10px; margin-bottom:15px;">
            <button id="take_photo" class="button" style="flex:2;">📷 ЗРОБИТИ ФОТО</button>
            <button id="clear_photos" class="button" style="flex:1;">❌ ОЧИСТИТИ</button>
        </div>
        
        <div style="margin-bottom:15px;">
            <label style="font-weight:bold;">📝 ТЕКСТ ОГОЛОШЕННЯ:</label>
            <textarea id="voice_text" rows="15" style="width:100%; font-family:monospace; font-size:14px; padding:10px; border:2px solid #2271b1; border-radius:10px;" readonly></textarea>
        </div>
        
        <button id="mic_btn" style="width:100%; background:#1877F2; color:#fff; border:none; padding:15px; border-radius:10px; margin-bottom:15px;">🎤 ДИКТУВАТИ ГОЛОСОМ</button>
        <button id="save_btn" style="width:100%; background:#00a32a; color:#fff; border:none; padding:15px; border-radius:10px; font-size:18px; font-weight:bold;">➕ ОПУБЛІКУВАТИ</button>
        <div id="message" style="margin-top:15px; padding:10px; border-radius:10px; display:none;"></div>
    </div>

    <script>
        const attrs = <?php echo json_encode($attrs); ?>;
        const voiceText = document.getElementById('voice_text');
        let photos = [], stream = null, listening = false;
        let activeField = null;
        let lastCommandTime = 0;
        
        function buildTextTemplate() {
            let text = "НАЗВА: \n";
            text += "ЦІНА: \n";
            text += "КАТЕГОРІЯ: \n";
            text += "ОПИС: \n\n";
            text += "--- АТРИБУТИ ---\n";
            for (let i = 0; i < attrs.length; i++) {
                text += `АТРИБУТ ${attrs[i].toUpperCase()}: \n`;
            }
            voiceText.value = text;
        }
        buildTextTemplate();
        
        function findFieldPosition(fieldName) {
            const content = voiceText.value;
            const lines = content.split('\n');
            let pos = 0;
            for (let i = 0; i < lines.length; i++) {
                const line = lines[i];
                if (line.toLowerCase().startsWith(fieldName.toLowerCase() + ':')) {
                    const colonIndex = line.indexOf(':');
                    if (colonIndex !== -1) return pos + colonIndex + 2;
                }
                pos += line.length + 1;
            }
            return -1;
        }
        
        function insertAtPosition(pos, text) {
            const content = voiceText.value;
            voiceText.value = content.slice(0, pos) + text + content.slice(pos);
            voiceText.focus();
            voiceText.setSelectionRange(pos + text.length, pos + text.length);
        }
        
        function showMessage(msg, type) {
            const div = document.getElementById('message');
            div.innerHTML = msg;
            div.style.background = type === 'error' ? '#f8d7da' : (type === 'success' ? '#d4edda' : '#e8f0fe');
            div.style.color = type === 'error' ? '#721c24' : (type === 'success' ? '#155724' : '#004085');
            div.style.display = 'block';
            setTimeout(() => div.style.display = 'none', 2000);
        }
        
        async function startCamera() {
            try {
                if (stream) stream.getTracks().forEach(t => t.stop());
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
                document.getElementById('video').srcObject = stream;
            } catch(e) { console.log(e); }
        }
        startCamera();
        
        document.getElementById('take_photo').onclick = () => {
            const v = document.getElementById('video');
            const canvas = document.createElement('canvas');
            canvas.width = v.videoWidth;
            canvas.height = v.videoHeight;
            canvas.getContext('2d').drawImage(v, 0, 0);
            const imgData = canvas.toDataURL('image/jpeg', 0.8);
            photos.push(imgData);
            const img = document.createElement('img');
            img.src = imgData;
            img.style.cssText = 'width:70px;height:70px;object-fit:cover;border-radius:8px;margin:2px;border:2px solid #ddd';
            document.getElementById('gallery').prepend(img);
        };
        
        document.getElementById('clear_photos').onclick = () => { photos = []; document.getElementById('gallery').innerHTML = ''; };
        
        if (window.SpeechRecognition || window.webkitSpeechRecognition) {
            const recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
            recognition.lang = 'uk-UA';
            recognition.continuous = true;
            const micBtn = document.getElementById('mic_btn');
            
            micBtn.onclick = () => {
                if (!listening) {
                    recognition.start();
                    listening = true;
                    micBtn.style.background = "#ff4b4b";
                    micBtn.innerText = "🛑 ЗУПИНИТИ ДИКТУВАННЯ";
                } else {
                    recognition.stop();
                    listening = false;
                    micBtn.style.background = "#1877F2";
                    micBtn.innerText = "🎤 ДИКТУВАТИ ГОЛОСОМ";
                }
            };
            
            recognition.onresult = (e) => {
                const fullText = e.results[e.results.length-1][0].transcript.trim();
                const lowerText = fullText.toLowerCase();
                const now = Date.now();
                
                let foundField = null;
                let valueToInsert = null;
                
                const basicFields = ['назва', 'ціна', 'категорія', 'опис'];
                for (const field of basicFields) {
                    if (lowerText === field) {
                        foundField = field.toUpperCase();
                        break;
                    }
                    if (lowerText.startsWith(field + ' ')) {
                        foundField = field.toUpperCase();
                        valueToInsert = fullText.substring(field.length + 1);
                        break;
                    }
                }
                
                if (!foundField) {
                    for (let i = 0; i < attrs.length; i++) {
                        const attr = attrs[i].toLowerCase();
                        if (lowerText === attr) {
                            foundField = `АТРИБУТ ${attrs[i].toUpperCase()}`;
                            break;
                        }
                        if (lowerText.startsWith(attr + ' ')) {
                            foundField = `АТРИБУТ ${attrs[i].toUpperCase()}`;
                            valueToInsert = fullText.substring(attr.length + 1);
                            break;
                        }
                    }
                }
                
                if (foundField) {
                    const pos = findFieldPosition(foundField);
                    if (pos !== -1) {
                        voiceText.focus();
                        voiceText.setSelectionRange(pos, pos);
                        activeField = foundField;
                        lastCommandTime = now;
                        showMessage(`🎯 Активовано: ${foundField}`, 'info');
                        
                        if (valueToInsert) {
                            setTimeout(() => {
                                insertAtPosition(pos, valueToInsert + ' ');
                                showMessage(`✅ Додано: ${valueToInsert}`, 'success');
                                activeField = null;
                            }, 50);
                        }
                    }
                }
                else if (activeField && (now - lastCommandTime) < 4000) {
                    const pos = findFieldPosition(activeField);
                    if (pos !== -1) {
                        insertAtPosition(pos, fullText + ' ');
                        showMessage(`✅ Додано: ${fullText}`, 'success');
                    }
                    activeField = null;
                }
                else {
                    let sample = attrs.length > 0 ? attrs[0].toLowerCase() : 'атрибут';
                    showMessage(`⚠️ Скажіть спочатку назву поля: "назва", "ціна", "${sample}"...`, 'error');
                }
            };
            
            recognition.onend = () => { if (listening) recognition.start(); };
        } else {
            document.getElementById('mic_btn').disabled = true;
            document.getElementById('mic_btn').innerText = "❌ ГОЛОС НЕ ПІДТРИМУЄТЬСЯ";
        }
        
        document.getElementById('save_btn').onclick = async function() {
            <?php if (!hva_can()): ?>
                showMessage('❌ Ліміт вичерпано! Придбайте ліцензію', 'error');
                return;
            <?php endif; ?>
            
            this.disabled = true;
            this.innerText = "⏳ ЗБЕРІГАЮ...";
            
            const fd = new FormData();
            fd.append('action', 'hva_save_listing');
            fd.append('text', voiceText.value);
            fd.append('images', JSON.stringify(photos));
            
            try {
                const resp = await fetch(ajaxurl, { method: 'POST', body: fd });
                const res = await resp.json();
                if (res.success) {
                    showMessage(`✅ Оголошення додано! ID: ${res.data.id}`, 'success');
                    this.innerText = "➕ ДОДАТИ ЩЕ";
                    this.style.background = "#ff9800";
                    photos = [];
                    document.getElementById('gallery').innerHTML = '';
                    buildTextTemplate();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage(`❌ ${res.data.message}`, 'error');
                    this.disabled = false;
                    this.innerText = "➕ ОПУБЛІКУВАТИ";
                }
            } catch(e) {
                showMessage('❌ Помилка з\'єднання', 'error');
                this.disabled = false;
                this.innerText = "➕ ОПУБЛІКУВАТИ";
            }
        };
    </script>
    <?php
}

add_action('wp_ajax_hva_save_listing', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Немає прав');
    if (!hva_can()) wp_send_json_error('Ліміт вичерпано. Придбайте ліцензію');
    
    $text = sanitize_textarea_field($_POST['text']);
    $photos = json_decode(stripslashes($_POST['images']), true);
    
    $data = [];
    $lines = explode("\n", $text);
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            $parts = explode(':', $line, 2);
            $key = trim($parts[0]);
            $value = trim($parts[1] ?? '');
            if ($value) $data[$key] = $value;
        }
    }
    
    if (!class_exists('HivePress\Models\Listing')) wp_send_json_error('HivePress не активовано!');
    
    $listing = (new \HivePress\Models\Listing())->fill([
        'title' => $data['НАЗВА'] ?? 'Оголошення ' . date('H:i:s'),
        'status' => 'publish'
    ]);
    if (!empty($data['ОПИС'])) $listing->set_description($data['ОПИС']);
    if (!empty($data['ЦІНА'])) $listing->set_price(floatval($data['ЦІНА']));
    
    $lid = $listing->save(['title']);
    if (!$lid) wp_send_json_error('Помилка створення');
    
    if (!empty($data['КАТЕГОРІЯ'])) {
        $term = term_exists($data['КАТЕГОРІЯ'], 'hp_listing_category');
        if (!$term) $term = wp_insert_term($data['КАТЕГОРІЯ'], 'hp_listing_category');
        if (!is_wp_error($term)) wp_set_object_terms($lid, [(int)$term['term_id']], 'hp_listing_category');
    }
    
    $allAttrs = hva_get_all_attributes();
    $extra = [];
    
    foreach ($data as $key => $value) {
        if (preg_match('/^АТРИБУТ (.+)$/i', $key, $matches)) {
            $attrName = trim($matches[1]);
            $found = false;
            foreach ($allAttrs as $existing) {
                if (strtolower($existing) === strtolower($attrName)) {
                    $tax_name = 'hp_listing_' . sanitize_title($attrName);
                    if (taxonomy_exists($tax_name)) {
                        $term = term_exists($value, $tax_name);
                        if (!$term) $term = wp_insert_term($value, $tax_name);
                        if (!is_wp_error($term)) wp_set_object_terms($lid, [(int)$term['term_id']], $tax_name);
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) $extra[] = $attrName . ': ' . $value;
        }
    }
    
    if (!empty($extra)) update_post_meta($lid, '_hva_extra', $extra);
    $listing->save();
    hva_inc();
    
    if (!empty($photos)) {
        foreach ($photos as $i => $b64) {
            $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $b64));
            $file = wp_upload_bits('hva_' . $lid . '_' . time() . '_' . $i . '.jpg', null, $img);
            if (!$file['error']) {
                $aid = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'], $file['file'], $lid);
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid, $file['file']));
                if ($i === 0) set_post_thumbnail($lid, $aid);
            }
        }
    }
    
    wp_send_json_success(['id' => $lid]);
});
?>
