<?php
namespace InvoiceboxPaymentGateway\Includes;

use Invoicebox\Contracts\Models\CartItemInterface;
use Invoicebox\Contracts\Models\CustomerInterface;
use Invoicebox\V3\Models\CartItem;

abstract class InvoiceboxHelper{
    protected $IB;
    protected $order;
    protected $settings;

    /**
     * InvoiceboxHelper constructor.
     * @param \Invoicebox\Invoicebox|null $IB
     * @param \WC_Order|null $order
     * @param InvoiceboxSettings $settings
     */
    public function __construct($IB, $order, InvoiceboxSettings $settings){
        $this->IB = $IB;
        $this->order = $order;
        $this->settings = $settings;
    }

    abstract function process_payment();

    abstract function process_refund($order_id, $amount = null, $reason = "");

    public function set_order($order){
        $this->order = $order;
    }

    /**
     * @return \Invoicebox\Contracts\Models\OrderInterface
     * @throws \Invoicebox\Exceptions\NotValidCartItemData
     * @throws \Invoicebox\Exceptions\NotValidCustomerData
     * @throws \Exception
     */
    public function form_transaction_data()
    {
        $order = $this->order;
        $orderData = $this->IB->createOrderData();
        $orderData->setMerchantId($this->settings->shop_id);
        $orderData->setMerchantCode($this->settings->shop_code);
        $orderData->setOrderId($order->get_id());
        $orderData->setApiVersion_3($this->settings->apiVersion3);
        $orderData->setCurrency($order->get_currency());
        $orderData->setLanguage($this->settings->invoicebox_language);
//        $expires = get_option( 'woocommerce_hold_stock_minutes', 3600 );
        $datetime = new \DateTime('NOW');


        if ($this->settings->customerType === CustomerInterface::LEGAL_PERSON) {
            $datetime->add(new \DateInterval('P' . abs($this->settings->legalCustomerPaymentType) .'D'));
        } else {
            $datetime->add(new \DateInterval('PT' . abs($this->settings->customerPaymentType) . 'H'));
        }

        $orderData->setExpirationDate($datetime);

        if ( $this->settings->invoicebox_language == "ru" )
        {
            $orderData->setDescription("Оплата заказа #" . $this->order->get_id() . " на сайте " . $_SERVER["HTTP_HOST"]);
        } else
        {
            $orderData->setDescription("Order #" . $this->order->get_id() . " at " . $_SERVER["HTTP_HOST"]);
        };


        $basket=[];
        $totalAmount = 0;
        $totalVatAmount = 0;
        /**
         * @var $item \WC_Order_Item_Product
         */
        foreach ($order->get_items() as $key => $item){
            $basketItem = $this->formItemData($item, $key);
            $basket[] = $basketItem;
            $totalAmount+= round(floatval($item->get_total()) + floatval($item->get_total_tax()),2);
            $totalVatAmount+= round($basketItem->getTotalVatAmount(),2);
        }


        if ( $order->get_shipping_total() > 0 )
        {
            /**
             * @var $shipping_item \WC_Order_Item_Shipping
             */
            foreach ($order->get_items("shipping") as $key => $shipping_item){
                $basketItem = $this->formShippingItemData($shipping_item, $key);
                $basket[] = $basketItem;
                $totalAmount+= round(floatval($shipping_item->get_total()) + floatval($shipping_item->get_total_tax()),2);
                $totalVatAmount+= round($basketItem->getTotalVatAmount(),2);
            }

        }

        $orderData->setBasket($basket);
        $orderData->setTotalAmount($totalAmount);
        $orderData->setTotalVatAmount($totalVatAmount);

        $customerItem = $this->IB->getInstance("Models\Customer");
        $customerItem->setName($order->get_formatted_billing_full_name());
        $phone = preg_replace("/[^0-9]/", '', $order->get_billing_phone());
        $customerItem->setPhone($phone);
        $customerItem->setEmail($order->get_billing_email());
        if($this->settings->customerType === CustomerInterface::LEGAL_PERSON){
            if(isset($_POST['invoicebox_legal_inn'])) $customerItem->setInn(esc_attr($_POST['invoicebox_legal_inn']));
            if(isset($_POST['invoicebox_legal_address'])) $customerItem->setAddress(esc_attr($_POST['invoicebox_legal_address']));
			if(isset($_POST['invoicebox_legal_kpp'])) $customerItem->setKpp(esc_attr($_POST['invoicebox_legal_kpp']));
        }
        else $customerItem->setAddress($order->get_formatted_billing_address());

        $customerItem->setType($this->settings->customerType);
        $orderData->setCustomerData($customerItem);

        $orderData->setUrls([
            "notificationUrl" => $this->settings->notificationUrl,
            "successUrl" => $this->settings->returnUrl,
            "itransfer_url_returnsuccess" => $this->settings->returnUrl,
            "cancelUrl" => $order->get_cancel_order_url(),
            "returnUrl" => $this->settings->returnUrl,
            "itransfer_url_return" => $this->settings->returnUrl,
        ]);
        $orderData->setInvoiceSettings(false, []);

        return $orderData;

    }

