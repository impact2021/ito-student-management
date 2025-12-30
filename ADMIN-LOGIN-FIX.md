# Admin Login Redirect Fix

## Issue
When administrators logged in through the custom login form, they were being redirected to the student account page instead of the WordPress admin dashboard. This created a confusing loop where admins couldn't easily access the WordPress admin area.

## Solution
Modified the `handle_login()` method in `/includes/class-login-manager.php` to check if the logged-in user has admin capabilities (`manage_options`). 

### Changes Made
- **File**: `includes/class-login-manager.php`
- **Method**: `handle_login()`
- **Line**: 84-87

Added a conditional redirect that checks user capabilities:
- If user has `manage_options` capability (admin): redirect to `/wp-admin/`
- If user is a regular user: redirect to the account page

## Code Change
```php
// Redirect admins to wp-admin, regular users to account page
$redirect_url = user_can($user, 'manage_options') 
    ? admin_url() 
    : get_permalink(get_option('ielts_ms_account_page_id'));

wp_send_json_success(array(
    'message' => 'Login successful',
    'redirect' => $redirect_url
));
```

## Testing Instructions

### Test 1: Admin Login
1. Log out if currently logged in
2. Navigate to the custom login page
3. Login with admin credentials
4. **Expected Result**: Should be redirected to `/wp-admin/` (WordPress admin dashboard)

### Test 2: Regular User Login  
1. Log out if currently logged in
2. Navigate to the custom login page
3. Login with a regular student/member account
4. **Expected Result**: Should be redirected to the membership account page

### Test 3: Existing Shortcode Redirects (Already Protected)
The following existing protections remain in place:
- Login form shortcode (`login_form()` in `class-shortcodes.php`) prevents admins from being redirected if they visit the login page while already logged in
- Register form shortcode (`register_form()` in `class-shortcodes.php`) prevents admins from being redirected if they visit the register page while already logged in
- WordPress login redirect (`redirect_wp_login()` in `class-login-manager.php`) excludes admins from being redirected from `wp-login.php`

## Impact
- **Admins**: Can now successfully login through the custom login form and access the WordPress admin area
- **Regular Users**: No change in behavior - still redirected to their account page as expected
- **Security**: No security impact - uses WordPress's built-in `user_can()` function to check capabilities
