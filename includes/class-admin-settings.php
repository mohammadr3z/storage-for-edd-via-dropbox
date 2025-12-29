<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dropbox Admin Settings
 * 
 * Integrates Dropbox configuration with EDD settings panel
 * and handles OAuth2 authorization flow.
 */
class DBXE_Admin_Settings
{
    private $client;
    private $config;

    public function __construct()
    {
        $this->config = new DBXE_Dropbox_Config();
        $this->client = new DBXE_Dropbox_Client();

        // Register EDD settings
        add_filter('edd_settings_extensions', array($this, 'addSettings'));
        add_filter('edd_settings_sections_extensions', array($this, 'registerSection'));

        // Enqueue admin scripts/styles
        add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));

        // OAuth callback handler
        add_action('admin_post_dbxe_oauth_start', array($this, 'startOAuthFlow'));
        add_action('admin_post_dbxe_disconnect', array($this, 'handleDisconnect'));

        // Register clean OAuth callback endpoint
        add_action('init', array($this, 'registerOAuthEndpoint'));
        add_action('template_redirect', array($this, 'handleOAuthEndpoint'));

        // Register query vars
        add_filter('query_vars', array($this, 'addQueryVars'));

        // Auto-flush rewrite rules if version changed
        add_action('init', array($this, 'maybeFlushRewriteRules'), 99);

        // Admin notices
        add_action('admin_notices', array($this, 'showAdminNotices'));

        // Clear tokens when App Key or App Secret changes
        add_filter('pre_update_option_edd_settings', array($this, 'checkCredentialsChange'), 10, 2);
    }

    /**
     * Add query variables
     * 
     * @param array $vars
     * @return array
     */
    public function addQueryVars($vars)
    {
        $vars[] = 'dbxe_oauth_callback';
        return $vars;
    }

    /**
     * Flush rewrite rules if plugin version changed
     */
    public function maybeFlushRewriteRules()
    {
        $current_version = DBXE_VERSION;
        $saved_version = get_option('dbxe_rewrite_version');

        if ($saved_version !== $current_version) {
            $this->registerOAuthEndpoint(); // Ensure rules are added before flushing
            flush_rewrite_rules();
            update_option('dbxe_rewrite_version', $current_version);
        }
    }

    /**
     * Register OAuth callback endpoint rewrite rule
     */
    public function registerOAuthEndpoint()
    {
        add_rewrite_rule(
            '^dbxe-oauth-callback/?$',
            'index.php?dbxe_oauth_callback=1',
            'top'
        );
        add_rewrite_tag('%dbxe_oauth_callback%', '1');
    }

    /**
     * Handle OAuth callback at custom endpoint
     */
    public function handleOAuthEndpoint()
    {
        if (get_query_var('dbxe_oauth_callback')) {
            $this->handleOAuthCallback();
            exit;
        }
    }

    /**
     * Flush rewrite rules on plugin activation
     */
    public static function activatePlugin()
    {
        // Register the endpoint first
        add_rewrite_rule(
            '^dbxe-oauth-callback/?$',
            'index.php?dbxe_oauth_callback=1',
            'top'
        );
        add_rewrite_tag('%dbxe_oauth_callback%', '1');

        // Flush to apply the new rule
        flush_rewrite_rules();
    }

    /**
     * Flush rewrite rules on plugin deactivation
     */
    public static function deactivatePlugin()
    {
        flush_rewrite_rules();
    }

    /**
     * Check if App Key or App Secret has changed and clear tokens if so
     * 
     * @param array $new_value New settings value
     * @param array $old_value Old settings value
     * @return array
     */
    public function checkCredentialsChange($new_value, $old_value)
    {
        $app_key_field = DBXE_Dropbox_Config::KEY_APP_KEY;
        $app_secret_field = DBXE_Dropbox_Config::KEY_APP_SECRET;

        $old_key = isset($old_value[$app_key_field]) ? $old_value[$app_key_field] : '';
        $new_key = isset($new_value[$app_key_field]) ? $new_value[$app_key_field] : '';

        $old_secret = isset($old_value[$app_secret_field]) ? $old_value[$app_secret_field] : '';
        $new_secret = isset($new_value[$app_secret_field]) ? $new_value[$app_secret_field] : '';

        // If App Key or App Secret changed, clear tokens
        if ($old_key !== $new_key || $old_secret !== $new_secret) {
            $this->config->clearTokens();
        }

        return $new_value;
    }

    /**
     * Add settings to EDD Extensions tab
     * 
     * @param array $settings
     * @return array
     */
    public function addSettings($settings)
    {
        $is_connected = $this->config->isConnected();

        // Check if we are on the EDD extensions settings page
        // This prevents API calls on every admin page load
        // Load folders if: tab=extensions AND (no section OR section is ours)
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only comparison against hardcoded strings for page detection, no data processing.
        $is_settings_page = is_admin() &&
            isset($_GET['page']) && $_GET['page'] === 'edd-settings' &&
            isset($_GET['tab']) && $_GET['tab'] === 'extensions' &&
            (!isset($_GET['section']) || $_GET['section'] === 'dbxe-settings');
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        // Build folder options
        // Only fetch from API if we are effectively on the settings page and connected
        $folder_options = array('' => __('Root folder', 'storage-for-edd-via-dropbox'));
        $has_permission_error = false;

        if ($is_connected && $is_settings_page) {
            try {
                $folders = $this->client->listFolders('');
                if (empty($folders)) {
                    $has_permission_error = true;
                } else {
                    $folder_options = array_merge($folder_options, $folders);
                }
            } catch (Exception $e) {
                $has_permission_error = true;
                $this->config->debug('Error loading folders: ' . $e->getMessage());
            }
        } elseif ($is_connected && !$is_settings_page) {
            // If connected but not on settings page, preserve the currently saved value in the dropdown options
            // so it doesn't look empty if accessed via other means (though rare)
            $saved_folder = $this->config->getSelectedFolder();
            if (!empty($saved_folder)) {
                $folder_options[$saved_folder] = $saved_folder;
            }
        }

        // Build connect/disconnect button HTML
        $connect_button = '';
        if ($is_connected) {
            $disconnect_url = wp_nonce_url(
                admin_url('admin-post.php?action=dbxe_disconnect'),
                'dbxe_disconnect'
            );

            // Different status display based on permissions
            if ($has_permission_error) {
                // Yellow/warning status when connected but permissions missing
                $connect_button = sprintf(
                    '<span class="dbxe-warning-status">%s</span><br><br><span class="dbxe-permission-warning">%s</span><br><br><a href="%s" class="button button-secondary">%s</a>',
                    esc_html__('Permissions Not Active', 'storage-for-edd-via-dropbox'),
                    esc_html__('Required permissions are not enabled. Please disconnect, enable files.metadata.read, files.content.read, and files.content.write in your Dropbox app settings, then reconnect.', 'storage-for-edd-via-dropbox'),
                    esc_url($disconnect_url),
                    esc_html__('Disconnect from Dropbox', 'storage-for-edd-via-dropbox')
                );
            } else {
                // Green status when fully connected
                $connect_button = sprintf(
                    '<span class="dbxe-connected-status">%s</span><br><br><a href="%s" class="button button-secondary">%s</a>',
                    esc_html__('Connected', 'storage-for-edd-via-dropbox'),
                    esc_url($disconnect_url),
                    esc_html__('Disconnect from Dropbox', 'storage-for-edd-via-dropbox')
                );
            }
        } elseif ($this->config->hasAppCredentials()) {
            $connect_url = wp_nonce_url(
                admin_url('admin-post.php?action=dbxe_oauth_start'),
                'dbxe_oauth_start'
            );
            $connect_button = sprintf(
                '<a href="%s" class="button button-primary">%s</a>',
                esc_url($connect_url),
                esc_html__('Connect to Dropbox', 'storage-for-edd-via-dropbox')
            );
        } else {
            $connect_button = '<span class="dbxe-notice">' . esc_html__('Please enter your App Key and App Secret first, then save settings.', 'storage-for-edd-via-dropbox') . '</span>';
        }

        $dbxe_settings = array(
            array(
                'id' => 'dbxe_settings',
                'name' => '<strong>' . __('Dropbox Storage Settings', 'storage-for-edd-via-dropbox') . '</strong>',
                'type' => 'header'
            ),
            array(
                'id' => DBXE_Dropbox_Config::KEY_APP_KEY,
                'name' => __('App Key', 'storage-for-edd-via-dropbox'),
                'desc' => __('Enter your Dropbox App Key from the Dropbox Developer Console.', 'storage-for-edd-via-dropbox'),
                'type' => 'text',
                'size' => 'regular',
                'class' => 'dbxe-credential'
            ),
            array(
                'id' => DBXE_Dropbox_Config::KEY_APP_SECRET,
                'name' => __('App Secret', 'storage-for-edd-via-dropbox'),
                'desc' => __('Enter your Dropbox App Secret from the Dropbox Developer Console.', 'storage-for-edd-via-dropbox'),
                'type' => 'password',
                'size' => 'regular',
                'class' => 'dbxe-credential'
            ),
            array(
                'id' => 'dbxe_connection',
                'name' => __('Connection Status', 'storage-for-edd-via-dropbox'),
                'desc' => $connect_button,
                'type' => 'descriptive_text'
            ),
            array(
                'id' => DBXE_Dropbox_Config::KEY_FOLDER,
                'name' => __('Default Folder', 'storage-for-edd-via-dropbox'),
                'desc' => $is_connected
                    ? __('Select the default folder for uploads.', 'storage-for-edd-via-dropbox')
                    : __('Connect to Dropbox first to select a folder.', 'storage-for-edd-via-dropbox'),
                'type' => 'select',
                'options' => $folder_options,
                'std' => '',
                'class' => $is_connected ? '' : 'dbxe-disabled'
            ),
            array(
                'id' => 'dbxe_help',
                'name' => __('Setup Instructions', 'storage-for-edd-via-dropbox'),
                'desc' => sprintf(
                    '<ol>
                        <li>%s <a href="https://www.dropbox.com/developers/apps" target="_blank">%s</a></li>
                        <li>%s</li>
                        <li><strong>%s</strong>
                            <ul style="margin-top:5px;margin-left:20px;">
                                <li><code>files.metadata.read</code></li>
                                <li><code>files.content.read</code> (%s)</li>
                                <li><code>files.content.write</code> (%s)</li>
                            </ul>
                        </li>
                        <li>%s <code>%s</code></li>
                        <li>%s</li>
                        <li>%s</li>
                    </ol>',
                    __('Go to', 'storage-for-edd-via-dropbox'),
                    __('Dropbox Developer Console', 'storage-for-edd-via-dropbox'),
                    __('Create a new app with "Scoped Access" and "Full Dropbox" access type.', 'storage-for-edd-via-dropbox'),
                    __('Enable these permissions in the "Permissions" tab:', 'storage-for-edd-via-dropbox'),
                    __('Required', 'storage-for-edd-via-dropbox'),
                    __('Required', 'storage-for-edd-via-dropbox'),
                    __('Add this OAuth Redirect URI:', 'storage-for-edd-via-dropbox'),
                    esc_html($this->getRedirectUri()),
                    __('Copy the App Key and App Secret and paste them above.', 'storage-for-edd-via-dropbox'),
                    __('Save settings, then click "Connect to Dropbox".', 'storage-for-edd-via-dropbox')
                ),
                'type' => 'descriptive_text'
            ),
        );

        return array_merge($settings, array('dbxe-settings' => $dbxe_settings));
    }

    /**
     * Register settings section
     * 
     * @param array $sections
     * @return array
     */
    public function registerSection($sections)
    {
        $sections['dbxe-settings'] = __('Dropbox Storage', 'storage-for-edd-via-dropbox');
        return $sections;
    }

    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook
     */
    public function enqueueAdminScripts($hook)
    {
        if ($hook !== 'download_page_edd-settings') {
            return;
        }

        wp_enqueue_script('jquery');

        wp_register_style('dbxe-admin-settings', DBXE_PLUGIN_URL . 'assets/css/admin-settings.css', array(), DBXE_VERSION);
        wp_enqueue_style('dbxe-admin-settings');

        wp_register_script('dbxe-admin-settings', DBXE_PLUGIN_URL . 'assets/js/admin-settings.js', array('jquery'), DBXE_VERSION, true);
        wp_enqueue_script('dbxe-admin-settings');
    }

    /**
     * Get OAuth redirect URI
     * 
     * @return string
     */
    private function getRedirectUri()
    {
        return home_url('/dbxe-oauth-callback/');
    }

    /**
     * Start OAuth authorization flow
     */
    public function startOAuthFlow()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'storage-for-edd-via-dropbox'));
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'dbxe_oauth_start')) {
            wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-dropbox'));
        }

        if (!$this->config->hasAppCredentials()) {
            wp_safe_redirect(add_query_arg('dbxe_error', 'no_credentials', wp_get_referer()));
            exit;
        }

        // Store state for security
        $state = wp_create_nonce('dbxe_oauth_state');
        set_transient('dbxe_oauth_state_' . get_current_user_id(), $state, 600);

        $auth_url = $this->client->getAuthorizationUrl($this->getRedirectUri());
        $auth_url .= '&state=' . $state;

        $auth_url .= '&state=' . $state;

        add_filter('allowed_redirect_hosts', function ($hosts) {
            $hosts[] = 'www.dropbox.com';
            $hosts[] = 'dropbox.com';
            return $hosts;
        });

        wp_safe_redirect($auth_url);
        exit;
    }

    /**
     * Handle OAuth callback from Dropbox
     */
    public function handleOAuthCallback()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'storage-for-edd-via-dropbox'));
        }

        // Verify state
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- OAuth uses state parameter as CSRF protection; properly sanitized.
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $stored_state = get_transient('dbxe_oauth_state_' . get_current_user_id());
        delete_transient('dbxe_oauth_state_' . get_current_user_id());

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- State is compared with stored transient value, this is OAuth CSRF protection.
        if (empty($state) || $state !== $stored_state) {
            $this->redirectWithError('invalid_state');
            return;
        }

        // Check for error from Dropbox
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback uses state parameter verification above instead of nonces.
        if (isset($_GET['error'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- OAuth callback uses state parameter verification instead of nonces.
            $this->redirectWithError(sanitize_text_field(wp_unslash($_GET['error'])));
            return;
        }

        // Get authorization code
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- OAuth callback uses state parameter verification instead of nonces.
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        if (empty($code)) {
            $this->redirectWithError('no_code');
            return;
        }

        // Exchange code for token
        $tokens = $this->client->exchangeCodeForToken($code, $this->getRedirectUri());
        if (!$tokens) {
            $this->redirectWithError('token_exchange_failed');
            return;
        }

        // Save tokens
        $this->config->saveTokens(
            $tokens['access_token'],
            $tokens['refresh_token'],
            $tokens['expires_in']
        );

        // Redirect back to settings with success message
        $redirect = admin_url('edit.php?post_type=download&page=edd-settings&tab=extensions&section=dbxe-settings&dbxe_connected=1');
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Handle disconnect request
     */
    public function handleDisconnect()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'storage-for-edd-via-dropbox'));
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'dbxe_disconnect')) {
            wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-dropbox'));
        }

        $this->config->clearTokens();

        $redirect = admin_url('edit.php?post_type=download&page=edd-settings&tab=extensions&section=dbxe-settings&dbxe_disconnected=1');
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Redirect to settings with error
     * 
     * @param string $error
     */
    private function redirectWithError($error)
    {
        $redirect = admin_url('edit.php?post_type=download&page=edd-settings&tab=extensions&section=dbxe-settings&dbxe_error=' . urlencode($error));
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Show admin notices
     */
    public function showAdminNotices()
    {
        // Success: Connected
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only check for admin notice, set by OAuth redirect with proper nonce verification in handleOAuthCallback().
        if (isset($_GET['dbxe_connected'])) {
?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Successfully connected to Dropbox!', 'storage-for-edd-via-dropbox'); ?></p>
            </div>
        <?php
        }

        // Success: Disconnected
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only check for admin notice, set by handleDisconnect() with proper nonce verification.
        if (isset($_GET['dbxe_disconnected'])) {
        ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Disconnected from Dropbox.', 'storage-for-edd-via-dropbox'); ?></p>
            </div>
        <?php
        }

        // Error messages
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only check, error codes are validated against hardcoded array and never echoed directly.
        if (isset($_GET['dbxe_error'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Sanitized with sanitize_text_field, used as lookup key only.
            $error = sanitize_text_field(wp_unslash($_GET['dbxe_error']));
            $messages = array(
                'no_credentials' => __('Please enter your App Key and App Secret first.', 'storage-for-edd-via-dropbox'),
                'invalid_state' => __('OAuth security check failed. Please try again.', 'storage-for-edd-via-dropbox'),
                'no_code' => __('No authorization code received from Dropbox.', 'storage-for-edd-via-dropbox'),
                'token_exchange_failed' => __('Failed to exchange authorization code for access token.', 'storage-for-edd-via-dropbox'),
                'access_denied' => __('Access was denied by the user.', 'storage-for-edd-via-dropbox'),
            );
            $message = isset($messages[$error]) ? $messages[$error] : __('An error occurred during authorization.', 'storage-for-edd-via-dropbox');
        ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
<?php
        }
    }
}
