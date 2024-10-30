<?php

namespace InvoiceboxPaymentGateway\Includes;

use InvoiceboxPaymentGateway\Main;
use PHPMailer\PHPMailer\Exception;

abstract class WC_Invoicebox_Abstract_Gateway extends \WC_Payment_Gateway {
    protected $invoiceboxSettings;
    protected $helper;
    protected $apiVersion;
    protected $logging;

    protected $order_id;
    /**
     * @var \WC_Order
     */
    protected $order;
    protected $customer_data;
    protected $transaction_params;
    protected $gateway_error;

    /**
     * @var \Invoicebox\Invoicebox
     */
    protected $IB;


    public function __construct() {

        $this->method_title         = esc_html__( 'Invoicebox Payment', 'invoicebox' );
        $this->method_description   = esc_html__( 'Enable your customer to pay for your products through ', 'invoicebox' ) . $this->method_title;

        $this->supports = array(
            'products',
            'refunds'
        );

        // Method with all the options fields
        $this->init_form_fields();
        // Load the settings.
        $this->init_settings();

        $this->title                  = $this->get_option('title');
        $this->description            = $this->get_option('description');
        $this->enabled                = $this->get_option('enabled');

        require_once "class-invoicebox-settings.php";
        require_once "class-invoicebox-helper.php";
        require_once "class-invoicebox-helper-v2.php";
        require_once "class-invoicebox-helper-v3.php";

        $this->invoiceboxSettings = new InvoiceboxSettings();
        $this->invoiceboxSettings->gateway_id             = $this->id;
        $this->invoiceboxSettings->test_mode              = 'yes' === $this->get_option('testmode');
        $this->invoiceboxSettings->test_env               = 'yes' === $this->get_option('testenv');
        $this->apiVersion                                 = $this->get_option('apiversion');
        $this->invoiceboxSettings->apiVersion3            = $this->get_option('apiversion_v3');
        if(empty($this->apiVersion)) $this->apiVersion = 2;
        $this->invoiceboxSettings->apiVersion    = $this->apiVersion;
        $this->invoiceboxSettings->invoicebox_language    = $this->get_option('invoicebox_language');

        if($this->invoiceboxSettings->invoicebox_language === "ru") $this->icon = apply_filters('woocommerce_gateway_icon', Main::$plugin_url . 'assets/images/invoicebox-logo-png-ru.png');
        else $this->icon = apply_filters('woocommerce_gateway_icon', Main::$plugin_url . 'assets/images/invoicebox-logo-png-en.png');

        $this->invoiceboxSettings->shop_id      = ($this->invoiceboxSettings->test_mode == 'yes') ? $this->get_option("shop_id_v{$this->apiVersion}_test") : $this->get_option("shop_id_v{$this->apiVersion}");
        $this->invoiceboxSettings->shop_code    = ($this->invoiceboxSettings->test_mode == 'yes') ? $this->get_option("shop_code_v{$this->apiVersion}_test") : $this->get_option("shop_code_v{$this->apiVersion}");
        $this->invoiceboxSettings->key          = ($this->invoiceboxSettings->test_mode == 'yes') ? $this->get_option("key_v{$this->apiVersion}_test") : $this->get_option("key_v{$this->apiVersion}");
        $this->invoiceboxSettings->token        = ($this->invoiceboxSettings->test_mode == 'yes') ? $this->get_option("token_v{$this->apiVersion}_test") : $this->get_option("token_v{$this->apiVersion}");
        $this->invoiceboxSettings->user        = ($this->invoiceboxSettings->test_mode == 'yes') ? $this->get_option("user_v{$this->apiVersion}_test") : $this->get_option("user_v{$this->apiVersion}");
        $this->invoiceboxSettings->password     = ($this->invoiceboxSettings->test_mode == 'yes') ? $this->get_option("password_v{$this->apiVersion}_test") : $this->get_option("password_v{$this->apiVersion}");

        $this->invoiceboxSettings->logging = $this->logging      = $this->get_option('logging');

        $this->invoiceboxSettings->access_config = [
            "user" => $this->invoiceboxSettings->user,
            "password" => $this->invoiceboxSettings->password,
            "key" => $this->invoiceboxSettings->key,
            "token" => $this->invoiceboxSettings->token,
            "shop_id" => $this->invoiceboxSettings->shop_id,
            "shop_code" => $this->invoiceboxSettings->shop_code,
            "user_agent" => "Woocommerce v" . $this->apiVersion,
        ];

        $this->invoiceboxSettings->returnUrl = $this->get_return_url();
        $className = explode("\\",get_class($this));
        $className = array_pop($className);
        $this->invoiceboxSettings->notificationUrl = get_home_url() . "/wc-api/" . strtolower($className);

        switch ($this->apiVersion){
            case "2" : $this->helper = new Invoicebox_Helper_V2($this->getIB(), $this->order, $this->invoiceboxSettings);
                break;
            default : $this->helper = new Invoicebox_Helper_V3($this->getIB(), $this->order, $this->invoiceboxSettings);
                break;
        }

        $this->invoiceboxSettings->orderStatus = $this->get_option("orderStatus");
        $this->invoiceboxSettings->email = $this->get_option("email");
        $this->invoiceboxSettings->excise = $this->get_option("excise");
        $this->invoiceboxSettings->vatCode = $this->get_option("vatCode");
        $this->invoiceboxSettings->measure = $this->get_option("measure");
        $this->invoiceboxSettings->measureField = $this->get_option("measureField");
        $this->invoiceboxSettings->measureCode = $this->get_option("measureCode");
        $this->invoiceboxSettings->measureCodeField = $this->get_option("measureCodeField");
        $this->invoiceboxSettings->paymentType = $this->get_option("paymentType");
        $this->invoiceboxSettings->productType = $this->get_option("productType");
        $this->invoiceboxSettings->productTypeField = $this->get_option("productTypeField");
        $this->invoiceboxSettings->customerPaymentType = $this->get_option("customerPaymentTime");
        $this->invoiceboxSettings->legalCustomerPaymentType = $this->get_option('legalCustomerPaymentTime');

        $this->invoiceboxSettings->originCountry = $this->get_option("originCountry");
        $this->invoiceboxSettings->originCountryField = $this->get_option("originCountryField");
        $this->invoiceboxSettings->originCountryCode = $this->get_option("originCountryCode");
        $this->invoiceboxSettings->originCountryCodeField = $this->get_option("originCountryCodeField");
        $this->invoiceboxSettings->metaField = $this->get_option("metaField");


        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_gateway_assets'));

        // Is displayed in checkout
        add_filter('woocommerce_available_payment_gateways', array($this, 'invoicebox_gateway_enable_condition'));

        add_action( 'woocommerce_api_' . strtolower($className), array($this, 'push_callback') );

        add_action( 'woocommerce_receipt_' . $this->id, array($this, 'receipt_page') );

    }

