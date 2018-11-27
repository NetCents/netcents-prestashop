<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . '/netcents/vendor/netcents/init.php';
require_once _PS_MODULE_DIR_ . '/netcents/vendor/version.php';
class NetCents extends PaymentModule {
    private $html = '';
    private $postErrors = array();

    public $api_auth_token;
    public $receive_currency;
    public $test;

    public function __construct()
    {
        $this->name = 'netcents';
        $this->tab = 'payments_gateways';
        $this->version = '1.4.0';
        $this->author = 'NetCents.com';
        $this->is_eu_compatible = 1;
        $this->controllers = array('payment', 'redirect', 'callback', 'cancel');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;

        $config = Configuration::getMultiple(
            array(
                'NETCENTS_API_KEY',
                'NETCENTS_WIDGET_ID',
                'NETCENTS_API_URL',
                'NETCENTS_SECRET_KEY',
                'NETCENTS_TEST',
            )
        );

        $this->api_key = $config['NETCENTS_API_KEY'];
        $this->widget_id = $config['NETCENTS_WIDGET_ID'];
        $this->api_url = $config['NETCENTS_API_URL'];
        $this->secret_key = $config['NETCENTS_SECRET_KEY'];

        if (!empty($config['NETCENTS_TEST'])) {
            $this->test = $config['NETCENTS_TEST'];
        }

        parent::__construct();

        $this->displayName = $this->l('Accept Cryptocurrencies with NetCents');
        $this->description = $this->l('Accept Bitcoin and other cryptocurrencies as a payment method with NetCents');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');

        if (!isset($this->api_key) || !isset($this->secret_key)
            || !isset($this->receive_currency)) {
            $this->warning = $this->l('API Access details must be configured in order to use this module correctly.');
        }
    }

    public function install()
    {
        if (!function_exists('curl_version')) {
            $this->_errors[] = $this->l('This module requires cURL PHP extension in order to function normally.');

            return false;
        }

        $order_pending = new OrderState();
        $order_pending->name = array_fill(0, 10, 'Awaiting NetCents payment');
        $order_pending->send_email = 0;
        $order_pending->invoice = 0;
        $order_pending->color = 'RoyalBlue';
        $order_pending->unremovable = false;
        $order_pending->logable = 0;

        $order_expired = new OrderState();
        $order_expired->name = array_fill(0, 10, 'NetCents payment expired');
        $order_expired->send_email = 0;
        $order_expired->invoice = 0;
        $order_expired->color = '#DC143C';
        $order_expired->unremovable = false;
        $order_expired->logable = 0;

        $order_confirming = new OrderState();
        $order_confirming->name = array_fill(0, 10, 'Awaiting NetCents payment confirmations');
        $order_confirming->send_email = 0;
        $order_confirming->invoice = 0;
        $order_confirming->color = '#d9ff94';
        $order_confirming->unremovable = false;
        $order_confirming->logable = 0;

        $order_invalid = new OrderState();
        $order_invalid->name = array_fill(0, 10, 'NetCents invoice is invalid');
        $order_invalid->send_email = 0;
        $order_invalid->invoice = 0;
        $order_invalid->color = '#8f0621';
        $order_invalid->unremovable = false;
        $order_invalid->logable = 0;

        if ($order_pending->add()) {
            copy(
                _PS_ROOT_DIR_ . '/modules/netcents/logo.png',
                _PS_ROOT_DIR_ . '/img/os/' . (int) $order_pending->id . '.png'
            );
        }

        if ($order_expired->add()) {
            copy(
                _PS_ROOT_DIR_ . '/modules/netcents/logo.png',
                _PS_ROOT_DIR_ . '/img/os/' . (int) $order_expired->id . '.png'
            );
        }

        if ($order_confirming->add()) {
            copy(
                _PS_ROOT_DIR_ . '/modules/netcents/logo.png',
                _PS_ROOT_DIR_ . '/img/os/' . (int) $order_confirming->id . '.png'
            );
        }

        if ($order_invalid->add()) {
            copy(
                _PS_ROOT_DIR_ . '/modules/netcents/logo.png',
                _PS_ROOT_DIR_ . '/img/os/' . (int) $order_invalid->id . '.png'
            );
        }

        Configuration::updateValue('NETCENTS_PENDING', $order_pending->id);
        Configuration::updateValue('NETCENTS_EXPIRED', $order_expired->id);
        Configuration::updateValue('NETCENTS_CONFIRMING', $order_confirming->id);
        Configuration::updateValue('NETCENTS_INVALID', $order_invalid->id);
        Configuration::updateValue('NETCENTS_API_URL', 'https://merchant.net-cents.com');

        if (!parent::install()
            || !$this->registerHook('payment')
            || !$this->registerHook('displayPaymentEU')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('paymentOptions')) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        $order_state_pending = new OrderState(Configuration::get('NETCENTS_PENDING'));
        $order_state_expired = new OrderState(Configuration::get('NETCENTS_EXPIRED'));
        $order_state_confirming = new OrderState(Configuration::get('NETCENTS_CONFIRMING'));

        return (
            Configuration::deleteByName('NETCENTS_APP_ID') &&
            Configuration::deleteByName('NETCENTS_WIDGET_ID') &&
            Configuration::deleteByName('NETCENTS_API_KEY') &&
            Configuration::deleteByName('NETCENTS_API_URL') &&
            Configuration::deleteByName('NETCENTS_SECRET_KEY') &&
            Configuration::deleteByName('NETCENTS_TEST') &&
            $order_state_pending->delete() &&
            $order_state_expired->delete() &&
            $order_state_confirming->delete() &&
            parent::uninstall()
        );
    }

