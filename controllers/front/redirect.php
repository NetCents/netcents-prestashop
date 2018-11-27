<?php
require_once(_PS_MODULE_DIR_ . '/netcents/vendor/netcents/init.php');
require_once(_PS_MODULE_DIR_ . '/netcents/vendor/version.php');
require_once(_PS_MODULE_DIR_ . 'netcents/vendor/autoload.php');

class NetCentsRedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;
        $address = new Address($cart->id_address_delivery);
        $customer = new Customer($cart->id_customer);

        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');
        }

        $total = (float)number_format($cart->getOrderTotal(true, 3), 2, '.', '');
        $currency = Context::getContext()->currency;


        $description = array();
        foreach ($cart->getProducts() as $product) {
            $description[] = $product['cart_quantity'] . ' × ' . $product['name'];
        }

        $link = new Link();
        $success_url = $link->getPageLink('order-confirmation', null, null, array(
          'id_cart'     => $cart->id,
          'id_module'   => $this->module->id,
          'key'         => $customer->secure_key
        ));

        $payload = array(
            'external_id' => $cart->id,
            'amount' => $total,
            'currency_iso' => $currency->iso_code,
            'callback_url' => $success_url,
            'first_name' => $address->firstname,
            'last_name' => $address->lastname,
            'email' => $customer->email,
            'webhook_url' => $this->context->link->getModuleLink('netcents', 'callback'),
            'merchant_id' => $this->module->api_key,
            'data_encryption' => array(
                'external_id' => $cart->id,
                'amount' => $total,
                'currency_iso' => $currency->iso_code,
                'callback_url' => $success_url,
                'first_name' => $address->firstname,
                'last_name' => $address->lastname,
                'email' => $customer->email,
                'webhook_url' => $this->context->link->getModuleLink('netcents', 'callback'),
                'merchant_id' => $this->module->api_key,
            )
        );

        $token = $this->getToken($payload)->body->token;

        if (isset($token)) {
            $this->module->validateOrder(
                $cart->id,
                Configuration::get('NETCENTS_PENDING'),
                $total,
                $this->module->displayName,
                null,
                null,
                (int)$currency->id,
                false,
                $customer->secure_key
            );

            Tools::redirect($this->module->api_url . "/merchant/widget?data=" . $token . '&widget_id=' . $this->module->widget_id);
        } else {
            Tools::redirect('index.php?controller=order&step=3');
        }
    }

    private function getToken($payload) {
        $formHandler =  new \Httpful\Handlers\FormHandler();
        $data = $formHandler->serialize($payload);

        $response =  \Httpful\Request::post($this->module->api_url . '/api/v1/widget/encrypt')
            ->body($data)
            ->addHeader('Authorization', 'Basic ' .  base64_encode( $this->module->api_key. ':' . $this->module->secret_key))
            ->send();
        return $response;
    }
}
