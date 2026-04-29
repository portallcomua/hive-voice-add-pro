<?php
/**
 * Plugin Name: HiveVoice ADD Pro
 * Version: 4.0
 * Description: Голосове додавання оголошень в HivePress (автоматичний мапінг атрибутів)
 */

if (!defined('ABSPATH')) exit;
define('HVA_VERSION', '4.0');
define('HVA_FREE_LIMIT', 25);

function hva_is_pro() { return get_option('hva_license_valid', false); }
function hva_remaining() { return max(0, HVA_FREE_LIMIT - wp_count_posts('hp_listing')->publish); }
function hva_get_attrs() { global $wpdb; return $wpdb->get_col("SELECT post_title FROM {$wpdb->posts} WHERE post_type='hp_attribute' AND post_status='publish'"); }

add_action('admin_menu', function() {
    add_menu_page('HiveVoice ADD', 'HiveVoice ADD', 'manage_options', 'hva_main', 'hva_render_gui', 'dashicons-microphone', 31);
    add_submenu_page('hva_main', 'Ліцензія', '🔑 Ліцензія', 'manage_options', 'hva_license', 'hva_license_page');
});

function hva_license_page() { ?>
    <div class="wrap"><h1>🔑 Ліцензія</h1>
    <?php if(hva_is_pro()): ?><div class="notice notice-success"><p>✅ Активна</p></div>
    <?php else: ?><div class="notice notice-warning"><p>⚠️ Безкоштовно: <?php echo hva_remaining(); ?> / <?php echo HVA_FREE_LIMIT; ?></p>
    <form method="post"><?php wp_nonce_field('hva_lic'); ?><input name="license_key" placeholder="Ключ"><button type="submit" name="activate_lic">🔑 Активувати</button></form>
    <p><a href="https://ua-server.pp.ua/product/hivevoice-add-pro/">💰 Придбати PRO (599 грн / $29)</a></p><?php endif; ?>
    </div><?php
}

add_action('admin_init', function() {
    if(isset($_POST['activate_lic']) && strlen($_POST['license_key'])>=16) update_option('hva_license_valid', true);
});

function hva_render_gui() {
    $attrs = hva_get_attrs();
    ?>
    <div class="wrap"><h1>🐝 HiveVoice ADD Pro</h1>
    <div style="background:<?php echo hva_is_pro()?'#d4edda':'#fff3cd';?>; padding:10px; margin-bottom:15px;"><?php echo hva_is_pro()?'✅ PRO':'📊 Безкоштовно: '.hva_remaining().' оголошень'; ?></div>
    <div style="margin-bottom:10px"><textarea id="voice_text" rows="10" style="width:100%; font-family:monospace; padding:10px;" placeholder='Наприклад:
назва: квартира
ціна: 45000
категорія: нерухомість
атрибут поверх: 7
атрибут площа: 65'></textarea></div>
    <div><video id="video" width="100%" autoplay playsinline muted style="background:#000; border-radius:10px;"></video></div>
    <div><button id="take_photo" class="button">📷 Фото</button> <button id="clear_photos" class="button">❌</button> <div id="gallery"></div></div>
    <button id="mic_btn" style="width:100%; background:#1877F2; color:#fff; padding:15px; margin:15px 0;">🎤 Диктувати</button>
    <button id="save_btn" class="button button-primary button-large">➕ Опублікувати</button>
    <div id="message"></div>
    <script>let photos=[],stream=null,listening=false;const attrs=<?php echo json_encode($attrs); ?>;
    async function start(){try{stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:"environment"}});document.getElementById('video').srcObject=stream;}catch(e){}}
    start();
    document.getElementById('take_photo').onclick=()=>{let v=document.getElementById('video'),c=document.createElement('canvas');c.width=v.videoWidth;c.height=v.videoHeight;c.getContext('2d').drawImage(v,0,0);let img=c.toDataURL();photos.push(img);let el=document.createElement('img');el.src=img;el.style.width='70px';document.getElementById('gallery').prepend(el);};
    document.getElementById('clear_photos').onclick=()=>{photos=[];document.getElementById('gallery').innerHTML='';};
    if(window.SpeechRecognition){let r=new (window.SpeechRecognition||window.webkitSpeechRecognition)();r.lang='uk-UA';r.continuous=true;
    document.getElementById('mic_btn').onclick=()=>{if(!listening){r.start();listening=true;micBtn.innerText='🛑 Стоп';}else{r.stop();listening=false;micBtn.innerText='🎤 Диктувати';}};
    r.onresult=(e)=>{let t=e.results[e.results.length-1][0].transcript;document.getElementById('voice_text').value+=(document.getElementById('voice_text').value?"\n":"")+t;};r.onend=()=>{if(listening)r.start();};}
    document.getElementById('save_btn').onclick=async function(){this.disabled=true;let fd=new FormData();fd.append('action','hva_save');fd.append('text',document.getElementById('voice_text').value);fd.append('images',JSON.stringify(photos));let r=await fetch(ajaxurl,{method:'POST',body:fd});let res=await r.json();if(res.success){alert('✅ Оголошення додано! ID:'+res.data.id);location.reload();}else{alert('❌ '+res.data.message);this.disabled=false;}};
    </script>
    </div><?php
}

