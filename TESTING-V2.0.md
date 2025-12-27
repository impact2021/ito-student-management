# Version 2.0.0 - Testing & Validation Guide

This guide provides comprehensive testing procedures for all features in version 2.0.0.

## Quick Test Checklist

- [ ] Plugin version displays as 2.0.0
- [ ] Admin can access wp-admin without redirect loops
- [ ] User roles change to 'active' when membership created
- [ ] User roles change to 'expired' when membership expires
- [ ] Pricing settings are configurable and reflect in checkout
- [ ] Homepage redirect works for logged-in non-admin users
- [ ] All PHP files have no syntax errors
- [ ] No JavaScript console errors

## Detailed Testing Procedures

### 1. Version Update Test

**Objective**: Verify plugin version is correctly updated

**Steps**:
1. Navigate to WordPress admin → Plugins
2. Find "IELTS Membership System" in the list
3. Verify version shows as 2.0.0

**Expected Result**: Plugin version displays as 2.0.0

---

### 2. Admin Login Loop Fix Test

**Objective**: Verify admins can access wp-admin without redirects

**Setup**:
- Have an admin account ready
- Enable custom login in settings

**Test Case A: Admin wp-login Access**
1. Log out completely
2. Navigate to `/wp-login.php`
3. Log in with admin credentials
4. After login, you should land on wp-admin dashboard

**Expected Result**: Admin successfully accesses wp-admin, no redirect to membership pages

**Test Case B: Admin on Login Page**
1. Log in as admin
2. Navigate to the membership login page (e.g., `/membership-login/`)
3. Page should display normally (not redirect)

**Expected Result**: Admin can view the login page without being redirected

**Test Case C: Admin on Register Page**
1. Log in as admin
2. Navigate to membership register page (e.g., `/membership-register/`)
3. Page should display normally (not redirect)

**Expected Result**: Admin can view the register page without being redirected

**Test Case D: Regular User Login**
1. Log out
2. Navigate to `/wp-login.php`
3. Log in with regular user credentials
4. Should be redirected to custom login page first, then to account page after login

**Expected Result**: Regular users still get redirected as expected

---

### 3. Custom User Roles Test

**Objective**: Verify 'active' and 'expired' roles work correctly

**Test Case A: Check Roles Exist**
1. Navigate to Users → Add New
2. Click on the "Role" dropdown
3. Verify "Active Member" and "Expired Member" appear in the list

**Expected Result**: Both custom roles are registered

**Test Case B: New Membership Creates Active Role**
1. Create a test user account (or register new account)
2. Purchase a membership (use test payment method)
3. Navigate to Users → All Users
4. Find the test user and check their role
5. Should show "Active Member"

**Expected Result**: User has 'Active Member' role after purchasing membership

**Test Case C: Course Access with Active Role**
1. Log in as user with active role
2. Try to access a course
3. Verify access is granted

**Expected Result**: User with 'Active Member' role can access courses

**Test Case D: Membership Expiration Creates Expired Role**
1. Find a user with active membership
2. Use database or plugin to manually expire their membership
   - Update `end_date` to past date in `wp_ielts_ms_memberships` table
   - Update `status` to 'expired'
3. Run cron manually: `wp cron event run ielts_ms_check_expired_memberships` (WP-CLI)
   - Or wait for daily cron
4. Check user's role

**Expected Result**: User role changes from 'Active Member' to 'Expired Member'

**Test Case E: Extension Restores Active Role**
1. User with 'Expired Member' role
2. Purchase an extension
3. Check user's role after payment completes

**Expected Result**: User role changes back to 'Active Member', 'Expired Member' role removed

---

### 4. Configurable Pricing Test

**Objective**: Verify pricing can be configured and applies correctly

**Test Case A: Access Pricing Settings**
1. Navigate to Membership → Settings
2. Scroll to "Pricing Settings" section
3. Verify all four price fields are present:
   - New 90-Day Membership
   - 1 Week Extension
   - 1 Month Extension
   - 3 Months Extension

