<?php
/**
* NOTICE OF LICENSE
*
* The MIT License (MIT)
*
* Copyright (c) 2015-2016 CoinGate
*
* Permission is hereby granted, free of charge, to any person obtaining a copy of
* this software and associated documentation files (the "Software"), to deal in
* the Software without restriction, including without limitation the rights to use,
* copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
* and to permit persons to whom the Software is furnished to do so, subject
* to the following conditions:
*
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
* WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
* IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*
*  @author    CoinGate <info@coingate.com>
*  @copyright 2015-2016 CoinGate
*  @license   https://github.com/coingate/prestashop-plugin/blob/master/LICENSE  The MIT License (MIT)
*/

require_once(_PS_MODULE_DIR_ . '/netcents/vendor/netcents/init.php');
require_once(_PS_MODULE_DIR_ . '/netcents/vendor/version.php');
require_once(_PS_MODULE_DIR_ . 'netcents/vendor/autoload.php');

class NetCentsCallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess() {
        parent::initContent();
        $data = json_decode(base64_decode($_POST['data']));
        $cart_id = $data->external_id;
        $order_id = Order::getOrderByCartId($cart_id);
        $order = new Order($order_id);

        try {
            if (!$order) {
                $error_message = 'NetCents Order #' . Tools::getValue('order_id') . ' does not exists';

                $this->logError($error_message, $cart_id);
                throw new Exception($error_message);
            }

            $result = $this->checkPayment($cart_id);

            if (!$result) {
                $error_message = 'NC Order and PS cart does not match';

                $this->logError($error_message, $cart_id);
                throw new Exception($error_message);
            }

            $api_url = $this->nc_get_api_url($this->module->api_url);
            $transaction = $this->fetchNetCentsTransaction($api_url, $data->transaction_id)->body;
            if (!isset($transaction)) {
                return;
            }

            switch ($transaction->status) {
                case 'paid':
                    $order_status = 'PS_OS_PAYMENT';
                    break;
                case 'overpaid':
                    $order_status = 'PS_OS_ERROR';
                    break;
                case 'underpaid':
                    $order_status = 'PS_OS_ERROR';
                    break;
                default:
                    $order_status = false;
            }

            if ($order_status !== false) {
                $history = new OrderHistory();
                $history->id_order = $order->id;
                $history->changeIdOrderState((int)Configuration::get($order_status), $order->id);
                $history->addWithemail(true, array(
                    'order_name' => Tools::getValue('order_id'),
                ));

                $this->context->smarty->assign(array(
                    'text' => 'OK'
                ));
            } else {
                $this->context->smarty->assign(array(
                    'text' => 'Order Status '.$cgOrder->status.' not implemented'
                ));
            }
        } catch (Exception $e) {
            $this->context->smarty->assign(array(
                'text' => get_class($e) . ': ' . $e->getMessage()
            ));
        }
        if (_PS_VERSION_ >= '1.7') {
            $this->setTemplate('module:netcents/views/templates/front/payment_callback.tpl');
        } else {
            $this->setTemplate('payment_callback.tpl');
        }
    }

    private function checkPayment() {
        $signature = $_POST['signature'];
        $data = $_POST['data'];
        $signing = $_POST['signing'];
        $exploded_parts = explode(",", $signature);
        $timestamp = explode("=", $exploded_parts[0])[1];
        $signature = explode("=", $exploded_parts[1])[1];
        $hashable_payload = $timestamp . '.' . $data;
        $hash_hmac = hash_hmac("sha256", $hashable_payload, $signing);
        $timestamp_tolerance = 1440;
        $date = new DateTime();
        $current_timestamp = $date->getTimestamp();
        if ($hash_hmac === $signature && ($current_timestamp - $timestamp) / 60 < $timestamp_tolerance) {
            return true;
        }
        return false;
    }

    private function fetchNetCentsTransaction($api_url, $transaction_id)
    {
        $response = \Httpful\Request::get($api_url . '/merchant/v2/transactions/' . $transaction_id)
            ->addHeader('Authorization', 'Basic ' .  base64_encode($this->module->api_key . ':' . $this->module->secret_key))
            ->send();
        return $response;
    }

    public function nc_get_api_url($host_url)
    {
        $parsed = parse_url($host_url);
        if ($host_url == 'https://merchant.net-cents.com') {
            $api_url = 'https://api.net-cents.com';
        } else if ($host_url == 'https://gateway-staging.net-cents.com') {
            $api_url = 'https://api-staging.net-cents.com';
        } else if ($host_url == 'https://gateway-test.net-cents.com') {
            $api_url = 'https://api-test.net-cents.com';
        } else {
            $api_url = $parsed['scheme'] . '://' . 'api.' . $parsed['host'];
        }
        return $api_url;
    }

    private function logError($message, $cart_id)
    {
        PrestaShopLogger::addLog($message, 3, null, 'Cart', $cart_id, true);
    }
}
