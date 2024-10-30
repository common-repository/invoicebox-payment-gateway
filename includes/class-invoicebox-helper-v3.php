<?php

namespace InvoiceboxPaymentGateway\Includes;

use Invoicebox\V3\Models\OrderResponse;
use Invoicebox\V3\Models\RefundOrderResponse;

class Invoicebox_Helper_V3 extends InvoiceboxHelper
{

    public function process_payment()
    {
        try {
            $order_data = $this->form_transaction_data();
            $this->lets_log($order_data, "create_order_v3");
            $result = $this->IB->Order()->createOrder($order_data);
            var_dump($result);

            update_post_meta($this->order->get_id(), "invoicebox_order_id", $result->getUuid());
            update_post_meta($this->order->get_id(), "invoicebox_parent_id", $result->getParentId());
            update_post_meta($this->order->get_id(), "invoicebox_payment_link", $result->getPaymentLink());

            $this->lets_log($result, "create_order_v3");
            if ($result->getPaymentLink()) {
                if($this->settings->invoicebox_language == "ru") {
                    $this->order->add_order_note(sprintf( " Заказ будет оплачен через %s. Номер заказа в системе %s. Номер счёта %s. Ссылка для оплаты заказа %s", $this->settings->gateway_id, $result->getUuid(), $result->getParentId(), $result->getPaymentLink()));
                }
                else {
                    sprintf("The order will be paid via %s. Order number in the system %s. Invoice number %s. Link to pay for the order %s", $this->settings->gateway_id, $result->getUuid(), $result->getParentId(), $result->getPaymentLink());
                }

                return array(
                    'result' => 'success',
                    'redirect' => $result->getPaymentLink(),
                );
            } else {

                $this->order->update_status("failed", "Failed");
                //$failed_page = $this->order->get_checkout_payment_url();
                return array(
                    'result' => 'failure',
                    //'redirect'	=> add_query_arg('order', $this->order->get_id(), add_query_arg('key', $this->order->get_order_key(), get_permalink(woocommerce_get_page_id('pay'))))
                );
            }


        } catch (\Exception $e) {
            $this->lets_log(array(
                'invoicebox_create_order_error' => $e->getMessage(),
                'order_id' => $this->order->get_id()
            ), "create_order_v3");
            $this->errorMail(sprintf("#%s - %s", $this->order->get_id(), $e->getMessage()));
            wc_add_notice($e->getMessage(), 'error');
            return ['result' => 'failure'];
        }
    }
    public function receipt_page(){}

    public function process_refund($order_id, $amount = null, $reason = "")
    {
        $order = wc_get_order($order_id);
        if (!$order) return false;
        $this->order = $order;
        try {

            $inv_order_id = get_post_meta($order_id, "invoicebox_order_id", true);
            if (!$inv_order_id) return false;

            $checkStatusRequest = $this->IB->Order()->getOrder($inv_order_id);
            if ($checkStatusRequest->get("paidAt")) {
                $this->lets_log("refund order " . $order_id, "refund_v3");
                $this->lets_log($_POST, "refund_v3");
                $refund_data = $this->formRefundData($order_id, floatval($amount), $reason);

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

                $refund_data->setVatAmount($totalVatAmount);


                //$basket = $this->IB->Order()->getRefundOrderBasket($inv_order_id);
                $this->lets_log($basket, "refund_v3");
                $refund_data->setBasket($basket);
                $this->lets_log($refund_data->formData(), "refund_v3");
                $result = $this->IB->Order()->refundOrder($refund_data);
                if($result->isSuccess()) return true;
                $this->lets_log($result, "refund_v3");
            } else {
                $order->add_order_note(__("Заказ не оплачен, возврат осуществить нельзя. ", "invoicebox"));
                return false;
            }


        } catch (\Exception $e) {
            if ($this->settings->invoicebox_language == "en") $order->add_order_note(__("Failed to refund: ", "invoicebox") . $e->getMessage());
            else $order->add_order_note(__("Не удалось сделать возврат: ", "invoicebox") . $e->getMessage());
            $this->errorMail(sprintf("Refund #%s - %s", $order->get_id(), $e->getMessage()));
            $this->lets_log($e->getMessage(), "refund_v3");
            return false;
        }

        return false;

    }

