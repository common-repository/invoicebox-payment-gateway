<?php

namespace InvoiceboxPaymentGateway;
/*
 * Plugin Name: Invoicebox Payment Gateway
 * Description: Enable your customer to pay for your products through Invoicebox.
 * Author: OnePix
 * Author URI: https://onepix.net
 * Version: 1.2.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

require_once __DIR__ . "/vendor/autoload.php";

use WC_Order;
use WP_Query;

class Main
{
    private static $instance;
    public static $gateway_id;
    public static $plugin_url;
    public static $plugin_path;

    private function __construct()
    {
        self::$gateway_id = 'invoicebox';
        self::$plugin_url = plugin_dir_url(__FILE__);
        self::$plugin_path = plugin_dir_path(__FILE__);

        add_action('plugins_loaded', [$this, 'pluginsLoaded']);

    }

    public function pluginsLoaded()
    {
        if(!class_exists("\WC_Payment_Gateway")){
            add_action('admin_notices', function(){
                $message = __("Для работы плагина Invoicebox требуется установленный и активированный плагин") . " <a href='https://ru.wordpress.org/plugins/woocommerce/'>Woocommerce</a>";
                echo '<div class="notice notice-error is-dismissible"> <p>'. $message .'</p></div>';
            });

            return;
        }

        require_once 'includes/class-wc-invoicebox-abstract-gateway.php';
        require_once 'includes/class-wc-invoicebox-gateway.php';
        require_once 'includes/class-wc-invoicebox-legal-gateway.php';

        add_action('admin_enqueue_scripts', [$this, 'plugin_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'plugin_assets']);
        add_filter('woocommerce_payment_gateways', [$this, 'woocommercePaymentGateways']);
        add_action('init', array($this, 'add_endpoints'), 10);
    }

    public function woocommercePaymentGateways($gateways)
    {
        $gateways[] = 'InvoiceboxPaymentGateway\Includes\WC_Invoicebox_Gateway';
        $gateways[] = 'InvoiceboxPaymentGateway\Includes\WC_Invoicebox_Legal_Gateway';
        return $gateways;
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function plugin_admin_assets()
    {
        wp_enqueue_script('invoicebox-admin-script', self::$plugin_url . 'assets/js/admin-scripts.js', array(), '1.1');
    }

    public function plugin_assets()
    {
        if(is_checkout()){
            wp_enqueue_script('invoicebox-imask', self::$plugin_url . 'assets/js/imask.js', array('jquery'), '1.6');
            wp_enqueue_script('invoicebox-script', self::$plugin_url . 'assets/js/script.js', array('jquery','invoicebox-imask'), '1.6');

        }

    }

    public function add_endpoints()
    {
        add_rewrite_endpoint('invoicebox-push', EP_ROOT | EP_PAGES);
    }

}

Main::getInstance();