    /**
     * @param object $item \WC_Order_Item
     * @param string $key
     * @param null|float $refundAmount
     * @param null|float $refundVat
     * @param null|int $refundCount
     * @return CartItemInterface
     * @throws \Exception
     */
    protected function formItemData($item, string $key, $refundAmount=null, $refundVat=null, $refundCount=null){
        $wc_tax = new \WC_Tax();

        $product = wc_get_product($item['variation_id'] ? $item['variation_id'] : $item['product_id']);

        $measure = $this->getDefaultMeasure();
        if(!empty($this->settings->measureField)){
            if($productMeasure = get_post_meta($product->get_id(), $this->settings->measureField, true)){
                $measure[0] = $productMeasure;
            }
        }
        elseif (!empty($this->settings->measure)) $measure[0] = $this->settings->measure;

        if(!empty($this->settings->measureCodeField)){
            if($productMeasureCode = get_post_meta($product->get_id(), $this->settings->measureCodeField, true)){
                $measure[1] = $productMeasureCode;
            }
        }
        elseif (!empty($this->settings->measureCode)) $measure[1] = $this->settings->measureCode;


        $quantity = $item->get_quantity();
        if(!is_null($refundCount)) $quantity = $refundCount;
        $amount = round($item->get_total() / $quantity + $item->get_total_tax() / $quantity, 2);
        if(!is_null($refundAmount)) $amount = $refundAmount / $quantity + floatval($refundVat) / $quantity;

        /**
         * @var $basket_item CartItemInterface
         */
        $basket_item = $this->IB->getInstance("Models\CartItem");
        $basket_item->setItemId($key);
        $basket_item->setSKU($product->get_sku());
        $basket_item->setName($item["name"]);
        $basket_item->setMeasure($measure[0], $measure[2]);
        $basket_item->setMeasureCode($measure[1], $measure[2]);
        if($product->has_weight())  $basket_item->setGrossWeight($product->get_weight() * $item["quantity"]);
        $basket_item->setQuantity($quantity);
        $basket_item->setItemAmount($amount);

        $basket_item->setVatCode($this->settings->vatCode);
        $basket_item->setExciseSum(floatval($this->settings->excise));

        if(!empty($this->settings->productTypeField)){
            if($productType = get_post_meta($product->get_id(), $this->settings->productTypeField, true)){
                $basket_item->setType($productType);
            }
        }
        else{
            $basket_item->setType($this->settings->productType);
        }

        $basket_item->setPaymentType($this->settings->paymentType);

        if(!empty($this->settings->originCountryCodeField)){
            if($originCountryCode = get_post_meta($product->get_id(), $this->settings->originCountryCodeField, true)){
                $basket_item->setOriginCountryCode($originCountryCode);
            }
        }
        elseif(!empty($this->settings->originCountryCode)) $basket_item->setOriginCountryCode($this->settings->originCountryCode);

        if(!empty($this->settings->originCountryField)){
            if($originCountry = get_post_meta($product->get_id(), $this->settings->originCountryField, true)){
                $basket_item->setOriginCountry($originCountry);
            }
        }
        elseif(!empty($this->settings->originCountry)) $basket_item->setOriginCountry($this->settings->originCountry);

        if(!empty($this->settings->metaField)) {
            $meta = json_decode(wc_get_order_item_meta($key, $this->settings->metaField, true), true);
            if(!empty($meta)) $basket_item->setMetaData($meta);
        }

        if(wc_tax_enabled()){
            $taxes_rates = $wc_tax->get_rates($product->get_tax_class());
            $rates = array_shift($taxes_rates);
            $item_rate = round(array_shift($rates),2);

            $vatCode = CartItemInterface::VAT_CODE_RUS_VAT0;

            switch ($item_rate){
                case 0: break;
                case 10: $vatCode = CartItemInterface::VAT_CODE_RUS_VAT10; break;
                case 20: $vatCode = CartItemInterface::VAT_CODE_RUS_VAT20; break;
                default: throw new \Exception("Неверная налоговая ставка для товара " . $product->get_id());
            }

            $basket_item->setItemVatRate($item_rate);
            $wc_vat = floatval($item->get_total_tax()) / $quantity;
            $this->lets_log([$amount, $wc_vat]);
            $vat =  ($amount) * $item_rate / (100 + $item_rate);
            $this->lets_log([$vat]);
            if(!is_null($refundVat)) $vat = round($refundVat / $quantity,2);
            $basket_item->setItemVat(round($vat, 2));
            $basket_item->setItemAmountWithoutVat(round(($amount - $vat),2));

            $basket_item->setVatCode($vatCode);
            $basket_item->setTotalVatAmount(round((floatval($item->get_total()) + floatval($item->get_total_tax()))* $item_rate / (100 + $item_rate),2));
        }
        else{
            $vatRate = 0;
            if($this->settings->vatCode == CartItemInterface::VAT_CODE_RUS_VAT10) $vatRate = 10;
            if($this->settings->vatCode == CartItemInterface::VAT_CODE_RUS_VAT20) $vatRate = 20;
            $basket_item->setItemVatRate($vatRate);
            if($vatRate == 0) $vat = 0;
            else $vat = $amount * $vatRate / (100 + $vatRate);
            if(!is_null($refundVat)) $vat = $refundVat / $quantity;
            $basket_item->setItemVat(round($vat, 2));
            $basket_item->setItemAmountWithoutVat(round($amount-$vat,2));

            $basket_item->setVatCode($this->settings->vatCode);
            $basket_item->setTotalVatAmount(round(floatval($item->get_total())* $vatRate / (100 + $vatRate),2));
        }

        $basket_item->setTotalAmount(round(floatval($item->get_total()) + floatval($item->get_total_tax()),2));


        if(!empty($refundAmount)){
            $basket_item->setTotalAmount(round(($refundAmount+$refundVat)*$refundCount,2));
            $basket_item->setTotalVatAmount(round($refundVat*$refundCount,2));
        }

       return $basket_item;
    }

