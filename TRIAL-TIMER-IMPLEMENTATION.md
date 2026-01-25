# Trial Timer Implementation - Complete Documentation

## Overview

The trial timer is a floating widget that displays in the bottom-left corner of the page for users on a free trial. It shows the remaining time and provides a quick link to upgrade to a full membership.

## Visual Appearance

![Trial Timer Demo](https://github.com/user-attachments/assets/63ace2cc-dd66-42b4-85c3-bbcad0a1b883)

The timer displays in three states:
1. **Normal State** (>2 hours remaining): Blue text showing countdown
2. **Warning State** (<2 hours remaining): Red text showing countdown
3. **Expired State**: Red text displaying "Expired"

## When Timer Displays

The timer appears when ALL of the following conditions are met:
- User is logged in
- User has an active membership (`status = 'active'`)
- Membership is a trial (`is_trial = 1`)
- Trial end date is in the future (not expired)

## Features

✓ **Auto-updating**: Refreshes every 60 seconds  
✓ **Fixed position**: Always visible in bottom-left corner  
✓ **Warning state**: Turns red when less than 2 hours remain  
✓ **Upgrade link**: Configurable button to upgrade membership  
✓ **Auto-hide**: Disappears when trial expires  
✓ **Responsive**: Adapts to mobile screens  

## Admin Configuration

Navigate to: **WordPress Admin → Settings → IELTS Membership System → Trial Settings**

### Available Settings:
1. **Enable/Disable Trials**: Toggle trial registration availability
2. **Trial Duration**: Set duration in hours (default: 72 hours)
3. **Upgrade Link**: URL for the "Upgrade to Full Membership" button
   - Example: `https://yoursite.com/my-account/#extend-course`
   - Can link to any page, typically the account page with payment options

## Technical Implementation

### PHP (ielts-membership-system.php, lines 357-376)

```php
// Get trial information for current user
$trial_data = array(
    'isTrial' => false,
    'endTime' => '',
    'upgradeLink' => esc_url(get_option('ielts_ms_trial_upgrade_link', ''))
);

if (is_user_logged_in()) {
    $user_id = get_current_user_id();
    $membership = new IELTS_MS_Membership();
    $user_membership = $membership->get_user_membership($user_id);
    
    if ($user_membership && $user_membership->status === 'active' && $user_membership->is_trial === 1) {
        $end_timestamp = strtotime($user_membership->end_date);
        // Only show timer if trial hasn't expired yet
        if ($end_timestamp > time()) {
            $trial_data['isTrial'] = true;
            $trial_data['endTime'] = $end_timestamp;
        }
    }
}

wp_localize_script('ielts-membership-script', 'ieltsMS', array(
    'trial' => $trial_data
    // ... other data
));
```

**Key Points:**
- Retrieves user's membership from database
- Validates trial status and expiration
- Passes data to JavaScript via `wp_localize_script`
- Upgrade link configured in WordPress admin settings

### JavaScript (assets/js/script.js, lines 920-977)

```javascript
// Trial Timer Functionality
if (ieltsMS.trial && ieltsMS.trial.isTrial && ieltsMS.trial.endTime) {
    let timerInterval;
    
    // Validate endTime
    const endTime = Number(ieltsMS.trial.endTime);
    if (isNaN(endTime) || endTime <= 0) {
        console.error('Invalid trial end time');
        return;
    }
    
    // Create and append the timer HTML
    const timerDiv = $('<div>').addClass('ielts-ms-trial-timer');
    const headerDiv = $('<div>').addClass('timer-header').text('Free Trial Time Remaining');
    const displayDiv = $('<div>').addClass('timer-display').attr('id', 'trial-countdown').text('--:--');
    
    timerDiv.append(headerDiv).append(displayDiv);
    
    if (ieltsMS.trial.upgradeLink) {
        const upgradeLink = $('<a>')
            .addClass('timer-upgrade-link')
            .attr('href', ieltsMS.trial.upgradeLink)
            .text('Upgrade to Full Membership');
        timerDiv.append(upgradeLink);
    }
    
    $('body').append(timerDiv);
    
    // Function to update the timer
    function updateTrialTimer() {
        const now = Math.floor(Date.now() / 1000);
        const remaining = endTime - now;
        
        if (remaining <= 0) {
            $('#trial-countdown').text('Expired').addClass('warning');
            clearInterval(timerInterval);
            return;
        }
        
        const hours = Math.floor(remaining / 3600);
        const minutes = Math.floor((remaining % 3600) / 60);
        const displayText = hours + 'h ' + minutes + 'm';
        $('#trial-countdown').text(displayText);
        
        // Add warning class if less than 2 hours remaining
        if (remaining < 7200) {
            $('#trial-countdown').addClass('warning');
        }
    }
    
    // Update immediately and then every minute
    updateTrialTimer();
    timerInterval = setInterval(updateTrialTimer, 60000);
}
```

**Key Points:**
- Validates trial data exists and is valid
- Creates timer HTML dynamically using jQuery
- Updates countdown every 60 seconds (60000ms)
- Handles expiration by stopping updates and showing "Expired"
- Adds warning class when <2 hours remain

### CSS (assets/css/style.css, lines 651-716)

```css
/* Floating Trial Timer */
.ielts-ms-trial-timer {
    position: fixed;
    bottom: 20px;
    left: 20px;
    background: #fff;
    border: 2px solid #0073aa;
    border-radius: 8px;
    padding: 15px 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    min-width: 280px;
}

.ielts-ms-trial-timer .timer-header {
    font-size: 12px;
    color: #666;
    margin-bottom: 8px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ielts-ms-trial-timer .timer-display {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 10px;
    font-variant-numeric: tabular-nums;
}

.ielts-ms-trial-timer .timer-display.warning {
    color: #d63638;  /* Red color for warnings */
}

.ielts-ms-trial-timer .timer-upgrade-link {
    display: inline-block;
    padding: 8px 16px;
    background: #0073aa;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
    transition: background-color 0.2s;
}

.ielts-ms-trial-timer .timer-upgrade-link:hover {
    background: #005a87;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .ielts-ms-trial-timer {
        left: 10px;
        bottom: 10px;
        min-width: 250px;
        padding: 12px 16px;
    }
    
    .ielts-ms-trial-timer .timer-display {
        font-size: 20px;
    }
}
```

**Key Points:**
- Fixed positioning ensures timer stays in bottom-left corner
- High z-index (9999) keeps it above other content
- Responsive design adjusts for mobile screens
- Warning class changes color to red (#d63638)
- Smooth hover effects on upgrade button

## Database Schema

The trial information is stored in the memberships table:

```sql
CREATE TABLE wp_ielts_ms_memberships (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    status varchar(20) DEFAULT 'active',
    enrollment_type varchar(20) DEFAULT 'both',
    is_trial tinyint(1) DEFAULT 0,  -- 1 for trial, 0 for paid
    start_date datetime NOT NULL,
    end_date datetime NOT NULL,
    created_date datetime DEFAULT CURRENT_TIMESTAMP,
    updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY status (status),
    KEY is_trial (is_trial)
);
```

## Testing the Timer

To test the timer functionality:

1. **Enable Trials** in admin settings
2. **Set Trial Duration** (e.g., 72 hours)
3. **Configure Upgrade Link** (e.g., your account page URL)
4. **Register a new trial user** via the registration form
5. **Log in as the trial user** - timer should appear in bottom-left corner
6. **Verify countdown updates** every minute
7. **Test warning state** by setting a short trial duration (e.g., 1 hour)
8. **Test expiration** - timer should show "Expired" when trial ends

## Troubleshooting

### Timer Not Showing

Check these conditions:
1. User is logged in
2. User has an active membership
3. Membership has `is_trial = 1` in database
4. Trial `end_date` is in the future
5. JavaScript is enabled in browser
6. No JavaScript errors in browser console

### Timer Shows "Expired" Immediately

- Trial `end_date` is in the past
- System time is incorrect
- Database timezone settings issue

### Upgrade Link Not Working

- Check admin settings: **Settings → IELTS Membership System → Trial Settings**
- Ensure URL is valid and properly formatted
- Link should start with `http://` or `https://`

## Files Modified

- `ielts-membership-system.php` - Added future date validation (lines 369-375)

## Files Involved (No Changes)

- `assets/js/script.js` - Timer JavaScript logic (lines 920-977)
- `assets/css/style.css` - Timer styling (lines 651-716)
- `admin/class-admin.php` - Admin settings configuration
- `includes/class-database.php` - Database schema
- `includes/class-membership.php` - Membership management
- `includes/class-login-manager.php` - Trial registration handling

## Changelog

### 2026-01-25
- Added future date validation to prevent timer from displaying for expired trials
- Improved robustness by checking `$end_timestamp > time()` before enabling timer
- Created comprehensive documentation
- Added demo page showing all timer states

## Support

For questions or issues, please refer to:
- WordPress admin: **Settings → IELTS Membership System**
- Plugin documentation
- GitHub repository issues
