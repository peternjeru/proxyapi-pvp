<?php
/**
 * Plugin Name: Pay via ProxyAPI
 * Plugin URI: http://woocommerce.com/products/pay-via-proxyapi/
 * Description: Accept Safaricom Lipa na M-Pesa payments via Proxy API using PVP's Smart Payment Button.
 * Version: 1.0.0
 * Author: maxp555
 * Author URI: https://proxyapi.co.ke/
 * Text Domain: woocommerce-extension
 *
 * WC requires at least: 3.9.2
 * WC tested up to: 3.9.2
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) or die( 'Not allowed' );

require "proxyapi-pvp-activation.php";
require "proxyapi-pvp-deactivation.php";
require "proxyapi-pvp-uninstall.php";
require "proxyapi-pvp-settings.php";

function init_ProxyAPI_PVP()
{
    class ProxyAPI_PVP extends WC_Payment_Gateway
    {
        private static $instance = false;
        private $webHook = "ProxyAPI_PVP";

        public function __construct()
        {
            $this->id = 'proxyapi_pvp_settings'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = false; // in case you need a custom credit card form
            $this->method_title = __('Pay via Proxy API', 'woocommerce' );
            $this->method_description = __("Allow customers to pay using Safaricom's Lipa na M-Pesa via Proxy API", 'woocommerce' );
            $this->max_amount = 70000;

            $this->supports = array(
                'products'
            );
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->api_key = $this->get_option('api_key');

            add_action('woocommerce_update_options_payment_gateways_'.$this->id,
                array(
                    $this,
                    'process_admin_options'
                )
            );
            add_action('woocommerce_api_'.$this->webHook, array( $this, 'pvpCallback'));
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
                    'default'     => __( "Check out using Safaricom's Lipa na MPesa.", 'woocommerce' ),
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
           if(empty($this->api_key))
            {
                wc_add_notice( 'Missing API Key! Please contact the Administrator.', 'error');
                return false;
            }

            if (!is_ssl())
            {
                wc_add_notice( 'Cannot make payment over non-SSL channel! Please contact the Administrator.', 'error');
                return false;
            }

            if( empty($_POST['billing_phone']))
            {
                wc_add_notice( 'Phone Number is required!', 'error');
                return false;
            }

            if(preg_match('/^(\+?254|0)(7|1)[\d]{8}$/', $_POST['billing_phone']) !== 1)
            {
                wc_add_notice( 'Please enter a valid Phone Number.', 'error');
                return false;
            }
            return true;
        }

        public function process_payment($order_id)
        {
            global $woocommerce;
            // we need it to get any order details
            $order = new WC_Order( $order_id );

            $requestID = $this->__getRandom(20);
            $apiKey = $this->api_key;
            $callbackUrl = home_url('/wc-api/'.$this->webHook);
            $timestamp = time();
            $amount = $order->get_total();
            $senderMSISDN = $this->__formatMsisdn($order->get_billing_phone());
            $accountRef = $order_id;

            $urlparts = parse_url(home_url());
            $origin = $urlparts['scheme']."://".$urlparts['host'];

            $endpoint = "https://api.proxyapi.co.ke/pvp/lnm";
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
                'httpversion' => '1.0',
                'sslverify'   => false,
                'data_format' => 'body',
            ];

            $response = wp_remote_post( $endpoint, $options);
            if (!is_wp_error($response))
            {
                $body = json_decode($response['body'], true);
                if(empty($body))
                {
                    wc_add_notice( 'Lipa na MPesa request failed. Please try again.', 'error' );
                    return;
                }

                write_log($body);

                $order->update_status('on-hold', 'Please check your Phone for an instant payment prompt from Safaricom');
                $order->add_order_note('Order sent. Awaiting Lipa na M-Pesa Confirmation', 1);
                $woocommerce->cart->empty_cart();
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
                return;
            }
        }

        public function pvpCallback()
        {
            $json = file_get_contents('php://input');
            if (empty($json))
            {
                write_log("Empty callback");
                return;
            }

            write_log($json);
        }

        private function __formatMsisdn($msisdn)
        {
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
    $gateways[] = 'ProxyAPI_PVP';
    return $gateways;
}

if (!function_exists( 'remove_fields'))
{
    function remove_fields( $fields )
    {
        unset( $fields['billing']['billing_company'] ); // remove company field
        unset( $fields['billing']['billing_country'] );
        unset( $fields['billing']['billing_address_1'] );
        unset( $fields['billing']['billing_address_2'] );
        unset( $fields['billing']['billing_city'] );
        unset( $fields['billing']['billing_state'] ); // remove state field
        unset( $fields['billing']['billing_postcode'] ); // remove zip code field

        unset( $fields['shipping']['shipping_company'] );
        unset( $fields['shipping']['shipping_last_name'] );
        unset( $fields['shipping']['shipping_country'] );
        unset( $fields['shipping']['shipping_address_2'] );
        unset( $fields['shipping']['shipping_city'] );
        unset( $fields['shipping']['shipping_state'] );
        unset( $fields['shipping']['shipping_postcode'] );

        return $fields;
    }
}

add_action('plugins_loaded', 'init_ProxyAPI_PVP');
add_filter( 'woocommerce_payment_gateways', 'add_ProxyAPI_PVP');
add_filter( 'woocommerce_checkout_fields' , 'remove_fields', 9999);

if (!function_exists('write_log'))
{
    function write_log($log)
    {
        if ( true === WP_DEBUG )
        {
            if ( is_array( $log ) || is_object( $log ) )
            {
                error_log( print_r( $log, true ) );
            }
            else
            {
                error_log( $log );
            }
        }
    }
}