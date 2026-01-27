# Stripe Integration Guide: Solutions for Common Issues

This document explains how this repository successfully implements Stripe payment integration and provides solutions for two common Stripe integration issues:

1. **Stripe checkout showing at only 50% width**
2. **Error: "Payment details were collected through Stripe Elements using payment_method_types and cannot be confirmed through the API configured with automatic payment methods"**

## Table of Contents
- [Problem Overview](#problem-overview)
- [Solution 1: Full-Width Stripe Elements](#solution-1-full-width-stripe-elements)
- [Solution 2: Correct Payment Intent Configuration](#solution-2-correct-payment-intent-configuration)
- [Complete Implementation](#complete-implementation)
- [Key Takeaways](#key-takeaways)

---

## Problem Overview

### Issue 1: 50% Width Stripe Element
When Stripe Elements are rendered, they may appear at only 50% of the container width due to missing or incorrect CSS styling.

### Issue 2: Payment Method Types Conflict
The error "Payment details were collected through Stripe Elements using payment_method_types and cannot be confirmed through the API configured with automatic payment methods" occurs when there's a mismatch between:
- How the Stripe Elements were initialized
- How the Payment Intent was created on the server

**The conflict:** If you use `payment_method_types: ['card']` in one place and `automatic_payment_methods: {enabled: true}` in another, Stripe will reject the payment confirmation.

---

## Solution 1: Full-Width Stripe Elements

### CSS Styling

The Stripe payment element container needs proper CSS to ensure it displays at full width:

```css
/* Stripe Payment Element Styles */
.stripe-payment-section {
    margin-top: 20px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
}

.stripe-payment-element {
    padding: 15px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    min-height: 50px;
}

#payment-errors {
    margin-top: 10px;
}
```

**Key points:**
- The `.stripe-payment-element` class provides a clean white background
- No width restrictions - allows the element to take full container width
- Padding and borders create a professional appearance
- Min-height ensures the element has space while Stripe initializes

### HTML Structure

```html
<div id="stripe-payment-section" class="stripe-payment-section">
    <div class="ielts-ms-form-group">
        <label>Card Details</label>
        <div id="payment-element" class="stripe-payment-element">
            <!-- Stripe Elements will be inserted here -->
        </div>
        <div id="payment-errors" class="ielts-ms-message" style="display: none;"></div>
    </div>
</div>
```

**Important:** 
- The `#payment-element` div is where Stripe mounts the payment UI
- The container should have NO fixed width or max-width constraints
- Parent containers should also avoid restrictive widths

---

## Solution 2: Correct Payment Intent Configuration

### The Core Issue

You MUST be consistent in how you configure Stripe Elements and Payment Intents:

**❌ WRONG - Mixing configurations:**
```javascript
// Client-side: Using specific payment_method_types
elements = stripe.elements({
    mode: 'payment',
    payment_method_types: ['card'],  // ❌ Specific types
    amount: amount,
    currency: 'usd'
});
```

```php
// Server-side: Using automatic payment methods
$stripe_data = array(
    'amount' => $amount,
    'currency' => 'usd',
    'automatic_payment_methods' => array('enabled' => true)  // ❌ Conflict!
);
```

**✅ CORRECT - Consistent configuration:**

### Client-Side JavaScript

```javascript
// Initialize Stripe Elements with 'payment' mode
const appearance = {
    theme: 'stripe',
    variables: {
        colorPrimary: '#0073aa'
    }
};

elements = stripe.elements({
    mode: 'payment',
    amount: Math.round(parseFloat(membershipAmount) * 100), // Convert to cents
    currency: 'usd',
    appearance: appearance
    // ✅ NO payment_method_types specified - let automatic_payment_methods work
});

paymentElement = elements.create('payment');
paymentElement.mount('#payment-element');
```

**Key points:**
- Use `mode: 'payment'` for one-time payments
- Specify `amount` and `currency` in the elements initialization
- DO NOT specify `payment_method_types` here
- Use `appearance` to customize the look

### Server-Side PHP (Payment Intent Creation)

```php
public function create_payment_intent() {
    check_ajax_referer('ielts_ms_nonce', 'nonce');
    
    // Validate required POST parameters
    if (!isset($_POST['amount']) || !isset($_POST['duration_days']) || 
        !isset($_POST['payment_type']) || !isset($_POST['plan_key'])) {
        wp_send_json_error(array('message' => 'Missing required parameters'));
    }
    
    $amount = floatval($_POST['amount']);
    $duration_days = intval($_POST['duration_days']);
    $payment_type = sanitize_text_field($_POST['payment_type']);
    $plan_key = sanitize_text_field($_POST['plan_key']);
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
    
    // Validate amount is positive and reasonable (max $1000)
    if ($amount <= 0 || $amount > 1000) {
        wp_send_json_error(array('message' => 'Invalid amount'));
    }
    
    // Validate duration_days is positive
    if ($duration_days <= 0) {
        wp_send_json_error(array('message' => 'Invalid duration'));
    }
    
    $secret_key = get_option('ielts_ms_stripe_secret_key', '');
    
    if (empty($secret_key)) {
        wp_send_json_error(array('message' => 'Stripe is not configured'));
    }
    
    // Get email for receipt
    $email = '';
    if ($user_id) {
        $user = get_userdata($user_id);
        if ($user) {
            $email = $user->user_email;
        }
    }
    
    if (empty($email) && isset($_POST['email'])) {
        $email = sanitize_email($_POST['email']);
    }
    
    // Create pending payment record
    $payment_id = $this->record_payment($user_id, $amount, $duration_days, null, 'pending', $payment_type);
    
    // Prepare Stripe API request for Payment Intent
    $stripe_data = array(
        'amount' => intval($amount * 100), // Convert to cents
        'currency' => 'usd',
        'automatic_payment_methods' => array('enabled' => true), // ✅ Use automatic
        'metadata' => array(
            'user_id' => $user_id,
            'duration_days' => $duration_days,
            'payment_type' => $payment_type,
            'payment_id' => $payment_id,
            'plan_key' => $plan_key
        )
    );
    
    if (!empty($email)) {
        $stripe_data['receipt_email'] = $email;
    }
    
    // Make API request to Stripe
    $body_data = $this->build_stripe_query($stripe_data);
    
    $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $secret_key,
            'Content-Type' => 'application/x-www-form-urlencoded'
        ),
        'body' => $body_data,
        'timeout' => 60
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Failed to create payment intent'));
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['client_secret'])) {
        wp_send_json_success(array(
            'clientSecret' => $body['client_secret'],
            'payment_id' => $payment_id
        ));
    } else {
        wp_send_json_error(array('message' => 'Failed to create payment intent'));
    }
}
```

**Key points:**
- Use `'automatic_payment_methods' => array('enabled' => true)` in Payment Intent
- DO NOT use `'payment_method_types' => array('card')` 
- Include metadata for tracking
- Return `client_secret` to the frontend

### Confirming the Payment

```javascript
// After creating the Payment Intent on the server
stripe.confirmPayment({
    elements: elements,
    clientSecret: clientSecret,
    confirmParams: {
        return_url: window.location.origin + window.location.pathname
    },
    redirect: 'if_required'  // ✅ Only redirect if needed (3D Secure, etc.)
}).then(function(result) {
    if (result.error) {
        // Show error to customer
        console.error('Payment failed:', result.error.message);
    } else {
        // Payment succeeded
        console.log('Payment succeeded:', result.paymentIntent.id);
    }
});
```

**Key points:**
- Use the same `elements` instance initialized earlier
- Use `redirect: 'if_required'` to handle the flow inline when possible
- Always handle both success and error cases

---

## Complete Implementation

### Step 1: Initialize Stripe Elements (Client-Side)

```javascript
let stripe = null;
let elements = null;
let paymentElement = null;

// Initialize Stripe
if (typeof Stripe !== 'undefined' && stripePublicKey) {
    stripe = Stripe(stripePublicKey);
}

// Initialize Elements when needed
function initializeStripeElements() {
    if (!stripe) return;
    
    const membershipAmount = document.querySelector('input[name="membership_amount"]').value;
    
    if (!membershipAmount || parseFloat(membershipAmount) <= 0) {
        console.error('Invalid membership amount');
        return;
    }
    
    // Create Stripe Elements with payment mode
    const appearance = {
        theme: 'stripe',
        variables: {
            colorPrimary: '#0073aa'
        }
    };
    
    try {
        elements = stripe.elements({
            mode: 'payment',
            amount: Math.round(parseFloat(membershipAmount) * 100),
            currency: 'usd',
            appearance: appearance
        });
        
        paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');
    } catch (error) {
        console.error('Failed to initialize Stripe Elements:', error);
    }
}
```

### Step 2: Submit and Validate Card Details

```javascript
// When user submits the form
elements.submit().then(function(submitResult) {
    if (submitResult.error) {
        // Show validation error
        console.error('Validation failed:', submitResult.error.message);
        return;
    }
    
    // Validation passed, proceed to create Payment Intent
    createPaymentIntent();
});
```

### Step 3: Create Payment Intent (Server-Side AJAX Call)

```javascript
function createPaymentIntent() {
    $.ajax({
        url: ieltsMS.ajaxUrl,
        type: 'POST',
        data: {
            action: 'ielts_ms_create_payment_intent',
            nonce: ieltsMS.nonce,
            amount: $('input[name="membership_amount"]').val(),
            duration_days: $('input[name="membership_days"]').val(),
            payment_type: 'new',
            plan_key: $('input[name="membership_plan"]').val(),
            is_registration: 'true'
        },
        success: function(response) {
            if (response.success && response.data.clientSecret) {
                confirmPayment(response.data.clientSecret, response.data.payment_id);
            } else {
                console.error('Failed to create payment intent:', response.data.message);
            }
        },
        error: function() {
            console.error('An error occurred while creating payment intent');
        }
    });
}
```

### Step 4: Confirm Payment

```javascript
function confirmPayment(clientSecret, paymentId) {
    stripe.confirmPayment({
        elements: elements,
        clientSecret: clientSecret,
        confirmParams: {
            return_url: window.location.origin + window.location.pathname
        },
        redirect: 'if_required'
    }).then(function(result) {
        if (result.error) {
            console.error('Payment failed:', result.error.message);
        } else {
            // Notify server of successful payment
            notifyServerOfSuccess(result.paymentIntent.id, paymentId);
        }
    });
}
```

### Step 5: Update Server After Success

```javascript
function notifyServerOfSuccess(paymentIntentId, paymentId) {
    $.ajax({
        url: ieltsMS.ajaxUrl,
        type: 'POST',
        data: {
            action: 'ielts_ms_confirm_payment',
            nonce: ieltsMS.nonce,
            payment_intent_id: paymentIntentId,
            payment_id: paymentId
        },
        success: function(response) {
            if (response.success) {
                window.location.href = response.data.redirect;
            } else {
                console.error('Failed to confirm payment:', response.data.message);
            }
        },
        error: function() {
            console.error('An error occurred while confirming payment');
        }
    });
}
```

---

## Key Takeaways

### ✅ DO:
1. **Use consistent configuration** - If using `automatic_payment_methods` on the server, don't specify `payment_method_types` on the client
2. **Initialize Elements with `mode: 'payment'`** - This is for one-time payments
3. **Include `amount` and `currency`** in the Elements initialization
4. **Use proper CSS** to ensure full-width display
5. **Use `redirect: 'if_required'`** for inline payment flows
6. **Always validate** with `elements.submit()` before creating Payment Intent
7. **Handle errors gracefully** at every step

### ❌ DON'T:
1. **Don't mix `payment_method_types` and `automatic_payment_methods`** - This causes the API error
2. **Don't use fixed widths** on the payment element container
3. **Don't skip validation** - Always call `elements.submit()` first
4. **Don't forget error handling** - Payment failures need clear user feedback
5. **Don't create Payment Intent before validating** - Validate card details first

### The Two Critical Rules:

**Rule 1: CSS Width**
```css
/* Ensure no width restrictions on container */
.stripe-payment-element {
    /* ✅ Good: No max-width or fixed width */
    padding: 15px;
    background: #fff;
}

/* ❌ Bad: Don't do this */
.stripe-payment-element {
    width: 50%;  /* ❌ Causes 50% width issue */
    max-width: 400px;  /* ❌ May restrict width */
}
```

**Rule 2: Configuration Consistency**
```javascript
// Client-side: Use 'payment' mode, no payment_method_types
elements = stripe.elements({
    mode: 'payment',
    amount: amount,
    currency: 'usd'
    // ✅ NO payment_method_types
});
```

```php
// Server-side: Use automatic_payment_methods
$stripe_data = array(
    'amount' => $amount,
    'currency' => 'usd',
    'automatic_payment_methods' => array('enabled' => true)
    // ✅ NO payment_method_types
);
```

---

## Additional Notes

### Stripe Elements Mode Options

- **`mode: 'payment'`** - One-time payment (use this for purchases)
- **`mode: 'setup'`** - Save payment method for future use
- **`mode: 'subscription'`** - Recurring payments

### When to Use `payment_method_types`

You CAN use `payment_method_types: ['card']` if you:
1. Use it BOTH on client-side Elements initialization AND server-side Payment Intent creation
2. Do NOT use `automatic_payment_methods`

Example (consistent configuration):
```javascript
// Client
elements = stripe.elements({
    mode: 'payment',
    amount: amount,
    currency: 'usd',
    payment_method_types: ['card']  // Specified
});
```

```php
// Server
$stripe_data = array(
    'amount' => $amount,
    'currency' => 'usd',
    'payment_method_types' => array('card')  // Matching!
    // NO automatic_payment_methods
);
```

However, **using `automatic_payment_methods`** is recommended as it:
- Automatically enables new payment methods as Stripe adds them
- Provides better UX with local payment methods
- Reduces maintenance burden

---

## Testing Your Implementation

1. **Test the width**: The payment element should take the full width of its container
2. **Test validation**: Submit with empty fields - should show validation errors
3. **Test successful payment**: Complete a test payment using Stripe test cards
4. **Test error handling**: Use test cards that trigger errors
5. **Check console**: No errors about payment_method_types conflicts

**Stripe Test Cards:**
- Success: `4242 4242 4242 4242`
- Requires 3D Secure: `4000 0025 0000 3155`
- Declined: `4000 0000 0000 9995`

---

## References

- [Stripe Payment Element Documentation](https://stripe.com/docs/payments/payment-element)
- [Stripe Payment Intents API](https://stripe.com/docs/api/payment_intents)
- [Stripe Elements Appearance API](https://stripe.com/docs/elements/appearance-api)

---

## Questions?

If you encounter issues not covered in this guide:

1. Check browser console for specific error messages
2. Verify API keys are correct (publishable key on client, secret key on server)
3. Ensure HTTPS is enabled (Stripe requires secure connections in production)
4. Check that amounts match between client and server (in cents)
5. Verify webhook endpoints if using webhooks for fulfillment

---

**Last Updated:** January 2024  
**Repository:** impact2021/ito-student-management
