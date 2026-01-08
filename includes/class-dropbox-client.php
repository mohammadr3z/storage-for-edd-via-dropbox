<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dropbox API Client
 * 
 * Handles all Dropbox API communications including OAuth2 authentication,
 * file listing, uploads, and temporary link generation.
 */
class DBXE_Dropbox_Client
{
    private $httpClient = null;
    private $config;

    // Dropbox API endpoints
    const AUTH_URL = 'https://www.dropbox.com/oauth2/authorize';
    const TOKEN_URL = 'https://api.dropboxapi.com/oauth2/token';
    const API_URL = 'https://api.dropboxapi.com/2';
    const CONTENT_URL = 'https://content.dropboxapi.com/2';

    public function __construct()
    {
        $this->config = new DBXE_Dropbox_Config();
    }

    /**
     * Get HTTP client instance (Guzzle)
     * @return GuzzleHttp\Client|null
     */
    public function getClient()
    {
        if ($this->httpClient !== null) {
            return $this->httpClient;
        }

        try {
            $clientOptions = [
                'timeout' => 10,
                'connect_timeout' => 5,
                'verify' => true,
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => 'storage-for-edd-via-dropbox/1.0'
                ]
            ];

            $this->httpClient = new \GuzzleHttp\Client($clientOptions);
            return $this->httpClient;
        } catch (Exception $e) {
            $this->config->debug('Error creating HTTP client: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate OAuth2 authorization URL
     * 
     * @param string $redirect_uri The callback URL
     * @return string Authorization URL
     */
    public function getAuthorizationUrl($redirect_uri)
    {
        $params = [
            'client_id' => $this->config->getAppKey(),
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'token_access_type' => 'offline', // Get refresh token
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     * 
     * @param string $code Authorization code from OAuth callback
     * @param string $redirect_uri The callback URL (must match the one used for authorization)
     * @return array|false Token data or false on failure
     */
    public function exchangeCodeForToken($code, $redirect_uri)
    {
        $client = $this->getClient();
        if (!$client) {
            return false;
        }

        try {
            $response = $client->request('POST', self::TOKEN_URL, [
                'form_params' => [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirect_uri,
                    'client_id' => $this->config->getAppKey(),
                    'client_secret' => $this->config->getAppSecret(),
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->config->debug('Token exchange failed with status: ' . $statusCode);
                return false;
            }

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['access_token'])) {
                return [
                    'access_token' => $body['access_token'],
                    'refresh_token' => isset($body['refresh_token']) ? $body['refresh_token'] : '',
                    'expires_in' => isset($body['expires_in']) ? $body['expires_in'] : 14400,
                ];
            }

            return false;
        } catch (Exception $e) {
            $this->config->debug('Token exchange error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Refresh the access token using refresh token
     * 
     * @return bool Success status
     */
    public function refreshAccessToken()
    {
        $client = $this->getClient();
        $refresh_token = $this->config->getRefreshToken();

        if (!$client || empty($refresh_token)) {
            return false;
        }

        try {
            $response = $client->request('POST', self::TOKEN_URL, [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refresh_token,
                    'client_id' => $this->config->getAppKey(),
                    'client_secret' => $this->config->getAppSecret(),
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->config->debug('Token refresh failed with status: ' . $statusCode);
                return false;
            }

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['access_token'])) {
                $this->config->saveTokens(
                    $body['access_token'],
                    '', // Refresh token doesn't change
                    isset($body['expires_in']) ? $body['expires_in'] : 14400
                );
                return true;
            }

            return false;
        } catch (Exception $e) {
            $this->config->debug('Token refresh error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get valid access token (refreshing if needed)
     * Like official EDD plugin: always validate before use
     * 
     * @return string|false Access token or false on failure
     */
    public function getValidAccessToken()
    {
        // Always get fresh token from config
        $access_token = $this->config->getAccessToken();

        // If no token exists, fail
        if (empty($access_token)) {
            return false;
        }

        // Check if token needs refresh (transient expired)
        if ($this->config->isTokenExpired()) {
            if (!$this->refreshAccessToken()) {
                return false;
            }
            // Get the new token after refresh
            $access_token = $this->config->getAccessToken();
        }

        return $access_token;
    }

    /**
     * Make an authenticated API request
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $baseUrl Base URL to use
     * @return array|false Response data or false on failure
     */
    private function apiRequest($endpoint, $data = [], $baseUrl = null)
    {
        $client = $this->getClient();
        $access_token = $this->getValidAccessToken();

        if (!$client || !$access_token) {
            return false;
        }

        if ($baseUrl === null) {
            $baseUrl = self::API_URL;
        }

        try {
            $response = $client->request('POST', $baseUrl . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($data),
                'timeout' => 10, // 10 second timeout to prevent long loading
                'connect_timeout' => 5 // 5 second connection timeout
            ]);

            $statusCode = $response->getStatusCode();

            // Handle token expiration
            if ($statusCode === 401) {
                if ($this->refreshAccessToken()) {
                    // Retry request with new token
                    return $this->apiRequest($endpoint, $data, $baseUrl);
                }
                return false;
            }

            // Handle permission denied (missing scopes)
            if ($statusCode === 403) {
                $this->config->debug('API permission denied: ' . $endpoint . ' - Check Dropbox app permissions');
                return false;
            }

            if ($statusCode !== 200) {
                $this->config->debug('API request failed: ' . $endpoint . ' - Status: ' . $statusCode);
                return false;
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            $this->config->debug('API request error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * List files in a Dropbox folder
     * 
     * @param string $path Folder path (empty string for root)
     * @return array List of files
     */
    public function listFiles($path = '')
    {
        $files = [];

        if (!$this->config->isConnected()) {
            return $files;
        }

        // Dropbox uses empty string for root, or path starting with /
        if (!empty($path) && strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        $data = [
            'path' => empty($path) ? '' : $path,
            'recursive' => false,
            'include_deleted' => false,
            'include_has_explicit_shared_members' => false,
            'include_mounted_folders' => true,
            'include_non_downloadable_files' => false
        ];

        $response = $this->apiRequest('/files/list_folder', $data);

        // Debug: log the API response - Removed for performance
        // $this->config->debug('listFiles response for path "' . $path . '": ' . print_r($response, true));

        if (!$response || !isset($response['entries'])) {
            $this->config->debug('No entries in response or response is false');
            return $files;
        }

        foreach ($response['entries'] as $entry) {
            if ($entry['.tag'] === 'file') {
                $files[] = [
                    'name' => $entry['name'],
                    'path' => $entry['path_display'],
                    'path_lower' => $entry['path_lower'],
                    'size' => isset($entry['size']) ? $entry['size'] : 0,
                    'modified' => isset($entry['client_modified']) ? $entry['client_modified'] : '',
                    'is_folder' => false
                ];
            } elseif ($entry['.tag'] === 'folder') {
                $files[] = [
                    'name' => $entry['name'],
                    'path' => $entry['path_display'],
                    'path_lower' => $entry['path_lower'],
                    'size' => 0,
                    'modified' => '',
                    'is_folder' => true
                ];
            }
        }

        // Handle pagination if there are more files
        while (isset($response['has_more']) && $response['has_more'] && isset($response['cursor'])) {
            $response = $this->apiRequest('/files/list_folder/continue', [
                'cursor' => $response['cursor']
            ]);

            if ($response && isset($response['entries'])) {
                foreach ($response['entries'] as $entry) {
                    if ($entry['.tag'] === 'file') {
                        $files[] = [
                            'name' => $entry['name'],
                            'path' => $entry['path_display'],
                            'path_lower' => $entry['path_lower'],
                            'size' => isset($entry['size']) ? $entry['size'] : 0,
                            'modified' => isset($entry['client_modified']) ? $entry['client_modified'] : '',
                            'is_folder' => false
                        ];
                    } elseif ($entry['.tag'] === 'folder') {
                        $files[] = [
                            'name' => $entry['name'],
                            'path' => $entry['path_display'],
                            'path_lower' => $entry['path_lower'],
                            'size' => 0,
                            'modified' => '',
                            'is_folder' => true
                        ];
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Get list of folders for folder selection dropdown
     * Uses a lightweight request that only fetches top-level folders
     * 
     * @param string $path Starting path
     * @return array List of folders (max 50)
     */
    public function listFolders($path = '')
    {
        $folders = [];
        $maxFolders = 50;

        if (!$this->config->isConnected()) {
            return $folders;
        }

        // Dropbox uses empty string for root, or path starting with /
        if (!empty($path) && strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        $data = [
            'path' => empty($path) ? '' : $path,
            'recursive' => false,
            'include_deleted' => false,
            'include_has_explicit_shared_members' => false,
            'include_mounted_folders' => true,
            'include_non_downloadable_files' => false,
            // Note: Dropbox API doesn't support filtering by type, so we fetch items
            // and filter folders client-side. Using pagination to get all folders.
            'limit' => 50
        ];

        $response = $this->apiRequest('/files/list_folder', $data);

        if (!$response || !isset($response['entries'])) {
            $this->config->debug('listFolders: No response or entries');
            return $folders;
        }

        // Only get folders, ignore files
        foreach ($response['entries'] as $entry) {
            if ($entry['.tag'] === 'folder') {
                $folders[$entry['path_display']] = $entry['path_display'];
                if (count($folders) >= $maxFolders) {
                    return $folders;
                }
            }
        }

        // Paginate if there are more results and we haven't hit our folder limit
        while (isset($response['has_more']) && $response['has_more'] && count($folders) < $maxFolders) {
            $response = $this->apiRequest('/files/list_folder/continue', [
                'cursor' => $response['cursor']
            ]);

            if ($response && isset($response['entries'])) {
                foreach ($response['entries'] as $entry) {
                    if ($entry['.tag'] === 'folder') {
                        $folders[$entry['path_display']] = $entry['path_display'];
                        if (count($folders) >= $maxFolders) {
                            return $folders;
                        }
                    }
                }
            } else {
                break;
            }
        }

        return $folders;
    }

    /**
     * Get a temporary download link for a file
     * 
     * @param string $path File path in Dropbox
     * @return string|false Temporary download URL or false on failure
     */
    public function getTemporaryLink($path)
    {
        // Ensure path starts with /
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        $response = $this->apiRequest('/files/get_temporary_link', [
            'path' => $path
        ]);

        if ($response && isset($response['link'])) {
            return $response['link'];
        }

        return false;
    }

    /**
     * Upload a file to Dropbox
     * 
     * @param string $path Destination path in Dropbox
     * @param string $content File content
     * @return array|false File metadata or false on failure
     */
    public function uploadFile($path, $content)
    {
        $client = $this->getClient();
        $access_token = $this->getValidAccessToken();

        if (!$client || !$access_token) {
            return false;
        }

        // Ensure path starts with /
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        try {
            $response = $client->request('POST', self::CONTENT_URL . '/files/upload', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/octet-stream',
                    'Dropbox-API-Arg' => wp_json_encode([
                        'path' => $path,
                        'mode' => 'add',
                        'autorename' => true,
                        'mute' => false,
                        'strict_conflict' => false
                    ])
                ],
                'body' => $content
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->config->debug('Upload failed with status: ' . $statusCode);
                return false;
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            $this->config->debug('Upload error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current account info
     * 
     * @return array|false Account info or false on failure
     */
    public function getAccountInfo()
    {
        $response = $this->apiRequest('/users/get_current_account', null);
        return $response;
    }
}
