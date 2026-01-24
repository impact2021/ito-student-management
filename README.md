# IELTS Membership System

A complete membership and payment system for IELTS preparation courses with PayPal and Stripe integration, course module management, and free trial support.

## Features

- **Course Module Management**
  - General Training and Academic module options
  - Module-based course access control
  - Custom post types: ielts_course, ielts_lesson, ielts_resource, ielts_quiz
  - Course taxonomy for organizing by module

- **Free Trial System**
  - Configurable trial duration (default: 3 days)
  - One trial per email address enforcement
  - Toggle trial availability on/off
  - Module selection during trial signup

- **Membership Management**
  - 90-day membership ($24.95 USD)
  - Membership extensions (1 week - $5, 1 month - $10, 3 months - $20)
  - Automatic expiration tracking
  - Module-specific access control (General Training, Academic, or Both)
  - Full admin access without membership requirement

- **Email Notifications**
  - Trial enrollment confirmation
  - Trial expiration notice
  - Paid enrollment confirmation
  - Paid membership expiration notice
  - Fully customizable email templates
  - Support for dynamic placeholders

- **Payment Integration**
  - PayPal Standard integration
  - Stripe inline payment with Payment Elements (no redirect required)
  - Secure payment processing
  - Payment history tracking

- **Custom Login System**
  - Custom login/registration pages
  - Forgot password functionality
  - Replaces WordPress default login

- **Account Management**
  - View membership status and expiration
  - Change email address
  - Change password
  - View payment history
  - Purchase/extend membership

## Installation

1. Upload the plugin folder to the `/wp-content/plugins/` directory (or install directly from GitHub by cloning this repository into the plugins directory)
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Membership > Settings to configure payment gateways
4. The plugin will automatically create required pages:
   - Membership Login (`/membership-login/`)
   - Membership Registration (`/membership-register/`)
   - My Account (`/my-account/`)

## Configuration

### PayPal Setup

1. Go to **Membership > Settings**
2. Under PayPal Settings:
   - Enable PayPal
   - Enter your PayPal business email
   - Enable sandbox mode for testing (optional)
3. Save settings

### Stripe Setup

1. Go to **Membership > Settings**
2. Under Stripe Settings:
   - Enable Stripe
   - Enter your Stripe Publishable Key
   - Enter your Stripe Secret Key
   - (Optional) Configure webhook for advanced features
3. Save settings

**Stripe Webhook URL:** `https://yoursite.com/wp-admin/admin-ajax.php?action=ielts_ms_stripe_webhook`

### Login Settings

- Enable/disable custom login page replacement
- Default: WordPress login redirects to custom login page
- Set a custom homepage for logged-in users (non-admins will be redirected to this page when visiting the homepage)

### Pricing Settings

Customize membership pricing in **Membership > Settings** under the Pricing Settings section:
- **New 90-Day Membership** - Default: $24.95 USD
- **1 Week Extension** - Default: $5.00 USD
- **1 Month Extension** - Default: $10.00 USD
- **3 Months Extension** - Default: $20.00 USD

All prices can be adjusted to suit your business needs.

### Trial Settings

Configure free trial availability in **Membership > Settings** under the Trial Settings section:
- **Enable Free Trial** - Toggle to enable/disable free trial for new users
- **Trial Duration** - Set trial duration in days (default: 3 days)

**Note:** One trial per email address is automatically enforced. Users can select their course module (General Training, Academic, or Both) when signing up for the trial.

### Email Settings

Customize all email notifications in **Membership > Settings** under the Email Settings section:

**Sender Configuration:**
- **From Name** - The name emails will be sent from
- **From Email** - The email address emails will be sent from

**Email Templates:**
Each email template supports the following placeholders:
- `{user_name}` - User's display name
- `{user_email}` - User's email address
- `{enrollment_type}` - Selected course module (General Training, Academic, or Both)
- `{duration}` - Membership duration in days
- `{expiry_date}` - Membership expiration date
- `{account_url}` - Link to user's account page
- `{site_name}` - Your site name
- `{site_url}` - Your site URL

**Customizable Templates:**
1. **Trial Enrollment Email** - Sent when user starts free trial
2. **Trial Expiration Email** - Sent when trial expires
3. **Paid Enrollment Email** - Sent when user purchases membership
4. **Paid Membership Expiration Email** - Sent when paid membership expires

### Course Module Management

The system includes custom post types for managing IELTS courses:

1. **IELTS Courses** - Main course container
2. **IELTS Lessons** - Individual lessons within courses
3. **IELTS Resources** - Downloadable resources and materials
4. **IELTS Quizzes** - Assessment and practice quizzes

**Module Taxonomy:**
- Courses can be assigned to "General Training" or "Academic" modules
- Users only see courses matching their enrollment type
- Admins have full access to all courses regardless of enrollment

## Usage

### Shortcodes

- `[ielts_membership_login]` - Display login form
- `[ielts_membership_register]` - Display registration form
- `[ielts_membership_account]` - Display account page

