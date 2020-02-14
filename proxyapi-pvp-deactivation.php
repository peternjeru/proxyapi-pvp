<?php
defined( 'ABSPATH' ) or die( 'Not allowed' );

if (!function_exists( 'proxyapi_pvp_teardown'))
{
    function proxyapi_pvp_teardown()
    {

    }
}

register_deactivation_hook(__FILE__, 'proxyapi_pvp_teardown' );
