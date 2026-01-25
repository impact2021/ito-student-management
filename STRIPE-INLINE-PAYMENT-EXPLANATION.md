# Stripe Inline Payment - How It Works

## Overview

The IELTS Membership System uses **Stripe's Payment Elements** to provide a seamless, secure inline payment experience. Unlike traditional payment flows that redirect users to external payment pages, this implementation allows customers to enter their payment information directly on your website and complete the entire registration and payment process without ever leaving the page.

## What "Inline Payment" Means

**Inline payment** means the credit card form is embedded directly into your webpage, not opened in a popup or redirect. When a user registers for a paid membership:

1. They fill out their account information (name, email, password)
2. They select their course module (General Training, Academic, or Both)
3. **The credit card payment form appears right there on the same page**
4. They enter their payment details in that embedded form
5. They click "Register & Pay" once and everything happens instantly
6. They're redirected to login - no external Stripe pages, no popups

**Key Benefit**: The user never leaves your website. It creates a smooth, professional experience that looks like the payment system is part of your site.

---

## How It Works - Step by Step

### **For Your Customers** (What They See)

1. **Visit Registration Page**: Customer goes to the membership registration page
2. **Fill Account Details**: They enter first name, last name, username, email, and password
3. **Choose Course Module**: They select which IELTS module they want (General Training, Academic, or Both)
4. **Choose Membership Type**: They select "Paid Membership" (or "Free Trial" if enabled)
5. **See Payment Form Appear**: When they select "Paid Membership", a credit card form automatically appears on the same page
6. **Enter Card Details**: They type their credit card number, expiration date, and CVC code directly into the embedded form
7. **Click Register & Pay**: With one click, the system:
   - Creates their user account
   - Processes the payment
   - Activates their membership
8. **Redirect to Login**: After successful payment, they're sent to the login page to access their account

**Total Time**: Usually 30-60 seconds from start to finish, all on one page.

---

### **Behind The Scenes** (Technical Flow)

Here's what happens when a customer completes registration with payment:

#### **Phase 1: Page Load & Initialization**

1. **Customer lands on registration page** (`/membership-register/`)
2. **Stripe JavaScript library loads** from Stripe's secure servers
3. **Payment form initializes** (but stays hidden until needed)
   - Stripe Elements creates a secure payment form using your Stripe Publishable Key
   - The form is styled to match your website's look
   - All sensitive card data is handled by Stripe's servers, never touching your WordPress server

#### **Phase 2: Customer Selects Paid Membership**

4. **Customer selects "Paid Membership" option**
5. **Payment section slides into view** with the embedded Stripe card form
6. **Stripe Elements displays the secure card input**
   - Card number field (with automatic card type detection)
   - Expiration date field
   - CVC/CVV security code field
   - Billing postal code (optional)

#### **Phase 3: Form Submission**

7. **Customer clicks "Register & Pay" button**
8. **JavaScript validates the payment form first**
   - Card details are validated by Stripe (without submitting yet)
   - If invalid, shows error immediately (e.g., "Card number is incomplete")
   - If valid, proceeds to next step

#### **Phase 4: Account Creation**

9. **AJAX request sent to WordPress backend**
   - Action: `ielts_ms_register_with_payment`
   - Data: First name, last name, username, email, password, enrollment type
10. **WordPress validates and creates user account**
    - Checks username and email aren't already taken
    - Creates WordPress user with proper role
    - Stores enrollment type preference
    - Sets account as "pending payment"
11. **Backend returns user ID** back to the JavaScript

#### **Phase 5: Payment Intent Creation**

12. **JavaScript makes second AJAX request to create Payment Intent**
    - Action: `ielts_ms_create_payment_intent`
    - Data: User ID, amount ($24.95), duration (90 days), plan type
13. **WordPress backend calls Stripe API**
    - Creates a Payment Intent with Stripe
    - Amount: $24.95 USD (converted to 2495 cents for Stripe)
    - Uses automatic payment methods
    - Includes metadata: user_id, duration, payment_type, plan_key
14. **Stripe returns a "client secret"**
    - This is a one-time-use key to confirm this specific payment
    - Sent back to the JavaScript

#### **Phase 6: Payment Confirmation**

15. **JavaScript uses Stripe SDK to confirm payment**
    - Calls `stripe.confirmPayment()` with the client secret
    - Stripe securely collects the card data from the payment form
    - Stripe processes the payment directly with the customer's bank
    - **Important**: Your server never sees or handles the actual card details
16. **Stripe processes the transaction**
    - Validates card details
    - Checks for fraud
    - Charges the card
    - Returns success or failure

