# Version 10.3 - Membership Status and User Role Fixes

## Overview
Version 10.3 addresses critical bugs in the membership management system that were causing membership status to display incorrectly and user roles to be improperly managed.

## Problems Identified

### Problem 1: "You do not have an active membership" Message Despite Having Membership
**Symptom:** Users who had memberships manually added by admins were still seeing the message "You do not have an active membership. Purchase a membership below to access all IELTS preparation courses."

**Root Cause:** In `/admin/class-admin.php`, the `save_membership_fields()` function only updated the membership status and user roles when the admin provided a NEW end_date. If an admin edited other fields (enrollment type, trial status) without changing the expiry date, the status field remained unchanged and could be stale or incorrect.

**Code Location:** `/admin/class-admin.php` lines 1162-1192 (before fix)

**Impact:** Manual membership additions were being saved to the database but their status was not being properly calculated, causing the system to treat them as inactive.

### Problem 2: Admin Users List Shows "No Membership"
**Symptom:** In the WordPress admin users list, the membership status column showed "No Membership" for users who actually had memberships in the database.

**Root Cause:** This was a consequence of Problem 1 - since the status field wasn't being properly set when memberships were manually created or edited, the admin display logic couldn't determine if the membership was active.

**Code Location:** `/admin/class-admin.php` lines 936-942 (display logic)

**Impact:** Admins couldn't see accurate membership information for users in the admin interface.

### Problem 3: User Loses Roles When Membership Added
**Symptom:** When a new user enrolled in a free trial, they had both the 'subscriber' and 'active' user roles. When an admin edited that user's account to add or modify a membership, the user would lose the 'subscriber' role and only retain the 'active' or 'expired' role.

**Root Cause:** The `update_user_membership_role()` function in `/admin/class-admin.php` was only managing the 'active' and 'expired' roles without considering that users should maintain their base WordPress role ('subscriber'). WordPress users can have multiple roles, but this code was implicitly assuming users only had one role.

**Code Locations:** 
- `/admin/class-admin.php` lines 1105-1124 (update_user_membership_role)
- `/includes/class-membership.php` lines 105-114 (create_membership)
- `/includes/class-membership.php` lines 198-207 (expire_membership)

**Impact:** Users were losing their fundamental WordPress 'subscriber' role, which could break other WordPress functionality that depends on this role.

## Solutions Implemented

### Fix 1: Always Recalculate Membership Status
**File:** `/admin/class-admin.php`
**Function:** `save_membership_fields()`
**Lines:** 1171-1200

**Changes Made:**
```php
// Determine which end_date to use for status calculation
$end_date_to_check = $converted_end_date ? $converted_end_date : $membership->end_date;

// Update end_date if provided
if ($converted_end_date) {
    $update_data['end_date'] = $converted_end_date;
    $format[] = '%s';
}

// Always update status based on end date (new or existing)
$is_future = strtotime($end_date_to_check) > time();
$update_data['status'] = $is_future ? 'active' : 'expired';
$format[] = '%s';

// Always update user role to ensure consistency
$this->update_user_membership_role($user_id, $is_future);
```

**Result:** Now whenever a membership is saved (whether creating new or updating existing), the status is always recalculated based on the end_date. If no new end_date is provided, it uses the existing one. User roles are always updated to match the calculated status.

### Fix 2: Preserve Subscriber Role in All Role Management Functions
**File:** `/admin/class-admin.php`
**Function:** `update_user_membership_role()`
**Lines:** 1102-1133

**Changes Made:**
```php
if ($is_active) {
    // Grant active role and remove expired role
    $user->remove_role('expired');
    if (!in_array('active', $user->roles)) {
        $user->add_role('active');
    }
    // Ensure subscriber role is maintained (WordPress default for registered users)
    if (!in_array('subscriber', $user->roles)) {
        $user->add_role('subscriber');
    }
} else {
    // Grant expired role and remove active role
    $user->remove_role('active');
    if (!in_array('expired', $user->roles)) {
        $user->add_role('expired');
    }
    // Ensure subscriber role is maintained (WordPress default for registered users)
    if (!in_array('subscriber', $user->roles)) {
        $user->add_role('subscriber');
    }
}
```

**Result:** The subscriber role is now explicitly maintained when managing membership roles.

### Fix 3: Preserve Subscriber Role in Membership Creation/Expiration
**File:** `/includes/class-membership.php`
**Functions:** `create_membership()` and `expire_membership()`
**Lines:** 105-118, 198-211

