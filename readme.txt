=== Storage for EDD via Dropbox ===
author: mohammadr3z
Contributors: mohammadr3z
Tags: easy-digital-downloads, dropbox, storage, cloud, edd
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.6
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable secure cloud storage and delivery of your digital products through Dropbox for Easy Digital Downloads.

== Description ==

Storage for EDD via Dropbox is a powerful extension for Easy Digital Downloads that allows you to store and deliver your digital products using Dropbox cloud storage. This plugin provides seamless integration with Dropbox's API, featuring OAuth2 authentication and secure temporary download links.


= Key Features =

* **Dropbox Integration**: Store your digital products securely in Dropbox
* **OAuth2 Authentication**: Secure and easy connection to your Dropbox account
* **Temporary Download Links**: Generates secure 4-hour temporary links for downloads
* **Easy File Management**: Upload files directly to Dropbox through WordPress admin
* **Media Library Integration**: Browse and select files from your Dropbox within WordPress
* **Folder Support**: Navigate and organize files in folders
* **Security First**: Built with WordPress security best practices
* **Developer Friendly**: Clean, well-documented code with hooks and filters

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/storage-for-edd-via-dropbox` directory, or install the plugin through the WordPress plugins screen directly.
2. Make sure you have Easy Digital Downloads plugin installed and activated.
3. Run `composer install` in the plugin directory if installing from source (not needed for release versions).
4. Activate the plugin through the 'Plugins' screen in WordPress.
5. Navigate to Downloads > Settings > Extensions > Dropbox Storage to configure the plugin.

== Configuration ==

= Step 1: Create a Dropbox App =

1. Go to [Dropbox Developer Console](https://www.dropbox.com/developers/apps)
2. Click "Create app"
3. Choose "Scoped Access" for API
4. Choose "Full Dropbox" for access type
5. Give your app a unique name
6. Click "Create app"

= Step 2: Configure Your App =

1. In your app settings, go to the "Permissions" tab
2. Enable these permissions:
   * files.metadata.read
   * files.content.read
   * files.content.write
3. Click "Submit" to save permissions

= Step 3: Set OAuth Redirect URI =

1. In your app settings, find "OAuth 2 Redirect URIs"
2. Add this URL: `https://your-site.com/wp-admin/admin-post.php?action=dbxe_oauth_callback`
3. Replace `your-site.com` with your actual domain

= Step 4: Connect in WordPress =

1. Go to Downloads > Settings > Extensions > Dropbox Storage
2. Enter your App Key and App Secret from the Dropbox Developer Console
3. Save settings
4. Click "Connect to Dropbox"
5. Authorize the connection in the Dropbox popup
6. You're connected!

== Usage ==

= Browsing and Selecting Files =

1. When creating or editing a download in Easy Digital Downloads
2. Click on "Upload File" or "Choose File"
3. Select the "Dropbox Library" tab
4. Browse your Dropbox storage using the folder navigation
5. Use the breadcrumb navigation bar to quickly jump to parent folders
6. Use the search box in the header to filter files by name
7. Click "Select" to use an existing file for your download

= Uploading New Files =

1. In the "Dropbox Library" tab, click the "Upload" button in the header row
2. The upload form will appear above the file list
3. Choose your file and click "Upload"
4. After a successful upload, the file URL will be automatically set with the Dropbox prefix
5. Click "Back" to return to the file browser without uploading

== Frequently Asked Questions ==

= How secure are the download links? =

The plugin generates temporary download links that are valid for 4 hours. These links are generated on-demand when a customer purchases your product, ensuring that each download session gets a fresh, time-limited URL.

= Why 4 hours? Can I change the link duration? =

The 4-hour duration is set by Dropbox and cannot be changed. This is different from S3 where you can customize the expiry time. The 4-hour window is actually beneficial for larger files as it gives customers more time to complete their downloads.

= What file types are supported for upload? =

The plugin supports safe file types including:
* Archives: ZIP, RAR, 7Z, TAR, GZ
* Documents: PDF, DOC, DOCX, TXT, RTF, XLS, XLSX, CSV, PPT, PPTX
* Images: JPG, JPEG, PNG, GIF, WEBP
* Audio: MP3, WAV, OGG, FLAC, M4A
* Video: MP4, AVI, MOV, WMV, FLV, WEBM
* E-books: EPUB, MOBI, AZW, AZW3
* Web files: CSS, JS, JSON, XML

Dangerous file types (executables, scripts) are automatically blocked for security.

= Can I customize the URL prefix for Dropbox files? =