#### **Phase 7: Completion**

17. **If payment succeeds:**
    - JavaScript receives confirmation from Stripe with Payment Intent ID
    - Makes third AJAX request to confirm payment on server
    - Action: `ielts_ms_confirm_payment`
    - Backend updates payment record from "pending" to "completed"
    - Backend creates/activates membership for 90 days
    - Backend removes "pending payment" status from user
    - Success message shown: "Payment successful! Redirecting..."
    - Customer redirected to login page after 1 second
    
18. **If payment fails:**
    - Error message shown (e.g., "Card was declined")
    - Customer can try again with different card
    - User account remains in "pending payment" state
    - No membership is activated

---

## Security & Compliance

### **PCI Compliance Made Easy**

The inline payment system is **PCI DSS compliant** without you needing to do anything special. Here's why:

- **Card data never touches your server**: When customers type their card number, it goes directly from their browser to Stripe's servers via encrypted connection
- **Stripe Elements handles all sensitive data**: The card form is actually an iframe from Stripe
- **Your WordPress database never stores card numbers**: Only transaction IDs and payment status are saved
- **3D Secure authentication supported**: For cards that require it, Stripe handles the authentication flow

### **How Data is Protected**

1. **SSL/HTTPS Required**: Payment forms only work on HTTPS websites
2. **Tokenization**: Card details are converted to secure tokens by Stripe
3. **Encryption**: All data transmitted between browser and Stripe is encrypted
4. **No sensitive data logs**: Card numbers are never written to WordPress logs
5. **Stripe Security**: Stripe is certified to PCI Service Provider Level 1 (the highest level)

### **What Gets Stored in Your Database**

Your WordPress database only stores:
- User account information (name, email, username)
- Membership details (start date, end date, enrollment type)
- Payment records (amount, date, transaction ID from Stripe, payment status)
- **Never stored**: Credit card numbers, CVV codes, or full card details

---

## Technical Components

### **Frontend (What Runs in the Browser)**

**File**: `/assets/js/script.js`

**Key Functions**:
- `initializeRegistrationStripeElements()` - Sets up the Stripe payment form when page loads
- `handleStripeInlineRegistration()` - Processes the registration when user submits
- `createPaymentIntentAndProcessRegistration()` - Creates payment intent and confirms payment
- `confirmPaymentOnServer()` - Finalizes payment on WordPress backend

**Stripe Elements Configuration**:
```javascript
elements = stripe.elements({
    mode: 'payment',           // One-time payment (not subscription)
    amount: 2495,              // $24.95 in cents
    currency: 'usd',           // US Dollars
    appearance: {              // Styling to match your site
        theme: 'stripe',
        variables: {
            colorPrimary: '#0073aa'
        }
    }
});
```

### **Backend (What Runs on WordPress Server)**

**File**: `/includes/class-stripe-gateway.php`

**Key Functions**:
- `create_payment_intent()` - Creates Stripe Payment Intent via API
- `confirm_payment()` - Confirms successful payment and activates membership
- `handle_callback()` - Processes Stripe webhook notifications (backup confirmation)

**Stripe API Call**:
```php
$stripe_data = array(
    'amount' => 2495,                                    // $24.95 in cents
    'currency' => 'usd',                                 // US Dollars
    'automatic_payment_methods' => array('enabled' => true), // Accept all card types
    'metadata' => array(
        'user_id' => 123,
        'duration_days' => 90,
        'payment_type' => 'new',
        'plan_key' => 'new_90'
    )
);
```

### **Template (What Customers See)**

**File**: `/templates/register-form.php`

**Key Elements**:
- Standard HTML form for account details
- Radio buttons for membership type (trial vs paid)
- Radio buttons for course module selection
- Payment gateway selection (Stripe vs PayPal)
- `<div id="payment-element">` - Where Stripe injects the secure payment form
- Hidden form fields with pricing data

---

## Payment Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ CUSTOMER BROWSER                                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. Load registration page                                     │
│  2. Fill account details                                       │
│  3. Select "Paid Membership"                                   │
│  4. Enter card details in Stripe Elements form                 │
│  5. Click "Register & Pay"                                     │
│     │                                                           │
│     ├──────────────────────────────────────────────────────────┤
│     │ JavaScript Validation                                    │
│     └──────────────────────────────────────────────────────────┤
│     │                                                           │
└─────┼───────────────────────────────────────────────────────────┘
      │
      │ AJAX Request 1: Create User Account
      ▼
