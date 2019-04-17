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

        $token = $this->getToken($payload);

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

            Tools::redirect(
                $this->module->api_url . "/merchant/widget?data=" . $token . '&widget_id=' . $this->module->widget_id
            );
        } else {
            Tools::redirect('index.php?controller=order&step=3');
        }
    }

    private function getToken($payload)
    {
        $response = \NetCents\NetCents::request(
            $this->module->api_url . '/api/v1/widget/encrypt',
            $payload,
            $this->module->api_key,
            $this->module->secret_key
        );
        return $response['token'];
    }
}
