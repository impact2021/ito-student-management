# Version 2.0.0 Changes

This document outlines the changes made in version 2.0.0 of the IELTS Membership System plugin.

## Summary

Version 2.0.0 addresses three main requirements:
1. Update plugin to version 2.0.0
2. Fix admin login loop issue
3. Implement custom user roles for active and expired memberships

## Detailed Changes

### 1. Version Update

**Files Modified:**
- `ielts-membership-system.php`
- `README.md`

**Changes:**
- Updated plugin version from 1.1.0 to 2.0.0 in plugin header
- Updated `IELTS_MS_VERSION` constant to 2.0.0
- Added version 2.0.0 changelog entry in README.md

### 2. Admin Login Loop Fix

**Problem:** 
Administrators were getting redirected in a loop when trying to access wp-admin pages because the custom login system redirected all logged-in users to the account page without checking for admin privileges.

**Files Modified:**
- `includes/class-login-manager.php`
- `includes/class-shortcodes.php`

**Changes:**
- Added `current_user_can('manage_options')` check in `redirect_wp_login()` method to prevent redirecting administrators
- Updated `login_form()` shortcode to only redirect non-admin users to account page
- Updated `register_form()` shortcode to only redirect non-admin users to account page

**Impact:**
- Administrators can now access wp-admin and all WordPress admin pages without being redirected
- Regular users are still redirected to account page when trying to access login/register pages while logged in
- Admins can view login/register pages if needed for testing or demonstration

### 3. Custom User Roles (Active/Expired)

**Problem:**
All users were assigned the generic 'subscriber' role regardless of their membership status. The requirement was to have distinct roles for active and expired members.

**Files Modified:**
- `ielts-membership-system.php`
- `includes/class-membership.php`
- `includes/class-login-manager.php`

**Changes:**

#### New Roles Created:
- **active**: Assigned to users with active (non-expired) memberships
  - Capabilities: `read`, `level_0`
- **expired**: Assigned to users with expired memberships
  - Capabilities: `read`, `level_0`

#### Role Assignment Logic:

**On Membership Creation/Extension (`create_membership()`):**
- Removes 'expired' role if present
- Adds 'active' role to user
- This happens when:
  - A new membership is purchased
  - An existing membership is extended

**On Membership Expiration (`expire_membership()`):**
- Removes 'active' role if present
- Adds 'expired' role to user
- This happens when:
  - A membership reaches its end date
  - The automatic cron job runs daily

**On User Registration (without membership):**
- New users still get 'subscriber' role initially
- When they purchase a membership, they get upgraded to 'active' role

#### Automatic Expiration System:

**New Daily Cron Job:**
- Hook: `ielts_ms_check_expired_memberships`
- Frequency: Daily
- Function: `ielts_ms_check_expired_memberships_callback()`
- Action: Queries database for all memberships with status='active' and end_date < NOW(), then calls `expire_membership()` for each

**Cron Job Management:**
- Scheduled on plugin initialization if not already scheduled
- Unscheduled on plugin deactivation to clean up

#### Course Access Integration:

Updated `check_course_access()` to:
- Check if user has 'active' role as primary verification
- Fallback to checking active membership status in database
- Maintains backward compatibility

### 4. Backward Compatibility

**Existing Users:**
- Users with old 'subscriber' role will need to be manually migrated or will get new role on next membership activity
- Course access logic checks both role and database status, so existing users maintain access

**Future Enhancement Recommendations:**
- Consider adding a one-time migration script to update all existing users to appropriate roles
- Add admin interface to bulk update user roles
- Add role display in members admin page

## Testing Recommendations

1. **Admin Access Test:**
   - Log in as admin
   - Navigate to wp-admin - should not be redirected
   - Visit login page while logged in as admin - should see the page
   - Log out and back in as admin - should work normally

2. **User Role Test:**
   - Create new user account
   - Verify user has 'subscriber' role
   - Purchase membership
   - Verify user now has 'active' role
   - Manually expire membership in database
   - Run cron job: `wp cron event run ielts_ms_check_expired_memberships`
   - Verify user now has 'expired' role

3. **Membership Extension Test:**
   - Create user with expired membership and 'expired' role
   - Extend membership
   - Verify user now has 'active' role (expired role removed)

4. **Course Access Test:**
   - User with 'active' role should have course access
   - User with 'expired' role should not have course access
   - Admin should always have course access

## Files Changed

1. `ielts-membership-system.php` - Main plugin file
   - Version update
   - Role registration function
   - Cron job scheduling
   - Cron callback function

2. `includes/class-membership.php` - Membership management
   - Updated `create_membership()` to assign 'active' role
   - Updated `expire_membership()` to assign 'expired' role
   - Enhanced `check_course_access()` to check 'active' role

3. `includes/class-login-manager.php` - Login management
   - Added admin check in `redirect_wp_login()`

4. `includes/class-shortcodes.php` - Shortcode handlers
   - Added admin check in `login_form()`
   - Added admin check in `register_form()`

5. `README.md` - Documentation
   - Added version 2.0.0 changelog
   - Updated integration section to reflect new roles

## Migration Notes

### For Existing Installations:

1. **Deactivate and Reactivate Plugin:**
   - This will register the new custom roles
   - Schedule the daily cron job

2. **Existing Users:**
   - Users with active memberships will get 'active' role on their next membership extension
   - Users with expired memberships will get 'expired' role when cron job runs
   - Consider running manual SQL to update all existing users:
   
   ```sql
   -- Get all users with active memberships and update their roles
   -- This would need to be done via WordPress admin or custom script
   ```

3. **Cron Job:**
   - First run will process all currently expired memberships
   - Runs daily thereafter automatically
   - Can be manually triggered: `wp cron event run ielts_ms_check_expired_memberships` (using WP-CLI)

## Security Considerations

- Admin access bypass is properly secured using `current_user_can('manage_options')`
- Role capabilities are minimal (read, level_0) to prevent privilege escalation
- Cron job uses prepared SQL statements to prevent SQL injection
- No sensitive data exposed in role names or capabilities

## Performance Considerations

- Daily cron job queries only active memberships with expired dates (indexed query)
- Role checks use WordPress core functions (cached)
- No impact on page load times
- Database queries are optimized with proper WHERE clauses

## Future Enhancement Opportunities

1. Add migration script for existing users
2. Add admin UI to manually assign/update user roles
3. Display user role in admin members page
4. Add email notifications when role changes (active → expired)
5. Add grace period before downgrading to 'expired' role
6. Add 'pending' role for users who registered but haven't purchased
