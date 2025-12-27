# WordPress Installation Instructions

This repository is now structured as a **complete WordPress plugin** ready for installation.

## Installation Methods

### Method 1: Direct Clone (Recommended for Development)

Clone this repository directly into your WordPress plugins directory:

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/impact2021/ito-student-management.git
```

Then activate the plugin:
1. Log in to your WordPress admin panel
2. Navigate to **Plugins** > **Installed Plugins**
3. Find "IELTS Membership System" in the list
4. Click **Activate**

### Method 2: Download and Upload

1. Download the repository as a ZIP file from GitHub
2. Extract the contents
3. Upload the entire folder to `/wp-content/plugins/` on your WordPress site
4. Activate the plugin through the WordPress admin panel

## Verification

After installation, WordPress should recognize the plugin. You can verify this by:

1. Going to **Plugins** in WordPress admin
2. Looking for "IELTS Membership System" in the plugins list
3. The plugin should display:
   - **Name:** IELTS Membership System
   - **Description:** Membership and payment system for IELTS preparation courses with PayPal and Stripe integration.
   - **Version:** 1.0.0
   - **Author:** IELTStestONLINE

## Plugin Structure

The plugin files are now in the repository root:
```
ito-student-management/           # Plugin root directory
├── ielts-membership-system.php   # Main plugin file (WordPress reads this)
├── includes/                      # Core functionality classes
├── admin/                         # Admin interface
├── templates/                     # Frontend templates
├── assets/                        # CSS, JS, images
├── uninstall.php                  # Cleanup on uninstall
└── README.md                      # Plugin documentation
```

## Troubleshooting

If WordPress doesn't recognize the plugin:

1. **Check file permissions:** Ensure WordPress can read the plugin files
2. **Verify plugin header:** The main file `ielts-membership-system.php` must contain the WordPress plugin header
3. **Clear caches:** Clear any WordPress caching plugins or server caches
4. **Check error logs:** Look for PHP errors in your WordPress debug log

## Next Steps

After activation, refer to the [Installation Guide](INSTALLATION.md) for configuration instructions.
