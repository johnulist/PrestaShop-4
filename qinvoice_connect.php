<?php
/**
 * Plugin Name: Q-invoice Connect for PrestaShop
 * Plugin URI: www.q-invoice.com
 * Description: Print order invoices directly through q-invoice
 * Version: 2.0.9
 * Author: q-invoice.com
 * License: GPLv3 or later
 * License URI: http://www.opensource.org/licenses/gpl-license.php
 */


@ini_set('display_errors', 'on');
if (!defined('_PS_VERSION_'))
  exit;

include_once(_PS_MODULE_DIR_.'/qinvoice_connect/controllers/class.qinvoice.php');

class Qinvoice_Connect extends PaymentModule
{
	public function __construct()
	{
		$this->bootstrap = true;
		$this->name = 'qinvoice_connect';
	    $this->tab = 'billing_invoicing';
        $this->author = 'q-invoice.com';
        $this->module_key = 'c846bb4014bfa988d9a1ca1778215296';
        $this->version = '2.0.9';
        $this->need_instance = 0;
        $this->is_configurable = 1;
        $this->displayName = $this->l('Qinvoice Connect');
       	$this->ps_versions_compliancy = array('min' => '1.4', 'max' => '1.7');
        parent::__construct();
       
 
	    $this->displayName = $this->l('Qinvoice Connect');
	    $this->description = $this->l('Connects to q-invoice.com API for sending invoices.');
	 
	    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
	 
	    if (!Configuration::get('QINVOICE_CONNECT_API_URL')){     
	    	$this->warning = $this->l('No URL provided');
	    }
	    if (!Configuration::get('QINVOICE_CONNECT_API_USERNAME')){     
	    	$this->warning = $this->l('No username provided');
	    }
	    if (!Configuration::get('QINVOICE_CONNECT_API_PASSWORD')){     
	    	$this->warning = $this->l('No password provided');
	    }
	}
	public function install()
	{
		parent::install();

		$this->registerHook('actionOrderStatusPostUpdate');
        //$this->registerHook('actionOrderReturn');
        $this->registerHook('actionValidateOrder');

        return true;
	}
	public function hookactionValidateOrder($params = null){
		// echo '<pre>';
		// print_r($params);
		// echo '</pre>';
		
		$order = new Order();
		$orderid = $order->getOrderByCartId($params['cart']->id);
		$trigger = Configuration::get('QINVOICE_CONNECT_INVOICE_TRIGGER');
		if($trigger == 'order'){
			$this->generateInvoice($orderid);
		}
	}
	

	public function hookactionOrderStatusPostUpdate($params = null){

		$trigger = Configuration::get('QINVOICE_CONNECT_INVOICE_TRIGGER');

		// echo $trigger;
		// echo ($params['newOrderStatus']->id);
		$statusid = $params['newOrderStatus']->id;

		// echo '<hr/>';
		// echo $params['id_order'];
		// $this->generateInvoice($params['id_order']);
		switch($statusid){
			case '1': // awaiting check
				// do nothing
			break;
			case '2': // Payment accepted
				if($trigger == 'payment'){
					// new invoice!
					$this->generateInvoice($params['id_order']);
				}
			break;
			case '3': // In Preparation
				// do nothing
			break;
			case '4': // Shipped
				if($trigger == 'shipped'){
					// new invoice!
					$this->generateInvoice($params['id_order']);
				}
			break;
			case '12': // Payment remotely accepted
				// do nothing
			break;
		}
	}