┌─────────────────────────────────────────────────────────────────┐
│ WORDPRESS BACKEND (YOUR SERVER)                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  6. Validate username, email, password                         │
│  7. Create WordPress user account                              │
│  8. Store enrollment type                                      │
│  9. Mark as "pending payment"                                  │
│ 10. Return user_id to browser                                  │
│     │                                                           │
└─────┼───────────────────────────────────────────────────────────┘
      │
      │ AJAX Request 2: Create Payment Intent
      ▼
┌─────────────────────────────────────────────────────────────────┐
│ WORDPRESS BACKEND (YOUR SERVER)                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ 11. Validate amount and plan                                   │
│ 12. Create pending payment record in database                  │
│ 13. Call Stripe API to create Payment Intent                   │
│     │                                                           │
└─────┼───────────────────────────────────────────────────────────┘
      │
      │ API Request: Create Payment Intent
      ▼
┌─────────────────────────────────────────────────────────────────┐
│ STRIPE SERVERS                                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ 14. Create Payment Intent                                      │
│ 15. Generate client_secret                                     │
│ 16. Return client_secret to WordPress                          │
│     │                                                           │
└─────┼───────────────────────────────────────────────────────────┘
      │
      │ Client Secret returned to browser
      ▼
┌─────────────────────────────────────────────────────────────────┐
│ CUSTOMER BROWSER                                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ 17. JavaScript calls stripe.confirmPayment()                   │
│ 18. Stripe Elements sends card data directly to Stripe         │
│     │                                                           │
└─────┼───────────────────────────────────────────────────────────┘
      │
      │ Card details sent securely (bypasses WordPress)
      ▼
┌─────────────────────────────────────────────────────────────────┐
│ STRIPE SERVERS                                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ 19. Process card payment                                       │
│ 20. Contact customer's bank                                    │
│ 21. Charge the card                                            │
│ 22. Return success or error                                    │
│     │                                                           │
└─────┼───────────────────────────────────────────────────────────┘
      │
      │ Payment result returned to browser
      ▼
┌─────────────────────────────────────────────────────────────────┐
│ CUSTOMER BROWSER                                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ 23. Receive payment success                                    │
│ 24. Display success message                                    │
│     │                                                           │
└─────┼───────────────────────────────────────────────────────────┘
      │
      │ AJAX Request 3: Confirm Payment
      ▼
┌─────────────────────────────────────────────────────────────────┐
│ WORDPRESS BACKEND (YOUR SERVER)                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ 25. Update payment record to "completed"                       │
│ 26. Store Stripe transaction ID                                │
│ 27. Create membership (90 days from today)                     │
│ 28. Activate user account                                      │
│ 29. Send welcome email                                         │
│ 30. Return success to browser                                  │
│     │                                                           │
└─────┼───────────────────────────────────────────────────────────┘
      │
      │ Redirect to login page
      ▼
┌─────────────────────────────────────────────────────────────────┐
│ CUSTOMER BROWSER                                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ 31. Show "Payment successful! Redirecting..."                  │
│ 32. Redirect to login page                                     │
│ 33. Customer logs in and accesses courses                      │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## User Experience Comparison

### **Without Inline Payment** (Traditional Redirect Method)
1. User fills registration form
2. Clicks submit
3. **Redirected to Stripe's checkout page** (different website)
4. Enters payment details on Stripe's page
5. **Redirected back to your website**
6. Sees confirmation

**Problems**:
- Multiple page loads (slower)
- Breaks user flow (confusing)
- Looks less professional
- Higher abandonment rate

### **With Inline Payment** (Current Implementation)
1. User fills registration form
2. Payment form appears on same page
3. Enters payment details
4. Clicks one button
5. Sees confirmation

**Benefits**:
- ✅ Stays on your website throughout
- ✅ Single-page experience (faster)
- ✅ Professional appearance
- ✅ Higher conversion rate
- ✅ Mobile-friendly
- ✅ Fewer clicks = less confusion

---

## Configuration Requirements

### **Stripe Dashboard Setup**

To make this work, you need to configure your Stripe account:

