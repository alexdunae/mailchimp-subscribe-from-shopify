<?php
/**
 * Process a WebHook from Shopify and subscribe the
 * purchaser to a specific MailChimp list.
 *
 * Implementation notes are available at 
 * http://dialect.ca/code/mailchimp-subscribe-from-shopify/
 *
 *
 * @package   MailChimpSubscribeFromShopify
 * @version   1.0
 * @author    Alex Dunae, Dialect <alex[at]dialect[dot]ca>
 * @copyright Copyright (c) 2009, Alex Dunae
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 * @link      http://dialect.ca/code/mailchimp-subscribe-from-shopify/
 */

define('SHOPIFY_SHOP_ID',       '000000');
define('MAILCHIMP_API_KEY',     'XXXXXXXXXXXXXX');
define('MAILCHIMP_LIST_WEB_ID', '000000');

define('MAILCHIMP_EMAIL_TYPE',        'html');
define('MAILCHIMP_DOUBLE_OPTIN',      false);
define('MAILCHIMP_DOUBLE_OPTIN',      false);
define('MAILCHIMP_UPDATE_EXISTING',   true);
define('MAILCHIMP_REPLACE_INTERESTS', true);


// Map billing address data from Shopify to your MailChimp merge tags
// Use NULL to ignore fields from Shopify
$shopify_field_mapping = array(
                            'first-name' => 'FNAME',
                            'last-name'  => 'LNAME',
                            'company'    => NULL,
                            'address1'   => NULL,
                            'address2'   => NULL,
                            'city'       => NULL,
                            'province'   => NULL,
                            'zip'        => NULL,
                            'country'    => NULL,
                            'phone'      => NULL,
                            'address2'   => NULL,
                            'address2'   => NULL,
                            'address2'   => NULL);


// end of editable content

require_once('inc/MCAPI.class.php');

// Check for a valid Shopify shop ID
if ($_SERVER['HTTP_X_SHOPIFY_SHOP_ID'] != SHOPIFY_SHOP_ID) {
	header('HTTP/1.1 403 Forbidden');
	exit(0);
}

// Read the XML data from Shopify
$xml_str  = '';
$xml_data = fopen('php://input' , 'rb');

while ( !feof($xml_data) ) {
	$xml_str .= fread($xml_data, 4096);
}

fclose($xml_data);

$req_xml = new SimpleXMLElement($xml_str);

// Check for opt-in
if(strcasecmp($req_xml->{'buyer-accepts-marketing'}, 'true') != 0) {
	header('HTTP/1.1 200 OK');
	exit(0);
}

// Connect to MailChimp
$api = new MCAPI(MAILCHIMP_API_KEY);

if ( false === empty($api->errorCode) ) {
	header('HTTP/1.1 400 Bad request');
	echo 'Could not connect to MailChimp API';
	exit(0);
}


// Get API list ID
$lists   = $api->lists();
$list_id = null;

foreach ( $lists as $l ) {
	if ( intval(MAILCHIMP_LIST_WEB_ID) == intval($l['web_id']) ) {
		$list_id = $l['id'];
		break;
	}
}

if ( !$list_id ) {
	header('HTTP/1.1 400 Bad request');
	echo 'List ' . MAILCHIMP_LIST_WEB_ID . ' not found';
	exit(0);
}

// Map fields from Shopify to MailChimp merge fields
$merge_vars = array();

if ( !empty($req_xml->{'browser-ip'}) ) {
	$merge_vars['OPTINIP'] = $req_xml->{'browser-ip'};
}

foreach ( $shopify_field_mapping as $s_field => $mc_field ) {
	if ( null === $mc_field )
		continue;

	$merge_vars[$mc_field] = trim($req_xml->{'billing-address'}->{$s_field});
}

// Can't pass a zero-length array to MailChimp
if ( sizeof($merge_vars) === 0 ) {
	$merge_vars = array('');
}

$sub_result = $api->listSubscribe( $list_id,
                               trim($req_xml->email),
                               $merge_vars,
                               MAILCHIMP_EMAIL_TYPE,
                               MAILCHIMP_DOUBLE_OPTIN,
                               MAILCHIMP_UPDATE_EXISTING,
                               MAILCHIMP_REPLACE_INTERESTS);

if ( false === $sub_result ) {
	header('HTTP/1.1 400 Bad request');
	print 'Error subscribing';
} else {
    header('HTTP/1.1 200 OK');
    print 'Success';
}

exit(0);
