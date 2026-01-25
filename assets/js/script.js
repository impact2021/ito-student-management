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
        
        // Update URL hash without jumping
        if (history.pushState) {
            history.pushState(null, null, '#' + targetTab);
        }
    });
    
    // Check for hash in URL on page load and switch to that tab
    if (window.location.hash) {
        const hash = window.location.hash.substring(1); // Remove the # character
        // Sanitize hash to prevent selector injection
        const sanitizedHash = hash.replace(/[^a-zA-Z0-9_-]/g, '');
        const $targetTab = $('.ielts-ms-tab-link[data-tab="' + sanitizedHash + '"]');
        
        if ($targetTab.length) {
            $targetTab.click();
        }
    }
    
    // Initialize Stripe if enabled
    let stripe = null;
    let elements = null;
    let paymentElement = null;
    let registrationStripeInitialized = false;
    
    // Separate variables for account page to avoid conflicts
    let accountElements = null;
    let accountPaymentElement = null;
    
    if (typeof Stripe !== 'undefined' && ieltsMS.stripeEnabled && ieltsMS.stripePublicKey) {
        stripe = Stripe(ieltsMS.stripePublicKey);
    }
    
    // Initialize Stripe Elements on registration page load
    function initializeRegistrationStripeElements() {
        // Only initialize if we're on the registration page and Stripe is available
        if (!stripe || !$('#ielts-ms-register-form').length || registrationStripeInitialized) {
            return;
        }
        
        // Check if there's a membership amount available
        const membershipAmount = $('input[name="membership_amount"]').val();
        if (!membershipAmount || parseFloat(membershipAmount) <= 0) {
            console.error('Invalid membership amount for Stripe initialization');
            // Show user-facing error
            $('#payment-element').html('<div style="padding: 10px; color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">Unable to initialize payment system. Please refresh the page and try again.</div>');
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
                amount: Math.round(parseFloat(membershipAmount) * 100), // Convert to cents
                currency: 'usd',
                appearance: appearance
            });
            
            paymentElement = elements.create('payment');
            paymentElement.mount('#payment-element');
            registrationStripeInitialized = true;
        } catch (error) {
            console.error('Failed to initialize Stripe Elements:', error);
            // Show user-facing error
            $('#payment-element').html('<div style="padding: 10px; color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">Unable to initialize payment system. Please refresh the page and try again.</div>');
        }
    }
    
    // Show/hide Stripe payment section based on gateway selection
    $('input[name="payment_gateway"]').on('change', function() {
        const gateway = $(this).val();
        
        if (gateway === 'stripe' && stripe) {
            $('#stripe-payment-section').slideDown();
            // Initialize Stripe Elements when Stripe is selected
            if (!registrationStripeInitialized) {
                initializeRegistrationStripeElements();
            }
        } else {
            $('#stripe-payment-section').slideUp();
        }
    });
    
    // Initialize Stripe payment section on page load if Stripe is selected
    if ($('input[name="payment_gateway"]:checked').val() === 'stripe' && stripe) {
        $('#stripe-payment-section').show();
        // Initialize Stripe Elements immediately on page load
        initializeRegistrationStripeElements();
    }
    
    // Handle membership type toggle (trial vs paid)
    $('input[name="membership_type"]').on('change', function() {
        const membershipType = $(this).val();
        
        if (membershipType === 'trial') {
            // Hide paid membership section
            $('#paid-membership-section').slideUp();
            $('.submit-trial-text').show();
            $('.submit-paid-text').hide();
        } else {
            // Show paid membership section
            $('#paid-membership-section').slideDown();
            $('.submit-trial-text').hide();
            $('.submit-paid-text').show();
            
            // Initialize Stripe if needed
            if ($('input[name="payment_gateway"]:checked').val() === 'stripe' && stripe && !registrationStripeInitialized) {
                initializeRegistrationStripeElements();
            }
        }
    });
    
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
    
    // Real-time username availability check
    $('#reg_username').on('blur', function() {
        const username = $(this).val().trim();
        
        // Skip check if username is empty
        if (!username) {
            return;
        }
        
        // Add a loading indicator class
        $(this).addClass('checking');
        
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_check_username',
                nonce: ieltsMS.nonce,
                username: username
            },
            success: function(response) {
                $('#reg_username').removeClass('checking');
                
                // Remove any previous validation messages
                $('#reg_username').siblings('.validation-message').remove();
                
                if (response.success) {
                    // Username is available - show success indicator
                    $('#reg_username').after('<span class="validation-message success">✓ Username is available</span>');
                } else {
                    // Username is taken - show error
                    $('#reg_username').after('<span class="validation-message error">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $('#reg_username').removeClass('checking');
            }
        });
    });
    
    // Real-time email availability check
    $('#reg_email').on('blur', function() {
        const email = $(this).val().trim();
        
        // Skip check if email is empty
        if (!email) {
            return;
        }
        
        // Basic email format validation - let server do comprehensive validation
        if (email.indexOf('@') === -1) {
            return; // Skip if no @ sign - let the browser's built-in validation handle this
        }
        
        // Add a loading indicator class
        $(this).addClass('checking');
        
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_check_email',
                nonce: ieltsMS.nonce,
                email: email
            },
            success: function(response) {
                $('#reg_email').removeClass('checking');
                
                // Remove any previous validation messages
                $('#reg_email').siblings('.validation-message').remove();
                
                if (response.success) {
                    // Email is available - show success indicator
                    $('#reg_email').after('<span class="validation-message success">✓ Email is available</span>');
                } else {
                    // Email is taken - show error
                    $('#reg_email').after('<span class="validation-message error">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $('#reg_email').removeClass('checking');
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
        
        const membershipType = $('input[name="membership_type"]:checked').val() || 'paid';
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
        
        // If trial, handle registration without payment
        if (membershipType === 'trial') {
            handleTrialRegistration($form, $button, $message);
        } else if (gateway === 'stripe' && stripe) {
            // Handle Stripe inline payment
            handleStripeInlineRegistration($form, $button, $message);
        } else {
            // Handle PayPal or legacy Stripe redirect
            handleLegacyRegistration($form, $button, $message, gateway);
        }
    });
    
    // Handle trial registration (no payment required)
    function handleTrialRegistration($form, $button, $message) {
        $.ajax({
            url: ieltsMS.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ielts_ms_register_trial',
                nonce: ieltsMS.nonce,
                first_name: $('#reg_first_name').val(),
                last_name: $('#reg_last_name').val(),
                username: $('#reg_username').val(),
                email: $('#reg_email').val(),
                password: $('#reg_password').val(),
                confirm_password: $('#reg_confirm_password').val(),
                enrollment_type: $('input[name="enrollment_type"]:checked').val(),
                trial_duration: $('input[name="trial_duration"]').val()
            },
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').text(response.data.message).show();
                    setTimeout(function() {
                        window.location.href = response.data.redirect;
                    }, 2000);
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
    
    // Handle Stripe inline payment for registration
    function handleStripeInlineRegistration($form, $button, $message) {
        // Validate that Stripe Elements are initialized
        if (!registrationStripeInitialized || !elements) {
            $message.removeClass('success').addClass('error').text('Payment system not ready. Please refresh the page and try again.').show();
            $button.removeClass('loading').prop('disabled', false);
            return;
        }
        
        // Submit the elements first to validate card details
        elements.submit().then(function(submitResult) {
            if (submitResult.error) {
                // Show validation error
                $('#payment-errors').removeClass('success').addClass('error').text(submitResult.error.message).show();
                $message.removeClass('success').addClass('error').text('Payment validation failed: ' + submitResult.error.message).show();
                $button.removeClass('loading').prop('disabled', false);
                return;
            }
            
            // Card details are valid, now create the user account
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
                    membership_days: $('input[name="membership_days"]').val(),
                    enrollment_type: $('input[name="enrollment_type"]:checked').val()
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
        });
    }
    
    // Create Payment Intent and process payment immediately for registration
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
                enrollment_type: $('input[name="enrollment_type"]:checked').val(),
                is_registration: 'true'
            },
            success: function(response) {
                if (response.success && response.data.clientSecret) {
                    // Confirm the payment immediately using the already-entered card details
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
                            // Payment succeeded - confirm on server
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
                    
                    accountElements = stripe.elements(options);
                    accountPaymentElement = accountElements.create('payment');
                    accountPaymentElement.mount('#payment-element-account');
                    
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
        
        // Submit the elements first (required by Stripe)
        accountElements.submit().then(function(submitResult) {
            if (submitResult.error) {
                // Show validation error
                $('#payment-errors-account').removeClass('success').addClass('error').text(submitResult.error.message).show();
                $button.removeClass('loading').prop('disabled', false);
                return;
            }
            
            // Confirm the payment with Stripe
            stripe.confirmPayment({
                elements: accountElements,
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
            },
            error: function() {
                alert('An error occurred. Please try again.');
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
    
    // Trial Timer Functionality
    console.log('Trial data check:', ieltsMS.trial);
    if (ieltsMS.trial && ieltsMS.trial.isTrial && ieltsMS.trial.endTime) {
        console.log('Trial timer initializing...');
        let timerInterval;
        
        // Validate endTime
        const endTime = Number(ieltsMS.trial.endTime);
        if (isNaN(endTime) || endTime <= 0) {
            console.error('Invalid trial end time');
            return;
        }
        console.log('Trial end time:', new Date(endTime * 1000));
        
        // Create and append the timer HTML using DOM methods for safety
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
            const now = Math.floor(Date.now() / 1000); // Current time in seconds
            const remaining = endTime - now;
            
            if (remaining <= 0) {
                // Trial has expired
                $('#trial-countdown').text('Expired').addClass('warning');
                clearInterval(timerInterval);
                return;
            }
            
            // Calculate hours and minutes
            const hours = Math.floor(remaining / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            
            // Format the display
            const displayText = hours + 'h ' + minutes + 'm';
            $('#trial-countdown').text(displayText);
            
            // Add warning class if less than 2 hours remaining
            if (remaining < 7200) { // 2 hours
                $('#trial-countdown').addClass('warning');
            }
        }
        
        // Update immediately and then every minute
        updateTrialTimer();
        timerInterval = setInterval(updateTrialTimer, 60000); // Update every minute
    }
});
