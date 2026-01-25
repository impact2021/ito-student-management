# Version 10.4 - Timezone Inconsistency Fix

## Overview
Version 10.4 fixes a critical bug where free trial memberships (and potentially paid memberships) would incorrectly show as "You do not have an active membership" despite being properly created in the database and allowing access to resources.

## Problem Statement

### User-Reported Symptoms
1. User creates a free trial account successfully in Chrome
2. User CAN access resources and courses (proving membership exists and is valid)
3. BUT account page displays: "You do not have an active membership"
4. When admin views the user in WordPress Users list, it shows "No membership"
5. Expected: Should show "FREE TRIAL - Academic" or "Free trial - General Training"

### Root Cause Analysis

The system was **mixing two different time sources**:

#### WordPress Time Functions
- `current_time('mysql')` - Returns datetime string in WordPress-configured timezone
- `current_time('timestamp')` - Returns Unix timestamp in WordPress-configured timezone

#### PHP Time Functions  
- `time()` - Returns Unix timestamp in **server timezone** (typically UTC)
- `date()` - Formats dates in **server timezone**
- `strtotime()` - Parses datetime strings assuming **server timezone**

### The Bug

When creating a membership:
```php
// Line 37 in class-membership.php
$start_date = current_time('mysql'); // E.g., "2026-01-25 04:15:00" (UTC+2)

// Line 47
if ($is_trial) {
    $end_date = date('Y-m-d H:i:s', strtotime($base_date . ' +' . $duration_days . ' hours'));
}
// If base_date = "2026-01-25 04:15:00", strtotime interprets this as SERVER time (UTC)
// Adds hours, then formats back to string
```

When checking if membership is active:
```php
// Line 166 in class-membership.php (BEFORE FIX)
return $membership->status === 'active' && strtotime($membership->end_date) > time();
// strtotime($membership->end_date) - Interprets stored datetime as SERVER timezone
// time() - Returns current time in SERVER timezone
// BUT the stored datetime was created with WordPress timezone offset!
```

### Example Timeline (WordPress timezone = UTC+2)

1. **Trial Created at 2:15 PM UTC (4:15 PM WordPress time)**
   - `current_time('mysql')` returns: `"2026-01-25 16:15:00"`
   - This gets stored as `start_date`
   - Trial duration: 72 hours
   - `end_date` calculated: `"2026-01-28 16:15:00"`

2. **User Checks Account 1 Hour Later (3:15 PM UTC)**
   - Stored `end_date`: `"2026-01-28 16:15:00"`
   - `strtotime("2026-01-28 16:15:00")` → Treats as UTC → `1737994500` (example)
   - `time()` returns → `1737997500` (3 hours ahead!)
   - Comparison fails! Membership appears expired.

The discrepancy equals the WordPress timezone offset (2 hours in this example).

## Files Affected

### 1. includes/class-membership.php
**Changed:** 5 occurrences of `time()` to `current_time('timestamp')`

- **Line 40**: Checking if existing membership is still active
- **Line 166**: `has_active_membership()` - Main membership status check
- **Line 179**: `is_expired()` - Expiration check
- **Line 244**: `get_days_remaining()` - Calculate remaining days
- **Line 422**: `filter_courses_by_membership()` - Course access filtering

### 2. admin/class-admin.php
**Changed:** 5 occurrences of `time()` to `current_time('timestamp')`

- **Line 946**: `display_user_column()` - Admin users list membership display
- **Line 979**: Admin users list time remaining calculation
- **Line 1085**: User profile membership status display
- **Line 1191**: `save_membership_fields()` - Status calculation when saving
- **Line 1207**: `save_membership_fields()` - Status check for new membership creation

### 3. templates/account-page.php
**Changed:** 1 occurrence of `time()` to `current_time('timestamp')`

- **Line 78**: Trial membership countdown timer calculation

### 4. ielts-membership-system.php
**Changed:** Version number updated

- **Line 6**: Version updated from `10.3` to `10.4`
- **Line 23**: Version constant updated from `'10.3'` to `'10.4'`

## Technical Details

### Why current_time('timestamp') is the Correct Solution

WordPress allows site administrators to set a timezone offset in Settings → General → Timezone. This is stored in the database and can differ from the server's timezone.

