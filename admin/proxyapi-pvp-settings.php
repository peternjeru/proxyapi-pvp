<?php
defined( 'ABSPATH' ) or die( 'Not allowed' );

if (!is_admin())
{
    return;
}

if (!function_exists( 'proxyapi_pvp_settings_init'))
{
    function proxyapi_pvp_settings_init()
    {
        register_setting('general', 'proxyapi_pvp_api_key_value');
//        register_setting('general', 'proxyapi_pvp_callback_url_value');

        add_settings_section(
            'proxyapi_pvp_settings',        //id
            'Pay via ProxyAPI Settings',    //title
            'proxyapi_pvp_settings_cb',     //callable
            'general'                       //page
        );

        add_settings_field(
            'proxyapi_pvp_api_key',         //id
            'API Key',                //title
            'proxyapi_pvp_api_key_cb',      //callback
            'general',                      //page
            'proxyapi_pvp_settings'        //section
        );
    }
}

if (!function_exists( 'proxyapi_pvp_settings_cb'))
{
    function proxyapi_pvp_settings_cb()
    {
        echo '<p>The below section allows integration into <a href="https://proxyapi.co.ke">Proxy API</a></p>';
    }
}

if (!function_exists( 'proxyapi_pvp_api_key_cb'))
{
    function proxyapi_pvp_api_key_cb()
    {
        ?>
        <input type="text" name="proxyapi_pvp_api_key_value" value="">
        <?php
    }
}

add_action('admin_init', 'proxyapi_pvp_settings_init');
