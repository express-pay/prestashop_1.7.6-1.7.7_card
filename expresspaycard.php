<?php
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_'))
    exit;

class ExpressPayCard extends PaymentModule
{
    private $_postErrors = array();

    public function __construct()
    {
        $this->name = 'expresspaycard';
        $this->tab = 'payments_gateways';
        $this->author = 'ООО "ТриИнком"';
        $this->version = '1.7';
        $this->controllers = array('redirect');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;

        parent::__construct();

        $this->page = basename(__FILE__, '.php');
        $this->displayName      = $this->l('ExpressPayCard');
        $this->description      = $this->l('This module allows you to accepts CARD payments');
        $this->confirmUninstall = $this->l('Are you sure you want to remove module ?');
    }

    // Установка модуля
    public function install()
    {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        return parent::install() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn') &&
            Configuration::updateValue('EXPRESSPAYCARD_MODULE_NAME', 'EXPRESSPAYCARDCARD') &&
            Configuration::updateValue('EXPRESSPAYCARD_TOKEN', '')&&
            Configuration::updateValue('EXPRESSPAYCARD_NOTIFICATION_URL', $this->context->link->getModuleLink($this->name,'notification',[]))&&
            Configuration::updateValue('EXPRESSPAYCARD_USE_DIGITAL_SIGN_SEND', false)&&
            Configuration::updateValue('EXPRESSPAYCARD_SEND_SECRET_WORD', '')&&
            Configuration::updateValue('EXPRESSPAYCARD_USE_DIGITAL_SIGN_RECEIVE', false)&&
            Configuration::updateValue('EXPRESSPAYCARD_RECEIVE_SECRET_WORD', '')&&
            Configuration::updateValue('EXPRESSPAYCARD_SESSION_TIMEOUT_SECS', 1200)&&
            Configuration::updateValue('EXPRESSPAYCARD_TESTING_MODE', true)&&
            Configuration::updateValue('EXPRESSPAYCARD_API_URL', "https://api.express-pay.by/v1/")&&
            Configuration::updateValue('EXPRESSPAYCARD_TEST_API_URL', "https://sandbox-api.express-pay.by/v1/")&&
            Configuration::updateValue('EXPRESSPAYCARD_SUCCESS_PAYMENT_TEXT', "Ваш номер заказа ##order_id##. Сумма к оплате: ##total_amount##."); 

    }

    // Удаление модуля
    public function uninstall()
    {
        return parent::uninstall() &&
        Configuration::deleteByName('EXPRESSPAYCARD_MODULE_NAME') &&
        Configuration::deleteByName('EXPRESSPAYCARD_TOKEN')&&
        Configuration::deleteByName('EXPRESSPAYCARD_NOTIFICATION_URL')&&
        Configuration::deleteByName('EXPRESSPAYCARD_USE_DIGITAL_SIGN_SEND')&&
        Configuration::deleteByName('EXPRESSPAYCARD_SEND_SECRET_WORD')&&
        Configuration::deleteByName('EXPRESSPAYCARD_USE_DIGITAL_SIGN_RECEIVE')&&
        Configuration::deleteByName('EXPRESSPAYCARD_RECEIVE_SECRET_WORD')&&
        Configuration::deleteByName('EXPRESSPAYCARD_SESSION_TIMEOUT_SECS')&&
        Configuration::deleteByName('EXPRESSPAYCARD_TESTING_MODE')&&
        Configuration::deleteByName('EXPRESSPAYCARD_API_URL')&&
        Configuration::deleteByName('EXPRESSPAYCARD_TEST_API_URL')&&
        Configuration::deleteByName('EXPRESSPAYCARD_SUCCESS_PAYMENT_TEXT');
    }

