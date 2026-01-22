<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dropbox Media Library Integration
 * 
 * Adds custom tabs to WordPress media uploader for browsing
 * and uploading files to Dropbox.
 */
class DBXE_Media_Library
{
    private $client;
    private $config;

    public function __construct()
    {
        $this->config = new DBXE_Dropbox_Config();
        $this->client = new DBXE_Dropbox_Client();

        // Media library integration
        add_action('media_upload_dbxe_lib', array($this, 'registerLibraryTab'));

        // Enqueue styles
        add_action('admin_enqueue_scripts', array($this, 'enqueueStyles'));

        // Add Dropbox button to EDD downloadable files (Server-Side)
        add_action('edd_download_file_table_row', array($this, 'renderBrowseButton'), 10, 3);

        // Add scripts for Dropbox button interaction
        add_action('admin_footer', array($this, 'printAdminScripts'));
    }



    /**
     * Register Dropbox Library tab
     */
    public function registerLibraryTab()
    {
        $mediaCapability = apply_filters('dbxe_media_access_cap', 'edit_products');
        if (!current_user_can($mediaCapability)) {
            wp_die(esc_html__('You do not have permission to access Dropbox library.', 'storage-for-edd-via-dropbox'));
        }

        // Check nonce for GET requests with parameters
        if (!empty($_GET) && (isset($_GET['path']) || isset($_GET['_wpnonce']))) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'media-form')) {
                wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-dropbox'));
            }
        }

        if (!empty($_POST)) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'media-form')) {
                wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-dropbox'));
            }

            $error = media_upload_form_handler();
            if (is_string($error)) {
                return $error;
            }
        }
        wp_iframe(array($this, 'renderLibraryTab'));
    }

    /**
     * Render Dropbox Library tab content
     */
    public function renderLibraryTab()
    {
        wp_enqueue_style('media');
        wp_enqueue_style('dbxe-media-library');
        wp_enqueue_style('dbxe-media-container');
        wp_enqueue_style('dbxe-upload');
        wp_enqueue_script('dbxe-media-library');
        wp_enqueue_script('dbxe-upload');

        $path = $this->getPath();

        // Check if Dropbox is connected
        if (!$this->config->isConnected()) {
?>
            <div id="media-items" class="dbxe-media-container">
                <h3 class="media-title"><?php esc_html_e('Dropbox File Browser', 'storage-for-edd-via-dropbox'); ?></h3>

                <div class="dbxe-notice warning">
                    <h4><?php esc_html_e('Dropbox not connected', 'storage-for-edd-via-dropbox'); ?></h4>
                    <p><?php esc_html_e('Please connect to Dropbox in the plugin settings before browsing files.', 'storage-for-edd-via-dropbox'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=download&page=edd-settings&tab=extensions&section=dbxe-settings')); ?>" class="button-primary">
                            <?php esc_html_e('Configure Dropbox Settings', 'storage-for-edd-via-dropbox'); ?>
                        </a>
                    </p>
                </div>
            </div>
        <?php
            return;
        }

        // Use default folder from settings if no path specified in URL
        if (empty($path)) {
            $path = $this->config->getSelectedFolder();
        }

        // Try to get files
        try {
            $files = $this->client->listFiles($path);
            $connection_error = false;
        } catch (Exception $e) {
            $files = [];
            $connection_error = true;
            $this->config->debug('Dropbox connection error: ' . $e->getMessage());
        }
        ?>

        <?php
        // Calculate back URL for header if in subfolder
        $back_url = '';
        if (!empty($path)) {
            $parent_path = dirname($path);
            $parent_path = ($parent_path === '/' || $parent_path === '.') ? '' : $parent_path;
            // Remove success parameters to prevent notice from showing after back navigation
            $back_url = remove_query_arg(array('dbxe_success', 'dbxe_filename', 'error'));
            $back_url = add_query_arg(array(
                'path' => $parent_path,
                '_wpnonce' => wp_create_nonce('media-form')
            ), $back_url);
        }
        ?>
        <div style="width: inherit;" id="media-items">
            <div class="dbxe-header-row">
                <h3 class="media-title"><?php esc_html_e('Select a file from Dropbox', 'storage-for-edd-via-dropbox'); ?></h3>
                <div class="dbxe-header-buttons">
                    <button type="button" class="button button-primary" id="dbxe-toggle-upload">
                        <?php esc_html_e('Upload File', 'storage-for-edd-via-dropbox'); ?>
                    </button>
                </div>
            </div>

            <?php if ($connection_error) { ?>
                <div class="dbxe-notice warning">
                    <h4><?php esc_html_e('Connection Error', 'storage-for-edd-via-dropbox'); ?></h4>
                    <p><?php esc_html_e('Unable to connect to Dropbox.', 'storage-for-edd-via-dropbox'); ?></p>
                    <p><?php esc_html_e('Please check your Dropbox configuration settings and try again.', 'storage-for-edd-via-dropbox'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=download&page=edd-settings&tab=extensions&section=dbxe-settings')); ?>" class="button-primary">
                            <?php esc_html_e('Check Settings', 'storage-for-edd-via-dropbox'); ?>
                        </a>
                    </p>
                </div>
            <?php } elseif (!$connection_error) { ?>

                <div class="dbxe-breadcrumb-nav">
                    <div class="dbxe-nav-group">
                        <?php if (!empty($back_url)) { ?>
                            <a href="<?php echo esc_url($back_url); ?>" class="dbxe-nav-back" title="<?php esc_attr_e('Go Back', 'storage-for-edd-via-dropbox'); ?>">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                            </a>
                        <?php } else { ?>
                            <span class="dbxe-nav-back disabled">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                            </span>
                        <?php } ?>

                        <div class="dbxe-breadcrumbs">
                            <?php
                            if (!empty($path)) {
                                // Build breadcrumb navigation
                                $path_parts = explode('/', trim($path, '/'));
                                $breadcrumb_path = '';
                                $breadcrumb_links = array();

                                // Root link
                                $root_url = remove_query_arg(array('path', 'dbxe_success', 'dbxe_filename', 'error'));
                                $root_url = add_query_arg(array('_wpnonce' => wp_create_nonce('media-form')), $root_url);
                                $breadcrumb_links[] = '<a href="' . esc_url($root_url) . '">' . esc_html__('Home', 'storage-for-edd-via-dropbox') . '</a>';

                                // Build path links
                                foreach ($path_parts as $index => $part) {
                                    $breadcrumb_path .= '/' . $part;
                                    if ($index === count($path_parts) - 1) {
                                        // Current folder - not a link
                                        $breadcrumb_links[] = '<span class="current">' . esc_html($part) . '</span>';
                                    } else {
                                        // Parent folder - make it a link
                                        $folder_url = remove_query_arg(array('dbxe_success', 'dbxe_filename', 'error'));
                                        $folder_url = add_query_arg(array(
                                            'path' => $breadcrumb_path,
                                            '_wpnonce' => wp_create_nonce('media-form')
                                        ), $folder_url);
                                        $breadcrumb_links[] = '<a href="' . esc_url($folder_url) . '">' . esc_html($part) . '</a>';
                                    }
                                }

                                echo wp_kses(implode(' <span class="sep">/</span> ', $breadcrumb_links), array(
                                    'a' => array('href' => array()),
                                    'span' => array('class' => array())
                                ));
                            } else {
                                echo '<span class="current">' . esc_html__('Home', 'storage-for-edd-via-dropbox') . '</span>';
                            }
                            ?>
                        </div>
                    </div>

                    <?php if (!empty($files)) { ?>
                        <div class="dbxe-search-inline">
                            <input type="search"
                                id="dbxe-file-search"
                                class="dbxe-search-input"
                                placeholder="<?php esc_attr_e('Search files...', 'storage-for-edd-via-dropbox'); ?>">
                        </div>
                    <?php } ?>
                </div>



                <?php
                // Upload form integrated into Library
                $successFlag = filter_input(INPUT_GET, 'dbxe_success', FILTER_SANITIZE_NUMBER_INT);
                $errorMsg = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                if ($errorMsg) {
                    $this->config->debug('Upload error: ' . $errorMsg);
                ?>
                    <div class="edd_errors dbxe-notice warning">
                        <h4><?php esc_html_e('Error', 'storage-for-edd-via-dropbox'); ?></h4>
                        <p class="edd_error"><?php esc_html_e('An error occurred during the upload process. Please try again.', 'storage-for-edd-via-dropbox'); ?></p>
                    </div>
                <?php
                }

                if (!empty($successFlag) && '1' == $successFlag) {
                    $savedPathAndFilename = filter_input(INPUT_GET, 'dbxe_filename', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $savedPathAndFilename = sanitize_text_field($savedPathAndFilename);
                    $lastSlashPos = strrpos($savedPathAndFilename, '/');
                    $savedFilename = $lastSlashPos !== false ? substr($savedPathAndFilename, $lastSlashPos + 1) : $savedPathAndFilename;
                ?>
                    <div class="edd_errors dbxe-notice success">
                        <h4><?php esc_html_e('Upload Successful', 'storage-for-edd-via-dropbox'); ?></h4>
                        <p class="edd_success">
                            <?php
                            // translators: %s: File name.
                            printf(esc_html__('File %s uploaded successfully!', 'storage-for-edd-via-dropbox'), '<strong>' . esc_html($savedFilename) . '</strong>');
                            ?>
                        </p>
                        <p>
                            <a href="javascript:void(0)"
                                id="dbxe_save_link"
                                class="button-primary"
                                data-dbxe-fn="<?php echo esc_attr($savedFilename); ?>"
                                data-dbxe-path="<?php echo esc_attr(ltrim($savedPathAndFilename, '/')); ?>">
                                <?php esc_html_e('Use this file in your Download', 'storage-for-edd-via-dropbox'); ?>
                            </a>
                        </p>
                    </div>
                <?php
                }
                ?>
                <!-- Upload Form (Hidden by default) -->
                <form enctype="multipart/form-data" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dbxe-upload-form" id="dbxe-upload-section" style="display: none;">
                    <?php wp_nonce_field('dbxe_upload', 'dbxe_nonce'); ?>
                    <input type="hidden" name="action" value="dbxe_upload" />
                    <div class="upload-field">
                        <input type="file"
                            name="dbxe_file"
                            accept=".zip,.rar,.7z,.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif" />
                    </div>
                    <p class="description">
                        <?php
                        // translators: %s: Maximum upload file size.
                        printf(esc_html__('Maximum upload file size: %s', 'storage-for-edd-via-dropbox'), esc_html(size_format(wp_max_upload_size())));
                        ?>
                    </p>
                    <input type="submit"
                        class="button-primary"
                        value="<?php esc_attr_e('Upload', 'storage-for-edd-via-dropbox'); ?>" />
                    <input type="hidden" name="dbxe_path" value="<?php echo esc_attr($path); ?>" />
                </form>

                <?php if (is_array($files) && !empty($files)) { ?>


                    <!-- File Display Table -->
                    <table class="wp-list-table widefat fixed dbxe-files-table">
                        <thead>
                            <tr>
                                <th class="column-primary" style="width: 40%;"><?php esc_html_e('File Name', 'storage-for-edd-via-dropbox'); ?></th>
                                <th class="column-size" style="width: 20%;"><?php esc_html_e('File Size', 'storage-for-edd-via-dropbox'); ?></th>
                                <th class="column-date" style="width: 25%;"><?php esc_html_e('Last Modified', 'storage-for-edd-via-dropbox'); ?></th>
                                <th class="column-actions" style="width: 15%;"><?php esc_html_e('Actions', 'storage-for-edd-via-dropbox'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Sort: folders first, then files
                            usort($files, function ($a, $b) {
                                if ($a['is_folder'] && !$b['is_folder']) return -1;
                                if (!$a['is_folder'] && $b['is_folder']) return 1;
                                return strcasecmp($a['name'], $b['name']);
                            });

                            foreach ($files as $file) {
                                // Handle folders
                                if ($file['is_folder']) {
                                    $folder_url = add_query_arg(array(
                                        'path' => $file['path'],
                                        '_wpnonce' => wp_create_nonce('media-form')
                                    ));
                            ?>
                                    <tr class="dbxe-folder-row">
                                        <td class="column-primary" data-label="<?php esc_attr_e('Folder Name', 'storage-for-edd-via-dropbox'); ?>">
                                            <a href="<?php echo esc_url($folder_url); ?>" class="folder-link">
                                                <span class="dashicons dashicons-category"></span>
                                                <span class="file-name"><?php echo esc_html($file['name']); ?></span>
                                            </a>
                                        </td>
                                        <td class="column-size">—</td>
                                        <td class="column-date">—</td>
                                        <td class="column-actions">
                                            <a href="<?php echo esc_url($folder_url); ?>" class="button-secondary button-small">
                                                <?php esc_html_e('Open', 'storage-for-edd-via-dropbox'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php
                                    continue;
                                }

                                // Handle files
                                $file_size = $this->formatFileSize($file['size']);
                                $last_modified = !empty($file['modified']) ? $this->formatHumanDate($file['modified']) : '—';
                                ?>
                                <tr>
                                    <td class="column-primary" data-label="<?php esc_attr_e('File Name', 'storage-for-edd-via-dropbox'); ?>">
                                        <div class="dbxe-file-display">
                                            <span class="file-name"><?php echo esc_html($file['name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="column-size" data-label="<?php esc_attr_e('File Size', 'storage-for-edd-via-dropbox'); ?>">
                                        <span class="file-size"><?php echo esc_html($file_size); ?></span>
                                    </td>
                                    <td class="column-date" data-label="<?php esc_attr_e('Last Modified', 'storage-for-edd-via-dropbox'); ?>">
                                        <span class="file-date"><?php echo esc_html($last_modified); ?></span>
                                    </td>
                                    <td class="column-actions" data-label="<?php esc_attr_e('Actions', 'storage-for-edd-via-dropbox'); ?>">
                                        <a class="save-dbxe-file button-secondary button-small"
                                            href="javascript:void(0)"
                                            data-dbxe-filename="<?php echo esc_attr($file['name']); ?>"
                                            data-dbxe-link="<?php echo esc_attr(ltrim($file['path'], '/')); ?>">
                                            <?php esc_html_e('Select File', 'storage-for-edd-via-dropbox'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } else { ?>
                    <div class="dbxe-notice info" style="margin-top: 15px;">
                        <p><?php esc_html_e('This folder is empty. Use the upload form above to add files.', 'storage-for-edd-via-dropbox'); ?></p>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    <?php
    }




    /**
     * Get current path from GET param
     * @return string
     */
    private function getPath()
    {
        $mediaCapability = apply_filters('dbxe_media_access_cap', 'edit_products');
        if (!current_user_can($mediaCapability)) {
            return '';
        }

        if (!empty($_GET['path'])) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'media-form')) {
                wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-dropbox'));
            }
        }

        // Security: Nonce verified in lines 399-403 when path is present.
        // Permission: current_user_can() verified at method start (lines 394-397).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above (lines 399-403) before accessing path.
        $path = !empty($_GET['path']) ? sanitize_text_field(wp_unslash($_GET['path'])) : '';

        // Prevent directory traversal
        if (strpos($path, '..') !== false) {
            return '';
        }

        return $path;
    }

    /**
     * Format file size in human readable format
     * @param int $size
     * @return string
     */
    private function formatFileSize($size)
    {
        if ($size == 0) return '0 B';

        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $power = floor(log($size, 1024));

        return round($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Format date in human readable format
     * @param string $date
     * @return string
     */
    private function formatHumanDate($date)
    {
        try {
            $timestamp = strtotime($date);
            if ($timestamp) {
                return date_i18n('j F Y', $timestamp);
            }
        } catch (Exception $e) {
            // Ignore date formatting errors
        }
        return $date;
    }

    /**
     * Enqueue CSS styles and JS scripts
     */
    public function enqueueStyles()
    {
        // Register styles
        wp_register_style('dbxe-media-library', DBXE_PLUGIN_URL . 'assets/css/dropbox-media-library.css', array(), DBXE_VERSION);
        wp_register_style('dbxe-upload', DBXE_PLUGIN_URL . 'assets/css/dropbox-upload.css', array(), DBXE_VERSION);
        wp_register_style('dbxe-media-container', DBXE_PLUGIN_URL . 'assets/css/dropbox-media-container.css', array(), DBXE_VERSION);
        wp_register_style('dbxe-modal', DBXE_PLUGIN_URL . 'assets/css/dropbox-modal.css', array('dashicons'), DBXE_VERSION);
        wp_register_style('dbxe-browse-button', DBXE_PLUGIN_URL . 'assets/css/dropbox-browse-button.css', array(), DBXE_VERSION);

        // Register scripts
        wp_register_script('dbxe-media-library', DBXE_PLUGIN_URL . 'assets/js/dropbox-media-library.js', array('jquery'), DBXE_VERSION, true);
        wp_register_script('dbxe-upload', DBXE_PLUGIN_URL . 'assets/js/dropbox-upload.js', array('jquery'), DBXE_VERSION, true);
        wp_register_script('dbxe-modal', DBXE_PLUGIN_URL . 'assets/js/dropbox-modal.js', array('jquery'), DBXE_VERSION, true);
        wp_register_script('dbxe-browse-button', DBXE_PLUGIN_URL . 'assets/js/dropbox-browse-button.js', array('jquery', 'dbxe-modal'), DBXE_VERSION, true);

        // Localize scripts
        wp_localize_script('dbxe-media-library', 'dbxe_i18n', array(
            'file_selected_success' => esc_html__('File selected successfully!', 'storage-for-edd-via-dropbox'),
            'file_selected_error' => esc_html__('Error selecting file. Please try again.', 'storage-for-edd-via-dropbox')
        ));

        wp_add_inline_script('dbxe-media-library', 'var dbxe_url_prefix = "' . esc_js($this->config->getUrlPrefix()) . '";', 'before');

        wp_localize_script('dbxe-upload', 'dbxe_i18n', array(
            'file_size_too_large' => esc_html__('File size too large. Maximum allowed size is', 'storage-for-edd-via-dropbox')
        ));

        wp_add_inline_script('dbxe-upload', 'var dbxe_url_prefix = "' . esc_js($this->config->getUrlPrefix()) . '";', 'before');
        wp_add_inline_script('dbxe-upload', 'var dbxe_max_upload_size = ' . wp_json_encode(wp_max_upload_size()) . ';', 'before');
    }

    /**
     * Render Browse Dropbox button in EDD file row (Server Side)
     */
    public function renderBrowseButton($key, $file, $post_id)
    {
        if (!$this->config->isConnected()) {
            return;
        }

        // Add hidden input to store connection status/check if needed by JS (optional)
    ?>
        <div class="edd-form-group edd-file-dropbox-browse">
            <label class="edd-form-group__label edd-repeatable-row-setting-label">&nbsp;</label>
            <div class="edd-form-group__control">
                <button type="button" class="button dbxe_browse_button">
                    <?php esc_html_e('Browse Dropbox', 'storage-for-edd-via-dropbox'); ?>
                </button>
            </div>
        </div>
<?php
    }

    /**
     * Add Dropbox browse button scripts
     */
    public function printAdminScripts()
    {
        global $pagenow, $typenow;

        // Only on EDD download edit pages
        if (!($pagenow === 'post.php' || $pagenow === 'post-new.php') || $typenow !== 'download') {
            return;
        }

        // Only if connected
        if (!$this->config->isConnected()) {
            return;
        }

        // Enqueue modal assets
        wp_enqueue_style('dbxe-modal');
        wp_enqueue_script('dbxe-modal');

        // Enqueue browse button assets
        wp_enqueue_style('dbxe-browse-button');
        wp_enqueue_script('dbxe-browse-button');

        // Localize script with dynamic data
        $dropbox_url = admin_url('media-upload.php?type=dbxe_lib&tab=dbxe_lib');
        wp_localize_script('dbxe-browse-button', 'dbxe_browse_button', array(
            'modal_url'   => $dropbox_url,
            'modal_title' => __('Dropbox Library', 'storage-for-edd-via-dropbox'),
            'nonce'       => wp_create_nonce('media-form'),
            'url_prefix'  => $this->config->getUrlPrefix()
        ));
    }
}
