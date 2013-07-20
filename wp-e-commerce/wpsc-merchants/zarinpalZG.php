<?php
/**
 * This is the zarinpalzg Payments Standard Gateway.
 * It uses the wpsc_merchant class as a base class which is handy for collating user details and cart contents.
 */

 /*
  * This is the gateway variable $nzshpcrt_gateways, it is used for displaying gateway information on the wp-admin pages and also
  * for internal operations.
  */
$nzshpcrt_gateways[$num] = array(
	'name' => 'درگاه پرداخت زرین گیت زرین پال',
	'merchent_version' => 2.0,
	'image' => 'http://zarinpal.com/img/merchant/merchant-1.png',
	'class_name' => 'wpsc_merchant_zarinpalzgzg_standard',
	'has_recurring_billing' => true,
	'wp_admin_cannot_cancel' => true,
	'display_name' => 'درگاه پرداخت zarinpalzg',
	'requirements' => array(
		/// so that you can restrict merchant modules to PHP 5, if you use PHP 5 features
		'php_version' => 4.3,
		 /// for modules that may not be present, like curl
		'extra_modules' => array()
	),

	// this may be legacy, not yet decided
	'internalname' => 'wpsc_merchant_zarinpalzg_standard',

	// All array members below here are legacy, and use the code in zarinpalzg_multiple.php
	'form' => 'form_zarinpalzg_multiple',
	'submit_function' => 'submit_zarinpalzg_multiple',
	'payment_type' => 'zarinpalzg',
	'supported_currencies' => array(
		'currency_list' =>  array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
		'option_name' => 'zarinpalzg_curcode'
	)
);



/**
	* WP eCommerce zarinpalzg Standard Merchant Class
	*
	* This is the zarinpalzg standard merchant class, it extends the base merchant class
	*
	* @package wp-e-commerce
	* @since 3.7.6
	* @subpackage wpsc-merchants
*/
class wpsc_merchant_zarinpalzg_standard extends wpsc_merchant {
  var $name = 'درگاه پرداخت zarinpalzg';
  var $zarinpalzg_ipn_values = array();

	/**
	* construct value array method, converts the data gathered by the base class code to something acceptable to the gateway
	* @access public
	*/
	function construct_value_array() {
		$this->collected_gateway_data = $this->_construct_value_array();
	}


	/**
	* construct value array method, converts the data gathered by the base class code to something acceptable to the gateway
	* @access private
	* @param boolean $aggregate Whether to aggregate the cart data or not. Defaults to false.
	* @return array $zarinpalzg_vars The zarinpalzg vars
	*/
	function _construct_value_array($aggregate = false) {
		global $wpdb;
		$zarinpalzg_vars = array();


		// Store settings to be sent to zarinpalzg
		$zarinpalzg_vars += array(
			'business' => get_option('zarinpalzg_multiple_business'),
			'return' => add_query_arg('sessionid', $this->cart_data['session_id'], $this->cart_data['transaction_results_url']),
			'charset' => 'utf-8',
		);
        $_SESSION['sec_zarinpalzg'] = $this->cart_data['session_id'];

		return apply_filters( 'wpsc_zarinpalzg_standard_post_data', $zarinpalzg_vars );
	}

	/**
	* submit method, sends the received data to the payment gateway
	* @access public
	*/
    function send($desc,$merchent,$amount,$redirect){
	$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
	$res = $client->PaymentRequest(
		array(
						'MerchantID' 	=> $merchent ,
						'Amount' 		=> $amount ,
						'Description' 	=> $desc ,
						'Email' 		=> '' ,
						'Mobile' 		=> '' ,
						'CallbackURL' 	=> $redirect

						)
		 );
	
	
    return $res;
	}
    function get($merchent,$au,$amount){
	$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
	$res = $client->PaymentVerification(
			array(
					'MerchantID'	 => $merchent ,
					'Authority' 	 => $au ,
					'Amount'	 	=> $amount
				));
        return $res;
    }     
	function submit() {
		$name_value_pairs = array();
		foreach ($this->collected_gateway_data as $key => $value) {
			$name_value_pairs[] = $key . '=' . urlencode($value);
		}
		$gateway_values =  implode('&', $name_value_pairs);
		$desc = 'پرداخت';
        $amount = substr($this->cart_data['total_price'],0,strpos($this->cart_data['total_price'],'.'));
        $merchent = get_option( "zarinpalzg_pro_username" );
        $redirect = urlencode(add_query_arg( 'gateway', 'wpsc_merchant_zarinpalzg_pro', $this->cart_data['notification_url'] ));
		$redirect = urlencode(get_option('siteurl')."/?zarinpalzg_callback=true");
   		$result = $this->send($desc,$merchent,$amount,$redirect);
		// URLs up to 2083 characters long are short enough for an HTTP GET in all browsers.
		// Longer URLs require us to send aggregate cart data to zarinpalzg short of losing data.
		// An exception is made for recurring transactions, since there isn't much we can do.
        if ($result->Status == 100 ){
            wp_redirect("https://www.zarinpal.com/pg/StartPay/" . $result->Authority . "/ZarinGate");
            exit;
        }
	}

