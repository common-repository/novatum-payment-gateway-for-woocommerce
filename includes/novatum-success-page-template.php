<?php
/*
 * Template Name: Novatum Success Page
 * Description:
 */
defined('ABSPATH') || exit;

get_header();

/**
 * Hook: woocommerce_before_main_content.
 *
 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
 * @hooked woocommerce_breadcrumb - 20
 * @hooked WC_Structured_Data::generate_website_data() - 30
 */
//do_action('woocommerce_before_main_content');

?>
    <article id="post-<?php echo the_ID(); ?>" class="post-<?php echo the_ID(); ?> page type-page status-publish hentry entry">
        <header class="entry-header alignwide">
            <?php if (apply_filters('woocommerce_show_page_title', true)) : ?>
                <h1 class="woocommerce-products-header__title page-title"><?php the_title(); ?></h1>
            <?php endif; ?>

            <?php
            /**
             * Hook: woocommerce_archive_description.
             *
             * @hooked woocommerce_taxonomy_archive_description - 10
             * @hooked woocommerce_product_archive_description - 10
             */
            do_action('woocommerce_archive_description');
            ?>
        </header>
        <div class="entry-content">
            <?php
            global $woocommerce;

            $orderId = sanitize_text_field( $_GET['orderId'] );
            if(strpos($orderId, '_')) {
                $orderId = substr($orderId, strpos($orderId, '_') + 1);
            }
            $order = wc_get_order($orderId);
            if (!empty($orderId)) { ?>
                <div class="woocommerce-order WooCommerceThankYouPage">
                    <?php
                    if ($order) :
                        do_action('woocommerce_before_thankyou', $order->get_id());
                        $order_payment_status = sanitize_text_field( $_GET['resultCode'] );

                        if ($order_payment_status == 0) :

                            $paymentAdvice = esc_html( 'ADVICE : ' . esc_html( $_GET['errorAdvice0'] ));

                            //Log HTTP Response
                            $logMessage = [
                                'Order Id' => $orderId,
                                'Order Number' => $order->get_order_number(),
                                'Order Payment status' => $order_payment_status,
                                'Payment advice' => $paymentAdvice
                            ];
                            WC_Novatum_Logger::log($logMessage);
                            ?>
                            <p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce'); ?></p>

                            <b class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e($paymentAdvice, 'novatum'); ?></b>
                            <p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
                                <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>"
                                   class="button pay"><?php esc_html_e('Pay', 'woocommerce'); ?></a>
                                <?php if (is_user_logged_in()) : ?>
                                    <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"
                                       class="button pay"><?php esc_html_e('My account', 'woocommerce'); ?></a>
                                <?php endif; ?>
                            </p>
                        <?php elseif ($order_payment_status == 1 && isset($_GET['txId'])) :

                            //Log HTTP Response
                            $logMessage = [
                                'Order Id' => $orderId,
                                'Order Number' => $order->get_order_number(),
                                'Order Date' => $order->get_date_created(),
                                'Order Payment Status' => $order_payment_status,
                                'Transaction Id' => sanitize_text_field( $_GET['txId'] ),
                                'Transaction_type' => sanitize_text_field( $_GET['txTypeId'] ),
                                'Recurrent Type' => sanitize_text_field( $_GET['recurrentTypeId'] ),
                                'ccnum' => sanitize_text_field( $_GET['ccNumber'] ),
                                'novatum Charge' => sanitize_text_field( $_GET['amount'] ),
                                'Charge Currency' => sanitize_text_field( $_GET['currencyCode'] ),
                            ];
                            WC_Novatum_Logger::log($logMessage);

                            if (empty($order->get_transaction_id())) {
                                $order->payment_complete();
                                //$_GET['txTypeId'] = 1 (Authorization), 2(Capture), 3(Purchase), 4(), 5(Refund)
                                $order->set_transaction_id(sanitize_text_field( $_GET['txId'] ));
                                if(isset( $_GET['txTypeId'] )):
                                    $order->add_meta_data('novatum_transaction_type', sanitize_text_field( $_GET['txTypeId'] ));
                                endif;
                                if(isset($_GET['recurrentTypeId'])):
                                    $order->add_meta_data('novatum_transaction_recurrent_type', sanitize_text_field( $_GET['recurrentTypeId'] ));
                                endif;
                                if(isset($_GET['ccNumber'])):
                                    $order->add_meta_data('novatum_ccnum', sanitize_text_field( $_GET['ccNumber'] ));
                                endif;
                                if(isset($_GET['amount'])):
                                    $order->add_meta_data('novatum_charge', sanitize_text_field( $_GET['amount'] ));
                                endif;
                                if(isset($_GET['currency'])):
                                    $order->add_meta_data('novatum_charge_currency', sanitize_text_field( $_GET['currency'] ));
                                endif;
                                if(isset($_GET['txId'])):
                                    $order->add_order_note( __( sanitize_text_field('Novatum has processed the payment. Ref Number: ' .  $_GET['txId'] ), 'novatum'));
                                endif;
                                if(isset($_GET['message'])):
                                    $order->add_order_note(sanitize_text_field( $_GET['message'] ));
                                endif;
                                $order->add_order_note( __( sanitize_text_field( 'Paid by Novatum' ), 'novatum'));
                                $order->save();
                                $woocommerce->cart->empty_cart();
                            }

                            if (isset($_GET['message'])) : ?>
                                <p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received"><?php echo apply_filters('woocommerce_thankyou_order_received_text', esc_html_e( $_GET['message'], 'novatum'), $order); ?></p>
                            <?php endif; ?>

                            <ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
                                <li class="woocommerce-order-overview__order order">
                                    <?php esc_html_e('Order number:', 'woocommerce'); ?>
                                    <strong><?php echo esc_html( $order->get_order_number() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        ?></strong>
                                </li>

                                <li class="woocommerce-order-overview__date date">
                                    <?php esc_html_e('Date:', 'woocommerce'); ?>
                                    <strong><?php echo wc_format_datetime($order->get_date_created()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        ?></strong>
                                </li>

                                <?php if (is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email()) : ?>
                                    <li class="woocommerce-order-overview__email email">
                                        <?php esc_html_e('Email:', 'woocommerce'); ?>
                                        <strong><?php echo esc_html($order->get_billing_email() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
                                    </li>
                                <?php endif; ?>

                                <li class="woocommerce-order-overview__total total">
                                    <?php esc_html_e('Total:', 'woocommerce'); ?>
                                    <strong><?php echo $order->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        ?></strong>
                                </li>

                                <?php if ($order->get_payment_method_title()) : ?>
                                    <li class="woocommerce-order-overview__payment-method method">
                                        <?php esc_html_e('Payment method:', 'woocommerce'); ?>
                                        <strong><?php echo wp_kses_post($order->get_payment_method_title()); ?></strong>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>

                        <div class="woocommerce_thankyou_middle">
                            <?php do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id()); ?>
                        </div>
                        <div class="woocommerce_thankyou_last">
                            <?php do_action('woocommerce_thankyou', $order->get_id()); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php } else { ?>
                <div class="woocommerce-order WooCommerceThankYouPage">
                    <p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received"><?php echo esc_html__('Thank you for
                        shopping with us. However, the transaction has been declined', 'novatum') . '.'; ?>
                    </p>
                </div>
                <?php
            } ?>
        </div>
    </article>

<?php
/**
 * Hook: woocommerce_after_main_content.
 *
 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
 */
do_action('woocommerce_after_main_content');

/**
 * Hook: woocommerce_sidebar.
 *
 * @hooked woocommerce_get_sidebar - 10
 */
do_action('woocommerce_sidebar');

get_footer('shop');