Yes, developers can customize the URL prefix using the `dbxe_url_prefix` filter. Add this code to your theme's functions.php:

`
function customize_dropbox_url_prefix($prefix) {
    return 'edd-myprefix://'; // Change to your preferred prefix
}
add_filter('dbxe_url_prefix', 'customize_dropbox_url_prefix');
`

= Can I customize the allowed file types (MIME types)? =

Yes, developers can customize the allowed MIME types using the `dbxe_allowed_mime_types` filter.

== Screenshots ==

1. Admin panel user interface
2. File selection from Dropbox storage section
3. File upload to Dropbox storage interface

== Changelog ==

= 1.0.6 =
* Added: Native search input type with clear ("X") icon support for a cleaner UI.
* Improved: Mobile breadcrumb navigation with path wrapping for long directory names.
* Improved: Reduced separator spacing in breadcrumbs on mobile devices.
* Improved: Media library table styling for more consistent file and folder display.
* Improved: Redesigned folder rows with better icons and refined hover effects.
* Improved: Enhanced mobile responsiveness for the file browser table.
* Fixed: Corrected file name and path display order in the media library.
* Improved: Standardized header row spacing and title font sizes for UI consistency.
* Improved: Enhanced notice detail styling for better error/success message readability.
* Improved: More robust handling of file lists with additional data validation.
* Security: Standardized use of wp_json_encode() for client-side data.
* Improved: Unified root folder label as "Home" across all breadcrumb states for consistent navigation.

= 1.0.4 =
* Added: Breadcrumb navigation in file browser - click any folder in the path to navigate directly.
* Improved: Integrated search functionality directly into the breadcrumb navigation bar for a cleaner UI.
* Improved: Better navigation experience without needing the Back button.
* Improved: Enhanced styling for search inputs and buttons, including compact padding.
* Fixed: RTL layout issues for breadcrumbs and navigation buttons.
* Cleaned: Removed legacy CSS and unused search container elements.

= 1.0.3 =
* Changed: Merged Upload tab into Library tab for a unified experience.
* Improved: Upload form toggles with a button in the header row.
* Improved: Back button moved to header row with new styling (orange for Upload, blue for Back).
* Improved: Success notice no longer persists after navigating back in the media library.
* Improved: Better RTL support for styling and layout.
* Fixed: Preserved folder name spaces during upload sanitization.

= 1.0.2 =
* Removed Show button for App Secret field
* Security improvement for credential visibility

= 1.0.1 =
* Improved OAuth callback URL structure with rewrite rules
* Optimized performance by caching access tokens
* Reduced debug logging for better performance
* Fixed WordPress coding standards compliance for global variables

= 1.0.0 =
* Initial release
* Dropbox OAuth2 integration
* Temporary download link generation
* Media library integration
* File upload functionality
* Admin settings interface
* Security enhancements and validation
* Internationalization support



== External services ==

This plugin connects to Dropbox API to manage files, create download links, and handle authentication.

It sends the necessary authentication tokens and file requests to Dropbox servers. This happens when you browse your Dropbox files in the dashboard, upload files, or when a customer downloads a file.

* **Service**: Dropbox API
* **Used for**: Authentication, file browsing, uploading, and generating download links.
* **Data sent**: OAuth tokens, file metadata, file content (during upload).
* **URLs**:
    * `https://api.dropboxapi.com` (API calls)
    * `https://content.dropboxapi.com` (File transfers)
    * `https://www.dropbox.com` (Authentication)
* **Legal**: [Terms of Service](https://www.dropbox.com/terms), [Privacy Policy](https://www.dropbox.com/privacy)

== Support ==

For support and bug reports, please use the WordPress.org plugin support forum.

If you find this plugin helpful, please consider leaving a review on WordPress.org.

== Other Storage Providers ==

Looking for a different storage provider? Check out our other plugins:

* [Storage for EDD via Box](https://wordpress.org/plugins/storage-for-edd-via-box/) - Use Box for your digital product storage
* [Storage for EDD via OneDrive](https://wordpress.org/plugins/storage-for-edd-via-onedrive/) - Use Microsoft OneDrive for your digital product storage
* [Storage for EDD via S3-Compatible](https://wordpress.org/plugins/storage-for-edd-via-s3-compatible/) - Use S3-compatible services like MinIO, DigitalOcean Spaces, Linode, Wasabi, and more

== Privacy Policy ==

This plugin requires authorization to access your Dropbox account for file storage and retrieval. It does not collect or store any personal data beyond the OAuth tokens needed to maintain the connection. All file storage and delivery is handled through Dropbox's secure infrastructure.
