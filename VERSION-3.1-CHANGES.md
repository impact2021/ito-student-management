# Version 3.1 Changes

## Overview
This release includes UI improvements, enhanced payment flow, and content access protection features.

## What's New in Version 3.1

### 1. Wider Form Layout
- **Registration and Login Forms**: Increased maximum width from 700px to 1500px
- Forms now utilize more screen space while maintaining proper padding
- Better user experience on larger screens
- Still fully responsive on mobile devices

### 2. Stripe Payment Fields on Registration
- **Inline Stripe Payment**: Card payment fields are now visible on the registration form
- Users can see the payment section immediately when registering
- Payment fields are initialized after account information is submitted
- Provides a more transparent registration + payment flow

### 3. Content Access Protection
- **Protected Content Types**: Exercises and sublessons now require login and active membership
- **Smart Detection**: Automatically detects protected content by:
  - Custom post types (exercise, sublesson)
  - URL patterns containing "exercise" or "sublesson"
  - Post slugs matching protected content patterns
- **Flexible Redirect**: Users without access are redirected to a configurable login/registration page
- **Courses and Lessons**: Still viewable without login (as per requirements)
- **Return URL**: Users are redirected back to the content they tried to access after logging in

### 4. Admin Configuration
- **New Setting**: "Protected Content Redirect Page" in admin settings
- **Customizable Redirect**: Choose which page users see when accessing protected content
- **Default Behavior**: Falls back to login page if no custom page is set
- **Use Case**: Allows creation of a combined login/registration landing page

### 5. Version Update
- Version number updated from 3.0.0 to 3.1
- Follows simplified versioning (no third number - e.g., 3.1 instead of 3.1.0)

## Files Changed

### 1. `ielts-membership-system.php`
- Updated version from 3.0.0 to 3.1
- Updated `IELTS_MS_VERSION` constant
- Added `ielts_ms_protect_content()` function for content access protection
- Added hook for content protection in template_redirect

### 2. `assets/css/style.css`
- Changed `.ielts-ms-form-container` max-width from 700px to 1500px

### 3. `assets/js/script.js`
- Simplified Stripe payment section visibility logic
- Removed registration form restriction from showing Stripe fields
- Payment section now shows for all forms when Stripe is selected

### 4. `templates/register-form.php`
- Removed `display: none` from Stripe payment section
- Added helpful message about payment field initialization
- Payment section now visible by default when Stripe is enabled

### 5. `admin/class-admin.php`
- Added `ielts_ms_protected_content_redirect_page_id` to registered settings
- Added UI field for selecting protected content redirect page
- Added save logic for the new redirect page setting

## How Content Protection Works

### Detection Logic
The system checks for protected content in this order:
1. Custom post types: `exercise` or `sublesson`
2. Post slugs containing: "exercise" or "sublesson"
3. URL paths containing: "/exercise" or "/sublesson"

### Access Control
- **Not Logged In**: Redirects to configured redirect page (or login page)
- **Logged In, No Membership**: Redirects to configured redirect page
- **Active Membership**: Full access granted
- **Admins**: Always have full access

### Configuration
1. Go to **Membership > Settings**
2. Find "Protected Content Redirect Page" under Login Settings
3. Select a page (or leave empty for default login page)
4. Users trying to access protected content will be redirected there

## Upgrade Notes

- No database changes required
- Existing functionality is preserved
- New settings have sensible defaults
- Safe to upgrade from version 3.0.0

## Compatibility

- WordPress 5.8+
- PHP 7.2+
- Works with existing Stripe and PayPal configurations
- Compatible with IELTS Course Manager plugin

## Developer Notes

### New Options
```php
// Protected content redirect page ID
get_option('ielts_ms_protected_content_redirect_page_id', 0);
```

### New Function
```php
// Check and redirect for protected content
ielts_ms_protect_content();
```

### Hook
```php
// Runs on template_redirect
add_action('template_redirect', 'ielts_ms_protect_content');
```
