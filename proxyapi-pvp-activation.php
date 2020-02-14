<?php
defined( 'ABSPATH' ) or die( 'Not allowed' );

if (!function_exists( 'proxyapi_pvp_setup'))
{
    function proxyapi_pvp_setup()
    {

    }
}

register_activation_hook(__FILE__, 'proxyapi_pvp_setup' );
