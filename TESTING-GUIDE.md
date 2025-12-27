# Testing Guide for Stripe Inline Payment & Form Width Changes

This guide will help you test the new inline payment feature and wider forms.

## Prerequisites

Before testing, ensure:
1. WordPress is installed and the plugin is activated
2. Stripe is configured in **Membership > Settings**:
   - Stripe is enabled
   - Publishable Key is set
   - Secret Key is set
3. You have Stripe test mode enabled (recommended for testing)

## Test Stripe Cards

For testing, use Stripe's test card numbers:
- **Success:** `4242 4242 4242 4242`
- **Declined:** `4000 0000 0000 0002`
- **Requires Authentication:** `4000 0025 0000 3155`

Use any future expiration date (e.g., 12/34), any 3-digit CVC, and any postal code.

## Test Cases

### 1. Form Width Testing

**Desktop Testing:**
1. Open the registration page (`/membership-register/`)
2. Verify the form is wider than before (should be ~700px instead of 500px)
3. The form should not require vertical scrolling for most screen sizes
4. Check that all form fields are easily readable

**Mobile Testing:**
1. Resize your browser to mobile width (or use a mobile device)
2. Verify the form still fits properly on small screens
3. Form should remain responsive and user-friendly

### 2. Inline Payment - Registration Flow

**Test successful registration with inline payment:**

1. Navigate to `/membership-register/`
2. Fill in the registration form:
   - Username: `testuser123`
   - Email: `test@example.com`
   - Password: `Test1234!`
   - Confirm Password: `Test1234!`
3. Ensure **"Credit Card (Stripe)"** is selected as payment method
4. Click **"Register & Pay"**
5. **Expected behavior:**
   - Form should NOT redirect to Stripe
   - A payment section should appear below with card input fields
   - Button text changes to "Complete Payment"
6. Enter test card details:
   - Card number: `4242 4242 4242 4242`
   - Expiration: Any future date (e.g., `12/34`)
   - CVC: Any 3 digits (e.g., `123`)
   - Postal code: Any code (e.g., `12345`)
7. Click **"Complete Payment"**
8. **Expected result:**
   - Payment processes inline (no redirect)
   - Success message appears
   - Redirected to login page with success message
   - You can log in with the created account

### 3. Inline Payment - Account Extension Flow

**Test membership extension with inline payment:**

1. Log in to the account page (`/my-account/`)
2. Click on any **"Extend"** button under the membership options
3. Payment gateway selector should appear
4. Click the **Stripe** payment method button
5. **Expected behavior:**
   - Payment form appears inline below the gateway selection
   - Card input fields are displayed
   - "Complete Payment" button appears
6. Enter test card details (same as above)
7. Click **"Complete Payment"**
8. **Expected result:**
   - Payment processes inline
   - Success message appears
   - Page refreshes showing updated membership

### 4. Error Handling

**Test error scenarios:**

1. **Invalid card:**
   - Use card `4000 0000 0000 0002`
   - Should show error message inline (not as alert)
   
2. **Missing fields:**
   - Try submitting without filling card details
   - Should show validation error
   
3. **Authentication required:**
   - Use card `4000 0025 0000 3155`
   - Should trigger 3D Secure authentication modal
   - Complete authentication to test full flow

### 5. PayPal Still Works

**Verify PayPal hasn't broken:**

1. During registration or extension, select **PayPal** instead of Stripe
2. Should redirect to PayPal as before
3. This ensures backward compatibility

## What to Look For

### ✅ Success Indicators:
- Form is wider (~700px) on desktop
- No redirect to Stripe for card payments
- Payment fields appear inline on the same page
- Error messages appear in styled boxes (not browser alerts)
- Payment completes without leaving the page
- Membership is activated/extended after payment

### ❌ Issues to Report:
- Forms are still narrow (500px)
- Redirects to Stripe's hosted page
- Alert boxes appear instead of styled messages
- Payment section doesn't show
- JavaScript errors in browser console
- Payment doesn't complete

## Browser Console

Open your browser's developer console (F12) to check for:
- JavaScript errors
- Network errors
- Stripe initialization messages

## Notes

- The inline payment only works when Stripe.js loads successfully
- Ensure your Stripe publishable key is valid and for the correct mode (test/live)
- If experiencing issues, check that HTTPS is enabled (Stripe requires it)
- Make sure your Stripe secret key matches the publishable key (test with test, live with live)

## Rollback

If issues occur, the system maintains backward compatibility:
- PayPal continues to work
- Legacy Stripe checkout (redirect-based) is still available as fallback
- No existing functionality has been removed

## Support

For any issues during testing, check:
1. Browser console for errors
2. WordPress debug log
3. Stripe dashboard for payment attempts
4. Network tab for failed API calls