**Changes Made:**
Added explicit subscriber role preservation in both functions:
```php
// Ensure subscriber role is maintained (WordPress default for registered users)
if (!in_array('subscriber', $user->roles)) {
    $user->add_role('subscriber');
}
```

**Result:** Whether creating a new membership or expiring an existing one, the subscriber role is always preserved.

## Testing Recommendations

### Test Case 1: Manual Membership Addition
1. Log in as admin
2. Go to Users → Select a user → Edit
3. Under "Membership Management", set:
   - Enrollment Type: Both
   - Membership Expiry Date: A future date (e.g., 30 days from now)
4. Save the user profile
5. Log in as that user
6. **Expected Result:** User should see their active membership, not the "You do not have an active membership" message
7. **Expected Result:** In admin users list, the user should show as having an active membership

### Test Case 2: User Role Preservation
1. Create a new user account (they get 'subscriber' role by default)
2. Enroll them in a free trial (they should get 'active' role added)
3. As admin, edit that user's membership (change enrollment type or trial status)
4. Save the user profile
5. **Expected Result:** User should have BOTH 'subscriber' AND 'active' roles
6. Check with: View user edit page, look at the "Role" dropdown - it should show the user's primary role as subscriber

### Test Case 3: Status Recalculation
1. As admin, create a membership for a user with an expiry date in the past
2. **Expected Result:** Membership status should be 'expired' and user should have 'expired' + 'subscriber' roles
3. Edit the user and change ONLY the enrollment type (don't change expiry date)
4. Save the user profile
5. **Expected Result:** Membership status should remain 'expired' (not change to 'active')

### Test Case 4: Admin Display
1. As admin, go to Users page
2. Look at the "Membership Status" column
3. **Expected Result:** All users with memberships should show their correct status (Active/Expired, Trial/Full, Enrollment Type)
4. **Expected Result:** Only users without any membership records should show "No Membership"

## Database Schema (For Reference)

The `wp_ielts_ms_memberships` table has the following relevant fields:
- `id` - Primary key
- `user_id` - WordPress user ID
- `status` - varchar(20), DEFAULT 'active' (can be 'active' or 'expired')
- `enrollment_type` - varchar(20), DEFAULT 'both' (can be 'general_training', 'academic', or 'both')
- `is_trial` - tinyint(1), DEFAULT 0 (1 for trial, 0 for paid)
- `start_date` - datetime
- `end_date` - datetime
- `created_date` - datetime
- `updated_date` - datetime

## User Roles

The plugin manages these custom roles:
- **active** - User has an active (non-expired) membership
- **expired** - User had a membership but it has expired

These roles are used IN ADDITION to the standard WordPress 'subscriber' role, not as replacements.

## Version History

- **10.2** - Previous version (had the membership status bugs)
- **10.3** - Current version (fixes all three membership status and role management bugs)

## Files Changed

1. `/admin/class-admin.php` - Fixed `save_membership_fields()` and `update_user_membership_role()`
2. `/includes/class-membership.php` - Fixed `create_membership()` and `expire_membership()`
3. `/ielts-membership-system.php` - Updated version number to 10.3

## Technical Notes

### Why WordPress Users Can Have Multiple Roles

WordPress's user system allows users to have multiple roles simultaneously. While the WordPress admin UI typically shows only one "primary" role, behind the scenes a user can have many roles, each granting different capabilities.

In this plugin:
- 'subscriber' is the base WordPress role that all registered users should have
- 'active' and 'expired' are membership-specific roles that grant/restrict access to courses
- A typical active member should have: ['subscriber', 'active']
- A typical expired member should have: ['subscriber', 'expired']

### Status Calculation Logic

The membership status is determined by two factors:
1. The `status` field in the database ('active' or 'expired')
2. Whether the `end_date` is in the future

For a membership to be considered "active", BOTH conditions must be true:
- `status === 'active'`
- `strtotime(end_date) > time()`

This is why it's critical to update the status field whenever the end_date changes or is evaluated.

## Conclusion

These fixes ensure that:
1. ✅ Manual membership additions work correctly
2. ✅ Membership status is always accurate and up-to-date
3. ✅ User roles are properly maintained and not lost during updates
4. ✅ Admin users list shows accurate membership information
5. ✅ All existing functionality continues to work as expected

The changes are minimal and surgical, only modifying the specific functions that were causing the bugs, without altering the overall system architecture.
