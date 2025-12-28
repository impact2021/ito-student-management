/**
 * IELTS Membership System JavaScript
 */

jQuery(document).ready(function($) {
    
    // Tab switching functionality
    $('.ielts-ms-tab-link').on('click', function() {
        const targetTab = $(this).data('tab');
        
        // Remove active class from all tabs and panes
        $('.ielts-ms-tab-link').removeClass('active');
        $('.ielts-ms-tab-pane').removeClass('active');
        
        // Add active class to clicked tab and corresponding pane
        $(this).addClass('active');
        $('#' + targetTab).addClass('active');
    });
    
    // Initialize Stripe if enabled
    let stripe = null;
    let elements = null;
    let paymentElement = null;
    let registrationStripeInitialized = false;
    
    if (typeof Stripe !== 'undefined' && ieltsMS.stripeEnabled && ieltsMS.stripePublicKey) {
        stripe = Stripe(ieltsMS.stripePublicKey);
    }
    
    // Show/hide Stripe payment section based on gateway selection
    $('input[name="payment_gateway"]').on('change', function() {
        const gateway = $(this).val();
        
        if (gateway === 'stripe' && stripe) {
            $('#stripe-payment-section').slideDown();
            // Initialize Stripe Elements for registration form if not already done
            if ($('#ielts-ms-register-form').length > 0 && !registrationStripeInitialized) {
                initializeRegistrationStripeElements();
            }
        } else {
            $('#stripe-payment-section').slideUp();
        }
    });
    
    // Initialize Stripe payment section on page load if Stripe is selected
    if ($('input[name="payment_gateway"]:checked').val() === 'stripe' && stripe) {
        $('#stripe-payment-section').show();
        // Initialize Stripe Elements for registration form on page load
        if ($('#ielts-ms-register-form').length > 0 && !registrationStripeInitialized) {
            initializeRegistrationStripeElements();
        }
    }
    
    // Function to initialize Stripe Elements for registration form
    function initializeRegistrationStripeElements() {
        if (!stripe || registrationStripeInitialized) {
            return;
        }
        
        // Validate required DOM elements exist
        if (!$('#payment-element').length || !$('input[name="membership_amount"]').length) {
            console.error('Required elements for Stripe initialization not found');
            return;
        }
        
        // Validate amount is valid
        const amountValue = parseFloat($('input[name="membership_amount"]').val());
        if (isNaN(amountValue) || amountValue <= 0) {
            console.error('Invalid membership amount for Stripe initialization');
            return;
        }
        
        // Initialize Stripe Elements with payment mode
        const appearance = {
            theme: 'stripe',
            variables: {
                colorPrimary: '#0073aa'
            }
        };
        
        elements = stripe.elements({
            mode: 'payment',
            amount: amountValue * 100, // Convert to cents
            currency: 'usd',
            appearance: appearance
        });
        
        paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');
        registrationStripeInitialized = true;
    }
    
    // Login form
    $('#ielts-ms-login-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $message = $form.find('.ielts-ms-message');
        
        $button.addClass('loading').prop('disabled', true);
        $message.hide();
        
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_login',
                nonce: ieltsMS.nonce,
                username: $('#username').val(),
                password: $('#password').val(),
                remember: $('input[name="remember"]').is(':checked') ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').text(response.data.message).show();
                    setTimeout(function() {
                        window.location.href = response.data.redirect;
                    }, 1000);
                } else {
                    $message.removeClass('success').addClass('error').text(response.data.message).show();
                    $button.removeClass('loading').prop('disabled', false);
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // Registration form
    $('#ielts-ms-register-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $message = $form.find('.ielts-ms-message');
        
        // Initial form submission
        $button.addClass('loading').prop('disabled', true);
        $message.hide();
        
        const gateway = $('input[name="payment_gateway"]:checked').val();
        
        // Validate form data first
        const first_name = $('#reg_first_name').val();
        const last_name = $('#reg_last_name').val();
        const username = $('#reg_username').val();
        const email = $('#reg_email').val();
        const password = $('#reg_password').val();
        const confirm_password = $('#reg_confirm_password').val();
        
        if (!first_name || !last_name || !username || !email || !password || !confirm_password) {
            $message.removeClass('success').addClass('error').text('Please fill in all required fields').show();
            $button.removeClass('loading').prop('disabled', false);
            return;
        }
        
        if (password !== confirm_password) {
            $message.removeClass('success').addClass('error').text('Passwords do not match').show();
            $button.removeClass('loading').prop('disabled', false);
            return;
        }
        
        if (password.length < 8) {
            $message.removeClass('success').addClass('error').text('Password must be at least 8 characters').show();
            $button.removeClass('loading').prop('disabled', false);
            return;
        }
        
        // Handle Stripe inline payment
        if (gateway === 'stripe' && stripe && registrationStripeInitialized) {
            handleStripeInlineRegistration($form, $button, $message);
        } else {
            // Handle PayPal or legacy Stripe redirect
            handleLegacyRegistration($form, $button, $message, gateway);
        }
    });
    
    // Handle Stripe inline payment for registration
    function handleStripeInlineRegistration($form, $button, $message) {
        // First create the user account
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_register_with_payment',
                nonce: ieltsMS.nonce,
                first_name: $('#reg_first_name').val(),
                last_name: $('#reg_last_name').val(),
                username: $('#reg_username').val(),
                email: $('#reg_email').val(),
                password: $('#reg_password').val(),
                confirm_password: $('#reg_confirm_password').val(),
                payment_gateway: 'stripe_inline',
                membership_plan: $('input[name="membership_plan"]').val(),
                membership_amount: $('input[name="membership_amount"]').val(),
                membership_days: $('input[name="membership_days"]').val()
            },
            success: function(response) {
                if (response.success && response.data.user_id) {
                    // User created successfully, now create payment intent and process payment
                    createPaymentIntentAndProcessRegistration(response.data.user_id, $form, $button, $message);
                } else {
                    $message.removeClass('success').addClass('error').text(response.data.message).show();
                    $button.removeClass('loading').prop('disabled', false);
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    }
    
    // Create Payment Intent and immediately process payment for registration
    function createPaymentIntentAndProcessRegistration(userId, $form, $button, $message) {
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_create_payment_intent',
                nonce: ieltsMS.nonce,
                user_id: userId,
                email: $('#reg_email').val(),
                plan_key: $('input[name="membership_plan"]').val(),
                amount: $('input[name="membership_amount"]').val(),
                duration_days: $('input[name="membership_days"]').val(),
                payment_type: 'new',
                is_registration: 'true'
            },
            success: function(response) {
                if (response.success && response.data.clientSecret) {
                    // Validate that Stripe Elements were initialized
                    if (!elements || !registrationStripeInitialized) {
                        $message.removeClass('success').addClass('error').text('Payment system not initialized. Please refresh the page and try again.').show();
                        $button.removeClass('loading').prop('disabled', false);
                        return;
                    }
                    
                    // Immediately confirm the payment with Stripe
                    stripe.confirmPayment({
                        elements: elements,
                        clientSecret: response.data.clientSecret,
                        confirmParams: {
                            return_url: window.location.origin + window.location.pathname
                        },
                        redirect: 'if_required'
                    }).then(function(result) {
                        if (result.error) {
                            // Show error to customer
                            $('#payment-errors').removeClass('success').addClass('error').text(result.error.message).show();
                            $message.removeClass('success').addClass('error').text('Payment failed: ' + result.error.message).show();
                            $button.removeClass('loading').prop('disabled', false);
                        } else {
                            // Payment succeeded
                            confirmPaymentOnServer(result.paymentIntent.id, response.data.payment_id, $button, $message);
                        }
                    });
                } else {
                    $message.removeClass('success').addClass('error').text(response.data.message || 'Failed to initialize payment').show();
                    $button.removeClass('loading').prop('disabled', false);
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    }
    
    // Handle form submission when Stripe is already initialized
    // (This is handled in the main submit handler above)
    
    // Confirm payment on server
    function confirmPaymentOnServer(paymentIntentId, paymentId, $button, $message) {
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
                    $message.removeClass('error').addClass('success').text('Payment successful! Redirecting...').show();
                    setTimeout(function() {
                        window.location.href = response.data.redirect;
                    }, 1000);
                } else {
                    $message.removeClass('success').addClass('error').text(response.data.message).show();
                    $button.removeClass('loading').prop('disabled', false);
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    }
    
    // Handle legacy registration (PayPal or redirect-based Stripe)
    function handleLegacyRegistration($form, $button, $message, gateway) {
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_register_with_payment',
                nonce: ieltsMS.nonce,
                first_name: $('#reg_first_name').val(),
                last_name: $('#reg_last_name').val(),
                username: $('#reg_username').val(),
                email: $('#reg_email').val(),
                password: $('#reg_password').val(),
                confirm_password: $('#reg_confirm_password').val(),
                payment_gateway: gateway,
                membership_plan: $('input[name="membership_plan"]').val(),
                membership_amount: $('input[name="membership_amount"]').val(),
                membership_days: $('input[name="membership_days"]').val()
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.redirect_url) {
                        // Redirect to payment gateway (Stripe Checkout or PayPal)
                        window.location.href = response.data.redirect_url;
                    } else if (response.data.form_data) {
                        // Build PayPal form
                        const $form = $('#paypal-form');
                        $form.attr('action', response.data.redirect_url || response.data.form_action);
                        $form.empty();
                        
                        $.each(response.data.form_data, function(key, value) {
                            $form.append($('<input>').attr({
                                type: 'hidden',
                                name: key,
                                value: value
                            }));
                        });
                        
                        $form.submit();
                    } else {
                        $message.removeClass('error').addClass('success').text(response.data.message).show();
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 1000);
                    }
                } else {
                    $message.removeClass('success').addClass('error').text(response.data.message).show();
                    $button.removeClass('loading').prop('disabled', false);
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    }
    
    // Forgot password form
    $('#ielts-ms-forgot-password-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $message = $form.find('.ielts-ms-message');
        
        $button.addClass('loading').prop('disabled', true);
        $message.hide();
        
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_forgot_password',
                nonce: ieltsMS.nonce,
                user_login: $('#user_login').val()
            },
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').text(response.data.message).show();
                } else {
                    $message.removeClass('success').addClass('error').text(response.data.message).show();
                }
                $button.removeClass('loading').prop('disabled', false);
            },
            error: function() {
                $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // Reset password form
    $('#ielts-ms-reset-password-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $message = $form.find('.ielts-ms-message');
        
        $button.addClass('loading').prop('disabled', true);
        $message.hide();
        
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_reset_password',
                nonce: ieltsMS.nonce,
                key: $('input[name="key"]').val(),
                login: $('input[name="login"]').val(),
                password: $('#new_password').val(),
                confirm_password: $('#confirm_password').val()
            },
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').text(response.data.message).show();
                    setTimeout(function() {
                        window.location.href = response.data.redirect;
                    }, 1500);
                } else {
                    $message.removeClass('success').addClass('error').text(response.data.message).show();
                    $button.removeClass('loading').prop('disabled', false);
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // Update email form
    $('#ielts-ms-update-email-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $message = $form.find('.ielts-ms-message');
        
        $button.addClass('loading').prop('disabled', true);
        $message.hide();
        
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_update_email',
                nonce: ieltsMS.nonce,
                new_email: $('#new_email').val(),
                password: $('#email_password').val()
            },
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').text(response.data.message).show();
                    $form[0].reset();
                } else {
                    $message.removeClass('success').addClass('error').text(response.data.message).show();
                }
                $button.removeClass('loading').prop('disabled', false);
            },
            error: function() {
                $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // Update password form
    $('#ielts-ms-update-password-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $message = $form.find('.ielts-ms-message');
        
        $button.addClass('loading').prop('disabled', true);
        $message.hide();
        
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_update_password',
                nonce: ieltsMS.nonce,
                current_password: $('#current_password').val(),
                new_password: $('#new_password').val(),
                confirm_password: $('#confirm_new_password').val()
            },
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').text(response.data.message).show();
                    $form[0].reset();
                } else {
                    $message.removeClass('success').addClass('error').text(response.data.message).show();
                }
                $button.removeClass('loading').prop('disabled', false);
            },
            error: function() {
                $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // Payment handling
    let selectedPlan = null;
    
    $('.purchase-btn').on('click', function() {
        selectedPlan = {
            plan_key: $(this).data('plan'),
            amount: $(this).data('amount'),
            days: $(this).data('days'),
            type: $(this).data('type')
        };
        
        // Show payment gateway selector
        $('#payment-gateway-selector').slideDown();
        
        // Auto-trigger Stripe/Credit Card payment if Stripe is enabled
        if (stripe && $('[data-gateway="stripe"]').length > 0) {
            // Automatically start the Stripe payment process
            setTimeout(function() {
                processStripeInlinePayment(selectedPlan);
            }, 300);
        }
        
        // Scroll to payment section
        $('html, body').animate({
            scrollTop: $('#payment-gateway-selector').offset().top - 100
        }, 500);
    });
    
    // Gateway selection
    $('.gateway-btn').on('click', function() {
        const gateway = $(this).data('gateway');
        
        if (!selectedPlan) {
            alert('Please select a plan first');
            return;
        }
        
        if (gateway === 'paypal') {
            processPayPalPayment(selectedPlan);
        } else if (gateway === 'stripe') {
            // Use inline payment if Stripe is initialized
            if (stripe) {
                processStripeInlinePayment(selectedPlan);
            } else {
                // Fallback to redirect-based payment
                processStripePayment(selectedPlan);
            }
        }
    });
    
    // Process Stripe inline payment for account page
    function processStripeInlinePayment(plan) {
        // Show loading state
        $('.gateway-btn').prop('disabled', true);
        
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_create_payment_intent',
                nonce: ieltsMS.nonce,
                plan_key: plan.plan_key,
                amount: plan.amount,
                duration_days: plan.days,
                payment_type: plan.type,
                is_registration: 'false'
            },
            success: function(response) {
                if (response.success && response.data.clientSecret) {
                    // Initialize Stripe Elements for account page
                    const options = {
                        clientSecret: response.data.clientSecret,
                        appearance: {
                            theme: 'stripe',
                            variables: {
                                colorPrimary: '#0073aa'
                            }
                        }
                    };
                    
                    elements = stripe.elements(options);
                    paymentElement = elements.create('payment');
                    paymentElement.mount('#payment-element-account');
                    
                    // Show the payment section
                    $('#stripe-payment-section-account').slideDown();
                    $('#complete-payment-btn').show();
                    $('.gateway-btn').prop('disabled', false);
                    
                    // Store payment data
                    $('#complete-payment-btn').data('clientSecret', response.data.clientSecret);
                    $('#complete-payment-btn').data('paymentId', response.data.payment_id);
                } else {
                    $('#payment-errors-account').removeClass('success').addClass('error')
                        .text(response.data.message || 'Failed to initialize payment').show();
                    $('.gateway-btn').prop('disabled', false);
                }
            },
            error: function() {
                $('#payment-errors-account').removeClass('success').addClass('error')
                    .text('An error occurred. Please try again.').show();
                $('.gateway-btn').prop('disabled', false);
            }
        });
    }
    
    // Handle complete payment button click
    $('#complete-payment-btn').on('click', function() {
        const $button = $(this);
        const clientSecret = $button.data('clientSecret');
        const paymentId = $button.data('paymentId');
        
        $button.addClass('loading').prop('disabled', true);
        $('#payment-errors-account').hide();
        
        // Confirm the payment with Stripe
        stripe.confirmPayment({
            elements: elements,
            confirmParams: {
                return_url: window.location.origin + window.location.pathname
            },
            redirect: 'if_required'
        }).then(function(result) {
            if (result.error) {
                // Show error to customer
                $('#payment-errors-account').removeClass('success').addClass('error').text(result.error.message).show();
                $button.removeClass('loading').prop('disabled', false);
            } else {
                // Payment succeeded - confirm on server
                $.ajax({
                    url: ieltsMS.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ielts_ms_confirm_payment',
                        nonce: ieltsMS.nonce,
                        payment_intent_id: result.paymentIntent.id,
                        payment_id: paymentId
                    },
                    success: function(response) {
                        if (response.success) {
                            // Redirect to success page
                            window.location.href = response.data.redirect + '?payment_status=success';
                        } else {
                            $('#payment-errors-account').removeClass('success').addClass('error').text(response.data.message).show();
                            $button.removeClass('loading').prop('disabled', false);
                        }
                    },
                    error: function() {
                        $('#payment-errors-account').removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
                        $button.removeClass('loading').prop('disabled', false);
                    }
                });
            }
        });
    });
    
    // Process PayPal payment
    function processPayPalPayment(plan) {
        // This would be handled server-side
        // For now, create a form and submit to PayPal
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_process_payment',
                nonce: ieltsMS.nonce,
                gateway: 'paypal',
                amount: plan.amount,
                duration_days: plan.days,
                payment_type: plan.type
            },
            success: function(response) {
                if (response.success && response.data.form_data) {
                    // Build PayPal form
                    const $form = $('#paypal-form');
                    $form.attr('action', response.data.redirect_url);
                    $form.empty();
                    
                    $.each(response.data.form_data, function(key, value) {
                        $form.append($('<input>').attr({
                            type: 'hidden',
                            name: key,
                            value: value
                        }));
                    });
                    
                    $form.submit();
                } else {
                    alert(response.data.message || 'Payment processing failed');
                }
            }
        });
    }
    
    // Process Stripe payment
    function processStripePayment(plan) {
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_create_stripe_session',
                nonce: ieltsMS.nonce,
                plan_key: plan.plan_key,
                amount: plan.amount,
                duration_days: plan.days,
                payment_type: plan.type
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    window.location.href = response.data.url;
                } else {
                    alert(response.data.message || 'Payment processing failed');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            }
        });
    }
});
