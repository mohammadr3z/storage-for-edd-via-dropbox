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

        // Check and sanitize file name
        $filename = '';
        if (isset($_FILES['dbxe_file']['name']) && !empty($_FILES['dbxe_file']['name'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Filename is sanitized using sanitize_file_name
            $filename = $path . sanitize_file_name($_FILES['dbxe_file']['name']);
        } else {
            wp_die(esc_html__('No file selected for upload.', 'storage-for-edd-via-dropbox'), esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
        }

        if (!$this->config->isConnected()) {
            wp_die(esc_html__('Dropbox is not connected. Please connect to Dropbox first.', 'storage-for-edd-via-dropbox'), esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
        }

        try {
            // Read file content securely
            $fileContent = '';
            if (
                isset($_FILES['dbxe_file']['tmp_name']) &&
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is a system path and cannot be sanitized
                is_uploaded_file($_FILES['dbxe_file']['tmp_name']) &&
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is a system path
                is_readable($_FILES['dbxe_file']['tmp_name'])
            ) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is a system path
                $fileContent = file_get_contents($_FILES['dbxe_file']['tmp_name']);
                if ($fileContent === false) {
                    wp_die(esc_html__('Unable to read uploaded file.', 'storage-for-edd-via-dropbox'), esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
                }
            } else {
                wp_die(esc_html__('Invalid file upload.', 'storage-for-edd-via-dropbox'), esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
            }

            // Upload to Dropbox
            $result = $this->client->uploadFile($filename, $fileContent);

            if (!$result) {
                wp_die(esc_html__('Failed to upload file to Dropbox.', 'storage-for-edd-via-dropbox'), esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
            }

            // Get the actual path from Dropbox response
            $uploadedPath = isset($result['path_display']) ? $result['path_display'] : $filename;

            // Create secure redirect URL
            $referer = wp_get_referer();
            if (!$referer) {
                $referer = admin_url('admin.php?page=edd-settings&tab=extensions&section=dbxe-settings');
            }

            $redirectURL = add_query_arg(
                array(
                    'dbxe_success'  => '1',
                    'dbxe_filename' => rawurlencode($uploadedPath),
                ),
                $referer
            );
            wp_safe_redirect(esc_url_raw($redirectURL));
            exit;
        } catch (Exception $e) {
            $this->config->debug('File upload error: ' . $e->getMessage());
            wp_die(esc_html__('An error occurred while attempting to upload your file.', 'storage-for-edd-via-dropbox'), esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
        }
    }

    /**
     * Validate file upload.
     * @return bool
     */
    private function validateUpload()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in performFileUpload() before this method is called.
        // Check for file existence and its components
        if (
            !isset($_FILES['dbxe_file']) ||
            !isset($_FILES['dbxe_file']['name']) ||
            !isset($_FILES['dbxe_file']['tmp_name']) ||
            !isset($_FILES['dbxe_file']['size']) ||
            empty($_FILES['dbxe_file']['name'])
        ) {
            wp_die(esc_html__('Please select a file to upload.', 'storage-for-edd-via-dropbox'), esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
            return false;
        }

        // Check uploaded file security
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- tmp_name is a system path
        if (!is_uploaded_file($_FILES['dbxe_file']['tmp_name'])) {
            wp_die(esc_html__('Invalid file upload.', 'storage-for-edd-via-dropbox'), esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
            return false;
        }

        // Validate file type
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Filename is sanitized using sanitize_file_name
        if (!$this->isAllowedFileType(sanitize_file_name($_FILES['dbxe_file']['name']))) {
            wp_die(esc_html__('File type not allowed. Only safe file types are permitted.', 'storage-for-edd-via-dropbox'), esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
            return false;
        }

        // Validate Content-Type (MIME type)
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- $_FILES array passed to validateFileContentType() where tmp_name is used as system path and name is sanitized via sanitize_file_name() with wp_check_filetype_and_ext() validation.
        if (!$this->validateFileContentType($_FILES['dbxe_file'])) {
            wp_die(esc_html__('File content type validation failed. The file may be corrupted or have an incorrect extension.', 'storage-for-edd-via-dropbox'), esc_html__('Error', 'storage-for-edd-via-dropbox'), array('back_link' => true));
            return false;
        }

        // Check and sanitize file size
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- File size is validated/sanitized using absint
        $fileSize = absint($_FILES['dbxe_file']['size']);
        $maxSize = wp_max_upload_size();
        if ($fileSize > $maxSize || $fileSize <= 0) {
            wp_die(
                // translators: %s: Maximum upload file size.
                sprintf(esc_html__('File size too large. Maximum allowed size is %s', 'storage-for-edd-via-dropbox'), esc_html(size_format($maxSize))),
                esc_html__('Error', 'storage-for-edd-via-dropbox'),
                array('back_link' => true)
            );
            return false;
        }

        // phpcs:enable WordPress.Security.NonceVerification.Missing
        return true;
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