    /**
     * @param $shipping_item \WC_Order_Item_Shipping
     * @param string $key
     * @param null $refundAmount
     * @param null $refundVat
     * @return CartItemInterface
     * @throws \Exception
     */
    protected function formShippingItemData($shipping_item, string $key, $refundAmount=null, $refundVat=null){

        $wc_tax = new \WC_Tax();

        $shipping_amount = round(floatval($this->order->get_shipping_total()) / $shipping_item->get_quantity() + floatval($this->order->get_shipping_tax()) / $shipping_item->get_quantity(), 2);

        if(!is_null($refundAmount)) $shipping_amount = $refundAmount / $shipping_item->get_quantity() + floatval($refundVat) / $shipping_item->get_quantity();

        $basket_item = $this->IB->getInstance("Models\CartItem");
        $basket_item->setSKU("shipping_" . $shipping_item->get_method_id());
        $basket_item->setName($shipping_item->get_name());
        $basket_item->setMeasure($this->settings->measure);
        $basket_item->setMeasureCode($this->settings->measureCode);
        $basket_item->setQuantity($shipping_item->get_quantity());
        $basket_item->setItemAmount($shipping_amount);

        $basket_item->setVatCode($this->settings->vatCode);
        $basket_item->setType(CartItemInterface::SERVICE_TYPE);
        $basket_item->setPaymentType($this->settings->paymentType);

        if(wc_tax_enabled()){
            $taxes_rates = $wc_tax->get_rates($shipping_item->get_tax_class());
            $rates = array_shift($taxes_rates);
            $item_rate = round(array_shift($rates),2);

            $vatCode = CartItemInterface::VAT_CODE_RUS_VAT0;

            switch ($item_rate){
                case 0: break;
                case 10: $vatCode = CartItemInterface::VAT_CODE_RUS_VAT10; break;
                case 20: $vatCode = CartItemInterface::VAT_CODE_RUS_VAT20; break;
                default: throw new \Exception("Неверная налоговая ставка для доставки " . $shipping_item->get_method_id());
            }

            $basket_item->setItemVatRate($item_rate);
            $vat = round($shipping_item->get_total_tax() / $shipping_item->get_quantity(),2);
            if(!is_null($refundVat)) $vat = round($refundVat / $shipping_item->get_quantity(),2);

            if(!$vat) $vatCode = CartItemInterface::VAT_CODE_RUS_VAT0;
            $basket_item->setItemVat($vat);
            $basket_item->setItemAmountWithoutVat(round($shipping_amount - $vat,2));

            $basket_item->setVatCode($vatCode);
            $basket_item->setTotalVatAmount(round(floatval($shipping_item->get_total_tax()),2));
        }
        else{
            $vatRate = 0;
            if($this->settings->vatCode == CartItemInterface::VAT_CODE_RUS_VAT10) $vatRate = 10;
            if($this->settings->vatCode == CartItemInterface::VAT_CODE_RUS_VAT20) $vatRate = 20;
            $basket_item->setItemVatRate($vatRate);
            if($vatRate == 0) $vat = 0;
            else $vat = $shipping_amount * $vatRate / (100 + $vatRate);
            if(!is_null($refundVat)) $vat = $refundVat / $shipping_item->get_quantity();
            $basket_item->setItemVat(round($vat,2));
            $basket_item->setItemAmountWithoutVat(round($shipping_amount-$vat,2));

            $basket_item->setVatCode($this->settings->vatCode);

            $basket_item->setTotalVatAmount(round(floatval($shipping_item->get_total())* $vatRate / (100 + $vatRate),2));
        }

        $basket_item->setTotalAmount(round(floatval($shipping_item->get_total()) + floatval($shipping_item->get_total_tax()),2));

        if(!empty($refundAmount)){
            $basket_item->setTotalAmount(round($refundAmount+$refundVat,2));
            $basket_item->setTotalVatAmount(round($refundVat,2));
        }
        return $basket_item;
    }