add_action('wp_ajax_hva_save', function() {
    if(!current_user_can('manage_options') || (!hva_is_pro() && wp_count_posts('hp_listing')->publish >= HVA_FREE_LIMIT)) wp_send_json_error('Ліміт');
    $lines=explode("\n",$_POST['text']); $data=[]; $attrs=[];
    foreach($lines as $line){ if(strpos($line,':')===false)continue; list($k,$v)=explode(':',$line,2); $k=trim($k); $v=trim($v);
        if(stripos($k,'атрибут')===0){ $attr_name=trim(str_ireplace('атрибут','',$k)); $attrs[$attr_name]=$v; }
        else $data[$k]=$v;
    }
    require_once ABSPATH.'wp-admin/includes/taxonomy.php';
    $listing = (new \HivePress\Models\Listing())->fill(['title'=>($data['назва']??'Оголошення'), 'status'=>'publish']);
    if(isset($data['ціна'])) $listing->set_price(floatval($data['ціна']));
    if(isset($data['опис'])) $listing->set_description($data['опис']);
    $lid=$listing->save(['title']);
    if(!$lid) wp_send_json_error('Помилка');
    
    if(isset($data['категорія'])){ $t=term_exists($data['категорія'],'hp_listing_category'); if(!$t)$t=wp_insert_term($data['категорія'],'hp_listing_category'); if(!is_wp_error($t)) wp_set_object_terms($lid,(int)$t['term_id'],'hp_listing_category'); }
    $extra=[];
    foreach($attrs as $name=>$val){
        $slug=sanitize_title($name); $tax='hp_listing_'.$slug;
        if(taxonomy_exists($tax)){ $term=term_exists($val,$tax); if(!$term)$term=wp_insert_term($val,$tax); if(!is_wp_error($term)) wp_set_object_terms($lid,(int)$term['term_id'],$tax); }
        else $extra[]="$name: $val";
    }
    if($extra) update_post_meta($lid,'_hva_extra',$extra);
    $imgs=json_decode(stripslashes($_POST['images']),true);
    if($imgs) foreach($imgs as $i=>$b64){ $img=base64_decode(preg_replace('#^data:image/\w+;base64,#i','',$b64)); $file=wp_upload_bits('hva_'.$lid.'_'.$i.'.jpg',null,$img); if(!$file['error']){ $aid=wp_insert_attachment(['post_mime_type'=>'image/jpeg','post_status'=>'inherit'],$file['file'],$lid); require_once(ABSPATH.'wp-admin/includes/image.php'); wp_update_attachment_metadata($aid,wp_generate_attachment_metadata($aid,$file['file'])); if($i===0) set_post_thumbnail($lid,$aid); } }
    wp_send_json_success(['id'=>$lid]);
});
?>