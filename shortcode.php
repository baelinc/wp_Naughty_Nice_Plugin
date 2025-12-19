<?php
if (!defined('ABSPATH')) exit;

add_shortcode('naughty_nice_form', 'nnl_render_shortcode');

function nnl_smart_filter($text) {
    $bad_words = array_map('trim', explode(',', get_option('nnl_bad_words', '')));
    $normalized = strtolower($text);
    $normalized = strtr($normalized, ['4'=>'a','@'=>'a','8'=>'b','3'=>'e','1'=>'i','!'=>'i','0'=>'o','5'=>'s','$'=>'s','7'=>'t','+'=>'t']);
    foreach ($bad_words as $word) {
        if (!empty($word) && stripos($normalized, strtolower($word)) !== false) return true;
    }
    return false;
}

function nnl_render_shortcode() {
    global $wpdb;
    $message = ''; $status = '';
    
    if (isset($_POST['nnl_submit'])) {
        $name = sanitize_text_field($_POST['child_name']);
        $method = get_option('nnl_verify_method');
        $auth = false;

        if (nnl_smart_filter($name)) {
            $message = "Ho Ho No! Let's stay on the Nice list with kind words!"; $status = 'error';
        } else {
            if ($method == 'none') $auth = true;
            elseif ($method == 'passcode' && $_POST['pass'] == get_option('nnl_passcode')) $auth = true;
            elseif ($method == 'geo') {
                $dist = nnl_calc_dist($_POST['u_lat'], $_POST['u_lng'], get_option('nnl_admin_lat'), get_option('nnl_admin_lng'));
                if ($dist <= floatval(get_option('nnl_geo_radius'))) $auth = true;
                else { $message = "You're too far from the North Pole Workshop!"; $status = 'error'; }
            } else { $message = "Humbug! That code isn't right."; $status = 'error'; }
        }

        if ($auth) {
            $wpdb->insert($wpdb->prefix.'naughty_nice', ['child_name'=>$name, 'list_type'=>$_POST['list_type']]);
            $message = ($_POST['list_type'] == 'Nice') ? "Ho Ho Ho! $name is on the Nice List!" : "Uh oh! The Naughty List it is for $name!";
            $status = 'success';
            // Store name for duplicate checking
            echo "<script>sessionStorage.setItem('nnl_last_name', '" . esc_js($name) . "');</script>";
        }
    }
    ob_start(); ?>
    <style>
        .santa-card { background:#fff; border:8px solid #d42426; border-radius:15px; padding:30px; max-width:400px; margin:auto; text-align:center; font-family:'Comic Sans MS', cursive; box-shadow:0 10px 20px rgba(0,0,0,0.1); }
        .santa-card input, .santa-card select { width:100%; padding:12px; margin:10px 0; border:2px solid #165b33; border-radius:5px; font-size:16px; }
        .santa-btn { background:#d42426; color:#fff; padding:15px; border:none; border-radius:50px; width:100%; cursor:pointer; font-weight:bold; font-size:1.1em; transition:0.3s; margin-top:10px; }
        .santa-btn:hover { background:#165b33; }
        .santa-btn.secondary { background:#555; margin-top: 5px; font-size: 0.9em; }

        /* Modal Overlay */
        .nnl-modal-overlay { display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.95); justify-content:center; align-items:center; color:#fff; text-align:center; padding:20px; }
        .nnl-modal-inner { background:#d42426; padding:40px; border:5px solid #f8b229; border-radius:20px; max-width:500px; width:100%; box-sizing: border-box; }
        
        .icon-nice { fill: #f8b229; filter: drop-shadow(0 0 10px #f8b229); }
        .icon-naughty { fill: #333; filter: drop-shadow(0 0 10px #000); }
        .icon-warn { fill: #f8b229; margin-bottom: 20px; }
    </style>

    <div class="santa-card">
        <svg width="50" height="50" viewBox="0 0 24 24" fill="#d42426"><path d="M12 2c-4.418 0-8 3.582-8 8 0 2.21 1.79 4 4 4h8c2.21 0 4-1.79 4-4 0-4.418-3.582-8-8-8zm0 18c-1.105 0-2-.895-2-2s.895-2 2-2 2 .895 2 2-.895 2-2 2z"/></svg>
        <h2 style="color:#d42426; margin-bottom:20px;">Santa's Official List</h2>
        <form method="POST" id="santa-list-form">
            <input type="text" id="child_name_input" name="child_name" placeholder="First Name Only" required maxlength="20">
            <select name="list_type">
                <option value="Nice">🌟 Nice List</option>
                <option value="Naughty">⚫ Naughty List</option>
            </select>
            <?php if(get_option('nnl_verify_method')=='passcode'): ?>
                <input type="text" name="pass" placeholder="Secret Workshop Code" required>
            <?php endif; ?>
            <input type="hidden" name="u_lat" id="u_lat">
            <input type="hidden" name="u_lng" id="u_lng">
            <button type="submit" name="nnl_submit" id="main_submit_btn" class="santa-btn">Send to North Pole</button>
        </form>
    </div>

    <div id="santa-modal" class="nnl-modal-overlay" style="<?php echo $message ? 'display:flex;' : ''; ?>">
        <div class="nnl-modal-inner">
            <div style="margin-bottom:20px;">
                <?php if($status == 'success'): ?>
                    <svg class="icon-nice" width="100" height="100" viewBox="0 0 24 24"><path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z"/></svg>
                <?php else: ?>
                    <svg class="icon-naughty" width="100" height="100" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <?php endif; ?>
            </div>
            <h1 style="color:#fff; font-size:1.8em; margin-bottom:20px;"><?php echo $message; ?></h1>
            <button class="santa-btn" onclick="document.getElementById('santa-modal').style.display='none'">Close</button>
        </div>
    </div>

    <div id="duplicate-modal" class="nnl-modal-overlay">
        <div class="nnl-modal-inner">
            <svg class="icon-warn" width="80" height="80" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <h2 style="color:#fff;">Wait a minute!</h2>
            <p style="font-size: 1.2em;">You just added that name to the list. <br>Are you sure you want to add it again?</p>
            <button type="button" id="confirm-duplicate" class="santa-btn">Yes, add it again!</button>
            <button type="button" id="cancel-duplicate" class="santa-btn secondary">No, my mistake!</button>
        </div>
    </div>

    <script>
        // Get Geolocation
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(p){ 
                document.getElementById('u_lat').value = p.coords.latitude; 
                document.getElementById('u_lng').value = p.coords.longitude; 
            });
        }

        const form = document.getElementById('santa-list-form');
        const dupModal = document.getElementById('duplicate-modal');
        const confirmBtn = document.getElementById('confirm-duplicate');
        const cancelBtn = document.getElementById('cancel-duplicate');
        const nameInput = document.getElementById('child_name_input');

        let duplicateChecked = false;

        form.addEventListener('submit', function(e) {
            if (duplicateChecked) return; // Allow submission if already confirmed

            const currentName = nameInput.value.trim();
            const lastName = sessionStorage.getItem('nnl_last_name');

            if (lastName && currentName.toLowerCase() === lastName.toLowerCase()) {
                e.preventDefault(); // Stop initial submission
                dupModal.style.display = 'flex'; // Show decorative modal
            }
        });

        // User clicks "Yes, add it again!"
        confirmBtn.addEventListener('click', function() {
            duplicateChecked = true;
            dupModal.style.display = 'none';
            document.getElementById('main_submit_btn').click(); // Re-trigger submission
        });

        // User clicks "No, my mistake!"
        cancelBtn.addEventListener('click', function() {
            dupModal.style.display = 'none';
            nameInput.value = ''; // Optional: clear the field
        });
    </script>
    <?php return ob_get_clean();
}

function nnl_calc_dist($lat1, $lon1, $lat2, $lon2) {
    $r = 3959; // Miles
    $dLat = deg2rad($lat2-$lat1); $dLon = deg2rad($lon2-$lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return $r * 2 * atan2(sqrt($a), sqrt(1-$a));
}
