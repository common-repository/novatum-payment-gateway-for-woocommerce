<?php

/**
 * Class WC_Novatum_Gateway
 */
class WC_Novatum_Gateway extends WC_Payment_Gateway
{
    /**
     * Setup our Gateway's id, description and other values
     * WC_Novatum_Gateway constructor.
     */
    public function __construct()
    {
        $this->apisource = 'https://api.novatum.me/FE/rest/';

        // The global ID for this Payment method
        $this->id = 'novatum'; // payment gateway plugin ID

        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = '';

        // Bool. Can be set to true if you want payment fields to show on the checkout
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __('Novatum Gateway', 'novatum');

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __('Novatum payment gateway for woocommerce', 'novatum'); // will be displayed on the options page

        // Supports the default credit card form
        $this->supports = array(
            'default_credit_card_form',
            'products',
            'refunds'
        );

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();
        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        $this->init_settings();

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = $this->get_option('title');

        // The description to be used for the vertical tabs that can be ordered top to bottom
        $this->description = $this->get_option('description');

        // The option to enable or disable the plugin settings.
        $this->enabled = $this->get_option('enabled');

        // Are we testing right now or is it a real transaction
        $this->testmode = 'yes' === $this->get_option('testmode');

        // Save settings
        if (is_admin()) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        // Add hooks
        add_action('admin_notices', array(
            $this,
            'novatum_ssl_check'
        ));

        //Prepare a new redirection for pre-payment page.
        add_action('woocommerce_receipt_novatum', array(
            $this,
            'novatum_receipt_page'
        ));

        // We need custom JavaScript to obtain plugin functionalities
        add_action('wp_enqueue_scripts', array(
            $this,
            'payment_scripts'
        ));

        $this->logger = wc_get_logger();
    }

    /**
     * Check if SSL is enabled and notify the user.
     */
    public function init_form_fields()
    {
        $this->form_fields = include plugin_dir_path( WC_NOVATUM_MAIN_FILE ) . 'includes/settings-novatum.php';
    }

    /**
     * @param false $title
     * @param bool $indent
     * @return array
     */
    public function get_pages($title = false, $indent = true)
    {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }

