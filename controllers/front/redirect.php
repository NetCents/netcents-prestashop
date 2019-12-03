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

        $total = (float) number_format($cart->getOrderTotal(true, 3), 2, '.', '');
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
            'hosted_payment_id' => $this->module->widget_id,
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
                'hosted_payment_id' => $this->module->widget_id,
            )
        );

        $api_url = $this->nc_get_api_url($this->module->api_url);
        $token = $this->getToken($payload, $api_url)->body->token;

        if (isset($token)) {
            $this->module->validateOrder(
                $cart->id,
                Configuration::get('NETCENTS_PENDING'),
                $total,
                $this->module->displayName,
                null,
                null,
                (int) $currency->id,
                false,
                $customer->secure_key
            );

            Tools::redirect($this->module->api_url . "/widget/merchant/widget?data=" . $token);
        } else {
            Tools::redirect('index.php?controller=order&step=3');
        }
    }

    private function getToken($payload, $api_url)
    {
        $formHandler =  new \Httpful\Handlers\FormHandler();
        $data = $formHandler->serialize($payload);

        $response =  \Httpful\Request::post($api_url . '/merchant/v2/widget_payments')
            ->body($data)
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
}
