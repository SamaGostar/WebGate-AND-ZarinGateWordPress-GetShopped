<?php
/**
 * This is the zarinpalwg Payments Standard Gateway.
 * It uses the wpsc_merchant class as a base class which is handy for collating user details and cart contents.
 */

 /*
  * This is the gateway variable $nzshpcrt_gateways, it is used for displaying gateway information on the wp-admin pages and also
  * for internal operations.
  */
$nzshpcrt_gateways[$num] = array(
	'name' => 'درگاه پرداخت وب گیت زرین پال',
	'merchent_version' => 2.0,
	'image' => 'http://zarinpal.com/img/merchant/merchant-1.png',
	'class_name' => 'wpsc_merchant_zarinpalwgzg_standard',
	'has_recurring_billing' => true,
	'wp_admin_cannot_cancel' => true,
	'display_name' => 'درگاه پرداخت zarinpalwg',
	'requirements' => array(
		/// so that you can restrict merchant modules to PHP 5, if you use PHP 5 features
		'php_version' => 4.3,
		 /// for modules that may not be present, like curl
		'extra_modules' => array()
	),

	// this may be legacy, not yet decided
	'internalname' => 'wpsc_merchant_zarinpalwg_standard',

	// All array members below here are legacy, and use the code in zarinpalwg_multiple.php
	'form' => 'form_zarinpalwg_multiple',
	'submit_function' => 'submit_zarinpalwg_multiple',
	'payment_type' => 'zarinpalwg',
	'supported_currencies' => array(
		'currency_list' =>  array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'TWD', 'USD'),
		'option_name' => 'zarinpalwg_curcode'
	)
);



/**
	* WP eCommerce zarinpalwg Standard Merchant Class
	*
	* This is the zarinpalwg standard merchant class, it extends the base merchant class
	*
	* @package wp-e-commerce
	* @since 3.7.6
	* @subpackage wpsc-merchants
*/
class wpsc_merchant_zarinpalwg_standard extends wpsc_merchant {
  var $name = 'درگاه پرداخت zarinpalwg';
  var $zarinpalwg_ipn_values = array();

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
	* @return array $zarinpalwg_vars The zarinpalwg vars
	*/
	function _construct_value_array($aggregate = false) {
		global $wpdb;
		$zarinpalwg_vars = array();


		// Store settings to be sent to zarinpalwg
		$zarinpalwg_vars += array(
			'business' => get_option('zarinpalwg_multiple_business'),
			'return' => add_query_arg('sessionid', $this->cart_data['session_id'], $this->cart_data['transaction_results_url']),
			'charset' => 'utf-8',
		);
        $_SESSION['sec_zarinpalwg'] = $this->cart_data['session_id'];

		return apply_filters( 'wpsc_zarinpalwg_standard_post_data', $zarinpalwg_vars );
	}

	/**
	* submit method, sends the received data to the payment gateway
	* @access public
	*/
    function send($desc,$merchent,$amount,$redirect){
	$client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
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
	$client = new SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
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
        $merchent = get_option( "zarinpalwg_pro_username" );
        $redirect = urlencode(add_query_arg( 'gateway', 'wpsc_merchant_zarinpalwg_pro', $this->cart_data['notification_url'] ));
		$redirect = urlencode(get_option('siteurl')."/?zarinpalwg_callback=true");
   		$result = $this->send($desc,$merchent,$amount,$redirect);
		// URLs up to 2083 characters long are short enough for an HTTP GET in all browsers.
		// Longer URLs require us to send aggregate cart data to zarinpalwg short of losing data.
		// An exception is made for recurring transactions, since there isn't much we can do.
        if ($result->Status == 100 ){
            wp_redirect("https://www.zarinpal.com/pg/StartPay/" . $result->Authority);
            exit;
        }
	}

	/**
	* nzshpcrt_zarinpalwg_callback method, receives data from the payment gateway
	* @access public
	*/



}





function nzshpcrt_zarinpalwg_callback() {
    if($_GET['zarinpalwg_callback'] == 'true' AND $_GET['Status'] == 'OK' ){
        global $wpdb;
        $f = new wpsc_merchant();
		$status = false;
        $merchent = get_option( "zarinpalwg_pro_username" );
        
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
                $where = array( 'sessionid' => $_SESSION['sec_zarinpalwg'] );
				$format = array( '%d', '%s', '%s' );
				$wpdb->update( WPSC_TABLE_PURCHASE_LOGS, $data, $where, $format );
                $f->set_transaction_details( $refid, 3 );
				$f->go_to_transaction_results( $_SESSION['sec_zarinpalwg'] );
				break;
			default:
                $f->set_transaction_details( $refid, 6 );
				$f->go_to_transaction_results( $_SESSION['sec_zarinpalwg'] );
                transaction_results($_SESSION['sec_zarinpalwg'], false, $refid);
				break;
		}

	}
}














/**
 * submit_zarinpalwg_multiple function.
 *
 * Use this for now, but it will eventually be replaced with a better form merchent for gateways
 * @access public
 * @return void
 */
function submit_zarinpalwg_multiple(){
  if(isset($_POST['zarinpalwg_multiple_business'])) {
    update_option('zarinpalwg_multiple_business', $_POST['zarinpalwg_multiple_business']);
	}

  if(isset($_POST['zarinpalwg_multiple_url'])) {
    update_option('zarinpalwg_multiple_url', $_POST['zarinpalwg_multiple_url']);
	}

  if(isset($_POST['zarinpalwg_curcode'])) {
    update_option('zarinpalwg_curcode', $_POST['zarinpalwg_curcode']);
	}

  if(isset($_POST['zarinpalwg_curcode'])) {
    update_option('zarinpalwg_curcode', $_POST['zarinpalwg_curcode']);
	}

  if(isset($_POST['zarinpalwg_ipn'])) {
    update_option('zarinpalwg_ipn', (int)$_POST['zarinpalwg_ipn']);
	}

  if(isset($_POST['address_override'])) {
    update_option('address_override', (int)$_POST['address_override']);
	}
  if(isset($_POST['zarinpalwg_ship'])) {
    update_option('zarinpalwg_ship', (int)$_POST['zarinpalwg_ship']);
	}

  if (!isset($_POST['zarinpalwg_form'])) $_POST['zarinpalwg_form'] = array();
  foreach((array)$_POST['zarinpalwg_form'] as $form => $value) {
    update_option(('zarinpalwg_form_'.$form), $value);
	}

  return true;
}



/**
 * form_zarinpalwg_multiple function.
 *
 * Use this for now, but it will eventually be replaced with a better form merchent for gateways
 * @access public
 * @return void
 */
function form_zarinpalwg_multiple() {
  global $wpdb, $wpsc_gateways;

  $account_type = get_option( 'zarinpalwg_multiple_url' );
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
      <input type='text' size='40' value='".get_option('zarinpalwg_multiple_business')."' name='zarinpalwg_multiple_business' />
      </td>
  </tr>";




  return $output;

}
add_action('init', 'nzshpcrt_zarinpalwg_callback');
?>