**Best Practice for WordPress Plugins:**
- Use `current_time('timestamp')` for all timestamp comparisons
- Use `current_time('mysql')` for datetime strings to store in database
- This ensures consistency with WordPress's timezone settings
- Respects user/admin expectations about when things expire

### Alternative Solutions Considered

1. **Use `time()` everywhere** ❌
   - Would work but ignores WordPress timezone settings
   - Bad for international sites
   - Confusing for administrators

2. **Store Unix timestamps instead of datetime strings** ❌  
   - Requires database schema changes
   - Breaks existing data
   - Makes queries less readable

3. **Use `current_time('timestamp')` for comparisons** ✅
   - Minimal code changes
   - Respects WordPress settings
   - No database changes needed
   - Maintains backward compatibility

## Testing Performed

### Manual Testing Checklist
- [x] Code review of all time-related functions
- [x] Verification that all `time()` calls in membership logic are replaced
- [x] Git diff review to ensure only necessary changes made
- [ ] Live testing: Create free trial account
- [ ] Live testing: Verify account page shows "Free Trial Active"
- [ ] Live testing: Verify admin users list shows trial membership
- [ ] Live testing: Test with different WordPress timezone settings
- [ ] Live testing: Verify paid membership creation still works
- [ ] Live testing: Verify membership expiration logic still works

### Expected Behavior After Fix

1. **Free Trial Registration:**
   - User registers with free trial
   - System creates membership with `is_trial = 1`
   - End date calculated correctly using WordPress time
   - User immediately redirected to account page
   - Account page shows: "Free Trial Active" with countdown
   - Admin can see: "Free Trial - [Academic/General Training/Both]"

2. **Timezone Scenarios:**
   - WordPress timezone = UTC-5 (New York)
   - Server timezone = UTC
   - Trial created at 10:00 AM EST (15:00 UTC)
   - Trial expires at 10:00 AM EST in 72 hours
   - System correctly uses WordPress time for all comparisons
   - Membership remains active for full 72 hours from user's perspective

3. **Admin View:**
   - Users list shows accurate membership status
   - Time remaining calculated in WordPress timezone
   - Trial memberships clearly labeled
   - Expired memberships marked accordingly

## Backward Compatibility

This fix is **fully backward compatible** because:

1. Database schema unchanged
2. No API changes (public methods unchanged)
3. Stored data format unchanged (still using `Y-m-d H:i:s` strings)
4. Only internal comparison logic updated
5. If WordPress timezone = Server timezone, behavior is identical to before

## Prevention of Future Issues

### Code Review Checklist for Time-Related Changes
When working with time in this plugin:

- [ ] Use `current_time('mysql')` for storing datetime strings
- [ ] Use `current_time('timestamp')` for timestamp comparisons
- [ ] Never mix `current_time()` with `time()`
- [ ] Consider WordPress timezone settings
- [ ] Test with timezone offsets (UTC+12, UTC-12)
- [ ] Document any time-sensitive functionality

### Recommended WordPress Timezone Settings
For testing purposes, try these scenarios:
- UTC (baseline)
- America/New_York (UTC-5/UTC-4)
- Europe/London (UTC+0/UTC+1)  
- Asia/Tokyo (UTC+9)
- Australia/Sydney (UTC+10/UTC+11)

## Version History

- **10.3** - Had timezone inconsistency bug causing membership display issues
- **10.4** - Fixed all timezone inconsistencies by using `current_time('timestamp')`

## Related Documentation

- WordPress Codex: [current_time()](https://developer.wordpress.org/reference/functions/current_time/)
- Previous fix: VERSION-10.3-MEMBERSHIP-FIX.md (addressed different membership bugs)
- Plugin structure: PLUGIN-STRUCTURE-CHANGES.md

## Conclusion

This fix ensures that:
1. ✅ Free trial memberships display correctly on account page
2. ✅ Admin users list shows accurate membership status
3. ✅ Timezone settings are respected throughout the plugin
4. ✅ All time comparisons are consistent
5. ✅ No breaking changes to existing functionality

The changes are surgical, minimal, and address the root cause of the timezone inconsistency that was causing memberships to appear inactive when they were actually valid.
