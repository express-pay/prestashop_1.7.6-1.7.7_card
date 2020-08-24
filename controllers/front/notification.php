<?php
/**
 * @since 1.0.0
 */
class ExpressPayCardNotificationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {

        $expressPay = Module::getInstanceByName('expresspaycard');//Объект ExpressPayCard

        $this->log_info('postProcess','start notification');

        $useSignature = Configuration::get('EXPRESSPAYCARD_USE_DIGITAL_SIGN_RECEIVE');

        $secretWord   = Configuration::get('EXPRESSPAYCARD_RECEIVE_SECRET_WORD');

        $dataJSON = ( isset($_REQUEST['Data']) ) ? htmlspecialchars_decode($_REQUEST['Data']) : '';//Получение данных в json формате
        $dataJSON = stripcslashes($dataJSON);
        
        $signature = ( isset($_REQUEST['Signature']) ) ? $_REQUEST['Signature'] : ''; //Полученая подпись
        //$this->log_info('postProcess','data received; DATA - '.$dataJSON);

        $sign = $this->computeSignature($dataJSON, $secretWord);//Вычисленная подпись

        if($useSignature && $signature != $sign) {
            $this->log_error('postProcess','signatures do not match');
            header("HTTP/1.0 200 OK");
            die('Подписи не совпадают');
        }
        else if($useSignature)
        {
            $this->log_info('postProcess','Signatures match');
        }
        
        $data = json_decode($dataJSON,true); //Преобразование из json в array

        $history = new OrderHistory();// Объект История заказов
        $history->id_order = $data['AccountNo'];//Получение данных о заказе через id заказа

        $this->log_info('postProcess','Response; CmdType - '.$data['CmdType'].($data['CmdType'] == 3 ? '; Status - '.$data['Status'] : ''));
        switch($data['CmdType']){
            /*case 3: 
                switch($data['Status']){
                    case 1: 
                        // Ожидает оплату
                        $history->changeIdOrderState(_PS_OS_CHEQUE_, $history->id_order);//Изменим статус заказа на "Ожидает оплату"
                        header("HTTP/1.0 200 OK");
                        $this->log_info('postProcess','order is waiting for payment');
                        die('order is waiting for payment');
                        break;
                    case 2: 
                        // Просрочен
                        die();
                        break;
                    case 3: 
                        //Оплачен
                        $history->changeIdOrderState(_PS_OS_PAYMENT_, $history->id_order);//Изменим статус заказа на "Оплачен"
                        header("HTTP/1.0 200 OK");
                        $this->log_info('postProcess','Order payment success');
                        die('Order payment success');
                        break;
                    case 4: 
                        //Оплачен частично
                        die();
                        break;
                    case 5: 
                        //Отменен
                        $history->changeIdOrderState(_PS_OS_CANCELED_,$history->id_order);//Изменим статус заказа на "Отменен"
                        header("HTTP/1.0 200 OK");
                        $this->log_info('postProcess','Order canceled');
                        die('Order canceled');
                        break;
                    default:
                        $this->log_error('postProcess',' STATUS ERROR- '.$data['Status']);
                        header("HTTP/1.0 200 OK");
                        die();
                        return;
                }
                break;*/
                case 1:
                     //Оплачен
                     $history->changeIdOrderState(_PS_OS_PAYMENT_, $history->id_order);//Изменим статус заказа на "Оплачен"
                     header("HTTP/1.0 200 OK");
                     $this->log_info('postProcess','Order payment success');
                     die('Order payment success');
                     break;
                break;
                case 2:

                    //Отменен
                    $history->changeIdOrderState(_PS_OS_CANCELED_,$history->id_order);//Изменим статус заказа на "Отменен"
                    header("HTTP/1.0 200 OK");
                    $this->log_info('postProcess','Order canceled');
                    die('Order canceled');
                    break;
                break;
            default: 
                $this->log_error('postProcess',' CMDTYPE ERROR- '.$data['CmdType']);
                header("HTTP/1.0 200 OK");
                die();
                return;
        }
    }

    private function computeSignature($json, $secretWord) {
        $hash = NULL;
        $trimSecretWord = trim($secretWord);
        if (empty($trimSecretWord))
            $hash = strtoupper(hash_hmac('sha1', $json, ""));
        else
            $hash = strtoupper(hash_hmac('sha1', $json, $secretWord));
        return $hash;
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

        file_put_contents($log_url, $type . " - IP - " . $_SERVER['REMOTE_ADDR'] . "; DATETIME - ".date('c')."; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);
    }
}
