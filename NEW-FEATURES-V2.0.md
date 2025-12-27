# New Features in Version 2.0.0

This document details the new features added in version 2.0.0 of the IELTS Membership System plugin.

## 1. Configurable Pricing Settings

### Overview
Administrators can now customize all membership and extension prices directly from the WordPress admin panel, eliminating the need to modify code.

### Location
**Membership > Settings** → Scroll down to the **Pricing Settings** section

### Available Pricing Options

1. **New 90-Day Membership**
   - Default: $24.95 USD
   - Description: Price for a brand new 90-day membership
   - Field: Accepts decimal numbers (e.g., 24.95, 29.99)

2. **1 Week Extension**
   - Default: $5.00 USD
   - Description: Price for a 7-day membership extension
   - Field: Accepts decimal numbers

3. **1 Month Extension**
   - Default: $10.00 USD
   - Description: Price for a 30-day membership extension
   - Field: Accepts decimal numbers

4. **3 Months Extension**
   - Default: $20.00 USD
   - Description: Price for a 90-day membership extension
   - Field: Accepts decimal numbers

### How It Works

1. Navigate to **Membership > Settings**
2. Scroll to the **Pricing Settings** section
3. Update any price field as needed
4. Click **Save Changes**
5. New prices are immediately reflected in:
   - Registration forms
   - Account page payment options
   - PayPal checkout
   - Stripe checkout
   - Payment records

### Technical Details

- Prices are stored as WordPress options in the database
- All values are validated and sanitized using `floatval()`
- The `get_pricing_options()` method in `class-payment-gateway.php` pulls prices from settings with fallback to defaults
- Currency is fixed to USD (future enhancement could add multi-currency support)

### Database Options

```php
ielts_ms_price_new_90     // New 90-day membership price
ielts_ms_price_extend_7   // 1 week extension price
ielts_ms_price_extend_30  // 1 month extension price
ielts_ms_price_extend_90  // 3 months extension price
```

## 2. Custom Homepage Redirect for Logged-In Users

### Overview
Administrators can specify a custom page that logged-in users (non-admins) will be redirected to when they visit the site's homepage. This allows you to create a personalized member experience.

### Location
**Membership > Settings** → **Login Settings** section → **Logged-In Homepage** dropdown

### How It Works

1. Navigate to **Membership > Settings**
2. In the **Login Settings** section, find **Logged-In Homepage**
3. Select a page from the dropdown (or leave as "Default Homepage")
4. Click **Save Changes**
5. When logged-in users (non-admins) visit your homepage, they'll be automatically redirected to the selected page

### Use Cases

**Example 1: Member Dashboard**
- Create a page called "Member Dashboard" with course links, resources, etc.
- Set it as the Logged-In Homepage
- Members always land on their dashboard instead of the public homepage

**Example 2: Course Catalog**
- Create a "My Courses" page listing available courses
- Set it as the Logged-In Homepage
- Members immediately see what courses they can access

**Example 3: Account Page**
- Set the "My Account" page as the Logged-In Homepage
- Members land directly on their account page to manage membership

### Behavior Details

- **Only affects non-admins**: Administrators always see the regular homepage
- **Only redirects on homepage**: Other pages are not affected
- **Respects front page settings**: Works whether you're using latest posts or a static page as homepage
- **One-time redirect**: No redirect loops - only happens when visiting the actual homepage

### Technical Implementation

```php
// In ielts-membership-system.php
function ielts_ms_redirect_logged_in_homepage() {
    // Only redirect on the actual homepage
    if (!is_front_page() || !is_user_logged_in()) {
        return;
    }
    
    // Don't redirect admins
    if (current_user_can('manage_options')) {
        return;
    }
    
    // Get custom homepage for logged-in users
    $logged_in_homepage_id = get_option('ielts_ms_logged_in_homepage_id', 0);
    
    if ($logged_in_homepage_id && $logged_in_homepage_id != get_option('page_on_front')) {
        $redirect_url = get_permalink($logged_in_homepage_id);
        if ($redirect_url) {
            wp_redirect($redirect_url);
            exit;
        }
    }
}
```

