<?php

namespace InvoiceboxPaymentGateway\Includes;

use Invoicebox\V2\Models\RefundOrderResponse;

require_once __DIR__ . "/class-invoicebox-helper.php";

class Invoicebox_Helper_V2 extends InvoiceboxHelper {

    public function process_payment(){
        $order = $this->order;
        return array(
            'result' 	=> 'success',
            'redirect'	=> add_query_arg('order-pay', $order->get_id(), add_query_arg('key', $order->get_order_key(), get_permalink(woocommerce_get_page_id('pay'))))
        );
    }

    public function process_refund($order_id, $amount = null, $reason = ""){
        $order = wc_get_order($order_id);
        if(!$order) return false;
        $this->order = $order;
        try{

            $this->lets_log("refund order " . $order_id, "refund_v3");
            $this->lets_log($_POST, "refund_v2");

            $inv_order_id = get_post_meta($order->get_id(), "invoicebox_order_id", true);
            $this->lets_log($inv_order_id, "refund_v2");
            if(!$inv_order_id) return false;

            if ($amount && $this->order->get_total() !== $amount){
                $refund_data = $this->formRefundData($order_id, $amount, $reason);
                $refund_data->setType(\Invoicebox\Contracts\Models\RefundOrderRequestInterface::PARTIAL_REFUND);

                $basket=[];
                $totalAmount = 0;
                $totalVatAmount = 0;

                $refund_count = $refund_totals = $refund_taxes = [];
                if(isset($_POST["line_item_qtys"])) $refund_count = json_decode(sanitize_text_field( wp_unslash($_POST["line_item_qtys"])), true);
                if(isset($_POST["line_item_totals"])) $refund_totals = json_decode(sanitize_text_field( wp_unslash($_POST["line_item_totals"])), true);
                if(isset($_POST["line_item_tax_totals"])) $refund_taxes = json_decode(sanitize_text_field( wp_unslash($_POST["line_item_tax_totals"])), true);
                if(empty($refund_count)) $refund_count = [];
                if(empty($refund_totals)) $refund_totals = [];
                if(empty($refund_taxes)) $refund_taxes = [];

                /**
                 * @var $item \WC_Order_Item_Product
                 */
                foreach ($order->get_items() as $key => $item){
                    if(!key_exists($key, $refund_count) || empty($refund_count[$key])) continue;
                    $basket[] = $this->formItemData($item, $key, $refund_totals[$key], array_sum($refund_taxes[$key]), $refund_count[$key]);
                    $totalAmount+= round($refund_totals[$key],2);
                    $totalVatAmount+= round(array_sum($refund_taxes[$key]),2);
                }


                if ( $order->get_shipping_total() > 0 )
                {
                    /**
                     * @var $shipping_item \WC_Order_Item_Shipping
                     */
                    foreach ($order->get_items("shipping") as $key => $shipping_item){
                        if((key_exists($key, $refund_totals) && !empty($refund_totals[$key])) || (key_exists($key, $refund_taxes) && !empty(array_sum($refund_taxes[$key])))) {
                            $basket[] = $this->formShippingItemData($shipping_item, $key, $refund_totals[$key], array_sum($refund_taxes[$key]), 1);
                            $totalAmount+= round($refund_totals[$key],2);
                            $totalVatAmount+= round(array_sum($refund_taxes[$key]),2);
                        }

                    }

                }

                $refund_data->setBasket($basket);

                $this->lets_log($refund_data, "refund_v2");
                $result = $this->IB->Order()->refundOrder($refund_data);
                $this->lets_log($result, "refund_v2");
            }
            else{
                $result = $this->IB->Order()->cancelOrder($inv_order_id, "#");
                $this->lets_log($result, "refund_v2");
            }

            /**
             * @var $result RefundOrderResponse
             */
            if($result->isSuccess()) return true;
            else {
                if($this->settings->invoicebox_language == "en") $order->add_order_note(__("Failed to refund: ", "invoicebox") . $result->getResultMessage());
                else $order->add_order_note(__("Не удалось сделать возврат: ", "invoicebox") . $result->getResultMessage());
            }

        }
        catch (\Exception $e){
            $order->add_order_note($e->getMessage());
            $this->lets_log($e->getMessage(), "refund_v2");
            $this->errorMail(sprintf("Refund #%s - %s", $order->get_id(), $e->getMessage()));
            return false;
        }

        return false;

    }