### For Users

1. **Register:** Visit the registration page to create an account
2. **Login:** Use the login page to access your account
3. **Purchase:** From the account page, select a membership plan
4. **Manage:** Change email/password, view membership status
5. **Extend:** Current or expired members can extend their membership

### For Administrators

- **Members:** View all members and their status
- **Payments:** Track all payment transactions
- **Settings:** Configure payment gateways and system options

## Integration with IELTS Course Manager

This plugin integrates with the IELTS Course Manager plugin to control access:

- Active members automatically get the "active" role
- Expired members automatically get the "expired" role
- Users with "active" role have access to all IELTS courses
- Access is checked via the `ielts_cm_has_course_access` filter

## Pricing Plans

### New Membership
- **90 Days:** $24.95 USD
- Full access to all content

### Extensions (for existing/expired members)
- **1 Week:** $5.00 USD
- **1 Month:** $10.00 USD (Recommended)
- **3 Months:** $20.00 USD (Best Value)

## Database Tables

The plugin creates three custom tables:

1. `wp_ielts_ms_memberships` - Stores membership records with enrollment type and trial status
2. `wp_ielts_ms_payments` - Stores payment transactions
3. `wp_ielts_ms_trial_usage` - Tracks trial usage by email to enforce one trial per email

## Support

For support or questions, contact the plugin maintainer at IELTStestONLINE.

## Changelog

### Version 10.0 (2026-01-24)
- **NEW**: Added course module system (General Training and Academic)
- **NEW**: Registered custom post types: ielts_course, ielts_lesson, ielts_resource, ielts_quiz
- **NEW**: Added ielts_module taxonomy for organizing courses
- **NEW**: Implemented free trial system with configurable duration
- **NEW**: One trial per email address enforcement
- **NEW**: Trial toggle in admin settings
- **NEW**: Complete email notification system
  - Trial enrollment confirmation email
  - Trial expiration email
  - Paid enrollment confirmation email
  - Paid membership expiration email
- **NEW**: Customizable email templates with placeholder support
- **NEW**: Email sender configuration
- **NEW**: Module-based course access control
- **NEW**: Enrollment type selection during registration (General Training, Academic, or Both)
- **NEW**: Trial/Paid membership toggle on registration page
- **ENHANCED**: Admin access - admins now have full access without membership requirement
- **ENHANCED**: Membership database schema with enrollment_type and is_trial columns
- **ENHANCED**: Payment gateways updated to support enrollment types
- **ENHANCED**: Registration flow supports trial and module selection
- **ENHANCED**: JavaScript handling for dynamic form behavior

### Version 9.0 (2025-12-31)
- Updated plugin version from 3.6 to 9.0
- Removed legacy course link and notice for pre-2026 enrollees from login page
- Removed legacy-related CSS styles (.ielts-ms-legacy-notice, .ielts-ms-legacy-link)
- Updated admin documentation to reflect removed features

### Version 3.6
- Fixed PayPal 500 error in admin-ajax.php by adding proper error handling and validation
- Added missing return statement after error responses in PayPal payment handler
- Added comprehensive parameter validation for PayPal payments
- Implemented real-time username availability check during registration
- Implemented real-time email availability check during registration
- Added AJAX endpoints for checking username and email availability
- Added visual feedback for duplicate username/email detection
- Improved user experience with inline validation messages

### Version 3.5
- Fixed PayPal payment error handling to properly display error messages when payment processing fails
- Added missing AJAX error callback for PayPal payment function
- Updated plugin version to 3.5

### Version 3.4
- Updated plugin to version 3.4
- Updated minimum WordPress requirement to 6.0

### Version 3.0.0
- Updated plugin to version 3.0.0

### Version 2.0.0
- Updated plugin to version 2.0.0
- Fixed admin login loop: Admins can now access wp-admin without being redirected to the membership account page
- Implemented custom user roles: 'active' for users with active memberships and 'expired' for users with expired memberships
- Added automatic daily cron job to update user roles based on membership status
- Improved access control for administrators
- **NEW**: Added configurable pricing settings in admin panel for enrollment and extensions
- **NEW**: Added custom homepage redirect for logged-in users (non-admins)
- Fixed SQL query to use prepared statements for security
- Improved database status verification in course access check

### Version 1.2.0
- Implemented Stripe inline payment using Payment Elements (no redirect to Stripe required)
- Increased form width from 500px to 700px for better desktop viewing experience
- Added support for embedded card payment directly on registration and account pages
- Improved user experience with seamless payment flow

### Version 1.1.0
- Added Documentation page in admin sidebar with comprehensive shortcodes reference
- Detailed descriptions and usage examples for all available shortcodes
- Updated plugin version to 1.1.0

### Version 1.0.0
- Initial release
- PayPal and Stripe integration
- Custom login/registration system
- Account management
- Membership system with extensions
- Admin dashboard for members and payments

## License

GPL v2 or later
