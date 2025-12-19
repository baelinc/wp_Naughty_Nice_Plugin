<?php
if (!defined('ABSPATH')) exit;

// Handle manual update check
global $myUpdateChecker;
if (isset($_POST['force_puc_check']) && $myUpdateChecker) {
    $myUpdateChecker->requestUpdate();
}

/**
 * Admin Menu Setup
 */
add_action('admin_menu', 'nnl_admin_menu');
function nnl_admin_menu() {
    add_menu_page('Santa List', "Santa's List", 'manage_options', 'nnl-settings', 'nnl_settings_page', 'dashicons-santa', 25);
}

function nnl_settings_page() {
    global $wpdb, $myUpdateChecker;
    $table_name = $wpdb->prefix . 'naughty_nice';

    // Handle Form Submissions
    if (isset($_POST['nnl_save_settings'])) {
        update_option('nnl_verify_method', $_POST['nnl_verify_method']);
        update_option('nnl_passcode', $_POST['nnl_passcode']);
        update_option('nnl_geo_radius', $_POST['nnl_geo_radius']);
        update_option('nnl_bad_words', $_POST['nnl_bad_words']);
        echo '<div class="updated"><p>Settings saved!</p></div>';
    }

    if (isset($_POST['nnl_delete_name'])) {
        $wpdb->delete($table_name, array('id' => $_POST['nnl_name_id']));
    }

    // Load Data
    $names = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 50");
    ?>

    <div class="wrap">
        <h1>🎅 Santa's List Manager</h1>

        <div style="display: flex; gap: 20px;">
            <div style="flex: 2;">
                <form method="post" class="postbox" style="padding: 20px;">
                    <h2>General Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th>Verification Method</th>
                            <td>
                                <select name="nnl_verify_method">
                                    <option value="none" <?php selected(get_option('nnl_verify_method'), 'none'); ?>>None (Open)</option>
                                    <option value="passcode" <?php selected(get_option('nnl_verify_method'), 'passcode'); ?>>Passcode Only</option>
                                    <option value="geo" <?php selected(get_option('nnl_verify_method'), 'geo'); ?>>Geofence Only</option>
                                    <option value="both" <?php selected(get_option('nnl_verify_method'), 'both'); ?>>Both (Passcode & Geo)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Passcode</th>
                            <td><input type="text" name="nnl_passcode" value="<?php echo esc_attr(get_option('nnl_passcode')); ?>"></td>
                        </tr>
                        <tr>
                            <th>Geo Radius (Miles)</th>
                            <td><input type="number" name="nnl_geo_radius" value="<?php echo esc_attr(get_option('nnl_geo_radius')); ?>"></td>
                        </tr>
                        <tr>
                            <th>Profanity Filter (Comma separated)</th>
                            <td><textarea name="nnl_bad_words" rows="4" style="width:100%;"><?php echo esc_textarea(get_option('nnl_bad_words')); ?></textarea></td>
                        </tr>
                    </table>
                    <input type="submit" name="nnl_save_settings" class="button button-primary" value="Save All Settings">
                </form>

                <h2>Recent Names</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>List</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($names as $name): ?>
                        <tr>
                            <td><?php echo esc_html($name->child_name); ?></td>
                            <td><strong><?php echo esc_html($name->list_type); ?></strong></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="nnl_name_id" value="<?php echo $name->id; ?>">
                                    <input type="submit" name="nnl_delete_name" class="button button-small" value="Delete" onclick="return confirm('Delete this name?');">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="flex: 1;">
                <?php if ($myUpdateChecker): 
                    $update = $myUpdateChecker->getUpdate();
                    $lastCheck = $myUpdateChecker->getUpdateState()->getLastCheck();
                ?>
                <div class="postbox" style="padding: 15px; border-top: 4px solid #165b33;">
                    <h2 style="margin-top:0;">🔄 System Updates</h2>
                    <p><b>Version:</b> <?php echo $myUpdateChecker->getInstalledVersion(); ?></p>
                    <p><b>Last Sync:</b> <?php echo $lastCheck ? date('M j, g:i a', $lastCheck) : 'Never'; ?></p>

                    <?php if ($update): ?>
                        <div style="background: #fff9e6; border: 1px solid #ffeeba; padding: 10px; margin-bottom: 15px;">
                            <span style="color: #856404;">🚀 Update v<?php echo esc_html($update->version); ?> available!</span><br>
                            <a href="<?php echo admin_url('update-core.php'); ?>" class="button button-link">Go to Updates</a>
                        </div>
                    <?php else: ?>
                        <p style="color: green;">✅ Plugin is up to date.</p>
                    <?php endif; ?>

                    <form method="post">
                        <input type="submit" name="force_puc_check" class="button button-secondary" value="Check for Updates Now">
                    </form>
                </div>
                <?php endif; ?>

                <div class="postbox" style="padding: 15px;">
                    <h2>🔗 FPP Integration</h2>
                    <p>Copy this URL into your FPP Plugin settings:</p>
                    <code><?php echo site_url('/wp-json/santa/v1/list'); ?></code>
                </div>
            </div>
        </div>
    </div>
    <?php
}
