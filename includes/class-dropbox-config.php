<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dropbox Configuration Manager
 * 
 * Handles all Dropbox API configuration including OAuth2 tokens,
 * app credentials, and folder settings.
 */
class DBXE_Dropbox_Config
{
    // Option keys for storing configuration in WordPress database
    const KEY_APP_KEY = 'dbxe_app_key';
    const KEY_APP_SECRET = 'dbxe_app_secret';
    const KEY_ACCESS_TOKEN = 'dbxe_access_token';
    const KEY_REFRESH_TOKEN = 'dbxe_refresh_token';
    const KEY_TOKEN_EXPIRY = 'dbxe_token_expiry';
    const KEY_FOLDER = 'dbxe_folder';

    // URL prefix for Dropbox file URLs in EDD
    const URL_PREFIX = 'edd-dropbox://';

    /**
     * Get the URL prefix for Dropbox file URLs
     * This method allows developers to customize the URL prefix using filter
     * 
     * @return string The URL prefix (default: 'edd-dropbox://')
     */
    public function getUrlPrefix()
    {
        /**
         * Filter the URL prefix for Dropbox file URLs
         * 
         * @param string $prefix The default URL prefix
         * @return string The filtered URL prefix
         */
        return apply_filters('dbxe_url_prefix', self::URL_PREFIX);
    }

    /**
     * Get Dropbox App Key
     * @return string
     */
    public function getAppKey()
    {
        return edd_get_option(self::KEY_APP_KEY, '');
    }

    /**
     * Get Dropbox App Secret
     * @return string
     */
    public function getAppSecret()
    {
        return edd_get_option(self::KEY_APP_SECRET, '');
    }

    /**
     * Get OAuth2 Access Token
     * Always get fresh from database (no transient caching)
     * @return string
     */
    public function getAccessToken()
    {
        return get_option(self::KEY_ACCESS_TOKEN, '');
    }

    /**
     * Get OAuth2 Refresh Token
     * @return string
     */
    public function getRefreshToken()
    {
        return get_option(self::KEY_REFRESH_TOKEN, '');
    }

    /**
     * Get token expiry timestamp
     * @return int
     */
    public function getTokenExpiry()
    {
        return (int) get_option(self::KEY_TOKEN_EXPIRY, 0);
    }

    /**
     * Get selected Dropbox folder path
     * @return string
     */
    public function getSelectedFolder()
    {
        return edd_get_option(self::KEY_FOLDER, '');
    }

    /**
     * Check if access token is expired
     * Uses transient as expiry indicator only
     * @return bool
     */
    public function isTokenExpired()
    {
        // If transient exists, token is still valid
        if (get_transient(self::KEY_ACCESS_TOKEN) !== false) {
            return false;
        }

        // If we have refresh token, we can refresh
        $refresh_token = $this->getRefreshToken();
        if (!empty($refresh_token)) {
            return true; // Needs refresh
        }

        // No refresh token = truly expired
        return true;
    }

    /**
     * Save OAuth2 tokens to database and transient
     * 
     * @param string $access_token
     * @param string $refresh_token
     * @param int $expires_in Seconds until token expires
     * @return bool
     */
    public function saveTokens($access_token, $refresh_token = '', $expires_in = 14400)
    {
        // Save access token to transient with buffer (5 mins less than actual expiry)
        set_transient(self::KEY_ACCESS_TOKEN, $access_token, $expires_in - 300);

        // Still save to option for persistence
        $saved = update_option(self::KEY_ACCESS_TOKEN, $access_token);

        if (!empty($refresh_token)) {
            update_option(self::KEY_REFRESH_TOKEN, $refresh_token);
        }

        // Calculate and save expiry timestamp
        $expiry = time() + $expires_in;
        update_option(self::KEY_TOKEN_EXPIRY, $expiry);

        return $saved;
    }

    /**
     * Clear all OAuth2 tokens (disconnect)
     * @return void
     */
    public function clearTokens()
    {
        delete_transient(self::KEY_ACCESS_TOKEN);
        delete_option(self::KEY_ACCESS_TOKEN);
        delete_option(self::KEY_REFRESH_TOKEN);
        delete_option(self::KEY_TOKEN_EXPIRY);
    }

    /**
     * Check if app credentials are configured
     * @return bool
     */
    public function hasAppCredentials()
    {
        return !empty($this->getAppKey()) && !empty($this->getAppSecret());
    }

    /**
     * Check if OAuth2 is connected
     * @return bool
     */
    public function isConnected()
    {
        return !empty($this->getAccessToken()) && $this->hasAppCredentials();
    }

    /**
     * Check if fully configured (connected + folder selected)
     * @return bool
     */
    public function isConfigured()
    {
        return $this->isConnected();
    }

    /**
     * Debug logging helper
     * @param mixed $log
     */
    public function debug($log)
    {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            if (is_array($log) || is_object($log)) {
                $message = wp_json_encode($log, JSON_UNESCAPED_UNICODE);
                if ($message !== false) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('[DBXE] ' . $message);
                }
            } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[DBXE] ' . sanitize_text_field($log));
            }
        }
    }
}
