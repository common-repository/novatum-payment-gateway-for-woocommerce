<?php
/**
* Settings for Novatum payment Gateway.
*
* @package WooCommerce\Classes\Payment
*/

defined( 'ABSPATH' ) || exit;

return array(
    'enabled' => array(
        'title' => __('Enable/Disable', 'novatum'),
        'label' => __('Enable Novatum Gateway', 'novatum'),
        'type' => 'checkbox',
        'description' => '',
        'default' => 'no'
    ),
    'title' => array(
        'title' => __('Title', 'novatum'),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout', 'novatum'),
        'default' => __('Pay via Credit Card', 'novatum'),
        'desc_tip' => true,
    ),
    'description' => array(
        'title' => __('Description', 'novatum'),
        'type' => 'textarea',
        'description' => __('This controls the description which the user sees during checkout', 'novatum'),
        'default' => __('Pay with your credit card via our novatum payment gateway', 'novatum'),
    ),
    'testmode' => array(
        'title' => __('Novatum Sandbox', 'novatum'),
        'label' => __('Enable Sandbox Mode', 'novatum'),
        'id' => 'novatum_testmode',
        'type' => 'checkbox',
        'description' => __('Place the payment gateway in sandbox mode using test API keys', 'novatum'),
        'default' => 'yes',
        'desc_tip' => true,
    ),
    'api_details'           => array(
        'title'       => __( 'API credentials', 'woocommerce' ),
        'type'        => 'title',
        /* translators: %s: URL */
        'description' => sprintf( __( 'Enter your Novatum API credentials to process. Learn how to access your <a href="%s">Novatum API Credentials</a>.', 'novatum' ), 'NOVATUM MERCHANT URL' ),
    ),
    'sandbox_merchant_id' => array(
        'title' => __('Sandbox Merchant Id', 'novatum'),
        'type' => 'text',
        'placeholder' => __( 'Required', 'novatum' ),
        'aria-required' => 1,
    ),
    'sandbox_merchant_account_id' => array(
        'title' => __('Sandbox Merchant Account Id', 'novatum'),
        'type' => 'text',
        'placeholder' => __( 'Required', 'novatum' ),
    ),
    'sandbox_merchant_account_key' => array(
        'title' => __('Sandbox Merchant Account Key', 'novatum'),
        'type' => 'text',
        'placeholder' => __( 'Required', 'novatum' ),
    ),
    'sandbox_merchant_account_user_name' => array(
        'title' => __('Sandbox Merchant Account User Name', 'novatum'),
        'type' => 'text',
        'placeholder' => __( 'Required', 'novatum' ),
    ),
    'sandbox_merchant_account_password' => array(
        'title' => __('Sandbox Merchant Account Password', 'novatum'),
        'type' => 'text',
        'placeholder' => __( 'Required', 'novatum' ),
    ),

    'merchant_id' => array(
        'title' => __('Merchant Id', 'novatum'),
        'type' => 'text',
        'placeholder' => __( 'Required', 'novatum' ),
        'aria-required' => 1,
    ),
    'merchant_account_id' => array(
        'title' => __('Merchant Account Id', 'novatum'),
        'type' => 'text',
        'placeholder' => __( 'Required', 'novatum' ),
    ),
    'merchant_account_key' => array(
        'title' => __('Merchant Account Key', 'novatum'),
        'type' => 'text',
        'placeholder' => __( 'Required', 'novatum' ),
        'aria-required' => 1,
    ),
    'merchant_account_user_name' => array(
        'title' => __('Merchant Account User Name', 'novatum'),
        'type' => 'text',
        'placeholder' => __( 'Required', 'novatum' ),
        'aria-required' => 1,
    ),
    'merchant_account_password' => array(
        'title' => __('Merchant Account Password', 'novatum'),
        'type' => 'text',
        'placeholder' => __( 'Required', 'novatum' ),
    ),
    'page_details'           => array(
        'title'       => __( 'Redirection Rule', 'novatum' ),
        'type'        => 'title',
        /* translators: %s: URL */
        'description' => sprintf( __( 'Set the redirection page Id once an order is successfully placed or failed', 'novatum')),
    ),
    'return_page_id' => array(
        'title' => __('Return or Success Page', 'novatum'),
        'type' => 'select',
        'options' => $this->get_pages('Select Page'),
        'description' => __("URL of return/success page", "novatum"),
        'desc_tip'    => true,
    ),
    'cancel_page_id' => array(
        'title' => __('Cancel Page', 'novatum'),
        'type' => 'select',
        'options' => $this->get_pages('Select Page'),
        'description' => __("URL of cancel page", "novatum"),
		'desc_tip'    => true,
    ),
    /*'notification_page_id' => array(
        'title' => __('Notification Page', 'novatum'),
        'type' => 'select',
        'options' => $this->get_pages('Select Page'),
        'description' => "URL of notification page"
    )*/
);