### Database Option

```php
ielts_ms_logged_in_homepage_id  // Page ID for logged-in user homepage (0 = disabled)
```

## Combined Use Case Examples

### Scenario 1: Premium Member Experience

**Setup:**
- New Membership: $29.99
- Extensions: $7.99, $14.99, $24.99
- Logged-In Homepage: "Member Dashboard" page

**Result:** Members pay your custom prices and always land on a personalized dashboard when visiting your site.

### Scenario 2: Course Platform

**Setup:**
- Keep default pricing
- Logged-In Homepage: "My Courses" page with course catalog

**Result:** Members immediately see their available courses without having to navigate from the homepage.

### Scenario 3: Promotional Pricing

**Setup:**
- New Membership: $19.95 (discounted from $24.95)
- 1 Month Extension: $8.00 (discounted from $10.00)
- Logged-In Homepage: Default

**Result:** Run a limited-time promotion with adjusted prices while maintaining normal user flow.

## Migration & Compatibility

### Existing Installations

When upgrading to version 2.0.0:

1. **Pricing**: All existing prices use the defaults (24.95, 5.00, 10.00, 20.00)
   - No action required unless you want to change prices
   - Existing memberships maintain their original purchase price in history

2. **Homepage Redirect**: Disabled by default (set to 0)
   - No action required unless you want to enable this feature
   - No impact on existing user experience

### Testing Recommendations

#### Test Pricing Changes:
1. Update a price in settings
2. View the registration page - verify new price displays
3. View the account page - verify extension prices are correct
4. (Optional) Test a payment with PayPal sandbox or Stripe test mode

#### Test Homepage Redirect:
1. Create or select a page for logged-in users
2. Set it in the Logged-In Homepage setting
3. Log in as a regular user (not admin)
4. Visit your homepage
5. Verify redirect to the selected page
6. Log out and verify public homepage still shows
7. Log in as admin and verify no redirect occurs

## Future Enhancement Opportunities

### Pricing
- Multiple pricing tiers (basic, premium, etc.)
- Discount codes/coupons
- Multi-currency support
- Special/promotional pricing with date ranges
- Trial periods (e.g., 7-day free trial)

### Homepage Redirect
- Different homepage per user role (active vs expired)
- Different homepage based on membership tier
- A/B testing different member landing pages
- Redirect based on membership status
- First-time login special page

## Security Considerations

### Pricing
- All price inputs are sanitized using `floatval()`
- Settings page requires `manage_options` capability
- Nonce verification on form submission
- Minimum value of 0 enforced in HTML input

### Homepage Redirect
- Uses `current_user_can('manage_options')` to exclude admins
- Uses WordPress core `wp_redirect()` function
- Validates page ID exists before redirecting
- No user input involved in redirect target
- Protected by WordPress's capability system

## Performance Impact

Both features have minimal performance impact:

### Pricing
- Options are cached by WordPress
- No additional database queries per page load
- Values only retrieved when needed (checkout, account page)

### Homepage Redirect
- Single option lookup per homepage visit
- Only runs on `is_front_page()`
- Early return for non-logged-in users
- No impact on other pages

## Support & Troubleshooting

### Pricing Issues

**Problem**: Prices not updating on frontend
- **Solution**: Clear all caches (browser, WordPress cache plugin, server cache)
- **Check**: Verify settings were saved (success message appears)

**Problem**: Payment shows old price
- **Solution**: Prices are fetched dynamically - refresh the page
- **Note**: Historical payments maintain original price paid

### Homepage Redirect Issues

**Problem**: Infinite redirect loop
- **Solution**: Make sure the Logged-In Homepage is NOT set to the current homepage
- **Check**: The redirect checks if target page != current homepage

**Problem**: Admin getting redirected
- **Solution**: This should not happen - admins are excluded. Check user has `manage_options` capability

**Problem**: Not redirecting
- **Solution**: Verify a page is selected (not "Default Homepage")
- **Check**: User is actually logged in and visiting homepage
