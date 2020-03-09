<?php
/**
 * Plugin Name: Pay via ProxyAPI
 * Plugin URI: http://woocommerce.com/products/woo-pay-via-proxyapi/
 * Description: Accept Safaricom Lipa na M-Pesa payments using Pay via Proxy API
 * Version: 2.2.0
 * Author: maxp555
 * Author URI: https://proxyapi.co.ke/
 * Text Domain: pay-via-proxyapi
 *
 * WC requires at least: 3.9.2
 * WC tested up to: 3.9.2
 * Requires at least: 5.3
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) or die( 'Not allowed');
define("WC_PROXYAPI_PVP_LOG_LEVEL_NOTICE", 0);
define("WC_PROXYAPI_PVP_LOG_LEVEL_WARN", 1);
define("WC_PROXYAPI_PVP_LOG_LEVEL_ERROR", 2);

if (!session_id("62c4c601-d9fe-459e-95c6-9518d7e8e786"))
{
    session_start();
}

require "proxyapi-pvp-uninstall.php";

function init_ProxyAPI_PVP()
{
    class WC_Payment_Gateway_ProxyAPI_PVP extends WC_Payment_Gateway
    {
        private static $instance = false;
        private $webHook = "WC_Payment_Gateway_ProxyAPI_PVP";

        public function __construct()
        {
            $this->id = 'proxyapi_pvp_settings'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = false; // in case you need a custom credit card form
            $this->method_title = 'Pay via Proxy API';
            $this->method_description = "Allow customers to pay using Safaricom's Lipa na M-Pesa via Proxy API";
            $this->max_amount = 70000;
            $this->endpoint = "https://api.proxyapi.co.ke/pvp/lnm";
            $this->reportEndpoint = "https://api.proxyapi.co.ke/pvp/report";

            $this->supports = array(
                'products'
            );
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->enabled = $this->get_option('enabled');
            $this->description = $this->get_option( 'description' );
            $this->api_key = $this->get_option('api_key');

            add_action('woocommerce_update_options_payment_gateways_'.$this->id,
                array(
                    $this,
                    'process_admin_options'
                )
            );
            add_action('woocommerce_api_'.strtolower($this->webHook), array( $this, 'pvp_callback'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'label' => __('Enable Pay via Proxy Payment', 'woocommerce'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'yes'
                ),
                //This controls the title which the user sees during checkout.
                'title' => array(
                    'title'       => __('Title', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce' ),
                    'default'     => __('Lipa na M-Pesa', 'woocommerce'),
                    'desc_tip'    => true
                ),
                //This controls the description which the user sees during checkout.
                'description' => array(
                    'title'       => __('Description', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce' ),
                    'default'     => __( "Check out using Safaricom's Lipa na MPesa. Please confirm your phone number is registered for Safaricom M-Pesa to use this service, "
                        ."and that it is enabled for STK Push. Check your mobile handset for an instant payment request from Safaricom after making the order", 'woocommerce' ),
                    'desc_tip'    => true
                ),

                "api_key" => array(
                    'title'       => __('API Key', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('API Key assigned from Proxy API', 'woocommerce'),
                    'default'     => ''
                )
            );
        }

        public function validate_fields()
        {
            if('no' === $this->enabled)
            {
                wc_add_notice( 'Check out with Lipa na MPesa is disabled. Please try another option.', 'error');
                return false;
            }

            if(empty($this->api_key))
            {
                wc_add_notice( 'API Key not found. Please contact the Administrator.', 'error');
                return false;
            }

            //invalid API Key format
            if(preg_match('/^[\d]{10}\-[\w]{20}$/', $this->api_key) !== 1)
            {
                wc_add_notice( 'API Key not found. Please contact the Administrator.', 'error');
                return false;
            }

            if (!is_ssl())
            {
                wc_add_notice( 'Cannot make payment over non-SSL channel! Please contact the Administrator.', 'error');
                return false;
            }

            $order_id = get_query_var('order-pay');
            if (!empty($order_id))
            {
                //it might be a reorder, check if it exists
                $order = wc_get_order($order_id);
                if(!empty($order))
                {
                    //order exists, thus its a reorder
                    $msisdn = $this->__format_msisdn($order->get_billing_phone());
                    if(empty($msisdn))
                    {
                        wc_add_notice('Phone number not available in order details.', 'error');
                        return false;
                    }
                }
                else
                {
                    //order does not exist
                    wc_add_notice('Order details cannot be found.', 'error');
                    return false;
                }
            }
            else
            {
                //its a new order
                if(empty($_POST['billing_phone']))
                {
                    //TODO: could be reorder, check for existing phone number
                    wc_add_notice( 'Phone Number is required!', 'error');
                    return false;
                }
                $msisdn = $this->__format_msisdn($_POST['billing_phone']);
            }

            if(preg_match('/^(\+?254|0)(7|1)[\d]{8}$/', $msisdn) !== 1)
            {
                //TODO: could be reorder, check for existing phone number
                wc_add_notice( 'Please enter a valid Phone Number.', 'error');
                return false;
            }
            return true;
        }

        public function process_payment($order_id)
        {
            global $woocommerce;
            $exists = false;
            if(!empty(wc_get_order($order_id)))
            {
                $order = wc_get_order($order_id);
                $exists = true;
            }
            else
            {
                $order = new WC_Order($order_id);
            }

            $requestID = strval($this->__getRandom(15));
            $callbackUrl = home_url('/wc-api/'.strtolower($this->webHook));
            $timestamp = time();
            $amount = floatval($order->get_total());
            $senderMSISDN = $this->__format_msisdn($order->get_billing_phone());
            $accountRef = strval($order_id);

            $urlparts = parse_url(home_url());
            $origin = $urlparts['scheme']."://".$urlparts['host'];

            $body = array(
                "RequestID" => $requestID,
                "ApiKey" => $this->api_key,
                "CallbackUrl" => $callbackUrl,
                "RequestTimestamp" => $timestamp,
                "Amount" => $amount,
                "SenderMSISDN" => $senderMSISDN,
                "AccountReference" => $accountRef,
                "Origin" => $origin
            );
            $body = wp_json_encode( $body );
            $options = [
                'body'        => $body,
                'headers'     => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'timeout'     => 60,
                'redirection' => 5,
                'blocking'    => true,
                'httpversion' => '1.1',
                'sslverify'   => false,
                'data_format' => 'body',
            ];

            $response = wp_remote_post($this->endpoint, $options);
            if (!is_wp_error($response))
            {
                $body = json_decode($response['body']);
                if(empty($body))
                {
                    $message = 'Lipa na MPesa request failed. Please try again later.';
                    wc_add_notice($message, 'error' );
                    write_log($message.": Empty Response Body from API");
                    return;
                }

                if (!isset($body->StatusCode) || !isset($body->ResponseCode))
                {
                    $message = 'Lipa na MPesa request failed. Please try again later.';
                    wc_add_notice($message, 'error' );
                    write_log($message.": Missing mandatory parameters in response from API");
                    return;
                }

                $responseCode = $body->ResponseCode;
                $responseDesc = $body->ResponseDesc;
                if (intval($responseCode) !== 0)
                {
                    wc_add_notice($responseDesc, 'error' );
                    write_log($responseDesc);
                    do_action('proxyapi_pvp_payment_failed', $order_id, $responseCode, $responseDesc);
                    return;
                }

                if(empty($body->CheckoutRequestID) || empty($body->MerchantRequestID))
                {
                    $message = 'Lipa na MPesa request failed. Please try again later.';
                    wc_add_notice($message, 'error' );
                    write_log($message);
                    do_action('proxyapi_pvp_payment_failed', $order_id, $responseCode, $responseDesc);
                    return;
                }

                $order->update_status('on-hold', 'Order sent. Waiting for customer to confirm instant payment prompt from Safaricom.');
                $exists ? update_post_meta($order_id, "request_id", $requestID) : add_post_meta($order_id, "request_id", $requestID, true);
                $exists ? update_post_meta($order_id, "checkout_request_id", $body->CheckoutRequestID) : add_post_meta($order_id, "checkout_request_id", $body->CheckoutRequestID, true);
                $woocommerce->cart->empty_cart();

                do_action('proxyapi_pvp_payment_pending', $order_id, $requestID, $body->MerchantRequestID, $body->CheckoutRequestID);
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            }
            else
            {
                if ($response->has_errors())
                {
                    $error = $response->get_error_message();
                }
                else
                {
                    $error = "Please try again later.";
                }
                wc_add_notice( 'Lipa na MPesa request failed. '.$error, 'error');
                write_log($error);
                return;
            }
        }

        public function pvp_callback()
        {
            $json = file_get_contents('php://input');
            if (empty($json))
            {
                write_log("Empty callback");
                return;
            }

            $callback = json_decode($json);
            if (empty($callback))
            {
                write_log("Empty callback");
                return;
            }

            if(!empty($callback->Body) && !empty($callback->Body->stkCallback))
            {
                //called either on success or failure
                $checkoutRequestId = $callback->Body->stkCallback->CheckoutRequestID;

                if(empty($callback->Body->stkCallback->CheckoutRequestID) || empty($callback->Body->stkCallback->MerchantRequestID))
                {
                    write_log('Missing mandatory parameters in callback:');
                    write_log($callback);
                    return;
                }

                $orders = wc_get_orders(array("checkout_request_id" => $checkoutRequestId));
                if (empty($orders))
                {
                    write_log("No orders found for given CheckoutRequestID '".$checkoutRequestId."'.");
                    return;
                }

                $order = $orders[0];
                if (strtolower($order->get_status()) === "completed" || strtolower($order->get_status()) === "refunded")
                {
                    do_action('proxyapi_pvp_payment_completed', $order->get_id());
                    write_log("Payment already processed: ".$order->get_status());
                    return;
                }

                $resultCode = intval($callback->Body->stkCallback->ResultCode);
                if($resultCode !== 0)
                {
                    //failed transaction
                    $resultDesc = $callback->Body->stkCallback->ResultDesc.".";
                    $order->update_status('failed', $resultDesc);
                    do_action('proxyapi_pvp_payment_failed', $order->get_id(), $resultCode, $resultDesc);
                    return;
                }
                else
                {
                    //success transaction
                    if(empty($callback->Body->stkCallback->CheckoutRequestID) || empty($callback->Body->stkCallback->MerchantRequestID))
                    {
                        write_log('Missing mandatory parameters in callback:');
                        write_log($callback);
                        return;
                    }

                    if (!empty($callback->Body->stkCallback->CallbackMetadata->Item))
                    {
                        $items = $callback->Body->stkCallback->CallbackMetadata->Item;
                        $orderDetails = array();
                        $paramKey = null;
                        foreach ($items as $item)
                        {
                            if (!empty($item->Name) && !empty($item->Value))
                            {
                                $orderDetails[$item->Name] = $item->Value;
                            }
                        }
                        if (!empty($orderDetails["TransactionDate"]) && !$order->meta_exists('mpesa_transaction_time'))
                        {
                            add_post_meta($order->get_id(), "mpesa_transaction_time", $orderDetails["TransactionDate"]);
                        }
                        if(!empty($orderDetails["PhoneNumber"]) && !$order->meta_exists('sender_msisdn'))
                        {
                            add_post_meta($order->get_id(), "sender_msisdn", $orderDetails["PhoneNumber"]);
                        }
                        if(!empty($orderDetails["MpesaReceiptNumber"]))
                        {
                            $order->payment_complete($orderDetails["MpesaReceiptNumber"]);
                            do_action('proxyapi_pvp_payment_completed', $order->get_id());
                            write_log("Order Payment completed successfully");
                        }
                    }
                }
            }
            else if(!empty($callback->Body) && !empty($callback->Body->pvpCallback))
            {
                //called only on success
                if(empty($callback->Body->pvpCallback->CheckoutRequestID) || empty($callback->Body->pvpCallback->RequestID))
                {
                    write_log('Missing mandatory parameters in callback:');
                    write_log($callback);
                    return;
                }

                $checkoutRequestId = $callback->Body->pvpCallback->CheckoutRequestID;
                $orders = wc_get_orders(array("checkout_request_id" => $checkoutRequestId));
                if (empty($orders))
                {
                    write_log("No orders found for given CheckoutRequestID '".$checkoutRequestId."'.");
                    return;
                }

                $order = $orders[0];
                if (!empty($callback->Body->pvpCallback->CallbackMetadata))
                {
                    $metadata = $callback->Body->pvpCallback->CallbackMetadata;
                    if (!empty($metadata->TransactionTime) && !$order->meta_exists('mpesa_transaction_time'))
                    {
                        add_post_meta($order->get_id(), "mpesa_transaction_time", $metadata->TransactionTime);
                    }
                    if (!empty($metadata->SenderMSISDN) && !$order->meta_exists('sender_msisdn'))
                    {
                        add_post_meta($order->get_id(), "sender_msisdn", $metadata->SenderMSISDN);
                    }
                    if (!empty($metadata->SenderFirstName) && !$order->meta_exists('sender_first_name'))
                    {
                        add_post_meta($order->get_id(), "sender_first_name", $metadata->SenderFirstName);
                    }
                    if (!empty($metadata->SenderLastName)&& !$order->meta_exists('sender_last_name'))
                    {
                        add_post_meta($order->get_id(), "sender_last_name", $metadata->SenderLastName);
                    }

                    if (!empty($metadata->DueDate))
                    {
                        $dueDate = intval($metadata->DueDate);
                        if (($dueDate - time()) <= (86400 * 2))
                        {
                            //less than two days left
                            $noticeLevel = WC_PROXYAPI_PVP_LOG_LEVEL_ERROR;
                        }
                        else if (($dueDate - time()) <= (86400 * 7))
                        {
                            //less than one week left
                            $noticeLevel = WC_PROXYAPI_PVP_LOG_LEVEL_WARN;
                        }
                        else
                        {
                            //enough time left
                            $noticeLevel = WC_PROXYAPI_PVP_LOG_LEVEL_NOTICE;
                        }
                        $_SESSION["dueDate"] = $dueDate;
                        $_SESSION["noticeLevel"] = $noticeLevel;
                    }

                    if (!empty($metadata->TransactionID)
                        && strtolower($order->get_status()) !== "completed"
                        && strtolower($order->get_status()) !== "refunded")
                    {
                        $order->payment_complete($metadata->TransactionID);
                        do_action('proxyapi_pvp_payment_completed', $order->get_id());
                        write_log("Order Payment completed successfully");
                    }
                }
            }
            else
            {
                write_log("Unknown callback");
            }
        }

        public function proxyapi_mpesa_transactions()
        {
            if (!is_admin())
            {
                return "";
            }

            if (!function_exists( 'wp_get_current_user'))
            {
                return "Unable to fetch Transactions. Please check on your WordPress installation";
            }
            $user = wp_get_current_user();
            $userId = isset($user->ID) ? (int) $user->ID : 0;

            $body = wp_json_encode(array(
                "APIKey" => $this->api_key,
                "UserID" => $userId
            ));

            $options = [
                'body'        => $body,
                'headers'     => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'timeout'     => 60,
                'redirection' => 3,
                'blocking'    => true,
                'httpversion' => '1.1',
                'sslverify'   => false,
                'data_format' => 'body',
            ];

            $response = wp_remote_post($this->reportEndpoint, $options);
            if (!is_wp_error($response))
            {
                $body = json_decode($response['body']);
                if(empty($body))
                {
                    return "Unable to fetch Transactions.";
                }

                if (!isset($body->StatusCode) || !isset($body->ResponseCode))
                {
                    return "Unable to fetch Transactions.";
                }

                $responseCode = $body->ResponseCode;
                $responseDesc = $body->ResponseDesc;
                if(intval($responseCode) !== 0)
                {
                    return "Unable to fetch Transactions".(empty($responseDesc) ? "" : ": ".$responseDesc);
                }

                $data = $body->Data;
                $html = "";

                $dueTimestamp = empty($_SESSION["dueDate"]) ? 0 : $_SESSION["dueDate"];
                if (!empty($dueTimestamp))
                {
                    $noticeLevel = empty($_SESSION["noticeLevel"]) ? WC_PROXYAPI_PVP_LOG_LEVEL_NOTICE : $_SESSION["noticeLevel"];
                    $date = new DateTime();
                    $date->setTimestamp($dueTimestamp);
                    if ($noticeLevel === WC_PROXYAPI_PVP_LOG_LEVEL_ERROR)
                    {
                        $formattedDate = sprintf( '<div><p style="padding-top: 5px; padding-bottom: 5px; font-weight: bold; color: red">Your ProxyAPI PVP Account is due on %1$s</p></div><br>', $date->format('Y-m-d H:i:s'));
                    }
                    else if ($noticeLevel === WC_PROXYAPI_PVP_LOG_LEVEL_WARN)
                    {
                        $formattedDate = sprintf( '<div><p style="padding-top: 5px; padding-bottom: 5px; font-weight: bold; color: orange">Your ProxyAPI PVP Account is due on %1$s</p></div><br>', $date->format('Y-m-d H:i:s'));
                    }
                    else
                    {
                        $formattedDate = sprintf( '<div><p style="padding-top: 5px; padding-bottom: 5px; font-weight: bold">Your ProxyAPI PVP Account is due on %1$s</p></div><br>', $date->format('Y-m-d H:i:s'));
                    }
                    $html .= $formattedDate;
                }

                $html .= '<table class="widefat">
                <thead>
                    <tr>
                        <th><strong>Checkout Request ID</strong></th>
                        <th><strong>MPesa Transaction ID</strong></th>
                        <th><strong>Amount</strong></th>
                        <th><strong>Order #</strong></th>
                        <th><strong>Sender MSISDN</strong></th>
                        <th><strong>Sender First Name</strong></th>
                        <th><strong>Sender Last Name</strong></th>
                        <th><strong>MPesa Transaction Time</strong></th>
                        <th><strong>Confirmed</strong></th>
                    </tr>
                </thead>
                <tbody>';

                $index = 0;
                foreach ($data as $transaction)
                {
                    if(!empty($transaction->TransactionTime))
                    {
                        $date = date_create_from_format("YmdHis", $transaction->TransactionTime);
                        $formatted = $date->format('Y-m-d H:i:s');
                    }
                    else
                    {
                        $formatted = "--";
                    }

                    if($index % 2 == 0)
                    {
                        $html .= '<tr class="alternate">
                        <td>'.$transaction->CheckoutRequestID.'</td>
                        <td>'.$transaction->MpesaTransactionID.'</td>
                        <td>'.$transaction->Amount.'</td>
                        <td>'.$transaction->AccountRef.'</td>
                        <td>'.$transaction->SenderMSISDN.'</td>
                        <td>'.$transaction->SenderFirstName.'</td>
                        <td>'.$transaction->SenderLastName.'</td>
                        <td>'.$formatted.'</td>
                        <td>'.((bool) $transaction->Confirmed === true
                                ? '<span style="font-weight: bold; color: forestgreen">Yes</span>'
                                : '<span style="font-weight: bold; color: orangered">No</span>').'</td>
                    </tr>';
                    }
                    else
                    {
                        $html .= '<tr>
                        <td>'.$transaction->CheckoutRequestID.'</td>
                        <td>'.$transaction->MpesaTransactionID.'</td>
                        <td>'.$transaction->Amount.'</td>
                        <td>'.$transaction->AccountRef.'</td>
                        <td>'.$transaction->SenderMSISDN.'</td>
                        <td>'.$transaction->SenderFirstName.'</td>
                        <td>'.$transaction->SenderLastName.'</td>
                        <td>'.$formatted.'</td>
                        <td>'.((bool) $transaction->Confirmed === true
                                ? '<span style="font-weight: bold; color: forestgreen">Yes</span>'
                                : '<span style="font-weight: bold; color: orangered">No</span>').'</td>
                    </tr>';
                    }
                    ++$index;
                }

                $html .= '</tbody></table>';
                echo $html;
            }
            else
            {
                echo "No transactions found";
            }
        }

        private function __format_msisdn($msisdn)
        {
            //allow local numbers only
            $msisdn = preg_replace("/^(\+?2547|07)/", "2547", $msisdn);
            $msisdn = preg_replace("/^(\+?2541|01)/", "2541", $msisdn);
            return $msisdn;
        }

        private function __getRandom($size)
        {
            if (intval($size) <= 0)
            {
                return null;
            }

            $token = "";
            $code = "ABCDEFGHJKLMNPQRSTUVWXYZ";
            $code .= "123456789";
            for ($i=0; $i < $size; $i++)
            {
                $token .= $code[$this->__crypto_rand_secure(strlen($code)-1)];
            }
            return $token;
        }

        private function __crypto_rand_secure($size)
        {
            $log = ceil(log($size, 2));
            $bytes = (int) ($log / 8) + 1; // length in bytes
            $bits = (int) $log + 1; // length in bits
            $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
            do
            {
                $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
                $rnd = $rnd & $filter; // discard irrelevant bits
            }
            while($rnd > $size);
            return $rnd;
        }
    }
}

function add_ProxyAPI_PVP( $gateways)
{
    $gateways[] = 'WC_Payment_Gateway_ProxyAPI_PVP';
    return $gateways;
}

if (!function_exists('write_log'))
{
    function write_log($log)
    {
        if ( true === WP_DEBUG )
        {
            if (is_array($log) || is_object($log))
            {
                error_log(print_r($log, true));
            }
            else
            {
                error_log($log);
            }
        }
    }
}

if (!function_exists('wc_get_orders_custom'))
{
    function wc_get_orders_custom($query, $filters)
    {
        if (!empty( $filters['checkout_request_id']))
        {
            $query['meta_query'][] = array(
                'key' => 'checkout_request_id',
                'value' => esc_attr( $filters['checkout_request_id']),
            );
        }
        else if (!empty( $filters['request_id']))
        {
            $query['meta_query'][] = array(
                'key' => 'request_id',
                'value' => esc_attr( $filters['request_id']),
            );
        }
        return $query;
    }
}

if (!function_exists('proxyapi_mpesa_report'))
{
    function proxyapi_mpesa_report($reports)
    {
        $reports["mpesa"] = array(
            'title' => __('M-Pesa', 'woocommerce'),
            'reports' => array(
                'received_mpesa_transactions' => array(
                    'title' => __('Received Lipa na M-Pesa Transactions','woocommerce'),
                    'description' => "Received Lipa na M-Pesa Transactions",
                    'hide_title' => true,
                    'callback' => array(new WC_Payment_Gateway_ProxyAPI_PVP(), 'proxyapi_mpesa_transactions')
                )
            )
        );
        return $reports;
    }
}

add_action('plugins_loaded', 'init_ProxyAPI_PVP');
add_filter( 'woocommerce_payment_gateways', 'add_ProxyAPI_PVP');
add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'wc_get_orders_custom', 10, 2);
add_filter( 'woocommerce_admin_reports', 'proxyapi_mpesa_report');