    private function postValidation() {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('NETCENTS_API_KEY')) {
                $this->postErrors[] = $this->l('API key is required.');
            }

            if (!Tools::getValue('NETCENTS_SECRET_KEY')) {
                $this->postErrors[] = $this->l('Secret key is required.');
            }

            if (!Tools::getValue('NETCENTS_WIDGET_ID')) {
                $this->postErrors[] = $this->l('Web plugin id is required.');
            }
        }
    }

    private function postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue(
                'NETCENTS_API_KEY',
                $this->stripString(Tools::getValue('NETCENTS_API_KEY'))
            );
            Configuration::updateValue('NETCENTS_WIDGET_ID', Tools::getValue('NETCENTS_WIDGET_ID'));
            Configuration::updateValue('NETCENTS_SECRET_KEY', Tools::getValue('NETCENTS_SECRET_KEY'));
            Configuration::updateValue('NETCENTS_TEST', Tools::getValue('NETCENTS_TEST'));
            Configuration::updateValue('NETCENTS_API_KEY', Tools::getValue('NETCENTS_API_KEY'));
        }

        $this->html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    private function displayNETCENTS()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    private function displayNETCENTSInformation($renderForm)
    {
        $this->html .= $this->displayNETCENTS();
        $this->context->controller->addCSS($this->_path.'/views/css/tabs.css', 'all');
        $this->context->controller->addJS($this->_path.'/views/js/javascript.js', 'all');
        $this->context->smarty->assign('form', $renderForm);
        return $this->display(__FILE__, 'information.tpl');
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->postValidation();
            if (!count($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->html .= $this->displayError($err);
                }
            }
        } else {
            $this->html .= '<br />';
        }

        $renderForm = $this->renderForm();
        $this->html .= $this->displayNETCENTSInformation($renderForm);

        return $this->html;
    }

    public function hookPayment($params)
    {
        if (_PS_VERSION_ >= 1.7) {
            return;
        }

        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(array(
            'this_path'     => $this->_path,
            'this_path_bw'  => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
        ));

        return $this->display(__FILE__, 'payment.tpl');
    }


    public function hookDisplayOrderConfirmation($params)
    {
        if (_PS_VERSION_ <= 1.7) {
            return;
        }

        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(array(
            'this_path'     => $this->_path,
            'this_path_bw'  => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
        ));

        return $this->context->smarty->fetch(__FILE__, 'payment.tpl');
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $newOption->setCallToActionText('Bitcoin, Ethereum, Litecoin or other')
            ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true))
            ->setAdditionalInformation(
                $this->context->smarty->fetch('module:netcents/views/templates/hook/netcents_intro.tpl')
            );

        $payment_options = array($newOption);

        return $payment_options;
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

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Accept Cryptocurrencies with NetCents'),
                    'icon'  => 'icon-bitcoin',
                ),
                'input'  => array(
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('API KEY'),
                        'name'     => 'NETCENTS_API_KEY',
                        'desc'     => $this->l('Your API Key (created on NetCents)'),
                        'required' => true,
                    ),
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('SECRET KEY'),
                        'name'     => 'NETCENTS_SECRET_KEY',
                        'desc'     => $this->l('Your SECRET Key (created on NetCents)'),
                        'required' => true,
                    ),
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('WIDGET ID'),
                        'name'     => 'NETCENTS_WIDGET_ID',
                        'desc'     => $this->l('Your web plugin id'),
                        'required' => true,
                    ),
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('API URL'),
                        'name'     => 'NETCENTS_API_URL',
                        'desc'     => $this->l('NetCents API URL'),
                        'required' => true,
                    ),
                    array(
                        'type'     => 'select',
                        'label'    => $this->l('Test Mode'),
                        'name'     => 'NETCENTS_TEST',
                        'desc'     => $this->l(
                            'To test by creating fake transactions, turn Test Mode "On".'
                        ),
                        'required' => true,
                        'options'  => array(
                            'query' => array(
                                array(
                                    'id_option' => 0,
                                    'name'      => 'Off',
                                ),
                                array(
                                    'id_option' => 1,
                                    'name'      => 'On',
                                ),
                            ),
                            'id'    => 'id_option',
                            'name'  => 'name',
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = (Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
            ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0);
        $this->fields_form = array();
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
        . '&configure=' . $this->name . '&tab_module='
        . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues() {
        return array(
            'NETCENTS_API_KEY' => Tools::getValue(
                'NETCENTS_API_KEY',
                Configuration::get('NETCENTS_API_KEY')
            ),
            'NETCENTS_SECRET_KEY' => Tools::getValue(
                'NETCENTS_SECRET_KEY',
                Configuration::get('NETCENTS_SECRET_KEY')
            ),
            'NETCENTS_TEST' => Tools::getValue(
                'NETCENTS_TEST',
                Configuration::get('NETCENTS_TEST')
            ),
            'NETCENTS_WIDGET_ID' => Tools::getValue(
                'NETCENTS_WIDGET_ID',
                Configuration::get('NETCENTS_WIDGET_ID')
            )
        );
    }

    private function stripString($item) {
        return preg_replace('/\s+/', '', $item);
    }
}
