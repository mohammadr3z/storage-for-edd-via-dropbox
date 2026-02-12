<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dropbox Uploader
 * 
 * Handles file uploads to Dropbox from WordPress admin.
 */
class DBXE_Dropbox_Uploader
{
    private $client;
    private $config;

    public function __construct()
    {
        $this->config = new DBXE_Dropbox_Config();
        $this->client = new DBXE_Dropbox_Client();

        // Register upload handler for admin-post.php
        add_action('admin_post_dbxe_upload', array($this, 'performFileUpload'));

        // Register AJAX upload handler
        add_action('wp_ajax_dbxe_ajax_upload', array($this, 'ajaxUpload'));
    }

    /**
     * Handle file upload to Dropbox.
     */
    public function performFileUpload()
    {
        if (!is_admin()) {
            return;
        }

        // Verify Nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is happening right here
        if (!isset($_POST['dbxe_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dbxe_nonce'])), 'dbxe_upload')) {
            wp_die(esc_html__('Security check failed.', 'storage-for-edd-via-dropbox'), esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
        }

        $uploadCapability = apply_filters('dbxe_upload_cap', 'edit_products');
        if (!current_user_can($uploadCapability)) {
            wp_die(esc_html__('You do not have permission to upload files to Dropbox.', 'storage-for-edd-via-dropbox'));
        }

        if (!$this->validateUpload()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified at top of function
        $path = filter_input(INPUT_POST, 'dbxe_path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($path)) {
            $path = $this->config->getSelectedFolder();
        }
        if (!empty($path) && substr($path, -1) !== '/') {
            $path .= '/';
        }

        try {
            // Processing upload
            $path_display = $this->processUpload($_FILES['dbxe_file'], $path);

            // Create secure redirect URL
            $referer = wp_get_referer();
            if (!$referer) {
                $referer = admin_url('admin.php?page=edd-settings&tab=extensions&section=dbxe-settings');
            }

            $redirectURL = add_query_arg(
                array(
                    'dbxe_success'  => '1',
                    'dbxe_filename' => rawurlencode($path_display),
                ),
                $referer
            );
            wp_safe_redirect(esc_url_raw($redirectURL));
            exit;
        } catch (Exception $e) {
            $this->config->debug('File upload error: ' . $e->getMessage());
            wp_die(esc_html__('An error occurred while attempting to upload your file.', 'storage-for-edd-via-dropbox') . ' ' . esc_html($e->getMessage()), esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
        }
    }

    /**
     * Handle AJAX file upload.
     */
    public function ajaxUpload()
    {
        check_ajax_referer('dbxe_upload', 'dbxe_nonce');

        $uploadCapability = apply_filters('dbxe_upload_cap', 'edit_products');
        if (!current_user_can($uploadCapability)) {
            wp_send_json_error(esc_html__('You do not have permission to upload files to Dropbox.', 'storage-for-edd-via-dropbox'));
        }

        // Use checkUploadValidation for better AJAX error handling
        $validation = $this->checkUploadValidation();
        if ($validation !== true) {
            wp_send_json_error($validation);
        }

        $path = isset($_POST['dbxe_path']) ? sanitize_text_field(wp_unslash($_POST['dbxe_path'])) : '';
        if (empty($path)) {
            $path = $this->config->getSelectedFolder();
        }
        if (!empty($path) && substr($path, -1) !== '/') {
            $path .= '/';
        }

        if (!$this->config->isConnected()) {
            wp_send_json_error(esc_html__('Dropbox is not connected.', 'storage-for-edd-via-dropbox'));
        }

        try {
            $path_display = $this->processUpload($_FILES['dbxe_file'], $path);

            // Return success with file info
            wp_send_json_success(array(
                'message' => esc_html__('File uploaded successfully!', 'storage-for-edd-via-dropbox'),
                'filename' => basename($path_display),
                'path' => $path_display,
                // Ensure data keys match what JS expects
                'dbxe_link' => ltrim($path_display, '/')
            ));
        } catch (Exception $e) {
            $this->config->debug('AJAX upload error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Core upload processing logic
     * 
     * @param array $file_array $_FILES item
     * @param string $path Target folder path
     * @return string Uploaded file path (display path)
     * @throws Exception
     */
    private function processUpload($file_array, $path)
    {
        // Check and sanitize file name
        $filename = '';
        if (isset($file_array['name']) && !empty($file_array['name'])) {
            $filename = $path . sanitize_file_name($file_array['name']);
        } else {
            throw new Exception(esc_html__('No file selected.', 'storage-for-edd-via-dropbox'));
        }

        // Open stream for upload
        $stream = fopen($file_array['tmp_name'], 'r');
        if (!$stream) {
            throw new Exception(esc_html__('Unable to open uploaded file.', 'storage-for-edd-via-dropbox'));
        }

        try {
            // Upload to Dropbox
            $result = $this->client->uploadFile($filename, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (!$result) {
            throw new Exception(esc_html__('Failed to upload file to Dropbox.', 'storage-for-edd-via-dropbox'));
        }

        // Get the actual path from Dropbox response
        return isset($result['path_display']) ? $result['path_display'] : $filename;
    }

    /**
     * Helper to return validation result without dying (for AJAX)
     * @return bool|string Returns true on success, or error message string on failure
     */
    private function checkUploadValidation()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in calling methods before this is called.
        // Check for file existence and its components
        if (
            !isset($_FILES['dbxe_file']) ||
            !isset($_FILES['dbxe_file']['name']) ||
            !isset($_FILES['dbxe_file']['tmp_name']) ||
            !isset($_FILES['dbxe_file']['size']) ||
            empty($_FILES['dbxe_file']['name'])
        ) {
            return esc_html__('Please select a file to upload.', 'storage-for-edd-via-dropbox');
        }

        // Check uploaded file security
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- tmp_name is a system path
        if (!is_uploaded_file($_FILES['dbxe_file']['tmp_name'])) {
            return esc_html__('Invalid file upload.', 'storage-for-edd-via-dropbox');
        }

        // Validate file type
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Filename is sanitized using sanitize_file_name
        if (!$this->isAllowedFileType(sanitize_file_name($_FILES['dbxe_file']['name']))) {
            return esc_html__('File type not allowed. Only safe file types are permitted.', 'storage-for-edd-via-dropbox');
        }

        // Validate Content-Type (MIME type)
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Validated and sanitized inside validateFileContentType() method.
        if (!$this->validateFileContentType($_FILES['dbxe_file'])) {
            return esc_html__('File content type validation failed. The file may be corrupted or have an incorrect extension.', 'storage-for-edd-via-dropbox');
        }

        // Check and sanitize file size
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- File size is validated/sanitized using absint
        $fileSize = absint($_FILES['dbxe_file']['size']);
        $maxSize = wp_max_upload_size();
        if ($fileSize > $maxSize || $fileSize <= 0) {
            return sprintf(
                // translators: %s: Maximum upload file size.
                esc_html__('File size too large. Maximum allowed size is %s', 'storage-for-edd-via-dropbox'),
                esc_html(size_format($maxSize))
            );
        }

        // phpcs:enable WordPress.Security.NonceVerification.Missing
        return true;
    }

    /**
     * Validate file upload (legacy wrapper for non-AJAX calls).
     * @return bool
     */
    private function validateUpload()
    {
        $result = $this->checkUploadValidation();
        if ($result === true) {
            return true;
        }

        wp_die($result, esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
        return false;
    }

    /**
     * Check if file type is allowed (simple extension-based validation)
     * @param string $filename
     * @return bool
     */
    private function isAllowedFileType($filename)
    {
        // Get file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Allowed safe extensions for digital products
        $allowedExtensions = array(
            'zip',
            'rar',
            '7z',
            'tar',
            'gz',
            'pdf',
            'doc',
            'docx',
            'txt',
            'rtf',
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'mp3',
            'wav',
            'ogg',
            'flac',
            'm4a',
            'mp4',
            'avi',
            'mov',
            'wmv',
            'flv',
            'webm',
            'epub',
            'mobi',
            'azw',
            'azw3',
            'xls',
            'xlsx',
            'csv',
            'ppt',
            'pptx',
            'css',
            'js',
            'json',
            'xml'
        );

        // Check if extension is in allowed list
        if (!in_array($extension, $allowedExtensions, true)) {
            return false;
        }

        // Block dangerous file patterns
        $dangerousPatterns = array(
            '.php',
            '.phtml',
            '.asp',
            '.aspx',
            '.jsp',
            '.cgi',
            '.pl',
            '.py',
            '.exe',
            '.com',
            '.bat',
            '.cmd',
            '.scr',
            '.vbs',
            '.jar',
            '.sh',
            '.bash',
            '.zsh',
            '.fish',
            '.htaccess',
            '.htpasswd'
        );

        $lowerFilename = strtolower($filename);
        foreach ($dangerousPatterns as $pattern) {
            if (strpos($lowerFilename, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate file content type (MIME type) matches the file extension
     * @param array $file The uploaded file array from $_FILES
     * @return bool
     */
    private function validateFileContentType($file)
    {
        // Ensure we have the required file information
        if (!isset($file['tmp_name']) || !isset($file['name'])) {
            return false;
        }

        // Use WordPress's built-in function to check file type and extension
        $filetype = wp_check_filetype_and_ext($file['tmp_name'], sanitize_file_name($file['name']));

        // Check if the file type was detected
        if (!$filetype || !isset($filetype['ext']) || !isset($filetype['type'])) {
            return false;
        }

        // If extension or type is false, the file failed validation
        if (false === $filetype['ext'] || false === $filetype['type']) {
            return false;
        }

        // Additional check: ensure the detected extension matches what we expect
        $actualExtension = strtolower(pathinfo(sanitize_file_name($file['name']), PATHINFO_EXTENSION));
        if ($filetype['ext'] !== $actualExtension) {
            return false;
        }

        // Validate against allowed MIME types
        $allowedMimeTypes = array(
            // Archives
            'application/zip',
            'application/x-zip-compressed',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/x-tar',
            'application/gzip',
            'application/x-gzip',
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'application/rtf',
            // Images
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            // Audio
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/ogg',
            'audio/flac',
            'audio/x-m4a',
            // Video
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'video/x-flv',
            'video/webm',
            // E-books
            'application/epub+zip',
            'application/x-mobipocket-ebook',
            // Spreadsheets
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            // Presentations
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // Web files
            'text/css',
            'application/javascript',
            'text/javascript',
            'application/json',
            'application/xml',
            'text/xml',
        );

        // Apply filter to allow customization
        $allowedMimeTypes = apply_filters('dbxe_allowed_mime_types', $allowedMimeTypes);

        // Check if the detected MIME type is in our allowed list
        if (!in_array($filetype['type'], $allowedMimeTypes, true)) {
            return false;
        }

        return true;
    }
}
