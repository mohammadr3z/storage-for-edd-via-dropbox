<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dropbox Downloader
 * 
 * Generates temporary download links for EDD downloads stored in Dropbox.
 */
class DBXE_Dropbox_Downloader
{
    private $client;
    private $config;

    public function __construct()
    {
        $this->config = new DBXE_Dropbox_Config();
        $this->client = new DBXE_Dropbox_Client();
    }

    /**
     * Generate a temporary Dropbox URL for download.
     * 
     * This method is hooked to 'edd_requested_file' filter.
     * 
     * @param string $file The original file URL
     * @param array $downloadFiles Array of download files
     * @param string $fileKey The key of the current file
     * @return string The temporary download URL or original file
     */
    public function generateUrl($file, $downloadFiles, $fileKey)
    {
        if (empty($downloadFiles[$fileKey])) {
            return $file;
        }

        $fileData = $downloadFiles[$fileKey];
        $filename = $fileData['file'];

        // Check if this is a Dropbox file
        $urlPrefix = $this->config->getUrlPrefix();
        if (strpos($filename, $urlPrefix) !== 0) {
            return $file;
        }

        // Extract the Dropbox path from the URL
        $path = substr($filename, strlen($urlPrefix));

        if (!$this->config->isConnected()) {
            $this->config->debug('Dropbox not connected for download: ' . $path);
            return $file;
        }

        try {
            // Get temporary link from Dropbox (valid for 4 hours)
            $temporaryLink = $this->client->getTemporaryLink($path);

            if ($temporaryLink) {
                return $temporaryLink;
            }

            $this->config->debug('Failed to get temporary link for: ' . $path);
            return $file;
        } catch (Exception $e) {
            $this->config->debug('Error generating download URL: ' . $e->getMessage());
            return $file;
        }
    }
}
