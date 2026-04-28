<?php
/**
 * Plugin Name: HiveVoice ADD Pro
 * Version: 1.1
 * Description: Голосове додавання оголошень для HivePress
 * Author: WooQuick
 * GitHub Plugin URI: portallcomua/hive-voice-add-pro
 * GitHub Branch: main
 */

if (!defined('ABSPATH')) exit;

define('HVA_VERSION', '1.1');
define('HVA_FREE_LIMIT', 25);
define('HVA_SHOP_URL', 'https://uaserver.pp.ua/product/hivevoice-add-pro/');

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

function hva_get_remaining_free() {
    return max(0, HVA_FREE_LIMIT - hva_get_listings_count());
}

// Адмін меню
add_action('admin_menu', function() {
    add_menu_page('HiveVoice ADD Pro', 'HiveVoice ADD Pro', 'manage_options', 'hva_main', 'hva_render_gui', 'dashicons-microphone', 31);
    add_submenu_page('hva_main', 'Ліцензія', '🔑 Ліцензія', 'manage_options', 'hva_license', 'hva_render_license_page');
});

// Сторінка ліцензії
function hva_render_license_page() {
    ?>
    <div class="wrap" style="max-width:600px; margin:auto; padding:20px;">
        <h2>🔑 HiveVoice ADD Pro - Ліцензія</h2>
        <?php if (hva_is_license_active()): ?>
            <div style="background:#d4edda; padding:15px; border-radius:10px;">
                ✅ <strong>Ліцензія активна!</strong><br>
                Домен: <?php echo get_option('hva_license_domain', ''); ?>
            </div>
        <?php else: ?>
            <div style="background:#fff3cd; padding:15px; border-radius:10px; margin-bottom:20px;">
                ⚠️ <strong>Безкоштовна версія</strong><br>
                Ліміт: <?php echo HVA_FREE_LIMIT; ?> оголошень.<br>
                Залишилось: <?php echo hva_get_remaining_free(); ?>
            </div>
            <div style="background:#e8f0fe; padding:20px; border-radius:10px;">
                <h3>💰 Придбати ліцензію - 599 грн / $29 USD</h3>
                <p><a href="<?php echo HVA_SHOP_URL; ?>" target="_blank" style="background:#4CAF50; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;">📦 ПЕРЕЙТИ ДО ОПЛАТИ</a></p>
                <hr>
                <form method="post">
                    <?php wp_nonce_field('hva_activate_action', 'hva_activate_nonce'); ?>
                    <input type="text" name="license_key" placeholder="Введіть ліцензійний ключ" style="width:100%; padding:10px; margin-bottom:10px;">
                    <button type="submit" name="hva_activate_license" style="background:#2196F3; color:#fff; padding:10px 20px;">🔑 Активувати</button>
                </form>
                <p style="font-size:12px; margin-top:10px;">📌 Після оплати ключ надійде на вашу пошту</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// Обробка активації ліцензії
add_action('admin_init', function() {
    if (isset($_POST['hva_activate_license']) && isset($_POST['license_key']) && wp_verify_nonce($_POST['hva_activate_nonce'], 'hva_activate_action')) {
        $key = trim($_POST['license_key']);
        if (strlen($key) >= 16) {
            update_option('hva_license_valid', true);
            update_option('hva_license_key', $key);
            update_option('hva_license_domain', $_SERVER['HTTP_HOST']);
            echo '<div class="notice notice-success"><p>✅ Ліцензію активовано!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Невірний ключ</p></div>';
        }
    }
});

// Отримання всіх атрибутів HivePress
function hva_get_all_attributes() {
    $attributes = [];
    $attrs = get_posts([
        'post_type' => 'hp_attribute',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ]);
    foreach ($attrs as $attr) {
        $type = get_post_meta($attr->ID, 'type', true);
        $choices = get_post_meta($attr->ID, 'choices', true);
        $attributes[] = [
            'id' => $attr->ID,
            'name' => $attr->post_title,
            'slug' => $attr->post_name,
            'type' => $type,
            'choices' => is_array($choices) ? $choices : [],
            'mayak' => strtolower(str_replace(' ', '_', $attr->post_title))
        ];
    }
    return $attributes;
}

// Головна сторінка
function hva_render_gui() {
    $total_listings = hva_get_listings_count();
    $remaining = hva_get_remaining_free();
    $license_active = hva_is_license_active();
    $attributes = hva_get_all_attributes();
    ?>
    <div class="wrap" style="max-width: 700px; margin: auto; padding: 20px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 2px solid #2271b1; margin-bottom: 20px;">
            <h1 style="color:#2271b1; margin:0;">🐝 HiveVoice ADD Pro</h1>
            <span style="background:#2271b1; color:white; padding:4px 12px; border-radius:20px;">v<?php echo HVA_VERSION; ?></span>
        </div>
        
        <div style="background: <?php echo $license_active ? '#d4edda' : ($remaining > 0 ? '#fff3cd' : '#f8d7da'); ?>; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
            <?php if ($license_active): ?>
                ✅ <strong>PRO ВЕРСІЯ</strong> - необмежено оголошень<br>
                <span style="font-size:12px;">Додано: <?php echo $total_listings; ?></span>
            <?php elseif ($remaining > 0): ?>
                📊 <strong>Безкоштовна версія</strong><br>
                Додано: <?php echo $total_listings; ?> з <?php echo HVA_FREE_LIMIT; ?> оголошень<br>
                Залишилось: <strong><?php echo $remaining; ?></strong>
            <?php else: ?>
                🚫 <strong>Ліміт вичерпано!</strong><br>
                <a href="<?php echo admin_url('admin.php?page=hva_license'); ?>" style="color:#d9534f;">Придбати ліцензію →</a>
            <?php endif; ?>
        </div>
        
        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
            <a href="https://uaserver.pp.ua/readme_hive-voice-add" target="_blank" style="flex:1; background:#f0f0f0; border:1px solid #ccc; border-radius:10px; padding:10px; text-align:center; text-decoration:none;">📖 ІНСТРУКЦІЯ</a>
            <a href="<?php echo admin_url('admin.php?page=hva_license'); ?>" style="flex:1; background:#4CAF50; color:#fff; border:none; border-radius:10px; padding:10px; text-align:center; text-decoration:none;">🔑 ЛІЦЕНЗІЯ</a>
        </div>
        
        <!-- Список доступних атрибутів -->
        <?php if (!empty($attributes)): ?>
        <div style="background: #f0f7fc; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            <h3>📋 Доступні атрибути та маяки</h3>
            <div style="max-height: 200px; overflow-y: auto;">
                <table style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#2271b1; color:white;">
                            <th style="padding:8px;">Атрибут</th>
                            <th style="padding:8px;">Маяк (що казати)</th>
                            <th style="padding:8px;">Приклад</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attributes as $attr): ?>
                            <tr style="border-bottom:1px solid #ddd;">
                                <td style="padding:8px;"><strong><?php echo esc_html($attr['name']); ?></strong></td>
                                <td style="padding:8px;"><code><?php echo esc_html($attr['mayak']); ?></code></td>
                                <td style="padding:8px;">
                                    <?php 
                                    if (!empty($attr['choices'])) {
                                        $sample = array_keys($attr['choices']);
                                        echo esc_html($sample[0]) . ' / ' . esc_html($sample[1] ?? '...');
                                    } else {
                                        echo 'текст або число';
                                    }
                                    ?>
                                 </td>
                             </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:10px; font-size:12px;">💡 Всього атрибутів: <?php echo count($attributes); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Голосова форма -->
        <div style="background: #fff; padding: 20px; border-radius: 15px; border: 2px solid #1877F2;">
            <video id="video" width="100%" autoplay playsinline muted style="border-radius:15px; background:#000; margin-bottom:10px; transform: scaleX(-1);"></video>
            <div id="gallery" style="display:flex; gap:5px; padding:5px; overflow-x:auto; min-height: 80px; margin-bottom:10px;"></div>
            
            <div style="display:flex; gap:10px; margin-bottom:10px;">
                <button id="take_photo" style="flex:2; background:#0073aa; color:#fff; border:none; border-radius:10px; padding:12px;">📷 ЗРОБИТИ ФОТО</button>
                <button id="clear_photos" style="flex:1; border-radius:10px; border:1px solid #ccc; background:#fff;">❌</button>
            </div>

            <button id="mic_btn" style="width:100%; background:#1877F2; color:#fff; border:none; border-radius:10px; padding:15px; margin-bottom:10px;">🎤 ДИКТУВАТИ ГОЛОСОМ</button>
            <textarea id="voice_text" style="width:100%; height:120px; border:2px solid #ddd; border-radius:10px; padding:10px; margin-bottom:10px;"></textarea>
            <button id="save_btn" style="width:100%; background:#00a32a; color:#fff; border:none; border-radius:10px; font-size:18px; font-weight:bold; padding:15px;">➕ ОПУБЛІКУВАТИ</button>
        </div>
        
        <div id="message" style="margin-top:15px; padding:10px; border-radius:10px; display:none;"></div>
        
        <div style="margin-top:15px; padding:10px; background:#f0f0f0; border-radius:10px; font-size:12px;">
            <strong>📝 Приклад диктування:</strong><br>
            "назва квартира на Оболоні"<br>
            "ціна 45000"<br>
            "категорія нерухомість"<br>
            "поверх 7"<br>
            "площа 65"<br>
            "стан новобудова"
        </div>

        <style>.photo-preview { width: 70px; height: 70px; object-fit: cover; border-radius: 8px; margin: 2px; border: 2px solid #ddd; }</style>

        <script>
        const video = document.getElementById('video');
        const gallery = document.getElementById('gallery');
        const voiceText = document.getElementById('voice_text');
        let photos = [], stream = null, listening = false;
        
        async function startCamera() {
            try {
                if (stream) stream.getTracks().forEach(t => t.stop());
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
                video.srcObject = stream;
            } catch(e) { console.log(e); }
        }
        startCamera();
        
        document.getElementById('take_photo').onclick = () => {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            const imgData = canvas.toDataURL('image/jpeg', 0.8);
            photos.push(imgData);
            const img = document.createElement('img');
            img.src = imgData;
            img.classList.add('photo-preview');
            gallery.prepend(img);
        };
        
        document.getElementById('clear_photos').onclick = () => { photos = []; gallery.innerHTML = ''; };
        
        if (window.SpeechRecognition || window.webkitSpeechRecognition) {
            const recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
            recognition.lang = 'uk-UA';
            recognition.continuous = true;
            document.getElementById('mic_btn').onclick = () => {
                if (!listening) {
                    recognition.start(); listening = true;
                    document.getElementById('mic_btn').style.background = "#ff4b4b";
                    document.getElementById('mic_btn').innerText = "🛑 ЗУПИНИТИ";
                } else {
                    recognition.stop(); listening = false;
                    document.getElementById('mic_btn').style.background = "#1877F2";
                    document.getElementById('mic_btn').innerText = "🎤 ДИКТУВАТИ ГОЛОСОМ";
                }
            };
            recognition.onresult = (e) => {
                const text = e.results[e.results.length-1][0].transcript;
                voiceText.value += (voiceText.value ? "\n" : "") + text;
                voiceText.scrollTop = voiceText.scrollHeight;
            };
            recognition.onend = () => { if (listening) recognition.start(); };
        } else {
            document.getElementById('mic_btn').disabled = true;
            document.getElementById('mic_btn').innerText = "❌ ГОЛОС НЕ ПІДТРИМУЄТЬСЯ";
        }
        
        document.getElementById('save_btn').onclick = async function() {
            const btn = this;
            btn.disabled = true;
            btn.innerText = "⏳ ЗБЕРІГАЮ...";
            
            const fd = new FormData();
            fd.append('action', 'hva_save_listing');
            fd.append('text', voiceText.value);
            fd.append('images', JSON.stringify(photos));
            
            try {
                const resp = await fetch(ajaxurl, { method: 'POST', body: fd });
                const res = await resp.json();
                const msgDiv = document.getElementById('message');
                if (res.success) {
                    msgDiv.innerHTML = '✅ Оголошення додано! ID: ' + res.data.id;
                    msgDiv.style.background = "#d4edda";
                    msgDiv.style.color = "#155724";
                    msgDiv.style.display = "block";
                    btn.innerText = "➕ ДОДАТИ ЩЕ";
                    btn.style.background = "#ff9800";
                    photos = []; gallery.innerHTML = ''; voiceText.value = '';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    msgDiv.innerHTML = '❌ ' + (res.data.message || 'Помилка');
                    msgDiv.style.background = "#f8d7da";
                    msgDiv.style.color = "#721c24";
                    msgDiv.style.display = "block";
                    btn.disabled = false;
                    btn.innerText = "➕ ОПУБЛІКУВАТИ";
                }
            } catch(e) {
                document.getElementById('message').innerHTML = '❌ Помилка з\'єднання';
                document.getElementById('message').style.background = "#f8d7da";
                document.getElementById('message').style.color = "#721c24";
                document.getElementById('message').style.display = "block";
                btn.disabled = false;
                btn.innerText = "➕ ОПУБЛІКУВАТИ";
            }
        };
        </script>
    </div>
    <?php
}

// ========== ЗБЕРЕЖЕННЯ ОГОЛОШЕННЯ ==========
add_action('wp_ajax_hva_save_listing', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Немає прав']);
        return;
    }
    
    if (!hva_can_add_listing()) {
        wp_send_json_error(['message' => 'Ліміт безкоштовної версії вичерпано. Придбайте ліцензію']);
        return;
    }
    
    if (!class_exists('HivePress\Models\Listing')) {
        wp_send_json_error(['message' => 'HivePress не активовано!']);
        return;
    }
    
    $lines = explode("\n", $_POST['text']);
    $data = [];
    $attrs = [];
    $tags = [];
    
    foreach ($lines as $line) {
        if (strpos($line, ':') === false) continue;
        list($k, $v) = explode(':', $line, 2);
        $key = trim(mb_strtolower($k));
        $val = trim($v);
        
        if (in_array($key, ['тег', 'мітка', 'позначка', 'tag'])) {
            $tags[] = $val;
        } elseif ($key === 'атрибут') {
            $parts = explode(' ', $val, 2);
            if (count($parts) == 2) {
                $attrs[$parts[0]] = $parts[1];
            }
        } else {
            $data[$key] = $val;
        }
    }
    
    // Отримуємо атрибути HivePress для мапінгу
    $all_attrs = hva_get_all_attributes();
    $attr_map = [];
    foreach ($all_attrs as $a) {
        $attr_map[strtolower($a['name'])] = $a['slug'];
        $attr_map[$a['slug']] = $a['slug'];
        $attr_map[$a['mayak']] = $a['slug'];
    }
    
    // Створюємо оголошення
    $listing = (new \HivePress\Models\Listing())->fill([
        'title'  => $data['назва'] ?? 'Оголошення ' . date('H:i:s'),
        'status' => 'publish'
    ]);
    
    if (isset($data['опис'])) {
        $listing->set_description($data['опис']);
    }
    if (isset($data['ціна'])) {
        $listing->set_price(preg_replace('/[^0-9.]/', '', $data['ціна']));
    }
    
    $listing_id = $listing->save(['title']);
    if (!$listing_id) {
        wp_send_json_error(['message' => 'Помилка створення оголошення']);
        return;
    }
    
    // Категорія
    if (isset($data['категорія'])) {
        $term = term_exists($data['категорія'], 'hp_listing_category');
        if (!$term) $term = wp_insert_term($data['категорія'], 'hp_listing_category');
        if (!is_wp_error($term)) {
            wp_set_object_terms($listing_id, [(int)$term['term_id']], 'hp_listing_category');
        }
    }
    
    // Теги
    if (!empty($tags)) {
        $tag_ids = [];
        foreach ($tags as $tag) {
            if (empty($tag)) continue;
            $term = term_exists($tag, 'hp_listing_tag');
            if (!$term) $term = wp_insert_term($tag, 'hp_listing_tag');
            if (!is_wp_error($term)) $tag_ids[] = (int)$term['term_id'];
        }
        if (!empty($tag_ids)) {
            wp_set_object_terms($listing_id, $tag_ids, 'hp_listing_tag');
        }
    }
    
    // Атрибути (мапінг)
    $extra_attrs = [];
    foreach ($attrs as $attr_name => $attr_value) {
        $slug = $attr_map[strtolower($attr_name)] ?? null;
        if ($slug && method_exists($listing, 'set_' . $slug)) {
            $method = 'set_' . $slug;
            $listing->$method($attr_value);
        } else {
            $extra_attrs[] = $attr_name . ': ' . $attr_value;
        }
    }
    
    if (!empty($extra_attrs) && method_exists($listing, 'set_additional_details')) {
        $listing->set_additional_details(implode(', ', $extra_attrs));
    } elseif (!empty($extra_attrs)) {
        update_post_meta($listing_id, '_hva_extra', $extra_attrs);
    }
    
    $listing->save();
    
    // Фото
    $imgs = json_decode(stripslashes($_POST['images']), true);
    if (!empty($imgs)) {
        $gallery_ids = [];
        foreach ($imgs as $idx => $base64) {
            if (strpos($base64, 'data:image') === 0) {
                $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
                $filename = 'hva_listing_' . $listing_id . '_' . time() . '_' . $idx . '.jpg';
                $upload = wp_upload_bits($filename, null, $imgData);
                if (!$upload['error']) {
                    $attach_id = wp_insert_attachment([
                        'post_mime_type' => 'image/jpeg',
                        'post_title' => 'Listing Image',
                        'post_status' => 'inherit'
                    ], $upload['file'], $listing_id);
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));
                    if ($idx === 0) {
                        set_post_thumbnail($listing_id, $attach_id);
                    } else {
                        $gallery_ids[] = $attach_id;
                    }
                }
            }
        }
        if (!empty($gallery_ids)) {
            update_post_meta($listing_id, '_hva_image_gallery', implode(',', $gallery_ids));
        }
    }
    
    wp_send_json_success(['id' => $listing_id]);
});
?>