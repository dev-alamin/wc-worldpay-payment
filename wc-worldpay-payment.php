<?php
/**
 * Plugin Name: VH WorldPay Woocommerce
 * Plugin URI:  
 * Description: This is a payment gateway plugin for WorldPay
 * Version:     1.0.0
 * Author:      al main
 * Author URI:  https://almn.me
 * Text Domain: wcwp
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * Requires at least: 5.4
 * Requires PHP: 7.0
 * Requires Plugins:
 *
 * @package     wpwc
 * @author      al main
 * @copyright   2024 ads
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 *
 * Prefix:      wcwp
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

define( 'WCWP_VERSION', '1.0.0' );
define( 'WCWP_PLUGIN', __FILE__ );
define( 'WCWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCWP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', 'woocommerce_worldpay_init', 0 );

function woocommerce_worldpay_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_Gateway_Worldpay extends WC_Payment_Gateway {
        public function __construct() {
            $this->id                 = 'vh_worldpay';
            $this->icon               = plugins_url( '/assets/images/wp.png', __FILE__ ); // URL to an image file
            $this->has_fields         = true;
            $this->method_title       = __( 'Worldpay', 'woocommerce-worldpay-addon' );
            $this->method_description = __( 'Description of Worldpay payment gateway', 'woocommerce-worldpay-addon' );

                // Set title and description based on settings
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Save settings
            add_action( 'admin_notices', array( $this, 'admin_notices' ) );
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'woocommerce-worldpay-addon' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Worldpay', 'woocommerce-worldpay-addon' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __( 'Title', 'woocommerce-worldpay-addon' ),
                    'type'        => 'text',
                    'description' => __( 'Title that the user sees during checkout.', 'woocommerce-worldpay-addon' ),
                    'default'     => __( 'Pay by Card', 'woocommerce-worldpay-addon' ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Description', 'woocommerce-worldpay-addon' ),
                    'type'        => 'text',
                    'description' => __( 'Description for the payment gateway, visible to the merchant.', 'woocommerce-worldpay-addon' ),
                    'default'     => __( 'Accept all major debit and credit cards', 'woocommerce-worldpay-addon' ),
                    'desc_tip'    => true,
                ),
                'username' => array(
                    'title'       => __( 'API Username', 'woocommerce-worldpay-addon' ),
                    'type'        => 'text',
                    'description' => __( 'Your Worldpay API username.', 'woocommerce-worldpay-addon' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'password' => array(
                    'title'       => __( 'API Password', 'woocommerce-worldpay-addon' ),
                    'type'        => 'password',
                    'description' => __( 'Your Worldpay API password.', 'woocommerce-worldpay-addon' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'entity' => array(
                    'title'       => __( 'Merchant Entity', 'woocommerce-worldpay-addon' ),
                    'type'        => 'text',
                    'description' => __( 'Your Worldpay merchant entity ID.', 'woocommerce-worldpay-addon' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'test_mode' => array(
                    'title'       => __( 'Test Mode', 'woocommerce-worldpay-addon' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable test mode', 'woocommerce-worldpay-addon' ),
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
            );
        }
        
        public function admin_notices() {
            if ( $this->is_valid_for_use() ) {
                return;
            }
            echo '<div class="error"><p>' . sprintf( __( 'Worldpay payment gateway does not support your store currency. Please set your store currency to GBP.', 'woocommerce-worldpay-addon' ) ) . '</p></div>';
        }

        public function is_valid_for_use() {
            return in_array( get_woocommerce_currency(), array( 'GBP' ) );
        }

        public function process_payment( $order_id ) {
            global $woocommerce;
            $order = new WC_Order( $order_id );
            // Step 1: Check for WooCommerce validation errors before proceeding
            if ( WC()->cart->needs_payment() && WC()->cart->check_cart_items() ) {
                // Check if any notices (errors) were added by WooCommerce
                if ( wc_notice_count( 'error' ) > 0 ) {
                    wc_add_notice( __( 'Payment could not be processed due to cart errors. Please review your cart and try again.', 'woocommerce-worldpay-addon' ), 'error' );
                    return array(
                        'result'   => 'fail',
                        'redirect' => wc_get_checkout_url(), // Redirect to the checkout page again
                    );
                }
            }
        
            // Step 3: Verify the nonce to prevent CSRF
            if ( ! isset( $_POST['worldpay_payment_nonce'] ) || ! wp_verify_nonce( $_POST['worldpay_payment_nonce'], 'worldpay_payment' ) ) {
                wc_add_notice( __( 'Payment verification failed. Please try again.', 'woocommerce-worldpay-addon' ), 'error' );
                return array(
                    'result'   => 'fail',
                    'redirect' => wc_get_checkout_url(),
                );
            }
        
            // Step 4: Retrieve the order
            if ( ! $order ) {
                wc_add_notice( __( 'Order not found. Please try again.', 'woocommerce-worldpay-addon' ), 'error' );
                return array(
                    'result'   => 'fail',
                    'redirect' => wc_get_checkout_url(),
                );
            }
        
            // Step 5: Send the payment request
            $response = $this->send_request( $order );
        
            // Step 6: Handle the response from the payment gateway
            if ( isset( $response['result'] ) && $response['result'] === 'success' ) {
                // Handle successful payment
                // Remove cart
                $woocommerce->cart->empty_cart();

                return $this->handle_successful_payment( $response, $order );
            } else {
                return $this->handle_failed_payment( $response, $order );
            }
        }
        
        private function handle_successful_payment( $response, $order ) {
            if ( isset( $response['response']['outcome'] ) && ($response['response']['outcome'] === 'authorized' || 
            $response['response']['outcome'] === 'sentForSettlement') ) {
                // Payment was authorized or sent for settlement
                $order->add_order_note( 'Payment authorized. Awaiting settlement.' );
        
                if ( isset( $response['response']['code'] ) ) {
                    $order->update_meta_data( '_payment_response_code', $response['response']['code'] );
                }
        
                if ( isset( $response['response']['message'] ) ) {
                    $order->update_meta_data( '_payment_response_message', $response['response']['message'] );
                }
        
                $order->update_status( 'processing', 'Payment authorized. Awaiting settlement.' );
        
                wc_get_logger()->log( 'info', 'Payment successful for Order ' . $order->get_id() . '. Outcome: ' . $response['response']['outcome'] );
        
                $order->save();
        
                return [
                    'result'   => 'success',
                    'redirect' => $this->get_return_url( $order ),
                ];
        
            } else {
                // Outcome was not authorized or failed
                $order->add_order_note( 'Payment not authorized. Outcome: ' . $response['response']['outcome'] );
                wc_get_logger()->log( 'error', 'Payment error for Order ' . $order->get_id() . '. Outcome: ' . print_r( $response, true ) );
                wc_add_notice( __( 'Payment was not authorized. Please try again.', 'woocommerce-worldpay-addon' ), 'error' );
                return [
                    'result'   => 'failure',
                    'redirect' => '',
                ];
            }
        }        
        
        private function handle_failed_payment( $response, $order ) {
            // Log error for debugging (optional)
            if ( isset( $response['response']['message'] ) ) {
                $order->add_order_note( 'Payment failed: ' . $response['response']['message'] );
            } else {
                $order->add_order_note( 'Payment failed. No error message returned from gateway.' );
            }
        
            wc_add_notice( __( 'Payment failed. Please try again or contact support if the issue persists.', 'woocommerce-worldpay-addon' ), 'error' );
            return [
                'result'   => 'failure',
                'redirect' => '',
            ];
        }

        // Add this method to your class for logging
        protected function log( $message ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $logger = wc_get_logger();
                $logger->debug( $message, array( 'source' => 'woocommerce-worldpay-addon' ) );
            }
        }

        private function send_request( $order ) {
            // Retrieve and sanitize the card details from POST request
            $card_number = isset($_POST['worldpay-card-number']) ? sanitize_text_field($_POST['worldpay-card-number']) : '';
            $cvc         = isset($_POST['worldpay-card-cvc']) ? sanitize_text_field($_POST['worldpay-card-cvc']) : '';
            $expiry      = isset($_POST['worldpay-card-expiry']) ? sanitize_text_field($_POST['worldpay-card-expiry']) : '';
        
            // Remove any spaces or special characters from card number and expiry date
            $card_number = preg_replace('/\s+/', '', $card_number);
            // Remove spaces and non-numeric characters except for the slash
            $cleaned_expiry = str_replace(' ', '', $expiry); // Remove spaces
            $cleaned_expiry = preg_replace('/[^0-9\/]/', '', $cleaned_expiry); // Remove any other non-numeric characters
        
            // Split the cleaned input by slash
            $parts = explode('/', $cleaned_expiry);
        
            // Initialize variables for logging
            $expiry_month = null;
            $expiry_year = null;
        
            if (count($parts) == 2) {
                $month = intval($parts[0]);
                $year = intval($parts[1]);
        
                // Ensure the month and year are valid
                if ($month >= 1 && $month <= 12 && $year >= 0 && $year <= 99) {
                    // Convert 2-digit year to 4-digit year (assuming 20xx for 2-digit year)
                    $year += 2000;
                    $expiry_month = $month;
                    $expiry_year = $year;
                }
            }
        
            // Check if the card info is empty
            if (empty($card_number) || empty($expiry) || empty($cvc)) {
                $this->handle_error('Card is empty', 'Please put the card info');
                return;
            }
        
            // Ensure expiry month and year are valid and not in the past
            if (!$this->validate_expiry_date_logic($expiry_month, $expiry_year)) {
                return;
            }
        
            // Build the payload
            $payload = $this->build_payload($order, $card_number, $expiry_month, $expiry_year, $cvc);
        
            // Send the payment request
            return $this->process_payment_request($payload);
        }
        
        // Method to validate expiry date logic
        private function validate_expiry_date_logic($expiry_month, $expiry_year) {
            $current_year = intval(date('Y'));
            $current_month = intval(date('m'));
        
            // Validate expiry month (must be between 1 and 12)
            if ($expiry_month < 1 || $expiry_month > 12) {
                $this->handle_error('Invalid expiry month.', 'Invalid expiry month.');
                return false;
            }
        
            // Check if the card is expired
            if ($expiry_year < $current_year || ($expiry_year === $current_year && $expiry_month < $current_month)) {
                $this->handle_error('Expiry date is in the past.', 'Expiry date must not be in the past.');
                return false;
            }
        
            return true;
        }

        private function validate_entity( $entity ) {
            // Example validation: Ensure 'entity' is a non-empty alphanumeric string
            if( ! empty( $entity ) ) {
                trim( $entity );
                preg_match('/^[a-zA-Z0-9]+$/', $entity);
            }else{
                return wc_add_notice( 'Invalid entity, please correct it', 'error' );
            }

            return $entity;
        }
        
        // Method to build the payment payload
        private function build_payload($order, $card_number, $expiry_month, $expiry_year, $cvc ) {
            $allowed_characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_!@#$%()*=-.:;?[]{}~/+';
            $transaction_reference = 'VapeHub_' . $order->get_order_number();
            $sanitized_reference = preg_replace('/[^' . preg_quote($allowed_characters, '/') . ']/', '', $transaction_reference);

            return array(
                'transactionReference' => $sanitized_reference,
                'merchant' => array(
                    'entity' => $this->validate_entity( $this->get_option( 'entity' ) ),
                ),
                'instruction' => array(
                    "settlement" => array(
                        "auto" => true
                        ),
                    'method' => 'card',
                    'paymentInstrument' => array(
                        'type' => 'plain',
                        'cardHolderName' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        'cardNumber' => $card_number,
                        'expiryDate' => array(
                            'month' => $expiry_month,
                            'year' => $expiry_year
                        ),
                        "cvc" => $cvc,  // CVC code
                    ),
                    'tokenCreation' => array(
                        'type' => 'worldpay'
                    ),
                    'customerAgreement' => array(
                        'type' => 'cardOnFile',
                        'storedCardUsage' => 'first'
                    ),
                    'narrative' => array(
                        'line1' => 'trading name'
                    ),
                    'value' => array(
                        'currency' => 'GBP',
                        'amount' => intval($order->get_total() * 100), // Convert to pence if needed
                    ),
                )
            );
        }
        
        // Method to send the payment request
        private function process_payment_request($payload) {
            $username = $this->get_option('username');
            $password = $this->get_option('password');
            $test_mode = $this->get_option('test_mode') === 'yes';
        
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $test_mode ? 'https://try.access.worldpay.com/api/payments' : 'https://access.worldpay.com/api/payments',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'WP-Api-Version: 2024-06-01',
                    'Authorization: Basic ' . base64_encode($username . ':' . $password)
                ]
            ]);
        
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        
            // Decode the response
            $response_data = json_decode($response, true);

            error_log( print_r( $response_data, true ) );

            if ($http_code >= 200 && $http_code < 300) {
                // Handle successful response
                return array(
                    'result' => 'success',
                    'messages' => 'Payment processed successfully.',
                    'response' => $response_data
                );
            } else {
                    // Handle failure response
                    $error_message = isset($response_data['refusalDescription']) ? $response_data['refusalDescription'] : 'Unknown error';
                    $refusal_code = isset($response_data['refusalCode']) ? $response_data['refusalCode'] : 'No refusal code provided';

                    error_log('Refusal Code: ' . $refusal_code . ' - ' . $error_message);

                    switch ($refusal_code) {
                        case '5':
                            $custom_message = 'Transaction declined: Do not honour. Please contact your bank or try a different card.';
                            break;

                        default:
                            $custom_message = 'Transaction failed: ' . $error_message;
                            break;
                    }

                    $this->handle_error('Payment failed: ' . $custom_message, 'Refusal Code: ' . $refusal_code . ' - ' . $error_message);

                return false;
            }
        }
        
        // Centralized error handler method
        private function handle_error($log_message, $user_message) {
            error_log($log_message); // Log error for developers
            wc_add_notice(__($user_message, 'woocommerce-worldpay-addon'), 'error'); // Show user-friendly message in WooCommerce
        }

        public function payment_fields() {
            // Display a brief description, if available
            if ( $this->description ) {
                echo wpautop( wp_kses_post( $this->description ) );
            }
        
            // Add a nonce field for security
            wp_nonce_field( 'worldpay_payment', 'worldpay_payment_nonce' );
        
            // Open the fieldset for credit card input
            echo '<fieldset id="wc-worldpay-cc-form" class="wc-credit-card-form wc-payment-form">';
        
            // Card Number Field
            echo '<p class="form-row form-row-wide">
                <label for="worldpay-card-number">' . __( 'Card Number', 'woocommerce-worldpay-addon' ) . ' <span class="required">*</span></label>
                <input id="worldpay-card-number" name="worldpay-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" 
                       autocomplete="cc-number" autocorrect="no" autocapitalize="no" maxlength="19" spellcheck="no" 
                       type="tel" placeholder="•••• •••• •••• ••••" required>
            </p>';
        
            // Expiry Date Field
            echo '<p class="form-row form-row-first">
                <label for="worldpay-card-expiry">' . __( 'Expiry (MM/YY)', 'woocommerce-worldpay-addon' ) . ' <span class="required">*</span></label>
                <input id="worldpay-card-expiry" name="worldpay-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" 
                       autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" 
                       type="tel" placeholder="MM / YY" pattern="[0-9]{2}/[0-9]{2}" title="MM / YY" required>
            </p>';
        
            // CVC Code Field
            echo '<p class="form-row form-row-last">
                <label for="worldpay-card-cvc">' . __( 'Card Code (CVC)', 'woocommerce-worldpay-addon' ) . ' <span class="required">*</span></label>
                <input id="worldpay-card-cvc" name="worldpay-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" 
                       autocomplete="cc-csc" autocorrect="no" autocapitalize="no" spellcheck="no" 
                       type="tel" maxlength="4" placeholder="CVC" pattern="[0-9]{3,4}" title="3 or 4 digits" required>
            </p>';
        
            // Clear div to ensure layout integrity
            echo '<div class="clear"></div>';
        
            // Close the fieldset
            echo '</fieldset>';
        } 

            /**
         * Process the refund.
         *
         * @param int    $order_id Order ID.
         * @param float  $amount   Refund amount.
         * @param string $reason   Refund reason.
         * @return bool|WP_Error True or false based on success, or a WP_Error object on failure.
         */
        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $order = wc_get_order( $order_id );
    
            if ( ! $order || ! $order->get_transaction_id() ) {
                return new WP_Error( 'invalid_order', __( 'Invalid order or missing transaction ID.', 'woocommerce-worldpay-addon' ) );
            }
    
            // Prepare the payload for the refund
            $payload = array(
                'refundAmount' => intval($amount), // Amount in the smallest currency unit
                'reason'       => $reason ? $reason : 'Customer requested refund',
            );
    
            // Worldpay's linkData (transaction reference)
            $linkData = $order->get_transaction_id();

            $username = $this->get_option('username');
            $password = $this->get_option('password');
            $test_mode = $this->get_option('test_mode') === 'yes';

            // Set up the cURL request
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "WP-Api-Version: 2024-06-01", // Use the correct API version
                    "Authorization: Basic " . base64_encode("$username:$password") // Replace with your credentials
                ),
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_URL => $test_mode ? "https://try.access.worldpay.com/api/payments" . $linkData . "/refunds" : "https://access.worldpay.com/api/payments" . $linkData . "/refunds",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
            ));
    
            // Execute the cURL request
            $response = curl_exec($curl);
            $error = curl_error($curl);
    
            curl_close($curl);
    
            // Handle the response
            if ($error) {
                return new WP_Error( 'refund_error', __( 'cURL Error: ', 'woocommerce-worldpay-addon' ) . $error );
            } else {
                $response_data = json_decode($response, true);
    
                if (isset($response_data['outcome']) && $response_data['outcome'] === 'refunded') {
                    // Refund was successful
                    $order->add_order_note( sprintf( __( 'Refunded %s - Refund ID: %s', 'woocommerce-worldpay-addon' ), wc_price( $amount ), $response_data['refundId'] ) );
                    return true;
                } else {
                    // Refund failed
                    return new WP_Error( 'refund_error', __( 'Refund failed: ', 'woocommerce-worldpay-addon' ) . $response_data['message'] );
                }
            }
        }

        /**
         * Send the refund request to Worldpay.
         *
         * @param array $payload The refund payload.
         * @return array The response from Worldpay.
         */
        protected function send_refund_request( $payload ) {
            $response = wp_remote_post( 'https://api.worldpay.com/refunds', array(
                'body'    => json_encode( $payload ),
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( 'your_service_key' ), // Replace with your Worldpay service key
                    'Content-Type'  => 'application/json',
                ),
            ));

            if ( is_wp_error( $response ) ) {
                return array( 'outcome' => 'error', 'message' => $response->get_error_message() );
            }

            return json_decode( wp_remote_retrieve_body( $response ), true );
        }
    }

    function add_worldpay_gateway( $methods ) {
        $methods[] = 'WC_Gateway_Worldpay';
        return $methods;
    }

    add_filter( 'woocommerce_payment_gateways', 'add_worldpay_gateway' );

//     add_filter('woocommerce_available_payment_gateways', 'restrict_payment_gateways_for_admin');

    function restrict_payment_gateways_for_admin($gateways) {
        if (!current_user_can('manage_options')) { // Check if the user is an admin
            foreach ($gateways as $gateway_id => $gateway) {
                // Replace 'cod' with your payment gateway ID
                if ($gateway_id === 'vh_worldpay') {
                    unset($gateways[$gateway_id]);
                }
            }
        }
        return $gateways;
    }

}

add_action('wp_enqueue_scripts', 'enqueue_worldpay_scripts');
function enqueue_worldpay_scripts() {
    if (is_checkout()) {
        // Enqueue a script that will handle payment method checks
        wp_enqueue_script('worldpay-checkout-handler', plugins_url('/js/worldpay-checkout.js', __FILE__), array('jquery'), time(), true);
    }
}


