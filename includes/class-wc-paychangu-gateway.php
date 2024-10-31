<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Paychangu_Gateway
 */
class Paychangu_Gateway extends WC_Payment_Gateway {

	/**
	 * Checkout page title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Checkout page description
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Is gateway enabled?
	 *
	 * @var bool
	 */
	public $enabled;

	/**
	 * API public key.
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * API secret key.
	 *
	 * @var string
	 */
	public $secret_key;

    /**
     * Invoice Prefix for the webiste
     * @var string
     */
    public $invoice_prefix;
    
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id  = 'paychangu';
        $this->icon = PAYCHANGU_GATEWAY_URL . '/assets/images/icon.png';
        $this->has_fields = true;
		$this->method_title = 'PayChangu';
		$this->method_description = 'Pay with PayChangu';
        $this->order_button_text = __( 'Proceed to PayChangu', 'paychangu' );
		$this->supports = array(
			'products',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled = $this->get_option( 'enabled' );
		$this->public_key = $this->get_option( 'public_key' );
		$this->secret_key = $this->get_option( 'secret_key' );
        $this->invoice_prefix = $this->get_option( 'invoice_prefix' );

		// Hooks.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		// Payment listener/API hook.
		add_action( 'woocommerce_api_paychangu_gateway', array( $this, 'verify_paychangu_transaction' ) );
		// Webhook listener/API hook.
		add_action( 'woocommerce_api_paychangu_success', array( $this, 'process_success' ) );
        add_action( 'woocommerce_api_paychangu_proceed', array( $this, 'paychangu_proceed' ) );
	}

	/**
     * @param bool $string
     * @return array|string
     * Get currently supported currencies from Paychangu
     */
    public function get_supported_currencies($string = false){
	    $currency_array = array('MWK', 'NGN', 'ZAR', 'GBP', 'USD');
		if ($string === true) {
			return implode(", ", $currency_array);
		}
		return $currency_array;
    }