    public function receipt_page(){

        try {
            $order_data = $this->form_transaction_data();
            $this->lets_log($order_data, "create_order_v2");
            $formData = $this->IB->Order()->createOrder($order_data);
            if($this->settings->test_mode) $formData["itransfer_testmode"] = 1;
            $formData["itransfer_cms_name"] = "Woocommerce v2";

            $action = $this->settings->test_env ? 'https://go-dev.invoicebox.ru/module_inbox_auto.u' : 'https://go.invoicebox.ru/module_inbox_auto.u';

            $form = "<form action='{$action}' method='post' name='invoicebox_form'> ";
            foreach ($formData as $key=>$val){
                $form .= "<input type='hidden' name='{$key}' value='{$val}'/>";
            }
            if($this->settings->invoicebox_language === "ru") $form .= "<input type=\"submit\" value=\"Перейти к оплате\"></form>";
            else $form .= "<input type=\"submit\" value=\"Submit\"></form>";

            echo '<p>'.__('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы перейти к оплате.', 'woocommerce').'</p>';
            echo $form;

        } catch (\Throwable $e) {
            $this->lets_log(array(
                'invoicebox_create_order_error' => $e->getMessage(),
                'order_id' => $this->order->get_id()
            ), "create_order_v2");
            $this->errorMail(sprintf("#%s - %s", $this->order->get_id(), $e->getMessage()));
            wc_add_notice($e->getMessage(), 'error');
            echo '<p>'.__('Оплата выбранным способом в данным момент невозможна.', 'woocommerce').'</p>';
        }
    }

    public function push_callback(){
        $this->lets_log("request", "push_v2");
        if(empty($_POST)){
            if(!empty(file_get_contents('php://input'))) $_POST = json_decode(file_get_contents('php://input'), true);
        }

        if(isset( $_GET['participantId'] )){
            $_POST =$_GET;
        }

        if ( isset( $_POST['participantId'] ) || isset( $_GET['participantId'] ) )
        {
            $this->lets_log($_POST, "push_v2");
            @ob_clean();
            ob_start();

            $_POST = stripslashes_deep($_POST);

            // Sign type A
            $sign_strA =
                $_POST["participantId"] .
                $_POST["participantOrderId"] .
                $_POST["ucode"] .
                $_POST["timetype"] .
                $_POST["time"] .
                $_POST["amount"] .
                $_POST["currency"] .
                $_POST["agentName"] .
                $_POST["agentPointName"] .
                $_POST["testMode"] .
                $this->settings->key;

            $sign_crcA = md5( $sign_strA ); //

            if ( $_POST["sign"] != $sign_crcA )
            {
                ob_clean();
                header("HTTP/1.1 200 OK");
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                        "resultCode" => 10,
                        "resultMessage" => "Invalid sign",
                    ]
                );
                $this->lets_log("Invalid sign", "push_v2");
                exit;
            }


            if ( $_POST['participantId'] != $this->settings->shop_id)
            {
                ob_clean();
                header("HTTP/1.1 200 OK");
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                        "resultCode" => 11,
                        "resultMessage" => "Invalid shop id, check WC settings",
                    ]
                );
                $this->lets_log("Invalid shop id, check WC settings", "push_v2");
                exit;
            }

            $participantOrderId = $_POST["participantOrderId"];

            //тестовый заказ
            if($_POST["ucode"] === "00000-00000-00000-00000"){
                ob_clean();
                header("HTTP/1.1 200 OK");
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                        "resultCode" => 0,
                        "resultMessage" => "Success",
                    ]
                );
                $this->lets_log("Success test", "push_v2");
                exit;
            }


            $order = wc_get_order($participantOrderId);
            if ( !$order )
            {
                ob_clean();
                header("HTTP/1.1 200 OK");
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                        "resultCode" => 12,
                        "resultMessage" => "Invalid order, order not found.",
                    ]
                );
                $this->lets_log("Invalid order, order not found.", "push_v2");
                exit;
            }

            $amount	= number_format($order->order_total, 2, '.', '');
            if ( $amount > $_POST["amount"] || $amount < $_POST["amount"] )
            {
                ob_clean();
                header("HTTP/1.1 200 OK");
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                        "resultCode" => 12,
                        "resultMessage" => "Invalid order amount (" . $amount . ")",
                    ]
                );
                $this->lets_log("Invalid order amount", "push_v2");
                exit;
            }

            WC()->cart->empty_cart();

            $order->update_status('processing', __('Платёж успешно завершён', 'woocommerce'));
            $order->add_order_note(__('Платёж успешно завершен.', 'woocommerce'));
            $order->payment_complete();

            ob_clean();
            header("HTTP/1.1 200 OK");
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                    "resultCode" => 0,
                    "resultMessage" => "Success",
                ]
            );
            $this->lets_log("Success", "push_v2");
            exit;
        }
    }
}