    /**
     * Check if ssl is enabled.
     */
    function novatum_ssl_check()
    {
        if (get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') {
            $admin_url = admin_url('admin.php?page=wc-settings&tab=checkout');
            echo '<div class="error"><p>' . sprintf(__('Novatum payment gateway is enabled and the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'novatum'), $admin_url) . '</p></div>';
        }
    }

    /**
     * @param $order
     */
    function novatum_receipt_page($order)
    {
        echo '<p>' . __('Thank you for your order, please wait as you will be automatically redirected to Novatum', 'novatum') . '.</p>';
        echo $this->generate_novatum_form($order);
    }

    /**
     * @param $order_id
     * @param array $paymentArr
     * @return string|void
     * @throws Exception
     */
    public function generate_novatum_form($order_id, $paymentArr = [])
    {
        // we need it to get any order details
        $order = wc_get_order($order_id);
        $gateway_URL = '';
        $return_URL = $cancel_URL = $notification_URL = $merchant_Id = $merchant_Account_Id = $merchant_Account_Key = $merchant_User_Name = $merchant_Password = '';
        if ($this->description) {
            if ($this->settings) {
	            $gateway_URL = $this->apisource . 'tx/purchase/w/execute';
	            $ext_settings = $this->settings;
	            if ($this->testmode == 'yes') {
                    $merchant_Id = $ext_settings['sandbox_merchant_id'];
                    $merchant_Account_Id = $ext_settings['sandbox_merchant_account_id'];
                    $merchant_Account_Key = $ext_settings['sandbox_merchant_account_key'];
                    $merchant_User_Name = $ext_settings['sandbox_merchant_account_user_name'];
                    $merchant_Password = $ext_settings['sandbox_merchant_account_password'];
                } else {
                    $merchant_Id = $ext_settings['merchant_id'];
                    $merchant_Account_Id = $ext_settings['merchant_account_id'];
                    $merchant_Account_Key = $ext_settings['merchant_account_key'];
                    $merchant_User_Name = $ext_settings['merchant_account_user_name'];
                    $merchant_Password = $ext_settings['merchant_account_password'];
                }
                $return_URL = get_permalink($ext_settings['return_page_id']);
                $cancel_URL = get_permalink($ext_settings['cancel_page_id']);
            }
        }

        if ($merchant_Id === '') {
            wc_add_notice(__('Novatum Payment Gateway Configuration not valid', 'novatum') . '.', 'error');
            return;
        }

        $user_info = get_userdata(1);
        $orderItemCol = [];
        $orderId = base64_encode($this->get_client_ip()) . 'WPORDER_' . $order_id;
        foreach ($order->get_items() as $items) {
            $unitGrossAmt = ($items->get_subtotal() / $items->get_quantity());
            $unitTaxAmt = ($items->get_total_tax() / $items->get_quantity());

            $product   = wc_get_product( $items->get_product_id() );
            $image_id  = $product->get_image_id();
            $image_url = wp_get_attachment_image_url( $image_id, 'full' );

            $term_names = wp_get_post_terms( $items->get_product_id(), 'product_cat', ['fields' => 'names'] );

            // Output as a coma separated string
            $category = implode(', ', $term_names);
            $orderItemCol[] = [
                'brand' => '',
                'category' => $category,
                'imgUrl' => $image_url,
                'itemName' => $items->get_name(),
                'qty' => $items->get_quantity(),
                'unitDiscountAmt' => abs($this->decorate_number_format(($items->get_total() - $items->get_subtotal())/ $items->get_quantity())),
                'unitGrossAmt' => $unitGrossAmt,
                'unitNetAmtTaxInc' => $this->decorate_number_format($items->get_total() + $items->get_total_tax()),
            ];
        }

        $billingAddress = array(
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'address1' => $order->get_billing_address_1(),
            'address2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'zipcode' => $order->get_billing_postcode(),
            'stateCode' => $order->get_billing_state(),
            'countryCode' => $order->get_billing_country(),
            'mobile' => $order->get_billing_phone(),
            'phone' => $order->get_billing_phone(),
            'email' => $order->get_billing_email(),
            'fax' => '',
        );
        $shippingAddress = array(
            'firstName' => $order->get_shipping_first_name() != '' ? $order->get_shipping_first_name() : $order->get_billing_first_name(),
            'lastName' => $order->get_shipping_last_name() != '' ? $order->get_shipping_last_name() : $order->get_billing_last_name(),
            'address1' => $order->get_shipping_address_1() != '' ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
            'address2' => $order->get_shipping_address_2() != '' ? $order->get_shipping_address_2() : $order->get_billing_address_2(),
            'city' => $order->get_shipping_city() != '' ? $order->get_shipping_city() : $order->get_billing_city(),
            'zipcode' => $order->get_shipping_postcode() != '' ? $order->get_shipping_postcode() : $order->get_billing_postcode(),
            'stateCode' => $order->get_shipping_state() != '' ? $order->get_shipping_state() : $order->get_billing_state(),
            'countryCode' => $order->get_shipping_country() != '' ? $order->get_shipping_country() : $order->get_billing_country(),
            'mobile' => $order->get_billing_phone(),
            'phone' => $order->get_billing_phone(),
            'email' => $order->get_billing_email(),
            'fax' => '',
        );
        $personalAddress = array(
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'address1' => $order->get_billing_address_1(),
            'address2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'zipcode' => $order->get_billing_postcode(),
            'stateCode' => $order->get_billing_state(),
            'countryCode' => $order->get_billing_country(),
            'mobile' => $order->get_billing_phone(),
            'phone' => $order->get_billing_phone(),
            'email' => $order->get_billing_email(),
            'fax' => '',
        );
        $orderDetails = array(
            'invoiceNo' => 'INV_' . $order->get_shipping_country() . $orderId,
            'invoiceDate' => date('Y-m-d'),
            'mctAddressCity' => WC()->countries->get_base_city(),
            'mctAddressCountry' => WC()->countries->get_base_country(),
            'mctAddressLine1' => WC()->countries->get_base_address(),
            'mctAddressLine2' => WC()->countries->get_base_address_2(),
            'mctAddressState' => WC()->countries->get_base_state(),
            'mctAddressZipcode' => WC()->countries->get_base_postcode(),
            'mctBusinessName' => get_bloginfo('name'),
            'mctEmail' => $user_info->user_email,
            'mctFirstName' => $user_info->display_name,
            'mctLastName' => $user_info->display_name,
            'mctMemo' => 'memo-abc',
            'mctPhone' => '1234567890',
            'note' => __('Online payment', 'novatum'),
            'status' => 'SENT',
            'totalDiscountAmt' => $order->get_discount_total(),
            'totalGrossAmt' => $order->get_total(),
            'totalNetAmtTaxInc' => $this->decorate_number_format($order->get_total() - $order->get_shipping_total()),
            'totalShippingAmt' => $order->get_shipping_total(),
            'amtCurrencyCode' => $order->get_currency(),
            'itemList' => $orderItemCol
        );

        /*
          * Array with parameters for API interaction
         */
        $order_description = 'Online Order';
	    $orderDataArr = [
		    'orderId' => $orderId,
		    'orderDescription' => $order_description,
		    'amount' => $order->get_total(),
		    'currencyCode' => $order->get_currency(),
		    'billingAddress' => $billingAddress,
		    'shippingAddress' => $shippingAddress,
		    'personalAddress' => $personalAddress,
		    'orderDetail' => $orderDetails,
	    ];
	    $transactionDetails = array(
            'apiVersion' => '1.0.1',
            'requestId' => $orderId,
            'recurrentType' => '1', //Non Recurrent Order
            'perform3DS' => '0',
            'orderData' => $orderDataArr,
            'statement' => '',
            'returnUrl' => $return_URL,
            'cancelUrl' => $cancel_URL,
            'notificationUrl' => $notification_URL,
        );
        $secured_Key = $merchant_Account_Key;
        $requestTime = date('Y-m-d H:i:s', current_time('timestamp', get_option('gmt_offset')));

        $inputString = $requestTime . $this->hashDataInBase64($merchant_Id) . $this->hashDataInBase64($merchant_Account_Id) . $this->encryptDataInBase64($merchant_User_Name, $secured_Key) . $this->encryptDataInBase64($merchant_Password, $secured_Key);

        $inputString .= $transactionDetails['apiVersion'] . $transactionDetails['requestId'] . $transactionDetails['recurrentType'] . $transactionDetails['perform3DS'] . $orderId . $order_description . $order->get_total() . $order->get_currency();

        $inputString .= $this->novaTrim($billingAddress['firstName']) . $this->novaTrim($billingAddress['lastName']) . $this->novaTrim($billingAddress['address1']) . $this->novaTrim($billingAddress['address2']) . $this->novaTrim($billingAddress['city']) . $this->novaTrim($billingAddress['zipcode']) . $this->novaTrim($billingAddress['stateCode']) . $this->novaTrim($billingAddress['countryCode']) . $this->novaTrim($billingAddress['mobile']) . $this->novaTrim($billingAddress['phone']) . $this->novaTrim($billingAddress['email']) . $this->novaTrim($billingAddress['fax']);

        $inputString .= $this->novaTrim($shippingAddress['firstName']) . $this->novaTrim($shippingAddress['lastName']) . $this->novaTrim($shippingAddress['address1']) . $this->novaTrim($shippingAddress['address2']) . $this->novaTrim($shippingAddress['city']) . $this->novaTrim($shippingAddress['zipcode']) . $this->novaTrim($shippingAddress['stateCode']) . $this->novaTrim($shippingAddress['countryCode']) . $this->novaTrim($shippingAddress['mobile']) . $this->novaTrim($shippingAddress['phone']) . $this->novaTrim($shippingAddress['email']) . $this->novaTrim($shippingAddress['fax']);

        $inputString .= $this->novaTrim($personalAddress['firstName']) . $this->novaTrim($personalAddress['lastName']) . $this->novaTrim($personalAddress['address1']) . $this->novaTrim($personalAddress['address2']) . $this->novaTrim($personalAddress['city']) . $this->novaTrim($personalAddress['zipcode']) . $this->novaTrim($personalAddress['stateCode']) . $this->novaTrim($personalAddress['countryCode']) . $this->novaTrim($personalAddress['mobile']) . $this->novaTrim($personalAddress['phone']) . $this->novaTrim($personalAddress['email']) . $this->novaTrim($personalAddress['fax']);

        $inputString .= $this->novaTrim($transactionDetails['statement']) . $this->novaTrim($transactionDetails['cancelUrl']) . $this->novaTrim($transactionDetails['returnUrl']) . $this->novaTrim($transactionDetails['notificationUrl']);

        $merchantSignature = $this->createSignature($inputString, $secured_Key);
	    $args = array(
		    'requestTime' => date('Y-m-d H:i:s'),
		    "merchantIdHash" => $this->hashDataInBase64($merchant_Id),
		    "merchantAccountIdHash" => $this->hashDataInBase64($merchant_Account_Id),
		    "encryptedAccountUsername" => $this->encryptDataInBase64($merchant_User_Name, $secured_Key),
		    "encryptedAccountPassword" => $this->encryptDataInBase64($merchant_Password, $secured_Key),
		    'signature' => $merchantSignature,
		    'lang' => 'en',
		    'metaData' =>
			    array(
				    'merchantUserId' => $this->hashDataInBase64($merchant_Account_Id),
			    ),
		    'txDetails' =>
			    $transactionDetails,
	    );
        $order->add_meta_data('novatum_signature', $merchantSignature);
        $order->save();

	    $args = base64_encode(json_encode($args));
	    $html = "<html lang=\"en-us\"><body><form action=\"" . $gateway_URL . "\" method=\"post\" id=\"novatum_form\" name=\"novatum_form\">
                <input type=\"hidden\" name=\"request\" value=\"" . $args . "\" />
                <button style='display:none' id='submit_novatum_payment_form' name='submit_novatum_payment_form'>Pay Now</button>
                </form>
                <script type=\"text/javascript\">
                 document.getElementById(\"novatum_form\").submit();
                </script>
                </body></html>";
	    return $html;
    }

    /**
     * Function to get the client IP address
     * @return mixed|string
     */
    public function get_client_ip()
    {
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if (isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

    /**
     * Encrypt Information in Base64 encoding with security procedures.
     * @param $input
     * @return string
     */
    public function hashDataInBase64($input)
    {
        $output = hash('sha256', $input, true);
        $hashDataInBase64 = base64_encode($output);
        return $hashDataInBase64;
    }

    /**
     * @param $input
     * @param $key
     * @return string
     */
    public function encryptDataInBase64($input, $key)
    {
        $alg = 'AES-128-ECB'; // AES-128 and ECB mode
//        $ivsize = openssl_cipher_iv_length($alg);
//        $iv = openssl_random_pseudo_bytes($ivsize);
        //perform encryption.
        $crypttext = openssl_encrypt($input, $alg, $key, OPENSSL_RAW_DATA, '');
        $encryptDataInBase64 = base64_encode($crypttext);
        return $encryptDataInBase64;
    }

    /**
     * Trim the string
     * @param $string
     * @return string
     */
    public function novaTrim($string)
    {
        if (is_null($string)) {
            return '';
        } else {
            return rtrim(trim($string));
        }
    }

    /**
     * Prepare Signature with Hash terminology.
     * @param $input
     * @param $key
     * @return string
     */
    function createSignature($input, $key)
    {
        $alg = 'AES-128-ECB'; // AES-128 and ECB mode
//        $ivsize = openssl_cipher_iv_length($alg);
//        $iv = openssl_random_pseudo_bytes($ivsize);
//perform encryption.
        $crypttext = openssl_encrypt($input, $alg, $key, OPENSSL_RAW_DATA, '');
//perform digest or hash on output of encryption.
        $output = hash('sha256', $crypttext, true);
//perform base64 on output of digest/hash.
        $signature = base64_encode($output);
        return $signature;
    }


    /**
     * Validate the credit card form fields.
     * @return bool
     */
    public function validate_fields()
    {
        if ($this->settings) {
            $ext_settings = $this->settings;
        }
        return true;
    }

    /**
     * Display credit card form and other plugin dependent checkout form fields.
     */
    public function payment_fields()
    {
        //display description before the payment form
        if ($this->description) {
            if ($this->testmode) {
                $cardNumberDetailsURL = '';
                $this->description .= '. ' . sprintf(__('SANDBOX MODE ENABLED. In test mode, you can use the card numbers listed in <a href="%s" target="_blank" rel="noopener noreferrer">documentation</a>', 'novatum'), $cardNumberDetailsURL);
                $this->description .= '. ';
                $this->description = trim($this->description);
            }
            // display the description with <p> tags etc.
            echo wpautop(wp_kses_post($this->description));
        }
    }

    /**
     * Execute the payment at merchant side.
     * @param array $args
     * @param array $ext_settings
     * @param $order
     * @return string
     * @throws Exception
     */
    protected function executePaymentMerchantSide(array $args, array $ext_settings, $order): string
    {
        //Log HTTP Response
        WC_Novatum_Logger::log('Info: Beginning Payment at Merchant side');

        global $woocommerce;
        $args = json_encode($args);
        $args_website = array(
            'method' => 'POST',
            'timeout' => 120,
            'httpversion' => '1.1',
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
            'body' => $args
        );
        $response_post = wp_remote_post($this->apisource . 'tx/sync/purchase', $args_website);
        $paymentResponse = wp_remote_retrieve_response_code($response_post);

        if (is_wp_error($paymentResponse))
            throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience', 'novatum') . '.');
        if (empty($paymentResponse))
            throw new Exception(__('Novatum\'s Response was empty', 'novatum') . '.');

        $responseBody = json_decode($response_post['body']);
        $resultCode = $responseBody->result->resultCode;

        //Log HTTP Response
        $logMessage = ['apiType' => 'Payment at merchant', 'httpResponseCode' => $paymentResponse, 'response' => $responseBody];
        WC_Novatum_Logger::log($logMessage);

        //If HTTP Response is 200 = Success.
        $url = '';
        if ($paymentResponse == 200) {
            $resultMessage = $responseBody->result->resultMessage;
            $ccNumber = '';

            //Get Redirection Page Id based on HTTP Response
            if ($resultCode == 0) {
                $pageId = $ext_settings['cancel_page_id'];
                $ccNumber = $responseBody->ccNumber;
            } else {
                $pageId = $ext_settings['return_page_id'];
            }
            $signature = $responseBody->signature;
            $txId = $responseBody->txId;
            $txTypeId = $responseBody->txTypeId;
            $recurrentTypeId = $responseBody->recurrentTypeId;
            $requestId = $responseBody->requestId;
            $orderId = $responseBody->orderId;
            $sourceAmount = $responseBody->source->amount;
            $sourceCurrency = $responseBody->source->currencyCode;
            $amount = $responseBody->amount->amount;
            $cyCode = $responseBody->amount->currencyCode;
            $responseTime = $responseBody->responseTime;
            $reasonCode = $responseBody->result->reasonCode;
            $orderResultBody = $responseBody->result->error;
            $errorMessage0 = '';
            $errorAdvice0 = '';
            $errorCode0 = '';
            foreach ($orderResultBody as $paymentOutputMessages) {
                $errorMessage0 = $paymentOutputMessages->errorMessage;
                $errorCode0 = $paymentOutputMessages->errorCode;
            }

            $novatum_response_str = [
                'signature' => $signature,
                'txId' => $txId,
                'txTypeId' => $txTypeId,
                'recurrentTypeId' => $recurrentTypeId,
                'requestId' => $requestId,
                'orderId' => $orderId,
                'sourceAmount' => $sourceAmount,
                'sourceCurrency' => $sourceCurrency,
                'amount' => $amount,
                'cyCode' => $cyCode,
                'responseTime' => $responseTime,
                'reasonCode' => $reasonCode,
                'orderResultBody' => $orderResultBody,
                'errorMessage0' => $errorMessage0,
                'errorCode0' => $errorCode0
            ];
            $order->add_meta_data('novatum_response_str', json_encode($novatum_response_str));
            // Payment has been successful
            $order->add_order_note(__('Novatum payment completed. Transaction ID ', 'novatum') . ':'. $txId);

            $order->payment_complete();

            $order->save();
            $url = '?page_id=' . $pageId . '&responseTime=' . $responseTime . '&txId=' . $txId . '&txTypeId=' . $txTypeId . '&recurrentTypeId=' . $recurrentTypeId . '&requestId=' . $requestId . '&orderId=' . $orderId . '&sourceAmount=' . $sourceAmount . '&sourceCurrencyCode=' . $sourceCurrency . '&amount=' . $amount . '&cyCode=' . $cyCode . '&resultCode=' . $resultCode . '&message=' . $resultMessage . '&resultReasonCode=' . $reasonCode . '&ccNumber=' . $ccNumber . '&cardId=&signature=' . $signature . '&errorCode0=' . $errorCode0 . '&errorMessage0=' . $errorMessage0 . '&errorAdvice0=' . $errorAdvice0;

            // Empty the cart (Very important step)
            $woocommerce->cart->empty_cart();
        }

        //Log HTTP Response
        $logMessage = ['apiType' => 'after payment at merchant', 'return url' => $url];
        WC_Novatum_Logger::log($logMessage);

        return $url;
    }

    /**
     * Process payment at server.
     * @param int $order_id
     * @return array
     * @throws Exception
     */
    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order_id,
                    add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true)))
            );
        } else {
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order_id,
                    add_query_arg('key', $order->get_order_key(), get_permalink(get_option('woocommerce_pay_page_id'))))
            );
        }
    }

    /**
     * @return false
     */
    public function payment_scripts()
    {
        // we need JavaScript to process a token only on cart/checkout pages, right?
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            wc_add_notice(__('Payment through Card can not be done if SSL is not enabled', 'novatum') . '.', 'error');
            return false;
        }

        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ('no' === $this->enabled) {
            wc_add_notice(__('Plugin is either not enabled', 'novatum') . '.', 'error');
            return false;
        }

        // do not work with card details without SSL unless your website is in a test mode
        if (!$this->testmode && !is_ssl()) {
            wc_add_notice(__('Payment through Card can not be done if SSL is not enabled', 'novatum') . '.', 'error');
            return false;
        }
        return true;
    }

    /**
     * Process a refund if supported
     *
     * @param int $order_id
     * @param float $amount
     * @param string $reason
     * @return  bool|wp_error True or false based on success, or a WP_Error object
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);

        // Decide which URL to post to
        $environment_url = $this->apisource . 'tx/sync/refund';

        if ($amount == 0) {
            $order->add_order_note(__('Novatum: The amount is already refunded', 'novatum') . '.');
            return true;
        }

        $ext_settings = $this->settings;
        if ($this->testmode == 'yes') {
            $merchant_Id = $ext_settings['sandbox_merchant_id'];
            $merchant_Account_Id = $ext_settings['sandbox_merchant_account_id'];
            $merchant_Account_Key = $ext_settings['sandbox_merchant_account_key'];
            $merchant_User_Name = $ext_settings['sandbox_merchant_account_user_name'];
            $merchant_Password = $ext_settings['sandbox_merchant_account_password'];
        } else {
            $merchant_Id = $ext_settings['merchant_id'];
            $merchant_Account_Id = $ext_settings['merchant_account_id'];
            $merchant_Account_Key = $ext_settings['merchant_account_key'];
            $merchant_User_Name = $ext_settings['merchant_account_user_name'];
            $merchant_Password = $ext_settings['merchant_account_password'];
        }

        $requestTime = date('Y-m-d H:i:s', current_time('timestamp', get_option('gmt_offset')));
        $previousTxId = $order->get_transaction_id();
        $requestId = rand(1, 10). "_WPREFUNDORDER_". $order_id;
        $statement = 'Novatum refund #'. $order_id;
        $secured_Key = $merchant_Account_Key;

        $inputString = $requestTime . $this->hashDataInBase64($merchant_Id) . $this->hashDataInBase64($merchant_Account_Id) . $this->encryptDataInBase64($merchant_User_Name, $secured_Key) . $this->encryptDataInBase64($merchant_Password, $secured_Key);

        $inputString .= '1.0.1' . $requestId . $previousTxId . $amount . $statement;

        $merchantSignature = $this->createSignature($inputString, $secured_Key);

        //Log HTTP Response
        WC_Novatum_Logger::log("Info: Beginning refund for order {$order_id} for the amount of {$amount}");

        // This is where the fun stuff begins
        $args = array(
            "requestTime" => $requestTime,
            "mId" => $this->hashDataInBase64($merchant_Id),
            "maId" => $this->hashDataInBase64($merchant_Account_Id),
            "userName" => $this->encryptDataInBase64($merchant_User_Name, $secured_Key),
            "password" => $this->encryptDataInBase64($merchant_Password, $secured_Key),
            "signature" => $merchantSignature,
            "apiVersion" => "1.0.1",
            "requestId" => $requestId,
            "previousTxId" => $previousTxId,
            'amount' => $amount,
            "statement" => $statement
        );

        // Send this payload to Novatum for processing
        $args = json_encode($args);
        $args_website = array(
            'method' => 'POST',
            'timeout' => 120,
            'httpversion' => '1.1',
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
            'body' => $args
        );
        $response_post = wp_remote_post($environment_url, $args_website);
        $apiResponse = wp_remote_retrieve_response_code($response_post);

        $responseBody = json_decode($response_post['body']);
        $resultCode = $responseBody->result->resultCode;

        //Log HTTP Response
        $logMessage = ['apiType' => 'Refund', 'httpResponseCode' => $apiResponse, 'response' => $responseBody];
        WC_Novatum_Logger::log($logMessage);

        if (is_wp_error($apiResponse))
            throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience', 'novatum')  . '.');
        if (empty($apiResponse))
            throw new Exception(__('Novatum\'s Response was empty', 'novatum') . '.');


        //If HTTP Response is 200 = Success.
        if($apiResponse == 200) {
            if ($resultCode == 1) {
                // Success
                $orderNote = __('Novatum refund completed. Transaction has been approved', 'novatum') . '.' . '<br>'. __('Reason', 'novatum') . ':' . $reason . '<br>' . __('Refund Transaction ID', 'novatum') . ':' . $responseBody->txId . '<br>' . $responseBody->result->resultMessage;
                $order->set_status('refunded');
                $order->add_order_note($orderNote);
                return true;
            } else {
                $refundError = current($responseBody->result->error);
                // Failure
                $orderNote = __('Novatum refund error. Response data', 'novatum') . ': ' . '<br>' . __('Reason', 'novatum') . ': ' . $reason . '<br>' . __('Refund Transaction ID', 'novatum') . ': ' . $responseBody->txId . '<br>' . $refundError->errorMessage;
                $order->add_order_note($orderNote);
                return false;
            }
        } else {
            $order->add_order_note(__('Novatum refund error. Refund can not be processed', 'woocommerce-gateway-novatum') . '.');
            return false;
        }
    }

    /**
     * @param $numbers
     * @return string
     */
    public function decorate_number_format($numbers) {
        return number_format((float)$numbers, 2, '.', '');
    }
}