**Expected Result**: All pricing fields visible with default values

**Test Case B: Update Prices**
1. Change "New 90-Day Membership" to $29.99
2. Change "1 Month Extension" to $12.50
3. Click "Save Changes"
4. Verify success message appears

**Expected Result**: Settings saved successfully

**Test Case C: Verify Prices on Registration**
1. Log out
2. Navigate to registration page
3. Check if new membership price shows $29.99

**Expected Result**: Updated price displays on registration page

**Test Case D: Verify Prices on Account Page**
1. Log in as regular user
2. Navigate to account page
3. Check extension prices
4. 1 Month should show $12.50

**Expected Result**: Updated prices display correctly

**Test Case E: Complete Payment with New Price**
1. As logged-in user, purchase 1 month extension ($12.50)
2. Complete payment (use test mode)
3. Navigate to Membership → Payments in admin
4. Verify payment record shows $12.50

**Expected Result**: Payment processed with correct new price

**Test Case F: Reset to Defaults**
1. Navigate to Membership → Settings
2. Set all prices back to defaults
3. Save changes

**Expected Result**: Can reset to original prices

---

### 5. Custom Homepage Redirect Test

**Objective**: Verify logged-in users redirect to custom homepage

**Test Case A: Create Test Page**
1. Create a new page titled "Member Dashboard"
2. Add some content (e.g., "Welcome, Member!")
3. Publish the page

**Expected Result**: Page created successfully

**Test Case B: Configure Homepage Redirect**
1. Navigate to Membership → Settings
2. Find "Logged-In Homepage" dropdown under "Login Settings"
3. Select "Member Dashboard" from dropdown
4. Click "Save Changes"

**Expected Result**: Setting saved successfully

**Test Case C: Test Redirect as Regular User**
1. Log out completely
2. Visit your homepage
3. Verify it shows the regular public homepage
4. Log in as regular user (not admin)
5. After login, navigate to your homepage URL
6. Should be redirected to "Member Dashboard"

**Expected Result**: Logged-in user redirected to custom homepage

**Test Case D: Test Admin Not Redirected**
1. Log out
2. Log in as admin
3. Navigate to homepage
4. Should see regular homepage (not redirected)

**Expected Result**: Admin sees normal homepage, no redirect

**Test Case E: Test Other Pages Not Affected**
1. Log in as regular user
2. Navigate to other pages (e.g., About, Contact)
3. Should see those pages normally (no redirect)

**Expected Result**: Only homepage redirects, other pages work normally

**Test Case F: Disable Redirect**
1. Navigate to Membership → Settings
2. Set "Logged-In Homepage" to "Default Homepage"
3. Save changes
4. Log in as regular user
5. Visit homepage
6. Should see regular homepage (no redirect)

**Expected Result**: Redirect disabled when set to default

---

## Integration Tests

### Test 1: Complete User Journey

**Scenario**: New user registers, pays, uses site, membership expires

1. **Register**: Create new account on registration page
2. **No Access**: Try to access course - should be denied (no membership)
3. **Purchase**: Buy 90-day membership at configured price
4. **Active Role**: Verify user has 'Active Member' role
5. **Course Access**: Can now access courses
6. **Homepage**: When visiting homepage, redirected to custom page
7. **Account Management**: Can view membership status on account page
8. **Extension**: Purchase an extension
9. **Manual Expiration**: Force membership expiration via database
10. **Run Cron**: `wp cron event run ielts_ms_check_expired_memberships`
11. **Expired Role**: Verify user has 'Expired Member' role
12. **No Access**: Course access denied
13. **Re-purchase**: Buy another extension
14. **Active Again**: Back to 'Active Member' role with access

**Expected Result**: Complete flow works smoothly

### Test 2: Admin Operations

**Scenario**: Admin manages the system

