<?php
/**
 * Plugin Name: Pay via ProxyAPI
 * Plugin URI: http://woocommerce.com/products/pay-via-proxyapi/
 * Description: Your extension's description text.
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

if (is_admin())
{
    require_once __DIR__ . '/admin/proxyapi-pvp-settings.php';
}

class Proxy_API_PVP
{
    private static $instance = false;

    private function __construct()
    {
//        // back end
//        add_action		( 'plugins_loaded', 					array( $this, 'textdomain'				) 			);
//        add_action		( 'admin_enqueue_scripts',				array( $this, 'admin_scripts'			)			);
//        add_action		( 'do_meta_boxes',						array( $this, 'create_metaboxes'		),	10,	2	);
//        add_action		( 'save_post',							array( $this, 'save_custom_meta'		),	1		);
    }

    public static function getInstance()
    {
        if(!self::$instance)
        {
            self::$instance = new self;
        }
        return self::$instance;
    }
}

$PVP = Proxy_API_PVP::getInstance();
