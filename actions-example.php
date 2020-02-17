<?php

//Custom actions
add_action('proxyapi_pvp_payment_pending', 'pvp_on_payment_pending', 10, 3);
add_action('proxyapi_pvp_payment_completed', 'pvp_on_payment_complete', 10, 1);
add_action('proxyapi_pvp_payment_failed', 'pvp_on_payment_failed', 10, 3);

if (!function_exists('pvp_on_payment_complete'))
{
	function pvp_on_payment_complete($orderId)
	{
		write_log("Payment #".$orderId." completed");
	}
}

if (!function_exists('pvp_on_payment_failed'))
{
	function pvp_on_payment_failed($orderId, $resultCode, $resultDescr)
	{
		write_log("Payment #".$orderId." failed: ".$resultDescr);
	}
}

if (!function_exists('pvp_on_payment_pending'))
{
	function pvp_on_payment_pending($orderId, $requestID, $checkoutRequestID)
	{
		write_log("Payment #".$orderId." pending");
	}
}
