=== Pay via ProxyAPI ===
Contributors: maxp555
Tags: mpesa,lipa-na-mpesa,safaricom-mpesa,proxy-api,proxy,c2b,mpesa-c2b,pvp,proxyapi-pvp
Requires at least: 5.3
Tested up to: 5.3.2
Requires PHP: 5.6
Stable tag: trunk
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Accept Safaricom Lipa na M-Pesa payments using Pay via Proxy API

== Description ==
The Pay via Proxy (PVP in short) extension for WooCommerce enables you to accept payments for Safaricom\\\'s M-Pesa payment gateway via Proxy API.

PVP enables one to directly integrate into MPesa using both Lipa na M-Pesa and C2B APIs. It enables you to simplify your checkout process and allow a customer to simply enter their phone number and have the Lipa na MPesa payment prompt sent immediately to their phone numbers, and the responses and callbacks automatically processed by WooCommerce.

For any assistance in your setup, please join the Support group at [Telegram](https://t.me/joinchat/I-jBHE2JnVFAfGpjWRgJbA).

DISCLAIMER

This is not an official plugin from M-Pesa, and this plugin does not have any control over the availability of M-Pesa APIs. Any issues not directly caused by or related to the plugin should be addressed to the Safaricom support group.

== Installation ==
You will need an existing M-Pesa Shortcode to work with, and a Proxy API user account to get started. Please visit https://proxyapi.co.ke to sign up for and set up an account. Once you have signed up, follow the instructions below to install the plugin.

MANUAL INSTALLATION VIA WORDPRESS ADMIN
1. Download the plugin zip file
2. Login to your WordPress Admin. Click on “Plugins > Add New” from the left hand menu.
3. Click on the “Upload” option, then click “Choose File” to select the zip file from your computer. Once selected, press “OK” and press the “Install Now” button.
4. After installation, go to WooCommerce -> Settings -> Payments tab (or equivalent for your WooCommerce version). You will see Pay via Proxy API (PVP) as part of the available payment checkout options. Activate the plugin, then configure it. See below for configuration instructions.

CONFIGURING THE PLUGIN
You shall need to enter the API Key. To get the API Key:
1. Log into the portal at https://api.proxyapi.co.ke
2. Navigate to Shortcodes on the main menu
3. Double click on the Shortcode you wish to use for PVP
4. Make sure the \"Use Shortcode for Pay via ProxyAPI\" checkbox is selected and the settings saved on selection.
5. At the bottom of the page, you will see the API Key field. You will see your API key in the text field.

After fetching the API key, enter the key in the API Key field in the WooCommerce PVP Settings page, then save the changes.

== Frequently Asked Questions ==
= What version of PHP is required for this plugin to work? =
Use PHP v5.6 or later.

= Can someone on Daraja API use this plugin? =
No, you need to be aboard Proxy API to be able to use this plugin.

= Does this plugin process M-Pesa callbacks? =
Yes, the plugin is set up to directly receive callbacks from both Daraja and ProxyAPI and process each separately for the same transaction

= Does this plugin have a separate M-Pesa transactions table? =
No, the plugin places the transaction details directly as metadata into the order. This enables for a simpler and more convenient way to view MPesa results

= Does the plugin automatically complete transactions? =
Yes, depending on the result received from Daraja or ProxyAPI. If a transaction returned a failed error code, the equivalent order is marked as failed too. If the transaction was a success, the equivalent order will be assigned the MPesa Transaction ID. as its own unique Transaction ID and marked as complete.

== Changelog ==
= 1.0 =
This is the first release.

== Upgrade Notice ==
This is the first release.