1. **Get API Keys**:
   - Log into Stripe Dashboard (https://dashboard.stripe.com)
   - Go to Developers → API Keys
   - Copy your **Publishable Key** (starts with `pk_`)
   - Copy your **Secret Key** (starts with `sk_`)

2. **Enter Keys in WordPress**:
   - Go to Membership → Settings in WordPress admin
   - Under "Stripe Settings" section
   - Paste Publishable Key
   - Paste Secret Key
   - Click "Save Settings"

3. **Enable Payment Methods** (in Stripe Dashboard):
   - Go to Settings → Payment methods
   - Enable the card types you want to accept:
     - ✅ Visa
     - ✅ Mastercard
     - ✅ American Express
     - ✅ Discover (US only)
   - Optional: Enable Apple Pay, Google Pay, Link

4. **Test Mode vs Live Mode**:
   - **Test Mode**: Use test API keys (pk_test_..., sk_test_...) for testing
   - **Live Mode**: Switch to live keys (pk_live_..., sk_live_...) for real payments
   - Toggle between modes in Stripe Dashboard (top right corner)

### **WordPress Settings**

1. **Enable Stripe**:
   - WordPress Admin → Membership → Settings
   - Check "Enable Stripe" checkbox
   - Save settings

2. **HTTPS Required**:
   - Your website **must** use HTTPS (SSL certificate)
   - Stripe will not work on non-secure HTTP sites
   - Most hosting providers offer free SSL certificates (Let's Encrypt)

3. **Test Cards** (for testing in Stripe Test Mode):
   - Success: `4242 4242 4242 4242`
   - Decline: `4000 0000 0000 0002`
   - Any future expiration date (e.g., 12/25)
   - Any 3-digit CVC (e.g., 123)
   - Any billing ZIP code (e.g., 12345)

---

## Error Handling

The system handles various error scenarios gracefully:

### **Card Declined**
- **What happens**: User sees "Your card was declined" message
- **What to tell customer**: "Please try a different payment method or contact your bank"
- **Technical**: Stripe returns decline reason, JavaScript shows user-friendly message

### **Insufficient Funds**
- **What happens**: User sees "Your card has insufficient funds"
- **What to tell customer**: "Please use a different card or add funds to your account"

### **Invalid Card Number**
- **What happens**: Form shows error before submission
- **Real-time validation**: User sees "Your card number is invalid" as they type

### **Payment Processing Error**
- **What happens**: Generic error message shown
- **What to tell customer**: "Please try again in a few moments"
- **Technical**: Server logs the error for debugging

### **Network Connection Lost**
- **What happens**: User sees "An error occurred. Please try again"
- **Safety**: Payment is never charged if confirmation doesn't complete
- **Recovery**: User can retry payment

---

## Refunds & Cancellations

### **How to Issue a Refund**

Refunds are processed through the Stripe Dashboard:

1. **Log into Stripe Dashboard**
2. **Go to Payments section**
3. **Find the payment** (search by customer email or amount)
4. **Click the payment** to view details
5. **Click "Refund payment" button**
6. **Enter refund amount** (full or partial)
7. **Click "Refund" to confirm**

**Refund Timeline**:
- Stripe processes refund immediately
- Customer sees refund in 5-10 business days (depends on their bank)
- Stripe fees are not refunded

**Membership Access**:
- Refunding payment in Stripe does **not** automatically revoke membership
- You must manually expire the membership in WordPress:
  - WordPress Admin → Membership → Members
  - Find the customer
  - Change membership status to "Expired"

---

## Support & Testing

### **How to Test the Payment Flow**

1. **Enable Stripe Test Mode**:
   - Use test API keys in WordPress settings
   - All payments will be simulated (no real charges)

2. **Go through registration**:
   - Visit `/membership-register/` on your site
   - Fill in account details
   - Select "Paid Membership"
   - Use test card: `4242 4242 4242 4242`
   - Complete registration

3. **Verify in Stripe Dashboard**:
   - Log into Stripe (test mode)
   - Go to Payments
   - See the test payment appear

4. **Verify in WordPress**:
   - WordPress Admin → Membership → Members
   - See new member with active membership
   - WordPress Admin → Membership → Payments
   - See completed payment record

### **Common Issues & Solutions**

| Issue | Solution |
|-------|----------|
| Payment form doesn't appear | Check that Stripe is enabled in settings and API keys are entered |
| "Stripe is not configured" error | Verify Secret Key is correct in WordPress settings |
| Payment succeeds but membership not activated | Check WordPress debug logs for errors; payment record should be in database |
| "Payment failed" with no specific error | Check Stripe Dashboard logs for detailed error message |
| Form appears but is blank | Check browser console for JavaScript errors; ensure HTTPS is enabled |

---

## Advantages of This Implementation

### **For Your Business**

1. **Higher Conversion Rates**: Customers complete payment 30-40% more often compared to redirect-based flows
2. **Professional Appearance**: Looks like a custom-built payment system
3. **Better Control**: Full control over styling and user experience
4. **Reduced Abandonment**: Fewer steps means fewer opportunities to leave
5. **Mobile Optimized**: Works perfectly on phones and tablets
6. **Easy Troubleshooting**: All payment activity visible in one Stripe dashboard

### **For Your Customers**

1. **Fast & Simple**: Complete registration in under a minute
2. **Secure**: Bank-level security without redirects
3. **Trustworthy**: Never leave your website
4. **Clear Errors**: Immediate feedback if something goes wrong
5. **Modern Experience**: Matches expectations from major e-commerce sites

### **Technical Benefits**

1. **PCI Compliant**: Automatically, without complex certification
2. **No Stored Cards**: Zero liability for card data
3. **Fraud Prevention**: Stripe's advanced fraud detection included
4. **Global Support**: Works in 135+ currencies (configured for USD currently)
5. **3D Secure Ready**: Handles SCA (Strong Customer Authentication) automatically
6. **Webhook Backup**: Secondary confirmation method via Stripe webhooks

---

## Pricing & Costs

### **What You Pay to Stripe**

Stripe charges a fee per transaction:
- **2.9% + $0.30 per successful charge**
- Example: $24.95 membership = $0.72 + $0.30 = **$1.02 fee**
- **You receive: $23.93** per membership

**No Monthly Fees**:
- No setup fees
- No monthly fees
- No hidden fees
- Only pay when you get paid

**Refunds**:
- When you refund a customer, Stripe refunds their processing fee
- You get back: 2.9% (percentage portion)
- You don't get back: $0.30 (fixed portion)

### **Payment Processing Time**

- **Funds available**: 2 business days after payment (default)
- **Payout schedule**: Automatic daily or weekly
- **Faster payouts**: Available with Instant Payouts (additional fee)

---

## Summary for Non-Technical Clients

### **What is Stripe Inline Payment?**

It's a credit card payment system that's embedded directly into your website's registration page. When someone wants to buy a membership, they enter their card details in a form that appears right there on your page - they never leave your site or get redirected to Stripe's website.

### **Why is it Better?**

- **Faster**: One page, one button click, done
- **More professional**: Looks like it's part of your website
- **More secure**: Stripe handles all the sensitive card data
- **Higher sales**: People complete checkout more often
- **Easier to use**: No confusing redirects or multiple pages

### **Is it Safe?**

Yes, extremely safe:
- Card numbers never touch your WordPress database
- Stripe is certified to the highest security level (PCI Level 1)
- Same security used by Amazon, Google, and major retailers
- Your website doesn't need special security certifications

### **How Much Does it Cost?**

- Stripe charges 2.9% + $0.30 per transaction
- On a $24.95 membership, you pay ~$1.02, you keep ~$23.93
- No monthly fees or setup costs

### **What Happens When a Customer Pays?**

1. Customer fills registration form
2. Enters card details in the embedded form
3. Clicks "Register & Pay"
4. System creates their account
5. Processes payment through Stripe
6. Activates their 90-day membership
7. Sends them to login page
8. Done - they can start accessing courses

**Total time: 30-60 seconds**

---

## Additional Resources

### **Stripe Documentation**
- Payment Elements: https://stripe.com/docs/payments/payment-element
- Payment Intents: https://stripe.com/docs/payments/payment-intents
- Security: https://stripe.com/docs/security

### **Files in This System**
- Frontend JavaScript: `/assets/js/script.js`
- Backend Gateway: `/includes/class-stripe-gateway.php`
- Registration Template: `/templates/register-form.php`
- Account Page Template: `/templates/account-page.php`

### **Admin Areas**
- Stripe Settings: WordPress Admin → Membership → Settings
- View Members: WordPress Admin → Membership → Members
- View Payments: WordPress Admin → Membership → Payments

---

## Questions & Answers

**Q: Can customers save their card for future purchases?**  
A: Currently no. Each payment requires entering card details. This reduces complexity and security risks.

**Q: Does it support PayPal too?**  
A: Yes! Customers can choose between Stripe (credit card) or PayPal during registration.

**Q: What if payment fails?**  
A: The user sees an error message and can try again. Their account is created but not activated until payment succeeds.

**Q: Can I customize the payment form design?**  
A: The form styling is controlled by Stripe for security and PCI compliance, but you can customize colors and basic appearance.

**Q: Does it work on mobile?**  
A: Yes! Stripe Elements are fully responsive and work perfectly on phones and tablets.

**Q: What about international cards?**  
A: Stripe accepts cards from nearly all countries, though your pricing is in USD.

**Q: How do I switch from test to live mode?**  
A: Replace the test API keys with your live API keys in WordPress Admin → Membership → Settings.

**Q: What happens if a customer pays but leaves before seeing confirmation?**  
A: The payment still completes. Stripe confirms it via webhook, and the membership is activated. The customer receives a welcome email.

---

**Document Version**: 1.0  
**Last Updated**: January 2026  
**System Version**: IELTS Membership System v10.0+
