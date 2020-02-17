<?php

//Custom actions
add_action('proxyapi_pvp_payment_pending', 'pvp_on_payment_pending', 10, 4);
add_action('proxyapi_pvp_payment_completed', 'pvp_on_payment_complete', 10, 1);
add_action('proxyapi_pvp_payment_failed', 'pvp_on_payment_failed', 10, 3);

//custom filter
add_filter('proxyapi_pvp_get_request_id_filter', 'pvp_get_request_id', 10, 1);

if (!function_exists('pvp_on_payment_pending'))
{
	function pvp_on_payment_pending($orderId, $requestID, $merchantRequestID, $checkoutRequestID)
	{
		write_log("Payment #".$orderId." pending");
	}
}

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

if (!function_exists('pvp_get_request_id'))
{
	/**
	 * User defined method to create own custom request ID for tracking on their end if needed and return it. The value must be unique in the system
	 *
	 * @param $orderId order number of current order if needed
	 * @return string
	 */
	function pvp_get_request_id($orderId)
	{
		return "Req_".microtime();
	}
}