1. **View Members**: Navigate to Membership → Members
2. **View Payments**: Navigate to Membership → Payments
3. **Update Pricing**: Change all four prices
4. **Update Homepage**: Set custom homepage for logged-in users
5. **Access Testing**: Admin can still access all pages
6. **Manual Operations**: Can manually manage users

**Expected Result**: All admin functions work correctly

---

## Security Tests

### Test 1: Settings Protection

1. Log out
2. Try to access `/wp-admin/admin.php?page=ielts-membership` directly
3. Should be redirected to login

**Expected Result**: Settings page requires authentication

### Test 2: Price Validation

1. Try to set negative price (if possible via browser dev tools)
2. Save settings
3. Price should be validated

**Expected Result**: Invalid prices rejected or sanitized to 0

### Test 3: Homepage Redirect Protection

1. Set homepage redirect to a page ID that doesn't exist
2. Log in and visit homepage
3. Should not redirect (invalid page ID)

**Expected Result**: Invalid redirect targets ignored safely

---

## Performance Tests

### Test 1: Page Load Speed

1. Before enabling redirect: Test homepage load time
2. Enable redirect for logged-in users
3. Test homepage load time as logged-in user
4. Compare times

**Expected Result**: Minimal impact (< 50ms difference)

### Test 2: Access Check Performance

1. Enable query monitor plugin
2. Access a course page multiple times
3. Check number of database queries

**Expected Result**: No significant increase in queries

---

## Regression Tests

Verify existing functionality still works:

- [ ] PayPal payments still work
- [ ] Stripe payments still work
- [ ] User can change email
- [ ] User can change password
- [ ] Password reset works
- [ ] Account page displays correctly
- [ ] Payment history displays
- [ ] Admin can view all members
- [ ] Admin can view all payments

---

## Browser Compatibility

Test on:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Mobile Chrome (Android)

---

## WordPress Compatibility

Test on:
- [ ] WordPress 5.8
- [ ] WordPress 6.0
- [ ] WordPress 6.4 (latest)

---

## PHP Compatibility

Test on:
- [ ] PHP 7.2
- [ ] PHP 7.4
- [ ] PHP 8.0
- [ ] PHP 8.1
- [ ] PHP 8.2

---

## Automated Testing Commands

If WP-CLI is available:

```bash
# Check plugin status
wp plugin list

# Verify plugin version
wp plugin get ielts-membership-system --field=version

# Run cron manually
wp cron event run ielts_ms_check_expired_memberships

# List all users with roles
wp user list --role=active --fields=ID,user_login,user_email
wp user list --role=expired --fields=ID,user_login,user_email

# Check options
wp option get ielts_ms_price_new_90
wp option get ielts_ms_price_extend_30
wp option get ielts_ms_logged_in_homepage_id
```

---

## Common Issues & Solutions

### Issue: Redirect loop on homepage

**Solution**: 
- Ensure logged-in homepage is NOT the same as the default homepage
- Check that page ID is valid
- Disable redirect temporarily

### Issue: Prices not updating

**Solution**:
- Clear all caches
- Verify settings were saved
- Check browser cache

### Issue: Roles not changing

**Solution**:
- Verify cron is running: `wp cron test`
- Manually run cron job
- Check database membership status matches

### Issue: Admin still redirected

**Solution**:
- Verify user has 'manage_options' capability
- Check for conflicting plugins
- Review error logs

---

## Rollback Plan

If issues occur:

1. Deactivate plugin
2. Note down any custom pricing settings
3. Revert to previous version
4. Report issue with details
5. Wait for fix
6. Re-test before deploying again

---

## Sign-off Checklist

Before deploying to production:

- [ ] All tests passed
- [ ] No PHP errors in error log
- [ ] No JavaScript errors in console
- [ ] Database backup completed
- [ ] Pricing configured correctly
- [ ] Homepage redirect configured (if desired)
- [ ] Admin can access all pages
- [ ] Regular users have expected experience
- [ ] Payment processing works
- [ ] Documentation updated
- [ ] Team trained on new features
