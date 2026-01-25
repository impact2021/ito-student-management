# User Interface Guide - Version 11.0

## Overview
This document describes the simplified user interface for the IELTS Membership System v11.0.

## 1. Users List Page (wp-admin/users.php)

When you navigate to the WordPress Users page, you will see:

### User Columns
- **Username** - Standard WordPress column
- **Name** - Standard WordPress column  
- **Email** - Standard WordPress column
- **Membership Status** - NEW CUSTOM COLUMN showing:
  - **For Active Members:**
    - Membership type in bold blue: "Academic" or "General Training"
    - Time remaining in small grey text: "X days remaining"
  - **For Expired Members:**
    - Red bold text: "Expired"
    - Membership type in small grey text
  - **For Users Without Membership:**
    - Grey text: "No Membership"

### Example Display
```
John Smith    Academic                      25 days remaining
Jane Doe      General Training              10 days remaining
Bob Jones     Expired                       Academic
Mary Wilson   No Membership
```

## 2. User Profile Edit Page (wp-admin/user-edit.php?user_id=X)

When editing a user profile, scroll down to find the new section:

### Membership Management Section

**Section Title:** Membership Management

#### Fields:

1. **Membership Type** (Dropdown)
   - Options:
     - General Training
     - Academic
   - Description: "Select the membership type for this user."

2. **Membership Expiry Date** (DateTime input)
   - Input type: datetime-local
   - Format: YYYY-MM-DD HH:MM
   - Description: "Set when this user's membership will expire. Leave empty to keep current expiry."
   - Shows current expiry if membership exists: "Current expiry: [Date in readable format]"

3. **Current Status** (Read-only, only shown if membership exists)
   - Shows:
     - "✓ Active" in blue if membership is active
     - "✗ Expired" in red if membership has expired

### Example Display
```
┌─────────────────────────────────────────────────┐
│ Membership Management                            │
├─────────────────────────────────────────────────┤
│ Membership Type:                                 │
│ [▼ Academic          ]                          │
│ Select the membership type for this user.       │
│                                                  │
│ Membership Expiry Date:                         │
│ [2024-02-25 23:59  ]                           │
│ Set when this user's membership will expire.    │
│ Leave empty to keep current expiry.             │
│ Current expiry: February 25, 2024 11:59 PM     │
│                                                  │
│ Current Status:                                  │
│ ✓ Active                                        │
└─────────────────────────────────────────────────┘
```

## 3. Creating a New Membership

**Steps:**
1. Go to Users > All Users
2. Click "Edit" on a user
3. Scroll to "Membership Management"
4. Select membership type (Academic or General Training)
5. (Optional) Set expiry date - if left empty, will default to 30 days from now
6. Click "Update User"

**Result:**
- User gets a new membership
- If no expiry date set: membership expires in 30 days
- If expiry date set: membership expires on that date
- User status shows "Active" in users list

## 4. Editing an Existing Membership

**Steps:**
1. Go to Users > All Users
2. Click "Edit" on a user with membership
3. Scroll to "Membership Management"
4. Change membership type if needed
5. Update expiry date if needed
6. Click "Update User"

**Result:**
- Membership type is updated
- Expiry date is updated if changed
- Status automatically updates based on expiry date

## 5. What's NOT Shown

The following are hidden/removed in v11.0:
- ❌ Admin menu for "Membership" settings
- ❌ Payment pages
- ❌ Course access configuration
- ❌ Email template settings
- ❌ Trial membership checkbox
- ❌ "Both" membership type option
- ❌ Stripe/PayPal configuration

## 6. Database Structure

The membership data is stored in the database table: `wp_ielts_ms_memberships`

### Key Fields Used:
- `user_id` - WordPress user ID
- `status` - "active" or "expired"
- `enrollment_type` - "academic" or "general_training"
- `is_trial` - Always set to 0 in v11.0
- `start_date` - When membership started
- `end_date` - When membership expires
- `created_date` - When record was created
- `updated_date` - When record was last updated

### Unused Fields (from previous versions):
- `is_trial` - Set to 0, kept for future use
- Payment-related tables are not used but preserved
