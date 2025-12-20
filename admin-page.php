<?php
if (!defined('ABSPATH')) exit;

/**
 * Handle Manual Update Check Logic
 */
add_action('admin_init', 'nnl_handle_manual_update');
function nnl_handle_manual_update() {
    if (isset($_POST['force_puc_check'])) {
        $checker = isset($GLOBALS['myUpdateChecker']) ? $GLOBALS['myUpdateChecker'] : null;
        if ($checker) {
            $checker->requestUpdate();
            wp_redirect(admin_url('admin.php?page=naughty-nice-list&check=complete'));
            exit;
        }
    }
}

/**
 * Register Admin Menu
 */
add_action('admin_menu', 'nnl_create_menu');
function nnl_create_menu() {
    add_menu_page(
        'Naughty & Nice', 
        'Naughty & Nice', 
        'manage_options', 
        'naughty-nice-list', 
        'nnl_admin_html', 
        'none', // We set this to 'none' to use custom CSS for the icon
        25
    );

    // This tiny bit of CSS injects the Santa emoji into that menu spot
    add_action('admin_head', function() {
        echo '<style>
            #toplevel_page_naughty-nice-list .wp-menu-image:before {
                content: "🎅";
                font-size: 18px !important;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        </style>';
    });
}

function nnl_admin_html() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'naughty_nice';
    // Access the update checker from the global scope
    $updateChecker = isset($GLOBALS['myUpdateChecker']) ? $GLOBALS['myUpdateChecker'] : null;

    // 1. Save Settings Logic
    if (isset($_POST['save_settings'])) {
        update_option('nnl_verify_method', $_POST['nnl_verify_method']);
        update_option('nnl_passcode', sanitize_text_field($_POST['nnl_passcode']));
        update_option('nnl_geo_radius', sanitize_text_field($_POST['nnl_geo_radius']));
        update_option('nnl_admin_lat', sanitize_text_field($_POST['nnl_admin_lat']));
        update_option('nnl_admin_lng', sanitize_text_field($_POST['nnl_admin_lng']));
        update_option('nnl_bad_words', sanitize_textarea_field($_POST['nnl_bad_words']));
        echo '<div class="updated"><p>🎅 Santa updated his workshop settings!</p></div>';
    }

    // 2. Manual Entry Logic
    if (isset($_POST['add_entry_manual'])) {
        $wpdb->insert($table_name, [
            'child_name' => sanitize_text_field($_POST['child_name']),
            'list_type'  => $_POST['list_type']
        ]);
        echo '<div class="updated"><p>Entry added manually.</p></div>';
    }

    // 3. Delete Single Entry Logic
    if (isset($_GET['delete_id'])) {
        $wpdb->delete($table_name, ['id' => intval($_GET['delete_id'])]);
        echo '<div class="updated"><p>Entry removed.</p></div>';
    }

    // 4. PURGE ENTIRE LIST LOGIC
    if (isset($_POST['purge_list_confirm'])) {
        $wpdb->query("TRUNCATE TABLE $table_name");
        echo '<div class="error"><p><strong>The list has been completely purged!</strong> Empty and ready for next year.</p></div>';
    }

    $entries = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
    ?>
    <div class="wrap">
        <h1>🎅 Naughty and Nice Management</h1>
        
        <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
            
            <div style="flex: 1; min-width: 350px; background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ccd0d4;">
                <h2>Workshop Settings</h2>
                <form method="POST">
                    <p><strong>Method:</strong> 
                        <select name="nnl_verify_method" style="width:100%">
                            <option value="none" <?php selected(get_option('nnl_verify_method'), 'none'); ?>>None (Open)</option>
                            <option value="passcode" <?php selected(get_option('nnl_verify_method'), 'passcode'); ?>>Passcode</option>
                            <option value="geo" <?php selected(get_option('nnl_verify_method'), 'geo'); ?>>Geofence</option>
                        </select>
                    </p>
                    <p><strong>Passcode:</strong> <input type="text" name="nnl_passcode" value="<?php echo esc_attr(get_option('nnl_passcode')); ?>" style="width:100%"></p>
                    <p><strong>Radius (Miles):</strong> <input type="number" name="nnl_geo_radius" value="<?php echo esc_attr(get_option('nnl_geo_radius')); ?>" style="width:100%"></p>
                    <p><strong>Your GPS Base:</strong><br>
                        Lat: <input type="text" id="nnl_lat" name="nnl_admin_lat" value="<?php echo esc_attr(get_option('nnl_admin_lat')); ?>" style="width:35%">
                        Lng: <input type="text" id="nnl_lng" name="nnl_admin_lng" value="<?php echo esc_attr(get_option('nnl_admin_lng')); ?>" style="width:35%">
                        <button type="button" class="button" onclick="nnl_get_geo()">📍 Get Location</button>
                    </p>
                    <p><strong>Blocked Words:</strong><br><textarea name="nnl_bad_words" rows="3" style="width:100%"><?php echo esc_textarea(get_option('nnl_bad_words')); ?></textarea></p>
                    <input type="submit" name="save_settings" class="button button-primary" value="Save All Settings">
                </form>
            </div>

            <div style="flex: 1; min-width: 300px;">
                
                <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ccd0d4; border-top: 5px solid #165b33; margin-bottom: 20px;">
                    <h2 style="margin-top:0;">🔄 GitHub Sync</h2>
                    <?php if ($updateChecker): 
                        $state = $updateChecker->getUpdateState();
                        $update = $updateChecker->getUpdate();
                        $lastCheck = $state ? $state->getLastCheck() : null;
                    ?>
                        <p><b>Your Version:</b> <?php echo esc_html($updateChecker->getInstalledVersion()); ?></p>
                        <p><b>Last Sync:</b> <?php echo $lastCheck ? date('M j, g:i a', $lastCheck) : 'Never'; ?></p>

                        <div style="background: #f4f4f4; padding: 10px; font-size: 11px; margin-bottom: 15px; border: 1px inset #ccc; color: #666;">
                            <strong>Diagnostic Info:</strong><br>
                            Remote Version Found: <?php echo ($update) ? esc_html($update->version) : 'None'; ?><br>
                            Plugin Slug: <?php echo esc_html($updateChecker->slug); ?><br>
                        </div>

                        <?php if ($update): ?>
                            <div style="background: #fff9e6; border-left: 4px solid #f1c40f; padding: 10px; margin-bottom: 15px;">
                                🚀 <b>Update Available: v<?php echo esc_html($update->version); ?></b><br>
                                <a href="<?php echo admin_url('update-core.php'); ?>" class="button button-link" style="padding:0;">Visit Updates Page</a>
                            </div>
                        <?php else: ?>
                            <p style="color: green; font-weight: bold;">✅ Up to date with GitHub.</p>
                        <?php endif; ?>

                        <form method="post">
                            <input type="submit" name="force_puc_check" class="button button-secondary" value="Check GitHub Now">
                        </form>
                    <?php else: ?>
                        <p style="color:red;">⚠️ Update engine offline. Please check folder paths.</p>
                    <?php endif; ?>
                </div>

                <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ccd0d4;">
                    <h2>Tools</h2>
                    <form method="POST" style="margin-bottom: 25px;">
                        <h3>Add Entry Manually</h3>
                        <p>Name: <input type="text" name="child_name" required style="width:100%"></p>
                        <p>List: <select name="list_type" style="width:100%"><option value="Nice">🌟 Nice List</option><option value="Naughty">⚫ Naughty List</option></select></p>
                        <input type="submit" name="add_entry_manual" class="button" value="Add Entry">
                    </form>

                    <hr>

                    <div style="background: #fbeaea; padding: 15px; border: 1px solid #dc3232; border-radius: 5px; margin-top: 20px;">
                        <h3 style="color: #dc3232; margin-top: 0;">Danger Zone</h3>
                        <form method="POST" onsubmit="return confirm('🚨 DANGER: Are you absolutely sure you want to PURGE THE ENTIRE LIST?');">
                            <input type="submit" name="purge_list_confirm" class="button" style="background: #dc3232; color: white; border-color: #902020;" value="Purge Entire List">
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <h2>The Official List (<?php echo count($entries); ?> Entries)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>First Name</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($entries): foreach ($entries as $row): ?>
                <tr>
                    <td><strong><?php echo esc_html($row->child_name); ?></strong></td>
                    <td>
                        <span style="background:<?php echo ($row->list_type=='Nice')?'#165b33':'#d42426'; ?>; color:#fff; padding:3px 8px; border-radius:5px;">
                            <?php echo esc_html($row->list_type); ?>
                        </span>
                    </td>
                    <td><a href="?page=naughty-nice-list&delete_id=<?php echo $row->id; ?>" style="color:red;" onclick="return confirm('Delete this entry?')">Delete</a></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="3">The list is currently empty.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top: 30px; padding: 15px; background: #e7f3ff; border: 1px solid #b3d7ff; border-radius: 8px;">
            <strong>🔗 FPP API Endpoint:</strong> <code><?php echo site_url('/wp-json/santa/v1/list'); ?></code>
        </div>
    </div>
    
    <script>
    function nnl_get_geo() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(p) {
                document.getElementById('nnl_lat').value = p.coords.latitude;
                document.getElementById('nnl_lng').value = p.coords.longitude;
                alert('Santa has found your coordinates!');
            });
        }
    }
    </script>
    <?php
}
