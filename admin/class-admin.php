<?php
/**
 * Admin settings and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class IELTS_MS_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Membership System',
            'Membership',
            'manage_options',
            'ielts-membership',
            array($this, 'settings_page'),
            'dashicons-id-alt',
            30
        );
        
        add_submenu_page(
            'ielts-membership',
            'Settings',
            'Settings',
            'manage_options',
            'ielts-membership',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'ielts-membership',
            'Members',
            'Members',
            'manage_options',
            'ielts-membership-members',
            array($this, 'members_page')
        );
        
        add_submenu_page(
            'ielts-membership',
            'Payments',
            'Payments',
            'manage_options',
            'ielts-membership-payments',
            array($this, 'payments_page')
        );
        
        add_submenu_page(
            'ielts-membership',
            'Documentation',
            'Documentation',
            'manage_options',
            'ielts-membership-documentation',
            array($this, 'documentation_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // PayPal settings
        register_setting('ielts_ms_settings', 'ielts_ms_paypal_enabled');
        register_setting('ielts_ms_settings', 'ielts_ms_paypal_email');
        register_setting('ielts_ms_settings', 'ielts_ms_paypal_sandbox');
        
        // Stripe settings
        register_setting('ielts_ms_settings', 'ielts_ms_stripe_enabled');
        register_setting('ielts_ms_settings', 'ielts_ms_stripe_publishable_key');
        register_setting('ielts_ms_settings', 'ielts_ms_stripe_secret_key');
        register_setting('ielts_ms_settings', 'ielts_ms_stripe_webhook_secret');
        
        // General settings
        register_setting('ielts_ms_settings', 'ielts_ms_custom_login_enabled');
        register_setting('ielts_ms_settings', 'ielts_ms_login_page_id');
        register_setting('ielts_ms_settings', 'ielts_ms_account_page_id');
        register_setting('ielts_ms_settings', 'ielts_ms_logged_in_homepage_id');
        
        // Pricing settings
        register_setting('ielts_ms_settings', 'ielts_ms_price_new_90');
        register_setting('ielts_ms_settings', 'ielts_ms_price_extend_7');
        register_setting('ielts_ms_settings', 'ielts_ms_price_extend_30');
        register_setting('ielts_ms_settings', 'ielts_ms_price_extend_90');
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('ielts_ms_settings_nonce');
            
            update_option('ielts_ms_paypal_enabled', isset($_POST['paypal_enabled']));
            update_option('ielts_ms_paypal_email', sanitize_email($_POST['paypal_email']));
            update_option('ielts_ms_paypal_sandbox', isset($_POST['paypal_sandbox']));
            
            update_option('ielts_ms_stripe_enabled', isset($_POST['stripe_enabled']));
            update_option('ielts_ms_stripe_publishable_key', sanitize_text_field($_POST['stripe_publishable_key']));
            update_option('ielts_ms_stripe_secret_key', sanitize_text_field($_POST['stripe_secret_key']));
            update_option('ielts_ms_stripe_webhook_secret', sanitize_text_field($_POST['stripe_webhook_secret']));
            
            update_option('ielts_ms_custom_login_enabled', isset($_POST['custom_login_enabled']));
            
            // Validate and save logged-in homepage
            $homepage_id = isset($_POST['logged_in_homepage_id']) ? intval($_POST['logged_in_homepage_id']) : 0;
            if ($homepage_id > 0) {
                $page_status = get_post_status($homepage_id);
                if ($page_status === 'publish') {
                    update_option('ielts_ms_logged_in_homepage_id', $homepage_id);
                }
            } else {
                update_option('ielts_ms_logged_in_homepage_id', 0);
            }
            
            // Update pricing with validation
            if (isset($_POST['price_new_90'])) {
                update_option('ielts_ms_price_new_90', floatval($_POST['price_new_90']));
            }
            if (isset($_POST['price_extend_7'])) {
                update_option('ielts_ms_price_extend_7', floatval($_POST['price_extend_7']));
            }
            if (isset($_POST['price_extend_30'])) {
                update_option('ielts_ms_price_extend_30', floatval($_POST['price_extend_30']));
            }
            if (isset($_POST['price_extend_90'])) {
                update_option('ielts_ms_price_extend_90', floatval($_POST['price_extend_90']));
            }
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $paypal_enabled = get_option('ielts_ms_paypal_enabled', true);
        $paypal_email = get_option('ielts_ms_paypal_email', '');
        $paypal_sandbox = get_option('ielts_ms_paypal_sandbox', false);
        
        $stripe_enabled = get_option('ielts_ms_stripe_enabled', true);
        $stripe_publishable_key = get_option('ielts_ms_stripe_publishable_key', '');
        $stripe_secret_key = get_option('ielts_ms_stripe_secret_key', '');
        $stripe_webhook_secret = get_option('ielts_ms_stripe_webhook_secret', '');
        
        $custom_login_enabled = get_option('ielts_ms_custom_login_enabled', true);
        
        ?>
        <div class="wrap">
            <h1>Membership System Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('ielts_ms_settings_nonce'); ?>
                
                <h2>PayPal Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable PayPal</th>
                        <td>
                            <label>
                                <input type="checkbox" name="paypal_enabled" value="1" <?php checked($paypal_enabled); ?>>
                                Enable PayPal payments
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">PayPal Email</th>
                        <td>
                            <input type="email" name="paypal_email" value="<?php echo esc_attr($paypal_email); ?>" class="regular-text">
                            <p class="description">Your PayPal business email address</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sandbox Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="paypal_sandbox" value="1" <?php checked($paypal_sandbox); ?>>
                                Enable PayPal sandbox for testing
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2>Stripe Settings</h2>
                <p class="description" style="margin-bottom: 15px;">
                    <strong>Setup Instructions:</strong> Get your API keys from the <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a> (Developers → API Keys).
                    Use <strong>test keys</strong> (starting with <code>pk_test_</code> and <code>sk_test_</code>) for testing, and <strong>live keys</strong> (starting with <code>pk_live_</code> and <code>sk_live_</code>) for production.
                </p>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Stripe</th>
                        <td>
                            <label>
                                <input type="checkbox" name="stripe_enabled" value="1" <?php checked($stripe_enabled); ?>>
                                Enable Stripe payments
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Publishable Key <span style="color: red;" aria-label="required">*</span></th>
                        <td>
                            <input type="text" name="stripe_publishable_key" value="<?php echo esc_attr($stripe_publishable_key); ?>" class="regular-text" placeholder="pk_test_... or pk_live_..." aria-required="true">
                            <p class="description">
                                <strong>Where to find:</strong> <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a> → Developers → API Keys → "Publishable key"<br>
                                <strong>Test mode:</strong> Starts with <code>pk_test_</code> (e.g., <code>pk_test_51A1B2C3...</code>)<br>
                                <strong>Live mode:</strong> Starts with <code>pk_live_</code> (e.g., <code>pk_live_51A1B2C3...</code>)<br>
                                <strong>Security:</strong> Publishable keys are <em>designed by Stripe to be safely exposed in client-side code</em>. Unlike secret keys, they cannot access sensitive account data or move money. They are primarily used to create secure tokens for payment information and can only access non-sensitive public data.<br>
                                <strong>Current implementation:</strong> This plugin uses server-side Stripe Checkout sessions, so the publishable key is stored securely on the server and not exposed to the browser.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Secret Key <span style="color: red;" aria-label="required">*</span></th>
                        <td>
                            <input type="password" name="stripe_secret_key" value="<?php echo esc_attr($stripe_secret_key); ?>" class="regular-text" placeholder="sk_test_... or sk_live_..." aria-required="true">
                            <p class="description">
                                <strong>Where to find:</strong> <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a> → Developers → API Keys → "Secret key" (click "Reveal test key" or "Reveal live key")<br>
                                <strong>Test mode:</strong> Starts with <code>sk_test_</code> (e.g., <code>sk_test_51A1B2C3...</code>)<br>
                                <strong>Live mode:</strong> Starts with <code>sk_live_</code> (e.g., <code>sk_live_51A1B2C3...</code>)<br>
                                <em>Keep this key confidential! Never share it publicly or commit it to version control.</em>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Webhook Secret <span style="color: #646970;">(Optional)</span></th>
                        <td>
                            <input type="text" name="stripe_webhook_secret" value="<?php echo esc_attr($stripe_webhook_secret); ?>" class="regular-text" placeholder="whsec_...">
                            <p class="description">
                                <strong>Webhook Endpoint URL:</strong> <code><?php echo admin_url('admin-ajax.php?action=ielts_ms_stripe_webhook'); ?></code><br>
                                <strong>Setup instructions:</strong><br>
                                1. Go to <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Dashboard</a> → Developers → Webhooks<br>
                                2. Click "Add endpoint"<br>
                                3. Enter the URL above as the endpoint URL<br>
                                4. Select event: <code>checkout.session.completed</code><br>
                                5. Click "Add endpoint"<br>
                                6. Click on your new webhook and reveal the "Signing secret" (starts with <code>whsec_</code>)<br>
                                7. Copy and paste it here<br>
                                <em>Webhooks ensure payment confirmations are securely verified. While optional, they are highly recommended for production use.</em>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2>Login Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Custom Login</th>
                        <td>
                            <label>
                                <input type="checkbox" name="custom_login_enabled" value="1" <?php checked($custom_login_enabled); ?>>
                                Replace WordPress login with custom login page
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Login Page</th>
                        <td>
                            <?php
                            $login_page = get_page_by_path('membership-login');
                            if ($login_page) {
                                echo '<a href="' . get_permalink($login_page) . '" target="_blank">View Login Page</a>';
                            } else {
                                echo 'Login page not found';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Account Page</th>
                        <td>
                            <?php
                            $account_page = get_page_by_path('my-account');
                            if ($account_page) {
                                echo '<a href="' . get_permalink($account_page) . '" target="_blank">View Account Page</a>';
                            } else {
                                echo 'Account page not found';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Logged-In Homepage</th>
                        <td>
                            <?php
                            $logged_in_homepage_id = get_option('ielts_ms_logged_in_homepage_id', 0);
                            wp_dropdown_pages(array(
                                'name' => 'logged_in_homepage_id',
                                'selected' => $logged_in_homepage_id,
                                'show_option_none' => 'Default Homepage',
                                'option_none_value' => 0
                            ));
                            ?>
                            <p class="description">Select a page to redirect logged-in users to when they visit the homepage</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Pricing Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">New 90-Day Membership</th>
                        <td>
                            $<input type="number" step="0.01" min="0" name="price_new_90" value="<?php echo esc_attr(get_option('ielts_ms_price_new_90', 24.95)); ?>" class="small-text"> USD
                            <p class="description">Price for a new 90-day membership</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">1 Week Extension</th>
                        <td>
                            $<input type="number" step="0.01" min="0" name="price_extend_7" value="<?php echo esc_attr(get_option('ielts_ms_price_extend_7', 5.00)); ?>" class="small-text"> USD
                            <p class="description">Price for a 7-day extension</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">1 Month Extension</th>
                        <td>
                            $<input type="number" step="0.01" min="0" name="price_extend_30" value="<?php echo esc_attr(get_option('ielts_ms_price_extend_30', 10.00)); ?>" class="small-text"> USD
                            <p class="description">Price for a 30-day extension</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">3 Months Extension</th>
                        <td>
                            $<input type="number" step="0.01" min="0" name="price_extend_90" value="<?php echo esc_attr(get_option('ielts_ms_price_extend_90', 20.00)); ?>" class="small-text"> USD
                            <p class="description">Price for a 90-day extension</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Members page
     */
    public function members_page() {
        $membership = new IELTS_MS_Membership();
        $memberships = $membership->get_all_memberships();
        
        ?>
        <div class="wrap">
            <h1>Members</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($memberships)): ?>
                        <tr>
                            <td colspan="6">No memberships found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($memberships as $member): 
                            $user = get_userdata($member->user_id);
                            if (!$user) continue;
                            
                            $now = time();
                            $end = strtotime($member->end_date);
                            $days_remaining = $end > $now ? ceil(($end - $now) / (60 * 60 * 24)) : 0;
                            ?>
                            <tr>
                                <td><?php echo esc_html($user->user_login); ?></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($member->status); ?>">
                                        <?php echo ucfirst($member->status); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($member->start_date)); ?></td>
                                <td><?php echo date('M j, Y', strtotime($member->end_date)); ?></td>
                                <td><?php echo $days_remaining; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Payments page
     */
    public function payments_page() {
        global $wpdb;
        $table = IELTS_MS_Database::get_payments_table();
        
        $payments = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY payment_date DESC LIMIT 100"
        );
        
        ?>
        <div class="wrap">
            <h1>Payments</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Transaction ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="7">No payments found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): 
                            $user = get_userdata($payment->user_id);
                            ?>
                            <tr>
                                <td><?php echo date('M j, Y H:i', strtotime($payment->payment_date)); ?></td>
                                <td><?php echo $user ? esc_html($user->user_login) : 'N/A'; ?></td>
                                <td><?php echo '$' . number_format($payment->amount, 2); ?> <?php echo esc_html($payment->currency); ?></td>
                                <td><?php echo ucfirst($payment->payment_type); ?></td>
                                <td><?php echo ucfirst($payment->payment_method); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($payment->payment_status); ?>">
                                        <?php echo ucfirst($payment->payment_status); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($payment->transaction_id ?: 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Documentation page
     */
    public function documentation_page() {
        ?>
        <div class="wrap">
            <h1>Membership System Documentation</h1>
            
            <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2>Available Shortcodes</h2>
                <p>Use these shortcodes to display membership system pages on your WordPress site.</p>
                
                <hr style="margin: 20px 0;">
                
                <h3>Login Form</h3>
                <p><code>[ielts_membership_login]</code></p>
                <p><strong>Description:</strong> Displays the membership login form. This shortcode also includes forgot password and password reset functionality.</p>
                <p><strong>Usage:</strong> Add this shortcode to any page or post where you want users to be able to log in.</p>
                <p><strong>Features:</strong></p>
                <ul>
                    <li>User login with username/email and password</li>
                    <li>Forgot password link and reset functionality</li>
                    <li>Link to registration page</li>
                    <li>Link to legacy course for pre-2026 enrollees</li>
                    <li>Automatically redirects logged-in users to their account page</li>
                </ul>
                <p><strong>Default Page:</strong> <code>/membership-login/</code></p>
                
                <hr style="margin: 20px 0;">
                
                <h3>Registration Form</h3>
                <p><code>[ielts_membership_register]</code></p>
                <p><strong>Description:</strong> Displays the membership registration form for new users to create an account.</p>
                <p><strong>Usage:</strong> Add this shortcode to any page or post where you want new users to register.</p>
                <p><strong>Features:</strong></p>
                <ul>
                    <li>Create new user account</li>
                    <li>Email and password fields</li>
                    <li>Link to login page for existing users</li>
                    <li>Automatically redirects logged-in users to their account page</li>
                </ul>
                <p><strong>Default Page:</strong> <code>/membership-register/</code></p>
                
                <hr style="margin: 20px 0;">
                
                <h3>Account Page</h3>
                <p><code>[ielts_membership_account]</code></p>
                <p><strong>Description:</strong> Displays the user account dashboard where members can manage their membership and account settings.</p>
                <p><strong>Usage:</strong> Add this shortcode to any page where you want users to access their account dashboard.</p>
                <p><strong>Features:</strong></p>
                <ul>
                    <li>View membership status and expiration date</li>
                    <li>Purchase new membership (90 days for $24.95)</li>
                    <li>Extend existing or expired membership (1 week, 1 month, or 3 months)</li>
                    <li>Change email address</li>
                    <li>Change password</li>
                    <li>View payment history</li>
                    <li>Logout functionality</li>
                    <li>Requires user to be logged in (redirects to login page if not)</li>
                </ul>
                <p><strong>Default Page:</strong> <code>/my-account/</code></p>
                
                <hr style="margin: 20px 0;">
                
                <h3>Example Implementation</h3>
                <p><strong>Creating a custom login page:</strong></p>
                <ol>
                    <li>Create a new page in WordPress (Pages → Add New)</li>
                    <li>Give it a title like "Member Login"</li>
                    <li>In the content area, add: <code>[ielts_membership_login]</code></li>
                    <li>Publish the page</li>
                </ol>
                
                <p><strong>Creating a custom account page:</strong></p>
                <ol>
                    <li>Create a new page in WordPress (Pages → Add New)</li>
                    <li>Give it a title like "My Dashboard"</li>
                    <li>In the content area, add: <code>[ielts_membership_account]</code></li>
                    <li>Publish the page</li>
                </ol>
                
                <hr style="margin: 20px 0;">
                
                <h3>Important Notes</h3>
                <ul>
                    <li><strong>Default Pages:</strong> The plugin automatically creates pages with these shortcodes during activation.</li>
                    <li><strong>Custom Pages:</strong> You can create additional pages with these shortcodes if needed.</li>
                    <li><strong>Redirects:</strong> Login and registration shortcodes automatically redirect logged-in users to the account page.</li>
                    <li><strong>Security:</strong> The account page requires users to be logged in and will redirect to the login page if they're not.</li>
                    <li><strong>Settings:</strong> Configure the custom login page and account page IDs in Membership → Settings.</li>
                </ul>
                
                <hr style="margin: 20px 0;">
                
                <h3>Additional Resources</h3>
                <p>For more information about the membership system, please refer to:</p>
                <ul>
                    <li><strong>Settings:</strong> Membership → Settings - Configure payment gateways and login options</li>
                    <li><strong>Members:</strong> Membership → Members - View all members and their status</li>
                    <li><strong>Payments:</strong> Membership → Payments - Track all payment transactions</li>
                </ul>
            </div>
        </div>
        <?php
    }
}