	/**
	 * Check if Paychangu merchant details is filled
	 */
	public function admin_notices() {

		if ( 'no' === $this->enabled ) {
			return;
		}

		// Check required fields.
		if ( ! ( $this->public_key && $this->secret_key ) ) {
			echo '<div class="error"><p>Please enter your Paychangu merchant details <a href="' . esc_url(admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paychangu' )) . '">here</a> to be able to use the PayChangu WooCommerce Gateway plugin.</p></div>';
			return;
		}

	}

	/**
	 * Check if Paychangu gateway is enabled.
	 */
	public function is_available() {

		if ( 'yes' === $this->enabled ) {

			if ( ! ( $this->public_key && $this->secret_key ) ) {

				return false;

			}

			return true;

		}

		return false;

	}

	/**
	 * Admin Panel Options
	 */
	public function admin_options() { ?>
		<h3>Paychangu</h3>
		<h4>Our Supported Currencies: <?php echo esc_html($this->get_supported_currencies(true)); ?></h4>
		<?php
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'         => array(
				'title'       => __( 'Enable/Disable', 'paychangu' ),
				'label'       => __( 'Enable PayChangu Geteway', 'paychangu' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable PayChangu as a payment option on the checkout page.', 'paychangu' ),
				'default'     => 'yes',
				'desc_tip'    => false
			),
			'title'           => array(
				'title'       => __( 'Title', 'paychangu' ),
				'type'        => 'text',
				'description' => __( 'This controls the payment method title which the user sees during checkout.', 'paychangu' ),
				'desc_tip'    => false,
				'default'     => __( 'Paychangu', 'paychangu' ),
			),
			'description'     => array(
				'title'       => __( 'Description', 'paychangu' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the payment method description which the user sees during checkout.', 'paychangu' ),
				'desc_tip'    => false,
				'default'     => __( 'PayChangu via your debit, credit card & Mobile Money', 'paychangu' ),
			),
            'invoice_prefix' => array(
                'title'       => __( 'Invoice Prefix', 'paychangu' ),
                'type'        => 'text',
                'description' => __( 'Please enter a prefix for your invoice numbers. If you use your Paychangu account for multiple stores ensure this prefix is unique as Paychangu will not allow orders with the same invoice number.', 'paychangu' ),
                'default'     => 'WC_',
                'desc_tip'    => false,
            ),
			'public_key' => array(
				'title'       => __( 'Public Key', 'paychangu' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Public Key here. You can get your Public Key from <a href="https://in.paychangu.com/user/profile/api">here</a>', 'paychangu' ),
				'default'     => '',
				'desc_tip'    => false,
			),
			'secret_key' => array(
				'title'       => __( 'Secret Key', 'paychangu' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Secret Key here. You can get your Secret Key from <a href="https://in.paychangu.com/user/profile/api">here</a>', 'paychangu' ),
				'default'     => '',
				'desc_tip'    => false,
			)
		);

	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		if ( $this->description ) {
			// echo esc_html( wpautop( wptexturize( $this->description ) ) );
			echo wpautop( wptexturize( $this->description ) );
		}

		if ( ! is_ssl() ){
			return;
		}
	}

    /**
     * Process the payment and return the result.
     *
     * @param  int $order_id Order ID.
     * @return array
     */
    public function process_payment( $order_id ) {
        global $woocommerce;
        $order = new WC_Order( $order_id );
        // Remove cart
        $woocommerce->cart->empty_cart();
        $currency = $order->get_currency();
        $currency_array = $this->get_supported_currencies();
        $currency_code = in_array( $currency , $currency_array ) ? $currency : '';
        $secret_key = urlencode($this->secret_key);
        $tx_ref = urlencode($this->invoice_prefix . $order_id . strtotime('now'));
        $amount = urlencode($order->get_total());
        $email = urlencode($order->get_billing_email());
		$callback_url = urlencode(WC()->api_request_url( 'Paychangu_Success' ));
        $first_name = urlencode($order->get_billing_first_name());
        $last_name = urlencode($order->get_billing_last_name());
		$title = urlencode("Payment For Items on " . get_bloginfo('name'));
        $url = WC()->api_request_url( 'Paychangu_Proceed' ) . "?order_id={$order_id}&secret_key={$secret_key}&callback_url={$callback_url}&return_url={$callback_url}&tx_ref={$tx_ref}&amount={$amount}&email={$email}&first_name={$first_name}&last_name={$last_name}&title={$title}&currency={$currency_code}";
        // Return to Paychangu Proceed page for the next step
        return array(
            'result' => 'success',
            'redirect' => $url
        );
    }

    /**
     * API page to handle the callback data from Paychangu
     */
    public function process_success(){
		$tx_ref = sanitize_text_field($_GET['tx_ref']);
        if ($tx_ref) {
            // Verify Paychangu payment
            $paychangu_request = wp_remote_get(
                'https://api.paychangu.com/verify-payment/' . $tx_ref,
				[
					'method' => 'GET',
					'headers' => [
						'content-type' => 'application/json',
						'Authorization' => 'Bearer ' . $this->secret_key,
					]
				]
            );
            if ( ! is_wp_error( $paychangu_request ) && 200 == wp_remote_retrieve_response_code( $paychangu_request ) ) {
                $paychangu_order = json_decode( wp_remote_retrieve_body( $paychangu_request ) );
                $status = $paychangu_order->status;
				$order_id = $paychangu_order->data->meta->order_id;
            	$wc_order = wc_get_order($order_id);
                if ($status === "success") {
                    $order_total = floatval(preg_replace('/[^\d\.]+/', '', $wc_order->get_total()));
                    $amount_paid = floatval(preg_replace('/[^\d\.]+/', '', $paychangu_order->data->amount));
                    $order_currency = $wc_order->get_currency();
                    $currency_symbol = get_woocommerce_currency_symbol( $order_currency );
                    if ($amount_paid < $order_total) {
                        // Mark as on-hold
                        $wc_order->update_status('on-hold','' );
                        update_post_meta( $order_id, '_transaction_id', $tx_ref );
                        $notice      = 'Thank you for shopping with us.<br />Your payment was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
                        $notice_type = 'notice';
                        // Add Customer Order Note
                        $wc_order->add_order_note( $notice, 1 );
                        // Add Admin Order Note
                        $wc_order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was <strong>' . $currency_symbol . $amount_paid . '</strong> while the total order amount is <strong>' . $currency_symbol . $order_total . '</strong><br /><strong>Reference ID:</strong> ' . $tx_ref);

                        wc_add_notice( $notice, $notice_type );
                    } else {
                        //Complete order
                        $wc_order->payment_complete( $tx_ref );
                        $wc_order->add_order_note( sprintf( 'Payment via PayChangu successful (<strong>Reference ID:</strong> %s)', $tx_ref ) );
                    }
                    wp_redirect($this->get_return_url($wc_order));
                    die();
                } else if ($status === "cancelled") {
                    $wc_order->update_status( 'canceled', 'Payment was canceled.' );
                    wc_add_notice( 'Payment was canceled.', 'error' );
                    // Add Admin Order Note
                    $wc_order->add_order_note('Payment was canceled by PayChangu.');
                    wp_redirect( wc_get_page_permalink( 'checkout' ) );
                    die();
                } else {
                    $wc_order->update_status( 'failed', 'Payment was declined by PayChangu.' );
                    wc_add_notice( 'Payment was declined by Paychangu.', 'error' );
                    // Add Admin Order Note
                    $wc_order->add_order_note('Payment was declined by PayChangu.');
                    wp_redirect( wc_get_page_permalink( 'checkout' ) );
                    die();
                }
            }
        }
        die();
    }

    /**
     * API page to redirect user to Paychangu
     */
    public function paychangu_proceed() {
        $invalid = 0;
		$order_id = sanitize_text_field($_GET['order_id']);
		$secret_key = sanitize_text_field($_GET['secret_key']);
		$callback_url = sanitize_url($_GET['callback_url']);
		$tx_ref = sanitize_text_field($_GET['tx_ref']);
		$amount = floatval(sanitize_text_field($_GET['amount']));
		$email = sanitize_email($_GET['email']);
		$first_name = sanitize_text_field($_GET['first_name']);
		$last_name = sanitize_text_field($_GET['last_name']);
		$title = sanitize_text_field($_GET['title']);
		$currency = sanitize_text_field($_GET['currency']);

		if (empty($order_id)) {
            wc_add_notice( 'It seems that something is wrong with your order. Please try again', 'error' );
            $invalid++;
        }
        if (empty($secret_key) || !wp_http_validate_url($callback_url)) {
            wc_add_notice( 'The payment setting of this website is not correct, please contact Administrator', 'error' );
            $invalid++;
        }
        if (empty($tx_ref)) {
            wc_add_notice( 'It seems that something is wrong with your order. Please try again', 'error' );
            $invalid++;
        }
        if (empty($amount) || !is_numeric($amount)) {
            wc_add_notice( 'It seems that you have submitted an invalid price for this order. Please try again', 'error' );
            $invalid++;
        }
        if (empty($email) || !is_email($email)){
            wc_add_notice( 'Your email is empty or not valid. Please check and try again', 'error' );
            $invalid++;
        }
        if (empty($first_name)) {
            wc_add_notice( 'Your first name is empty or not valid. Please check and try again', 'error' );
            $invalid++;
        }
        if (empty($last_name)) {
            wc_add_notice( 'Your last name is empty or not valid. Please check and try again', 'error' );
            $invalid++;
        }
		if (empty($title)) {
            wc_add_notice( 'The order title is empty or not valid. Please check and try again', 'error' );
            $invalid++;
        }
        if (empty($currency)) {
            wc_add_notice( 'The currency code is not valid. Please check and try again.', 'error' );
            $invalid++;
        }
        
		if ($invalid === 0) {
            $apiUrl = 'https://api.paychangu.com/payment';
			$apiResponse = wp_remote_post($apiUrl,
				[
					'method' => 'POST',
					'headers' => [
						'content-type' => 'application/json',
						'Authorization' => 'Bearer ' . $secret_key,
					],
					'body' => json_encode(array(
						"amount" => $amount,
						"currency" => $currency,
						"email" => $email,
						"first_name" => $first_name,
						"last_name" => $last_name,
						"callback_url" => $callback_url,
						"return_url" => wc_get_page_permalink('checkout'),
						"tx_ref" => $tx_ref,
						"customization" => array(
							"title" => $title,
							"description" => $title
						),
						"meta" => array(
							"uuid" => "uuid",
      						"response" => "Response",
							"redirect_to_url" => wc_get_page_permalink('checkout'),
							"order_id" => $order_id
						)
					))
				]
			);
			if (!is_wp_error($apiResponse)) {
				$apiBody = json_decode(wp_remote_retrieve_body($apiResponse));
				$external_url = $apiBody->data->checkout_url;
				if ($apiBody->status == 'success' && $external_url) {
					wp_redirect($external_url);
					die();
				} else {
					wc_add_notice( 'Payment was declined by PayChangu. Please check and try again', 'error' );
					wp_redirect(wc_get_page_permalink('checkout'));
					die();
				}
			} else {
                wc_add_notice( 'Payment was declined by PayChangu. Please check and try again', 'error' );
				wp_redirect(wc_get_page_permalink('checkout'));
				die();
			}
        }else{
            wp_redirect(wc_get_page_permalink('checkout'));
        }
        die();
    }
	
    /**
     * Get the return url (thank you page).
     *
     * @param WC_Order|null $order Order object.
     * @return string
     */
    public function get_return_url( $order = null ) {
        if ( $order ) {
            $return_url = $order->get_checkout_order_received_url();
        } else {
            $return_url = wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() );
        }
        return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
    }
}