	public function hookupdateOrderStatus($params = null){

		$trigger = Configuration::get('QINVOICE_CONNECT_INVOICE_TRIGGER');

		// echo $trigger;
		// echo ($params['newOrderStatus']->id);
		// $statusid = $params['newOrderStatus']->id;

		// echo '<hr/>';
		// echo $params['id_order'];
		//  $this->generateInvoice($params['id_order']);
		switch($statusid){
			case '1': // awaiting check
				// do nothing
			break;
			case '2': // Payment accepted
				if($trigger == 'payment'){
					// new invoice!
					$this->generateInvoice($params['id_order']);
				}
			break;
			case '3': // In Preparation
				// do nothing
			break;
			case '4': // Shipped
				if($trigger == 'shipped'){
					// new invoice!
					$this->generateInvoice($params['id_order']);
				}
			break;
			case '12': // Payment remotely accepted
				// do nothing
			break;
		}
	}
	private function generateInvoice($orderid){
		
		$invoice = new qinvoice(Configuration::get('QINVOICE_CONNECT_API_USERNAME'),Configuration::get('QINVOICE_CONNECT_API_PASSWORD'),Configuration::get('QINVOICE_CONNECT_API_URL'));
		$invoice->identifier = 'prestashop_'. $this->version;

		$order = new Order($orderid);

		$delivery_address = new Address($order->id_address_delivery);
		$invoice_address = new Address($order->id_address_invoice);

		$delivery_country = new Country($delivery_address->id_country);
		$invoice_country = new Country($invoice_address->id_country);

		$msg = new Message();
		$messages = $msg->getMessagesByOrderId($orderid);
		$cnote = null;
		foreach($messages as $m){
			if($m['private']) continue;
			$date = explode(" ", $m['date_add']);
			$cnote .= $date[0] .' '. $m['message'] ."\n";
		}

		$customer = new Customer($order->id_customer);
		

		$cart = new Cart($order->id_cart);
		$products = $cart->getProducts();

		$date = explode(" ", $order->invoice_date);
		$invoice->date = strlen($date[0]) > 0 ? $date[0] : Date('Y-m-d');

		$invoice->companyname = $invoice_address->company;
		
		$invoice->firstname = $customer->firstname;
		$invoice->lastname = $customer->lastname;
		$invoice->email = $customer->email;				// Your customers emailaddress (invoice will be sent here)
		
		$invoice->address = $invoice_address->address1; 				// Self-explanatory
		$invoice->address2 = $invoice_address->address2;
		$invoice->zipcode = $invoice_address->postcode;
		$invoice->city = $invoice_address->city;
		$invoice->country = $invoice_country->iso_code;

		$invoice->phone = $invoice_address->phone;

		$invoice->vatnumber = $invoice_address->vat_number;
		
		$invoice->delivery_address = $delivery_address->address1; 				// Self-explanatory
		$invoice->delivery_address2 = $delivery_address->address2;
		$invoice->delivery_zipcode = $delivery_address->postcode;
		$invoice->delivery_city = $delivery_address->city;
		$invoice->delivery_country = $delivery_country->iso_code;

		$remark = 	str_replace('[reference]',$order->reference,Configuration::get('QINVOICE_CONNECT_INVOICE_REMARK'));
		

		$invoice->customernote = $cnote;

		$invoice->paid = 0;
		if($order->total_paid > 0) {
			$remark .= ' '.str_replace('[method]',$order->payment,Configuration::get('QINVOICE_CONNECT_PAID_REMARK'));
			$invoice->paid = 1;
		}

		$invoice->remark = $remark;

		$invoice->action = (int)Configuration::get('QINVOICE_CONNECT_INVOICE_ACTION');
		$invoice->saverelation = (int)Configuration::get('QINVOICE_CONNECT_SAVE_RELATION');
		$invoice->layout = (int)Configuration::get('QINVOICE_CONNECT_LAYOUT_CODE');


		$invoice->calculation_method = Configuration::get('QINVOICE_CONNECT_CALCULATION_METHOD');

		
		$invoice->addTag($order->reference);

		$ecotax_total = 0;
		foreach($products as $p){

			// print_r($p);

			// die();

			$attributes = explode(",",$p['attributes']);
			$descattr = null;
			foreach($attributes as $a){
				$descattr .= "\n". trim($a);
			}
			switch(Configuration::get('QINVOICE_CONNECT_PRODUCT_CODE')){
				case 'ean13':
					$product_code = $p['ean13'];
				break;
				case 'upc':
					$product_coce = $p['upc'];
				break;
				case 'reference':
					$product_code = $p['reference'];
				break;
				case 'none':
					$product_code = null;
				break;
			}
			$params = array( 	'code' => $product_code,
                 					'description' => $p['name'] . $descattr,
                 					'price' => $p['price']*100,
                 					'price_incl' => $p['price_wt']*100,
									'price_vat' => ($p['price_wt']-$p['price'])*100,
                 					'vatpercentage' => $p['rate']*100,
                 					'discount' => 0,
                 					'quantity' => $p['cart_quantity']*100,
                 					'categories' => $p['category'],
                 					'ledgeraccount' => Configuration::get('QINVOICE_CONNECT_DEFAULT_LEDGER')

                 				);
                 $invoice->addItem($params);
                 $params = array();

            if($p['ecotax'] > 0){
            	$ecotax_total += $p['ecotax']*$p['cart_quantity'];
            }
		}

		if($ecotax_total > 0){
			$ecotax_total = $ecotax_total*100;
			$ecotax_total_incl = $ecotax_total*(100 + Configuration::get('QINVOICE_CONNECT_DISCOUNT_RATE'));

			$params = array( 	'code' => 'ECOTAX',
                 					'description' => 'Ecotax',
                 					'price' => $ecotax_total,
                 					'price_incl' => $ecotax_total_incl,
									'price_vat' => $ecotax_total_incl - $ecotax,
                 					'vatpercentage' => Configuration::get('QINVOICE_CONNECT_DISCOUNT_RATE')*100,
                 					'discount' => 0,
                 					'quantity' => 100,
                 					'categories' => ''

                 				);
                 $invoice->addItem($params);
                 $params = array();
		}

		if($order->total_discounts > 0){
			$params = array( 	'code' => 'DSCNT',
                 					'description' => 'Discount',
                 					'price' => $order->total_discounts_tax_excl*-100,
                 					'price_incl' => $order->total_discounts_tax_incl*-100,
									'price_vat' => '',
                 					'vatpercentage' => Configuration::get('QINVOICE_CONNECT_DISCOUNT_RATE')*100,
                 					'discount' => 0,
                 					'quantity' => 100,
                 					'categories' => 'discount'

                 				);
            $invoice->addItem($params);
            $params = array();
		}

		if($order->total_shipping > 0){
			$params = array( 	'code' => 'SHPMNT',
                 					'description' => 'Shipment',
                 					'price' => $order->total_shipping_tax_excl*100,
                 					'price_incl' => $order->total_shipping_tax_incl*100,
									'price_vat' => '',
                 					'vatpercentage' => $order->carrier_tax_rate*100,
                 					'discount' => 0,
                 					'quantity' => 100,
                 					'categories' => 'shipping'

                 				);
            $invoice->addItem($params);
            $params = array();
		}

		$invoice->sendRequest();
		//echo 'Invoice generated';
		//die();
	}
	public function getContent()
	{
	    $output = null;

	    $updated = false;
	    $errors = 0;
	    $error_fields = array();
	 
	    if (Tools::isSubmit('submit'.$this->name))
	    {
	    	// QINVOICE_CONNECT_API_URL
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_API_URL'));
	        if (!$value  || empty($value) || !Validate::isUrl($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for API URL') );
	            $errors++;
	            $error_fields[] = $this->l('API URL');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_API_URL', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_API_USERNAME
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_API_USERNAME'));
	        if (!$value  || empty($value) || !Validate::isGenericName($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for API Username') );
	            $errors++;
	            $error_fields[] = $this->l('API USERNAME');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_API_USERNAME', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_API_PASSWORD
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_API_PASSWORD'));
	        if (!$value  || empty($value) || !Validate::isGenericName($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for API Password') );
	            $errors++;
	            $error_fields[] = $this->l('API Password');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_API_PASSWORD', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_LAYOUT_CODE
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_LAYOUT_CODE'));
	        if (!$value  || empty($value) || !Validate::isInt($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for Layout code') );
	            $errors++;
	            $error_fields[] = $this->l('Layout code');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_LAYOUT_CODE', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_INVOICE_TAG
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_INVOICE_TAG'));
	        if (!$value  || empty($value) || !Validate::isGenericName($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for Invoice tag') );
	            $errors++;
	            $error_fields[] = $this->l('Invoice tag');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_INVOICE_TAG', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_INVOICE_REMARK
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_INVOICE_REMARK'));
	        if (!$value  || empty($value) || !Validate::isGenericName($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for Invoice remark') );
	            $errors++;
	            $error_fields[] = $this->l('Invoice remark');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_INVOICE_REMARK', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_PAID REMARK
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_PAID_REMARK'));
	        if (!$value  || empty($value) || !Validate::isGenericName($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for Invoice remark') );
	            $errors++;
	            $error_fields[] = $this->l('Paid remark');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_PAID_REMARK', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_PAID LEDGER
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_DEFAULT_LEDGER'));
	        if (!$value  || empty($value) || !Validate::isInt($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for Invoice remark') );
	            $errors++;
	            $error_fields[] = $this->l('Default ledger');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_DEFAULT_LEDGER', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_INVOICE_TRIGGER
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_INVOICE_TRIGGER'));
	        if (!$value  || empty($value) || !Validate::isGenericName($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for Invoice trigger') );
	            $errors++;
	            $error_fields[] = $this->l('Invoice trigger');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_INVOICE_TRIGGER', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_CALCULATION_METHOD
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_CALCULATION_METHOD'));
	        if (!$value  || empty($value) || !Validate::isGenericName($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for Invoice trigger') );
	            $errors++;
	            $error_fields[] = $this->l('Calculation method');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_CALCULATION_METHOD', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_INVOICE_ACTION
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_INVOICE_ACTION'));
	        if (!Validate::isInt($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for Invoice action') );
	            $errors++;
	            $error_fields[] = $this->l('Invoice action');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_INVOICE_ACTION', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_INVOICE_ACTION
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_PRODUCT_CODE'));
	        if (!$value  || empty($value) || !Validate::isGenericName($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for Invoice action') );
	            $errors++;
	            $error_fields[] = $this->l('Product code');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_PRODUCT_CODE', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_SAVE_RELATION
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_SAVE_RELATION'));
	        if (!Validate::isInt($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for save relation') );
	            $errors++;
	            $error_fields[] = $this->l('Save relation');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_SAVE_RELATION', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }

	        // QINVOICE_CONNECT_SAVE_RELATION
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_DISCOUNT_RATE'));
	        if (!Validate::isInt($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for save relation') );
	            $errors++;
	            $error_fields[] = $this->l('Discount VAT %');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_DISCOUNT_RATE', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }
	        // QINVOICE_CONNECT_SAVE_RELATION
	        $value = strval(Tools::getValue('QINVOICE_CONNECT_ECOTAX_RATE'));
	        if (!Validate::isInt($value))
	        {
	            //$output .= $this->displayError( $this->l('Invalid Configuration value for save relation') );
	            $errors++;
	            $error_fields[] = $this->l('Ecotax VAT %');
	        }
	        else
	        {
	            Configuration::updateValue('QINVOICE_CONNECT_ECOTAX_RATE', $value);
	            //$output .= $this->displayConfirmation($this->l('Settings updated'));
	            $updated = true;
	        }
	    
		    if(!$updated){
		    	$output .= $this->displayError( $this->l('No fields where updated') );
		    }else{

		    	if($errors > 0){
		    		$string = '';
		    		foreach($error_fields as $f){
		    			$i++;
		    			$string .= $f;
		    			if($i < count($error_fields)){
		    				$string .= ', ';
		    			}

		    		}
		    		$output .= $this->displayConfirmation($this->l('Settings updated, with errors'));
		    		$output .= $this->displayError( $this->l('Found errors in fields: '. $string) );
		    	}else{
		    		$output .= $this->displayConfirmation($this->l('Settings updated'));
		    	}
		    }
		}
		return $output.$this->displayForm();
	}
	public function displayForm()
	{
	    // Get default Language
	    $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

	    $product_code_options = array(
	    								array(
		    								'id_option' => 'none',
		    								'name' => $this->l('None')
	    								),
	    								array(
		    								'id_option' => 'reference',
		    								'name' => $this->l('Reference')
	    								),
	    								array(
		    								'id_option' => 'ean13',
		    								'name' => $this->l('EAN-13')
	    								),
	    								array(
		    								'id_option' => 'upc',
		    								'name' => $this->l('UPC')
	    								)
	    							);

		$invoice_trigger_options = array(
	    								array(
		    								'id_option' => 'order',
		    								'name' => $this->l('On every order')
	    								),
	    								array(
		    								'id_option' => 'payment',
		    								'name' => $this->l('After succesful payment')
	    								),
	    								array(
		    								'id_option' => 'shipped',
		    								'name' => $this->l('When order is marked as shipped')
	    								)
	    							);

		$calulation_method_options = array(
	    								array(
		    								'id_option' => 'incl',
		    								'name' => $this->l('Prices are with VAT included')
	    								),
	    								array(
		    								'id_option' => 'excl',
		    								'name' => $this->l('Prices are without VAT')
	    								)
	    							);

	    $invoice_action_options = array(
	    								array(
		    								'id_option' => 0,
		    								'name' => $this->l('Save as draft')
	    								),
	    								array(
		    								'id_option' => 1,
		    								'name' => $this->l('Finalize invoice')
	    								),
	    								array(
		    								'id_option' => 2,
		    								'name' => $this->l('Finalize and send to customer')
	    								)
	    							);
	    $save_relation_options = array(
	    								array(
		    								'id_option' => 0,
		    								'name' => $this->l('No, don\'t save')
	    								),
	    								array(
		    								'id_option' => 1,
		    								'name' => $this->l('Yes, save to relations')
	    								)
	    							);

	    $invoice_vat_options = array(
	    								array(
		    								'id_option' => 0,
		    								'name' => $this->l('0%')
	    								),
	    								array(
		    								'id_option' => 6,
		    								'name' => $this->l('6%')
	    								),
	    								array(
		    								'id_option' => 21,
		    								'name' => $this->l('21%')
	    								)
	    							);
	   	
	     
	    // Init Fields form array
	    $fields_form[0]['form'] = array(
	        'legend' => array(
	            'title' => $this->l('Settings'),
	        ),
	        'input' => array(
	            array(
	                'type' => 'text',
	                'label' => $this->l('API URL'),
	                'name' => 'QINVOICE_CONNECT_API_URL',
	                'desc' => $this->l('E.g.: ').'https://app.q-invoice.com/api/xml/1.1/',
	                'size' => 55,
	                'required' => true
	            ),
	            array(
	                'type' => 'text',
	                'label' => $this->l('API Username'),
	                'name' => 'QINVOICE_CONNECT_API_USERNAME',
	                'size' => 20,
	                'value' => 'ffdd',
	                'required' => true
	            ),
	            array(
	                'type' => 'text',
	                'label' => $this->l('API Password'),
	                'name' => 'QINVOICE_CONNECT_API_PASSWORD',
	                'size' => 20,
	                'required' => true
	            ),
	            array(
	                'type' => 'text',
	                'label' => $this->l('Layout code'),
	                'name' => 'QINVOICE_CONNECT_LAYOUT_CODE',
	                'size' => 5,
	                'value' => '123',
	                'required' => false
	            ),
	            array(
	                'type' => 'text',
	                'label' => $this->l('Invoice Tag'),
	                'desc' => $this->l('Seperate multiple tags by comma'),
	                'name' => 'QINVOICE_CONNECT_INVOICE_TAG',
	                'size' => 20,
	                'required' => false
	            ),
	            array(
	                'type' => 'text',
	                'label' => $this->l('Invoice Remark'),
	                'name' => 'QINVOICE_CONNECT_INVOICE_REMARK',
	                'size' => 55,
	                'required' => false
	            ),
	            array(
	                'type' => 'text',
	                'label' => $this->l('Paid Remark'),
	                'name' => 'QINVOICE_CONNECT_PAID_REMARK',
	                'size' => 55,
	                'required' => false
	            ),
	            array(
	                'type' => 'text',
	                'label' => $this->l('Default ledger'),
	                'name' => 'QINVOICE_CONNECT_DEFAULT_LEDGER',
	                'size' => 20,
	                'required' => false
	            ),
	            array(
	                'type' => 'select',
	                'label' => $this->l('Product code'),
	                'desc' => $this->l('Which field should be used as SKU'),
	                'name' => 'QINVOICE_CONNECT_PRODUCT_CODE',
	                'options' => array(
					    'query' => $product_code_options,                           // $options contains the data itself.
					    'id' => 'id_option',                           // The value of the 'id' key must be the same as the key for 'value' attribute of the <option> tag in each $options sub-array.
					    'name' => 'name'                               // The value of the 'name' key must be the same as the key for the text content of the <option> tag in each $options sub-array.
					  ),
	                'required' => true
	            ),

	            array(
	                'type' => 'select',
	                'label' => $this->l('Send request on'),
	                'desc' => $this->l('Choose a trigger moment'),
	                'name' => 'QINVOICE_CONNECT_INVOICE_TRIGGER',
	                'options' => array(
					    'query' => $invoice_trigger_options,                           // $options contains the data itself.
					    'id' => 'id_option',                           // The value of the 'id' key must be the same as the key for 'value' attribute of the <option> tag in each $options sub-array.
					    'name' => 'name'                               // The value of the 'name' key must be the same as the key for the text content of the <option> tag in each $options sub-array.
					  ),
	                'required' => true
	            ),
	            array(
	                'type' => 'select',
	                'label' => $this->l('After request'),
	                'desc' => $this->l('How to process the request'),
	                'name' => 'QINVOICE_CONNECT_INVOICE_ACTION',
	                'options' => array(
					    'query' => $invoice_action_options,                           // $options contains the data itself.
					    'id' => 'id_option',                           // The value of the 'id' key must be the same as the key for 'value' attribute of the <option> tag in each $options sub-array.
					    'name' => 'name'                               // The value of the 'name' key must be the same as the key for the text content of the <option> tag in each $options sub-array.
					  ),
	                'required' => true
	            ),
	            array(
	                'type' => 'select',
	                'label' => $this->l('Save/update relation?'),
	                'desc' => $this->l('Add to q-invoice address book'),
	                'name' => 'QINVOICE_CONNECT_SAVE_RELATION',
	                'options' => array(
					    'query' => $save_relation_options,                           // $options contains the data itself.
					    'id' => 'id_option',                           // The value of the 'id' key must be the same as the key for 'value' attribute of the <option> tag in each $options sub-array.
					    'name' => 'name'                               // The value of the 'name' key must be the same as the key for the text content of the <option> tag in each $options sub-array.
					  ),
	                'required' => true
	            ),
	            array(
	                'type' => 'select',
	                'label' => $this->l('Calulation method'),
	                'desc' => $this->l('Which price is leading?'),
	                'name' => 'QINVOICE_CONNECT_CALCULATION_METHOD',
	                'options' => array(
					    'query' => $calulation_method_options,                           // $options contains the data itself.
					    'id' => 'id_option',                           // The value of the 'id' key must be the same as the key for 'value' attribute of the <option> tag in each $options sub-array.
					    'name' => 'name'                               // The value of the 'name' key must be the same as the key for the text content of the <option> tag in each $options sub-array.
					  ),
	                'required' => true
	            ),
	            array(
	                'type' => 'select',
	                'label' => $this->l('Discount VAT %'),
	                'name' => 'QINVOICE_CONNECT_DISCOUNT_RATE',
	                'options' => array(
					    'query' => $invoice_vat_options,                           // $options contains the data itself.
					    'id' => 'id_option',                           // The value of the 'id' key must be the same as the key for 'value' attribute of the <option> tag in each $options sub-array.
					    'name' => 'name'                               // The value of the 'name' key must be the same as the key for the text content of the <option> tag in each $options sub-array.
					  ),
	                'required' => true
	            ),
	            array(
	                'type' => 'select',
	                'label' => $this->l('Ecotax VAT %'),
	                'name' => 'QINVOICE_CONNECT_ECOTAX_RATE',
	                'options' => array(
					    'query' => $invoice_vat_options,                           // $options contains the data itself.
					    'id' => 'id_option',                           // The value of the 'id' key must be the same as the key for 'value' attribute of the <option> tag in each $options sub-array.
					    'name' => 'name'                               // The value of the 'name' key must be the same as the key for the text content of the <option> tag in each $options sub-array.
					  ),
	                'required' => true
	            )
	        ),
	        'submit' => array(
	            'title' => $this->l('Save'),
	            'class' => 'button'
	        )
	    );

		if (_PS_VERSION_ < '1.5'){
			echo '<form id="configuration_form" class="defaultForm qinvoice_connect" action="index.php?tab=AdminModules&amp;configure=qinvoice_connect&amp;module_name=qinvoice_connect&amp;submitqinvoice_connect=1&amp;token='. $_GET['token'].'" method="post" enctype="multipart/form-data">';
			echo '<fieldset id="fieldset_0">';
			echo '<legend>'. $fields_form[0]['form']['legend']['title'] .'</legend>';
        	

        	foreach($fields_form[0]['form']['input'] as $input){
        		echo '<label>'. $input['label'].'</label>';
				echo '<div class="margin-form">'; 
        		switch($input['type']){
        			case 'text':
						echo '<input type="text" name="'. $input['name'] .'" id="'. $input['name'] .'" value="'. Configuration::get($input['name']) .'" class="" size="55">';
        			break;
        			case 'select':
        				$selected = Configuration::get($input['name']);
        				echo '<select name="'. $input['name'] .'" id="'. $input['name'] .'">';
        				foreach($input['options']['query'] as $option){
        					echo '<option value="'. $option[$input['options']['id']].'">'.$option[$input['options']['name']].'</option>';
        				}
        				echo '</select>';
        			break;
        		}
        		if($input['required'] == true){ echo '<sup>*</sup>'; }
				if($input['desc']){ echo '<p class="preference_description">'. $input['desc'].'</p>'; }
				echo '</div>';
				echo '<div class="clear"></div>';

        		//echo $input['type'];
        		//return true;
        	}
        	echo '<div class="margin-form">';
			echo '<input type="submit" id="configuration_form_submit_btn" value="Save" name="submitqinvoice_connect" class="button"">';
			echo '</div>';

        	echo '</fieldset>';
        	echo '</form>';

 
		    
	    }else{
		    $helper = new HelperForm();
		     
		    // Module, t    oken and currentIndex
		    $helper->module = $this;
		    $helper->name_controller = $this->name;
		    $helper->token = Tools::getAdminTokenLite('AdminModules');
		    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		     
		    // Language
		    $helper->default_form_language = $default_lang;
		    $helper->allow_employee_form_lang = $default_lang;
		     
		    // Title and toolbar
		    $helper->title = $this->displayName;
		    $helper->show_toolbar = true;        // false -> remove toolbar
		    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
		    $helper->submit_action = 'submit'.$this->name;
		    $helper->toolbar_btn = array(
		        'save' =>
		        array(
		            'desc' => $this->l('Save'),
		            'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
		            '&token='.Tools::getAdminTokenLite('AdminModules'),
		        ),
		        'back' => array(
		            'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
		            'desc' => $this->l('Back to list')
		        )
		    );
		     
		    // Load current value
		    $helper->fields_value['QINVOICE_CONNECT_API_URL'] = Configuration::get('QINVOICE_CONNECT_API_URL');
		    $helper->fields_value['QINVOICE_CONNECT_API_USERNAME'] = Configuration::get('QINVOICE_CONNECT_API_USERNAME');
		    $helper->fields_value['QINVOICE_CONNECT_API_PASSWORD'] = Configuration::get('QINVOICE_CONNECT_API_PASSWORD');
		    $helper->fields_value['QINVOICE_CONNECT_LAYOUT_CODE'] = Configuration::get('QINVOICE_CONNECT_LAYOUT_CODE');
		    $helper->fields_value['QINVOICE_CONNECT_INVOICE_TAG'] = Configuration::get('QINVOICE_CONNECT_INVOICE_TAG');
		    $helper->fields_value['QINVOICE_CONNECT_INVOICE_REMARK'] = Configuration::get('QINVOICE_CONNECT_INVOICE_REMARK');
		    $helper->fields_value['QINVOICE_CONNECT_DEFAULT_LEDGER'] = Configuration::get('QINVOICE_CONNECT_DEFAULT_LEDGER');
		    $helper->fields_value['QINVOICE_CONNECT_PRODUCT_CODE'] = Configuration::get('QINVOICE_CONNECT_PRODUCT_CODE');
		    $helper->fields_value['QINVOICE_CONNECT_PAID_REMARK'] = Configuration::get('QINVOICE_CONNECT_PAID_REMARK');
		    $helper->fields_value['QINVOICE_CONNECT_INVOICE_TRIGGER'] = Configuration::get('QINVOICE_CONNECT_INVOICE_TRIGGER');
		    $helper->fields_value['QINVOICE_CONNECT_INVOICE_ACTION'] = Configuration::get('QINVOICE_CONNECT_INVOICE_ACTION');
		    $helper->fields_value['QINVOICE_CONNECT_SAVE_RELATION'] = Configuration::get('QINVOICE_CONNECT_SAVE_RELATION');
		    $helper->fields_value['QINVOICE_CONNECT_DISCOUNT_RATE'] = Configuration::get('QINVOICE_CONNECT_DISCOUNT_RATE');
		    $helper->fields_value['QINVOICE_CONNECT_ECOTAX_RATE'] = Configuration::get('QINVOICE_CONNECT_ECOTAX_RATE');
		    $helper->fields_value['QINVOICE_CONNECT_CALCULATION_METHOD'] = Configuration::get('QINVOICE_CONNECT_CALCULATION_METHOD');
		     
		    return $helper->generateForm($fields_form);
		}
	}
}
?>