    // Сохранение значений из конфигурации
    public function getContent()
    {
        $output = null;
        $check = true;

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $output .= $this->_postProcess();

            }else{
                foreach ($this->_postErrors as $err) {
                    $output .= $this->displayError($err);
                }
            }
        }
        return $output . $this->displayForm();
    }

    protected function _postValidation()
    {
        if (!Tools::getValue('EXPRESSPAYCARD_TOKEN')) {
            $this->_postErrors[] = $this->trans('Token is empty', array(), 'Modules.ExpressPayCard.Admin');
        } elseif (!Tools::getValue('EXPRESSPAYCARD_SUCCESS_PAYMENT_TEXT')) {
            $this->_postErrors[] = $this->trans('payment text is empty.', array(), "Modules.ExpressPayCard.Admin");
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('EXPRESSPAYCARD_TOKEN', Tools::getValue('EXPRESSPAYCARD_TOKEN'));
            Configuration::updateValue('EXPRESSPAYCARD_NOTIFICATION_URL', Tools::getValue('EXPRESSPAYCARD_NOTIFICATION_URL'));
            Configuration::updateValue('EXPRESSPAYCARD_USE_DIGITAL_SIGN_SEND', Tools::getValue('EXPRESSPAYCARD_USE_DIGITAL_SIGN_SEND'));
            Configuration::updateValue('EXPRESSPAYCARD_SEND_SECRET_WORD', Tools::getValue('EXPRESSPAYCARD_SEND_SECRET_WORD'));
            Configuration::updateValue('EXPRESSPAYCARD_USE_DIGITAL_SIGN_RECEIVE', Tools::getValue('EXPRESSPAYCARD_USE_DIGITAL_SIGN_RECEIVE'));
            Configuration::updateValue('EXPRESSPAYCARD_RECEIVE_SECRET_WORD', Tools::getValue('EXPRESSPAYCARD_RECEIVE_SECRET_WORD'));
            Configuration::updateValue('EXPRESSPAYCARD_SESSION_TIMEOUT_SECS', Tools::getValue('EXPRESSPAYCARD_SESSION_TIMEOUT_SECS'));
            Configuration::updateValue('EXPRESSPAYCARD_TESTING_MODE', Tools::getValue('EXPRESSPAYCARD_TESTING_MODE'));
            Configuration::updateValue('EXPRESSPAYCARD_API_URL', Tools::getValue('EXPRESSPAYCARD_API_URL'));
            Configuration::updateValue('EXPRESSPAYCARD_TEST_API_URL', Tools::getValue('EXPRESSPAYCARD_TEST_API_URL'));
            Configuration::updateValue('EXPRESSPAYCARD_SUCCESS_PAYMENT_TEXT', Tools::getValue('EXPRESSPAYCARD_SUCCESS_PAYMENT_TEXT'));
        }
        return $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    // Форма страницы конфигурации
    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $fields_form[0]['form'] = array(

            'legend' => array(
                'title' => 'ExpressPayCard Settings',
                'icon' => 'icon-envelope'
            ),
            'input' =>[
                [
                    'type' => 'text',
                    'label' => $this->l('Token'),
                    'name' => 'EXPRESSPAYCARD_TOKEN',
                    'desc' => $this->l('Your token from express-pay.by website.'),
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Notification URL'),
                    'name' => 'EXPRESSPAYCARD_NOTIFICATION_URL',
                    'desc' => $this->l('Copy this URL to \"URL for notification\" field on express-pay.by.'),
                    'readonly' => true,
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Digital signature for API'),
                    'name' => 'EXPRESSPAYCARD_USE_DIGITAL_SIGN_SEND',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        ]
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Secret word for bills signing'),
                    'name' => 'EXPRESSPAYCARD_SEND_SECRET_WORD'
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Digital signature for notifications'),
                    'name' => 'EXPRESSPAYCARD_USE_DIGITAL_SIGN_RECEIVE',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        ]
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Secret word for notifications'),
                    'name' => 'EXPRESSPAYCARD_RECEIVE_SECRET_WORD'
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Duration of the session'),
                    'name' => 'EXPRESSPAYCARD_SESSION_TIMEOUT_SECS',
                    'desc' => $this->l('The time period specified in seconds, during which the customer can make a payment (is in the interval from 600 seconds (10 minutes) to 86400 seconds (1 day)). The default is 1200 seconds (20 minutes)'),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Use test mode'),
                    'name' => 'EXPRESSPAYCARD_TESTING_MODE',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        ]
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('API URL'),
                    'name' => 'EXPRESSPAYCARD_API_URL'
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Test API URL'),
                    'name' => 'EXPRESSPAYCARD_TEST_API_URL'
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Success payment message'),
                    'desc' => $this->l('This message will be showed to payer after payment.'),
                    'name' => 'EXPRESSPAYCARD_SUCCESS_PAYMENT_TEXT',
                    'required' => true
                ]
            ],
            'submit' => array(
                'title' => $this->l('Сохранить'),
                'class' => 'button'));

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;//AdminController::$currentIndex . '&configure=' . $this->name;

        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;

        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $helper->toolbar_scroll = false;
        $helper->submit_action = 'btnSubmit';

        $helper->fields_value['EXPRESSPAYCARD_TOKEN']                    = Configuration::get('EXPRESSPAYCARD_TOKEN');
        $helper->fields_value['EXPRESSPAYCARD_NOTIFICATION_URL']         = Configuration::get('EXPRESSPAYCARD_NOTIFICATION_URL');
        $helper->fields_value['EXPRESSPAYCARD_USE_DIGITAL_SIGN_SEND']    = Configuration::get('EXPRESSPAYCARD_USE_DIGITAL_SIGN_SEND');
        $helper->fields_value['EXPRESSPAYCARD_SEND_SECRET_WORD']         = Configuration::get('EXPRESSPAYCARD_SEND_SECRET_WORD');
        $helper->fields_value['EXPRESSPAYCARD_USE_DIGITAL_SIGN_RECEIVE'] = Configuration::get('EXPRESSPAYCARD_USE_DIGITAL_SIGN_RECEIVE');
        $helper->fields_value['EXPRESSPAYCARD_RECEIVE_SECRET_WORD']      = Configuration::get('EXPRESSPAYCARD_RECEIVE_SECRET_WORD');
        $helper->fields_value['EXPRESSPAYCARD_SESSION_TIMEOUT_SECS']     = Configuration::get('EXPRESSPAYCARD_SESSION_TIMEOUT_SECS');
        $helper->fields_value['EXPRESSPAYCARD_TESTING_MODE']             = Configuration::get('EXPRESSPAYCARD_TESTING_MODE');
        $helper->fields_value['EXPRESSPAYCARD_API_URL']                  = Configuration::get('EXPRESSPAYCARD_API_URL');
        $helper->fields_value['EXPRESSPAYCARD_TEST_API_URL']             = Configuration::get('EXPRESSPAYCARD_TEST_API_URL');
        $helper->fields_value['EXPRESSPAYCARD_SUCCESS_PAYMENT_TEXT']     = Configuration::get('EXPRESSPAYCARD_SUCCESS_PAYMENT_TEXT');

        $html = $this->_displayInfo();
        $html .= $helper->generateForm($fields_form);
        return $html;
    }

    private function _displayInfo()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    // Хук оплаты
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->l('ExpressPayCard'))
            ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true));
        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }
    
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        $state = $params['order']->getCurrentState();

        $config = Configuration::get("EXPRESSPAYCARD_SUCCESS_PAYMENT_TEXT");
        $successMessage = str_replace('##order_id##', $params['order']->id, $config);
        $successMessage = str_replace('##total_amount##', Tools::displayPrice($params['order']->total_paid), $successMessage);
        $successMessage = nl2br($successMessage);

        if($state == _PS_OS_PREPARATION_){
            $this->smarty->assign(array(
                'status' => 'fail'
            ));
        }
        else
        {
            $this->smarty->assign(array(
                'success_message' => $successMessage,
                'status' => 'ok'
            ));
        }

        return $this->display(__FILE__, 'payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;

    }

    public function log_error_exception($name, $message, $e) {
        $this->log($name, "ERROR" , $message . '; EXCEPTION MESSAGE - ' . $e->getMessage() . '; EXCEPTION TRACE - ' . $e->getTraceAsString());
    }

    public function log_error($name, $message) {
        $this->log($name, "ERROR" , $message);
    }

    public function log_info($name, $message) {
        $this->log($name, "INFO" , $message);
    }

    public function log($name, $type, $message) {
        $log_url = dirname(__FILE__) . '/Log';

        if(!file_exists($log_url)) {
            $is_created = mkdir($log_url, 0777);

            if(!$is_created)
                return;
        }

        $log_url .= '/express-pay-' . date('Y.m.d') . '.log';

        file_put_contents($log_url, $type . " - IP - " . $_SERVER['REMOTE_ADDR'] . "; DATETIME - ".date('c')."; USER AGENT - " . $_SERVER['HTTP_USER_AGENT'] . "; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);
    
    }
}