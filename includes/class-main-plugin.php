<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class
 * 
 * Initializes all plugin components and sets up hooks.
 */
class DBXE_DropboxStorage
{
    private $settings;
    private $media_library;
    private $downloader;
    private $uploader;

    public function __construct()
    {
        $this->init();
    }

    /**
     * Initialize plugin components
     */
    private function init()
    {
        add_action('admin_notices', array($this, 'showConfigurationNotice'));

        // Initialize components
        $this->settings = new DBXE_Admin_Settings();
        $this->media_library = new DBXE_Media_Library();
        $this->downloader = new DBXE_Dropbox_Downloader();
        $this->uploader = new DBXE_Dropbox_Uploader();

        // Register EDD download filter
        add_filter('edd_requested_file', array($this->downloader, 'generateUrl'), 11, 3);
    }

    /**
     * Show admin notice if Dropbox is not configured
     */
    public function showConfigurationNotice()
    {
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }

        // Don't show on Dropbox settings page itself
        $current_screen = get_current_screen();
        if ($current_screen && strpos($current_screen->id, 'edd-settings') !== false) {
            return;
        }

        $config = new DBXE_Dropbox_Config();

        // Show notice if not connected
        if (!$config->isConnected()) {
            $settings_url = admin_url('edit.php?post_type=download&page=edd-settings&tab=extensions&section=dbxe-settings');
?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Storage for EDD via Dropbox:', 'storage-for-edd-via-dropbox'); ?></strong>
                    <?php esc_html_e('Please connect to Dropbox to start using cloud storage for your digital products.', 'storage-for-edd-via-dropbox'); ?>
                    <a href="<?php echo esc_url($settings_url); ?>" class="button button-secondary" style="margin-left: 10px;">
                        <?php esc_html_e('Configure Dropbox', 'storage-for-edd-via-dropbox'); ?>
                    </a>
                </p>
            </div>
<?php
        }
    }

    /**
     * Get plugin version
     * @return string
     */
    public function getVersion()
    {
        return DBXE_VERSION;
    }
}
