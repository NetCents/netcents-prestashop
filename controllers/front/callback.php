<?php
require_once(_PS_MODULE_DIR_ . '/netcents/vendor/netcents/init.php');
require_once(_PS_MODULE_DIR_ . '/netcents/vendor/version.php');

class NetCentsCallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess() {
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
                $error_message = 'CG Order and PS cart does not match';

                $this->logError($error_message, $cart_id);
                throw new Exception($error_message);
            }


            switch ($data->transaction_status) {
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
        $timestamp_tolerance = 60;
        $date = new DateTime();
        $current_timestamp = $date->getTimestamp();
        if ($hash_hmac === $signature && ($current_timestamp - $timestamp) / 60 < $timestamp_tolerance) {
            return true;
        }
        return false;
    }

    private function logError($message, $cart_id)
    {
        PrestaShopLogger::addLog($message, 3, null, 'Cart', $cart_id, true);
    }
}