    public function push_callback()
    {
        @ob_clean();
        ob_start();

        try {
            if(empty($_POST)){
                if(!empty(file_get_contents('php://input'))) $_POST = json_decode(file_get_contents('php://input'), true);
            }

            $this->lets_log($_POST, "push_v3");
            $this->lets_log(file_get_contents('php://input'), "push_v3");
            $this->lets_log($_REQUEST, "push_v3");
            $this->lets_log(getallheaders(), "push_v3");

            if (isset($_POST['merchantOrderId']) || isset($_GET['merchantOrderId'])) {

                $_POST = stripslashes_deep($_POST);

                $headers = getallheaders();
                if(isset($headers["X-Signature"])) $x = $headers["X-Signature"];
                else $x = null;

                $sign_crcA = hash_hmac("sha1", file_get_contents('php://input'), $this->settings->key);

                if (is_null($x) || ($x != $sign_crcA)) {
                    ob_clean();
                    header("HTTP/1.1 200 OK");
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                            "status" => "error",
                            "code" => "signature_error",
                            "message" => "Ошибка проверки подписи запроса"
                        ]
                    );
                    $this->lets_log("signature_error", "push_v3");
                    exit;
                };

                if ($_POST['merchantId'] != $this->settings->shop_id) {
                    ob_clean();
                    header("HTTP/1.1 200 OK");
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                            "status" => "error",
                            "code" => "order_not_found",
                            "message" => "Invalid shop id, check WC settings"
                        ]
                    );
                    $this->lets_log("Invalid shop id", "push_v3");
                    exit;
                }

                //тестовый заказ
                if($_POST["id"] === "ffffffff-ffff-ffff-ffff-ffffffffffff"){
                    ob_clean();
                    header("HTTP/1.1 200 OK");
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                            "status" => "success"
                        ]
                    );
                    $this->lets_log("success test", "push_v3");
                    exit;
                }

                $orders = wc_get_orders( array(
                    'limit'        => 1, // Query all orders
                    'orderby'      => 'date',
                    'order'        => 'DESC',
                    'meta_key'     => 'invoicebox_order_id', // The postmeta key field
                    'meta_compare' => $_POST["id"], // The comparison argument
                ));
                $order = null;
                if(!empty($orders)) $order = array_shift($orders);
                if (!$order) {
                    ob_clean();
                    header("HTTP/1.1 200 OK");
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                            "status" => "error",
                            "code" => "order_not_found",
                            "message" => "Заказ не найден"
                        ]
                    );
                    $this->lets_log("order_not_found", "push_v3");
                    exit;
                }

                $amount = number_format($order->get_total(), 2, '.', '');
                if ($amount > $_POST["amount"] || $amount < $_POST["amount"]) {
                    ob_clean();
                    header("HTTP/1.1 200 OK");
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                            "status" => "error",
                            "code" => "order_wrong_amount",
                            "message" => "Сумма заказа должна быть " . $order->get_total()
                        ]
                    );
                    $this->lets_log("order_wrong_amount", "push_v3");
                    exit;
                }

                if ($_POST["status"] == "completed") {
                    $status = 'processing';
                    if(!empty($this->settings->orderStatus)){
                        $status = $this->settings->orderStatus;
                    }
                    $order->update_status($status, __('Платёж успешно завершён', 'woocommerce'));
                    $order->add_order_note(__('Платёж успешно завершен.', 'woocommerce'));
                    $order->payment_complete($_POST["id"]);
                    ob_clean();
                    header("HTTP/1.1 200 OK");
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                            "status" => "success"
                        ]
                    );
                    $this->lets_log("success", "push_v3");
                    exit;
                } elseif ($_POST["status"] == "cancelled"){
                    $order->update_status("cancelled", __('Уведомление платежной системы', 'woocommerce'));
                    $this->lets_log("status", "push_v3");
                    exit;
                }
                elseif ($_POST["status"] == "failed"){
                    $order->update_status("failed", __('Уведомление платежной системы', 'woocommerce'));
                    $this->lets_log("status", "push_v3");
                    exit;
                }

            } else {
                if (!isset($_POST["merchantId"]) || empty($_POST["merchantId"])) {
                    ob_clean();
                    header("HTTP/1.1 200 OK");
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                            "status" => "error",
                            "code" => "order_not_found",
                            "message" => "Заказ не найден"
                        ]
                    );
                    $this->lets_log("order_not_found", "push_v3");
                    exit;
                }
            }
        } catch (\Exception $e) {
            ob_clean();
            header("HTTP/1.1 200 OK");
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                    "status" => "error",
                    "code" => "out_of_service",
                    "message" => ""
                ]
            );
            $this->lets_log("out_of_service", "push_v3");

            exit;
        }
    }
}