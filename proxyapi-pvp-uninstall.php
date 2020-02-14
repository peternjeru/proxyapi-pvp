<?php
defined( 'ABSPATH' ) or die( 'Not allowed' );

if (!function_exists( 'proxyapi_pvp_uninstall'))
{
    function proxyapi_pvp_uninstall()
    {

    }
}

register_uninstall_hook(__FILE__, 'proxyapi_pvp_uninstall' );