        public function init_form_fields()
    {

        $this->form_fields = array(

            'enabled' => array(
                'title'       => __('Включить платежный шлюз Invoicebox', 'invoicebox'),
                'label'       => __('Включить/Выключить', 'invoicebox'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),

            'title' => array(
                'title'       => __('Название', 'invoicebox'),
                'type'        => 'text',
                'description' => __('', 'invoicebox'),
                'default'     => __('Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'description' => array(
                'title'       => __('Описание', 'invoicebox'),
                'type'        => 'textarea',
                'description' => __('', 'invoicebox'),
                'default'     => __('Оплатить с помощью Invoicebox.', 'invoicebox'),
            ),

            'invoicebox_language' => array(
                'title' 	=> __( 'Язык по-умолчанию', 'invoicebox' ),
                'description' 	=> __( 'Укажите используемый язык по-умолчанию', 'invoicebox' ),
                'type'        => 'select',
                'default'     => 'ru',
                'options'     => [
                    'ru' => 'русский',
                    'en' => 'english',
                ],
            ),

            'testmode' => array(
                'title'       => __('Тестовый режим', 'invoicebox'),
                'label'       => __('Включить/Выключить', 'invoicebox'),
                'type'        => 'checkbox',
                'description' => __('', 'invoicebox'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),

            'testenv' => array(
                'title'       => __('Тестовое окружение', 'invoicebox'),
                'label'       => __('Включить/Выключить', 'invoicebox'),
                'type'        => 'checkbox',
                'description' => __('Включите, если для магазина было настроено тестовое окружение', 'invoicebox'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),

            'apiversion' => array(
                'title'       => __('Версия api', 'invoicebox'),
                'type'        => 'select',
                'default'     => '3',
                'options'     => [
                    '2' => __('2 версия api', 'invoicebox'),
                    '3' => __('3 версия api', 'invoicebox'),
                ],
                'desc_tip'    => false,
            ),
            'apiversion_v3' => array(
                'title'       => __('Версия 3 api', 'invoicebox'),
                'type'        => 'select',
                'default'     => 'l3',
                'options'     => [
                    'l3' => __('Текущая', 'invoicebox'),
                    'v3' => __('Новая', 'invoicebox'),
                ],
                'desc_tip'    => false,
            ),

            'orderStatus' => array(
                'title'       => __('Статус заказа после поступления оплаты', 'invoicebox'),
                'type'        => 'select',
                'default'     => 'processing',
                'options'     => [
                    'on-hold' => __('На удержании', 'invoicebox'),
                    'processing' => __('В обработке', 'invoicebox'),
                    'completed' => __('Завершен', 'invoicebox'),
                ],
                'desc_tip'    => false,
            ),

            'email' => array(
                'title' => __('Email, куда отправлять сообщения об ошибках', 'invoicebox'),
                'type'  => 'text',
            ),

            'excise' => array(
                'title'       => __('Сумма акциза', 'invoicebox'),
                'type'        => 'number',
                'default'     => 0,
            ),

            'vatCode' => array(
                'title'       => __('Код процента НДС', 'invoicebox'),
                'type'        => 'select',
                'default'     => 'processing',
                'options'     => [
                    'VATNONE' => __('не облагается', 'invoicebox'),
                    'RUS_VAT0' => __('0%', 'invoicebox'),
                    'RUS_VAT10' => __('10%', 'invoicebox'),
                    'RUS_VAT20' => __('20%', 'invoicebox'),
                ],
                'desc_tip'    => true,
                'description' => __('Будет использоваться, если не включен функционал налогов в woocoomerce. !Важно: при использовании ставок, отличных от 0%, 10% или 20%, оплата через Invoicebox проходить не будет.', 'invoicebox'),
            ),

            'paymentType' => array(
                'title'       => __('Тип оплаты', 'invoicebox'),
                'type'        => 'select',
                'default'     => 'full_prepayment',
                'options'     => [
                    'prepayment' => __('prepayment', 'invoicebox'),
                    'full_prepayment' => __('full_prepayment', 'invoicebox'),
                    'advance' => __('advance', 'invoicebox'),
                    'full_payment' => __('full_payment', 'invoicebox'),
                ],
            ),

            'productType' => array(
                'title'       => __('Тип товара по умолчанию', 'invoicebox'),
                'type'        => 'select',
                'default'     => 'commodity',
                'options'     => [
                    'service' => __('услуга', 'invoicebox'),
                    'commodity' => __('товар', 'invoicebox'),
                ],
                'desc_tip'    => false,
            ),

            'productTypeField' => array(
                'title'       => __('Мета-поле, где задан тип для отдельного товара', 'invoicebox'),
                'type'        => 'text',
                'description' => __('Подробности заполнения мета-полей можно посмотреть в инструкции', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'measure' => array(
                'title'       => __('Единица измерения по умолчанию', 'invoicebox'),
                'type'        => 'text',
                'default'     => 'шт',
            ),

            'measureField' => array(
                'title'       => __('Мета-поле, где задана единица измерения для отдельного товара', 'invoicebox'),
                'type'        => 'text',
                'description' => __('Подробности заполнения мета-полей можно посмотреть в инструкции', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'measureCode' => array(
                'title'       => __('Код единицы измерения по умолчанию', 'invoicebox'),
                'type'        => 'text',
                'default'     => '796',
            ),

            'customerPaymentTime' => array(
                'title'       => __('Время на оплату по-умолчанию для физ.лиц', 'invoicebox'),
                'description' => __('Выставляется в часах', 'invoicebox'),
                'type'        => 'number',
                'default'     => '2',
            ),

            'legalCustomerPaymentTime' => array(
                'title'       => __('Время на оплату по-умолчанию для юр.лиц', 'invoicebox'),
                'description' => __('Выставляется в днях', 'invoicebox'),
                'type'        => 'number',
                'default'     => '1',
            ),

            'measureCodeField' => array(
                'title'       => __('Мета-поле, где задан код единицы измерения для отдельного товара', 'invoicebox'),
                'type'        => 'text',
                'description' => __('Подробности заполнения мета-полей можно посмотреть в инструкции', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'originCountry' => array(
                'title'       => __('Страна-производитель товара по умолчанию', 'invoicebox'),
                'type'        => 'text',
            ),


            'originCountryField' => array(
                'title'       => __('Мета-поле, где задана страна-производитель товара', 'invoicebox'),
                'type'        => 'text',
                'description' => __('Подробности заполнения мета-полей можно посмотреть в инструкции', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'originCountryCode' => array(
                'title'       => __('Код страны-производителя товара по умолчанию', 'invoicebox'),
                'type'        => 'text',
            ),

            'originCountryCodeField' => array(
                'title'       => __('Мета-поле, где задан код страны-производителя товара', 'invoicebox'),
                'type'        => 'text',
                'description' => __('Подробности заполнения мета-полей можно посмотреть в инструкции', 'invoicebox'),
                'desc_tip'    => true,
            ),



            'metaField' => array(
                'title'       => __('Мета-поле, где заданы в json-формате мета-данные элемента заказа', 'invoicebox'),
                'type'        => 'text',
                'description' => __('Подробности заполнения мета-полей можно посмотреть в инструкции', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'shop_id_v2' => array(
                'title' => __('Идентификатор магазина', 'invoicebox'),
                'type'  => 'text',
                'description' => __('Идентификатор магазина находится в личном кабинете платежной системы Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'shop_id_v3' => array(
                'title' => __('Идентификатор магазина', 'invoicebox'),
                'type'  => 'text',
                'description' => __('Идентификатор магазина находится в личном кабинете платежной системы Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'shop_id_v2_test' => array(
                'title' => __('Идентификатор магазина (тест)', 'invoicebox'),
                'type'  => 'text',
                'description' => __('Идентификатор магазина находится в личном кабинете платежной системы Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'shop_id_v3_test' => array(
                'title' => __('Идентификатор магазина (тест)', 'invoicebox'),
                'type'  => 'text',
                'description' => __('Идентификатор магазина находится в личном кабинете платежной системы Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'shop_code_v2' => array(
                'title' => __('Региональный код магазина', 'invoicebox'),
                'type'  => 'text',
                'description' => __('Региональный код магазина находится в личном кабинете платежной системы Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'shop_code_v3' => array(
                'title' => __('Региональный код магазина', 'invoicebox'),
                'type'  => 'text',
                'description' => __('Региональный код магазина находится в личном кабинете платежной системы Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'shop_code_v2_test' => array(
                'title' => __('Региональный код магазина (тест)', 'invoicebox'),
                'type'  => 'text',
                'description' => __('Региональный код магазина находится в личном кабинете платежной системы Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'shop_code_v3_test' => array(
                'title' => __('Региональный код магазина (тест)', 'invoicebox'),
                'type'  => 'text',
                'description' => __('Региональный код магазина находится в личном кабинете платежной системы Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'user_v2' => array(
                'title'   => __('Имя пользователя API', 'invoicebox'),
                'type'    => 'text',
                'default' => '',
                'description' => __('Имя пользователя направляется по почте при регистрации в платежной системе Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'token_v3' => array(
                'title'   => __('Токен', 'invoicebox'),
                'type'    => 'text',
                'default' => '',
                'description' => __('Токен находится в личном кабинете платежной системы Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'user_v2_test' => array(
                'title'   => __('Имя пользователя API', 'invoicebox'),
                'type'    => 'text',
                'default' => '',
                'description' => __('Имя пользователя направляется по почте при регистрации в платежной системе Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'token_v3_test' => array(
                'title'   => __('Токен (тест)', 'invoicebox'),
                'type'    => 'text',
                'default' => '',
                'description' => __('Токен находится в личном кабинете платежной системы Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'password_v2' => array(
                'title'   => __('Пароль API', 'invoicebox'),
                'type'    => 'password',
                'default' => '',
                'description' => __('Пароль API направляется по почте при регистрации в платежной системе Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'password_v2_test' => array(
                'title'   => __('Пароль API (тест)', 'invoicebox'),
                'type'    => 'password',
                'default' => '',
                'description' => __('Пароль API направляется по почте при регистрации в платежной системе Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'key_v2' => array(
                'title'   => __('API Ключ', 'invoicebox'),
                'type'    => 'text',
                'default' => '5',
                'description' => __('API Ключ направляется по почте при регистрации в платежной системе Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'key_v2_test' => array(
                'title'   => __('API Ключ (тест)', 'invoicebox'),
                'type'    => 'text',
                'default' => '5',
                'description' => __('API Ключ направляется по почте при регистрации в платежной системе Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'key_v3' => array(
                'title'   => __('API Ключ', 'invoicebox'),
                'type'    => 'text',
                'default' => '5',
                'description' => __('API Ключ направляется по почте при регистрации в платежной системе Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),


            'key_v3_test' => array(
                'title'   => __('API Ключ (тест)', 'invoicebox'),
                'type'    => 'text',
                'default' => '5',
                'description' => __('API Ключ направляется по почте при регистрации в платежной системе Invoicebox', 'invoicebox'),
                'desc_tip'    => true,
            ),

            'logging' => array(
                'title'       => esc_html__('Включить логирование', 'invoicebox'),
                'label'       => esc_html__('Включить/Выключить', 'invoicebox'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
        );
    }


    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
    }

    /**
     * @return \Invoicebox\Invoicebox
     * @throws \Invoicebox\Exceptions\ApiNotConfiguredException
     * @throws \Invoicebox\Exceptions\InvalidRequestException
     * @throws \Invoicebox\Exceptions\NotFoundException
     * @throws \Invoicebox\Exceptions\OperationErrorException
     */
    protected function getIB(){
        try{
            if(empty($this->IB)) $this->IB = new \Invoicebox\Invoicebox($this->apiVersion, $this->invoiceboxSettings->access_config, $this->invoiceboxSettings->test_env, $this->invoiceboxSettings->test_mode);
        }
        catch (\Exception $e){
            $this->errorMail($e->getMessage());
        }
        return $this->IB;
    }

    public function invoicebox_gateway_enable_condition($gateways){
        if(!$this->getIB()) unset($gateways[$this->id]);
        return $gateways;
    }

    public function process_payment( $order_id ) {
        $this->order_id = $order_id;
        $this->order = wc_get_order($order_id);
        $this->helper->set_order($this->order);
        $this->invoiceboxSettings->returnUrl = $this->get_return_url();
        WC()->cart->empty_cart();
    }

    function receipt_page($order_id)
    {
        $this->order_id = $order_id;
        $this->order = wc_get_order($order_id);
        $this->helper->set_order($this->order);
        $this->invoiceboxSettings->returnUrl = $this->get_return_url();
        $this->helper->receipt_page();

    }

    public function process_refund( $order_id, $amount = null, $reason = "" ) {
        return $this->helper->process_refund($order_id, $amount, $reason);
    }


    public function push_callback()
    {
        $this->helper->push_callback();
    }

    protected function lets_log($data, $prefix=false)
    {
        if ($this->logging == 'yes') {
            if($prefix) wc_get_logger()->debug(print_r($data, true), ['source' => $prefix ."_" . $this->id]);
            else wc_get_logger()->debug(print_r($data, true), ['source' => $this->id]);
        }
    }

    public function payment_gateway_assets()
    {

        if (!is_checkout()) {
            return;
        }

        if (!is_ssl()) {
            return;
        }

        if ('no' === $this->enabled) {
            return;
        }

        wp_register_style('invoicebox-frontend-styles', Main::$plugin_url . '/assets/css/styles.css');
        wp_enqueue_style('invoicebox-frontend-styles');
    }

    protected function errorMail($message){
        if($this->invoiceboxSettings->email) {
            if($this->invoiceboxSettings->invoicebox_language == "ru") wp_mail($this->invoiceboxSettings->email, sprintf("Ошибка в плагине Invoicebox на сайте %s", $_SERVER["HTTP_HOST"]), $message);
            else wp_mail($this->invoiceboxSettings->email, sprintf("Error in the Invoicebox plugin on the site %s", $_SERVER["HTTP_HOST"]), $message);
        }
    }


}