    /**
     * @param $order_id
     * @param null $amount
     * @param string $reason
     * @return bool|\Invoicebox\Contracts\Models\RefundOrderRequestInterface
     */
    public function formRefundData($order_id, $amount = null, $reason = ""){
        /**
         * @var $refund_data \Invoicebox\Contracts\Models\RefundOrderRequestInterface
         */

        $inv_order_id = get_post_meta($order_id, "invoicebox_order_id", true);
        if(!$inv_order_id) return false;

        if ($amount && $this->order->get_total() !== $amount){
            $refund_data = $this->IB->getInstance("Models\RefundOrderRequest");
            $refund_data->setAmount($amount);
            $vatAmount = 0;
            if(isset($_POST["line_item_tax_totals"])){
                $taxTotals = json_decode($_POST["line_item_tax_totals"], true);
                if($taxTotals){
                    foreach ($taxTotals as $taxTotal){
                        foreach ($taxTotals as $line) $vatAmount += floatval($line);
                    }
                }
            }
            $refund_data->setVatAmount($vatAmount);
            $refund_data->setMerchantId($order_id);
            $refund_data->setOrderId($inv_order_id, true);
            $refund_data->setRefundId("#" . strval(time()));
            if(empty($reason)) switch ($this->settings->invoicebox_language){
                case "ru": $reason = "Возврат по заказу #" . $order_id . ", сумма - ". $amount . get_woocommerce_currency();break;
                default: $reason = "Refund for order #" . $order_id . ", amount - ". $amount . get_woocommerce_currency();
            }
            $refund_data->setDescription($reason);
            return $refund_data;
        }
        return false;

    }

    protected function lets_log($data, $prefix=false, $mail=false)
    {
        if ($this->settings->logging == 'yes') {
            if($prefix) wc_get_logger()->debug(print_r($data, true), ['source' => $prefix ."_" . $this->settings->gateway_id]);
            else wc_get_logger()->debug(print_r($data, true), ['source' => $this->settings->gateway_id]);
        }
    }

    protected function errorMail($message){
        if($this->settings->email) {
            if($this->settings->invoicebox_language == "ru") wp_mail($this->settings->email, sprintf("Ошибка в плагине Invoicebox на сайте %s", $_SERVER["HTTP_HOST"]), $message);
            else wp_mail($this->settings->email, sprintf("Error in the Invoicebox plugin on the site %s", $_SERVER["HTTP_HOST"]), $message);
}
    }

    protected function getDefaultMeasure(){
        if ( $this->settings->invoicebox_language == "ru" )
        {
            $itransfer_item_measure = "шт.";
            $isOkei = true;
            $code = "796";
        } else
        {
            $itransfer_item_measure = "itm";
            $isOkei = false;
            $code = "796";
        };

        return [$itransfer_item_measure, $code, $isOkei];
    }
}