	/**
	* nzshpcrt_zarinpalzg_callback method, receives data from the payment gateway
	* @access public
	*/



}





function nzshpcrt_zarinpalzg_callback() {
    if($_GET['zarinpalzg_callback'] == 'true' AND $_GET['Status'] == 'OK' ){
        global $wpdb;
        $f = new wpsc_merchant();
		$status = false;
        $merchent = get_option( "zarinpalzg_pro_username" );
        
        $au = $_GET['Authority'];
		$amount = substr($this->cart_data['total_price'],0,strpos($this->cart_data['total_price'],'.'));
        $result = get($merchent,$au,$amount);
		$refid = $result->RefID;
		switch ( $result->Status) {
			case 100:
                $data = array(
						'processed'  => 2,
						'transactid' => $refid,
						'date'       => time(),
					);
                $where = array( 'sessionid' => $_SESSION['sec_zarinpalzg'] );
				$format = array( '%d', '%s', '%s' );
				$wpdb->update( WPSC_TABLE_PURCHASE_LOGS, $data, $where, $format );
                $f->set_transaction_details( $refid, 3 );
				$f->go_to_transaction_results( $_SESSION['sec_zarinpalzg'] );
				break;
			default:
                $f->set_transaction_details( $refid, 6 );
				$f->go_to_transaction_results( $_SESSION['sec_zarinpalzg'] );
                transaction_results($_SESSION['sec_zarinpalzg'], false, $refid);
				break;
		}

	}
}














/**
 * submit_zarinpalzg_multiple function.
 *
 * Use this for now, but it will eventually be replaced with a better form merchent for gateways
 * @access public
 * @return void
 */
function submit_zarinpalzg_multiple(){
  if(isset($_POST['zarinpalzg_multiple_business'])) {
    update_option('zarinpalzg_multiple_business', $_POST['zarinpalzg_multiple_business']);
	}

  if(isset($_POST['zarinpalzg_multiple_url'])) {
    update_option('zarinpalzg_multiple_url', $_POST['zarinpalzg_multiple_url']);
	}

  if(isset($_POST['zarinpalzg_curcode'])) {
    update_option('zarinpalzg_curcode', $_POST['zarinpalzg_curcode']);
	}

  if(isset($_POST['zarinpalzg_curcode'])) {
    update_option('zarinpalzg_curcode', $_POST['zarinpalzg_curcode']);
	}

  if(isset($_POST['zarinpalzg_ipn'])) {
    update_option('zarinpalzg_ipn', (int)$_POST['zarinpalzg_ipn']);
	}

  if(isset($_POST['address_override'])) {
    update_option('address_override', (int)$_POST['address_override']);
	}
  if(isset($_POST['zarinpalzg_ship'])) {
    update_option('zarinpalzg_ship', (int)$_POST['zarinpalzg_ship']);
	}

  if (!isset($_POST['zarinpalzg_form'])) $_POST['zarinpalzg_form'] = array();
  foreach((array)$_POST['zarinpalzg_form'] as $form => $value) {
    update_option(('zarinpalzg_form_'.$form), $value);
	}

  return true;
}



/**
 * form_zarinpalzg_multiple function.
 *
 * Use this for now, but it will eventually be replaced with a better form merchent for gateways
 * @access public
 * @return void
 */
function form_zarinpalzg_multiple() {
  global $wpdb, $wpsc_gateways;

  $account_type = get_option( 'zarinpalzg_multiple_url' );
  /*
  $account_types = array(
  	'https://www.zarinpal.com/' => __( 'Live Account', 'wpsc' ),
  	'https://www.zarinpal.com/' => __( 'Sandbox Account', 'wpsc' ),
  );
  */

  $output = "
  <tr>
      <td>" . __( 'merchent:', 'wpsc' ) . "
      </td>
      <td>
      <input type='text' size='40' value='".get_option('zarinpalzg_multiple_business')."' name='zarinpalzg_multiple_business' />
      </td>
  </tr>";




  return $output;

}
add_action('init', 'nzshpcrt_zarinpalzg_callback');
?>
