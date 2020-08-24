<?php

class ExpressPayCardRedirectModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();

		$token      = Tools::safeOutput(Configuration::get('EXPRESSPAYCARD_TOKEN'));
		$url        = Tools::safeOutput(Configuration::get('EXPRESSPAYCARD_TESTING_MODE'))  ? Tools::safeOutput(Configuration::get('EXPRESSPAYCARD_TEST_API_URL')) 
																					: Tools::safeOutput(Configuration::get('EXPRESSPAYCARD_API_URL'));
											
		$url    .= 'cardinvoices?token='.$token;																			
		$accountNo  = $this->context->cart->id;

		$expiration = ''; //Дата истечения срока действия выставлена счета на оплату. Формат - yyyyMMdd

		$cart       = new Cart($accountNo);// Объект корзины
		$expressPay = Module::getInstanceByName('expresspaycard');//Объект ExpressPayCard
		$amount     = $cart->getOrderTotal(true, Cart::BOTH);//Сумма заказа

		$amount = str_replace('.',',',$amount);

		$currency      = new Currency((int)($cart->id_currency));
		$currency_code = trim($currency->iso_code) == 'BYN' ? 933 : trim($currency->iso_code);
		//$currency   = Tools::safeOutput( Currency::getDefaultCurrency()->iso_code_num);//код валюты

		$required_currency = (date('y') > 16 || (date('y') >= 16 && date('n') >= 7)) ? '933' : '974';//требуемый код валюты

		$customer = new Customer((int)$this->context->cart->id_customer);//Покупатель

		if($currency_code != $required_currency)//проверка соответствия кода валюты
		{
			$expressPay->validateOrder($cart->id, Configuration::get('PS_OS_PREPARATION'),$cart->getOrderTotal(true, Cart::BOTH), $expressPay->displayName);//Создание заказа с статусом ожидаем оплату

			$expressPay->log_error('initContent','currency error; CURRENCY - '. json_encode($currency));

			Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);

			return;
		}

		$expressPay->validateOrder($cart->id, Configuration::get('PS_OS_CHEQUE'),$cart->getOrderTotal(true, Cart::BOTH), $expressPay->displayName);//Создание заказа с статусом ожидаем оплату

		$accountNo = Order::getOrderByCartId($accountNo);

		$info = 'Оплата заказа номер '.$accountNo.' в интернет-магазине '.Tools::safeOutput(Configuration::get('PS_SHOP_DOMAIN'));//Назначение платежа

		$returnUrl = (Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key;

		$failUrl = (Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'index.php?controller=order&step=1';

		$sessionTimeoutSecs = Tools::safeOutput(Configuration::get('EXPRESSPAYCARD_SESSION_TIMEOUT_SECS')) ;

		$requestParams = array(
			'url' => $url,
			'accountno'=> $accountNo,
			'expiration' => $expiration,
			'amount' => $amount,
			'currency' => $currency_code,
			'info' => $info,
			'returnurl' => $returnUrl,
			'failurl' => $failUrl,
			'language' => '',
			'sessiontimeoutsecs' => $sessionTimeoutSecs,
			'expirationdate' => ''
		);

		foreach($requestParams as $param){
			$param = (isset($param) ? $param : '');
		}

		$expressPay->log_info('initContent','requestParams - '.json_encode($requestParams));

		if(Configuration::get('EXPRESSPAYCARD_USE_DIGITAL_SIGN_SEND'))
		{
			$expressPay->log_info('initContent','computeSignature');

			$signature = $this->computeSignature($requestParams,Configuration::get('EXPRESSPAYCARD_SEND_SECRET_WORD'),'add-card-invoice', $expressPay, $token);

			$url .= '&signature='.$signature;
		}

		$expressPay->log_info('initContent','url - '.$url);

		$response = $this->sendRequestPOST($url, $requestParams);

		$expressPay->log_info('initContent','response - '.$response);

		$response = json_decode($response,true);

		if(isset($response['CardInvoiceNo']))
		{

			$expressPay->log_info('initContent','CardInvoiceNo - '.$response['CardInvoiceNo']);

			$url = Tools::safeOutput(Configuration::get('EXPRESSPAYCARD_TESTING_MODE'))  ? Tools::safeOutput(Configuration::get('EXPRESSPAYCARD_TEST_API_URL')) 
																					: Tools::safeOutput(Configuration::get('EXPRESSPAYCARD_API_URL'));
											
			$url .= 'cardinvoices/'.$response['CardInvoiceNo'].'/payment?token='.$token;

			if(Configuration::get('EXPRESSPAYCARD_USE_DIGITAL_SIGN_SEND'))
			{
				$expressPay->log_info('initContent','computeSignature');

				$signature = $this->computeSignature(array('cardinvoiceno' => $response['CardInvoiceNo']),Configuration::get('EXPRESSPAYCARD_SEND_SECRET_WORD'),'card-invoice-form', $expressPay, $token);

				$url .= '&signature='.$signature;
			}

			$expressPay->log_info('initContent','url - '.$url);

			$response = '';

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($ch);
			curl_close($ch);

			$expressPay->log_info('initContent','response - '.$response);

			$response = json_decode($response, true);

			if(isset($response['ErrorCode'])){
				$expressPay->log_error('callbackPost', 'Error response; RESPONSE - ' . $response['ErrorMessage']);
				return ;
			}

			$returnUrl = str_replace("https://192.168.10.95","https://192.168.10.95:9090",$response['FormUrl']);
	
			$expressPay->log_info('initContent','url - '.$returnUrl);

			$this->context->smarty->assign(array(
				'url' => $returnUrl
			));

			$this->setTemplate('module:expresspaycard/views/templates/front/redirect.tpl');

			return;
		}
		else if(isset($response['Error']))
		{
			$this->errors[] = $this->trans($response['Error']['Msg'], $response['Error'], 'Modules.ExpressPayCard.Shop');

			$expressPay->log_error('initContent','ERROR MESSAGE - '.$response['Message']);
		}

		Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
	}

	function sendRequestPOST($url, $params) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}

	function computeSignature($signatureParams, $secretWord, $method, $expressPay, $token) 
	{
		$normalizedParams = array_change_key_case($signatureParams, CASE_LOWER);
		
		$expressPay->log_info('computeSignature','normalizedParams - '.json_encode($normalizedParams).'; sectret word - '.$secretWord);

        $mapping = array(
            "add-invoice" => array(
                                    //"token",
                                    "accountno",
                                    "amount",
                                    "currency",
                                    "expiration",
                                    "info",
                                    "surname",
                                    "firstname",
                                    "patronymic",
                                    "city",
                                    "street",
                                    "house",
                                    "building",
                                    "apartment",
                                    "isnameeditable",
                                    "isaddresseditable",
                                    "isamounteditable",
									"emailnotification",
								),
            "get-details-invoice" => array(
                                    "token",
                                    "id"),
            "cancel-invoice" => array(
                                    "token",
                                    "id"),
            "status-invoice" => array(
                                    "token",
                                    "id"),
            "get-list-invoices" => array(
                                    "token",
                                    "from",
                                    "to",
                                    "accountno",
                                    "status"),
            "get-list-payments" => array(
                                    "token",
                                    "from",
                                    "to",
                                    "accountno"),
            "get-details-payment" => array(
                                    "token",
                                    "id"),
            "add-card-invoice"  =>  array(
                                    "accountno",                 
                                    "expiration",             
                                    "amount",                  
                                    "currency",
                                    "info",      
                                    "returnurl",
                                    "failurl",
                                    "language",
                                    "sessiontimeoutsecs",
                                    "expirationdate"),
           "card-invoice-form"  =>  array(
                                    "cardinvoiceno"),
            "status-card-invoice" => array(
                                    "token",
                                    "cardinvoiceno",
                                    "language"),
            "reverse-card-invoice" => array(
                                    "token",
                                    "cardinvoiceno")
		);
		
        $apiMethod = $mapping[$method];
        $result = $token;

		$expressPay->log_info('computeSignature','result string; RESULT - '.$result);
        foreach ($apiMethod as $item){
            $result .= $normalizedParams[$item];
		}

		$expressPay->log_info('computeSignature','result string; RESULT - '.$result);

		$hash = strtoupper(hash_hmac('sha1', $result, $secretWord, false));
		
		$expressPay->log_info('computeSignature','result hash; HASH - '.$hash);

        return $hash;
    }
}
