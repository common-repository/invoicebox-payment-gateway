<?php

require_once __DIR__ . "/../vendor/autoload.php";
$configFile = __DIR__ . "/configv2.json";

$config = json_decode(file_get_contents($configFile),true);

$IB = new \Invoicebox\Invoicebox(2, $config, false, true);

function createOrder(\Invoicebox\Invoicebox $IB){
    $order = new \Invoicebox\V2\Models\OrderData();
    $order->setMerchantId("131");
    $order->setMerchantCode("78043");
    $order->setOrderId(25);
    $order->setDescription(13123);
    $order->setCurrency("RUB");
    $order->setLanguage("ru");
    $datetime = new \DateTime();
    $datetime->add(new \DateInterval('P180M'));
    $order->setExpirationDate($datetime);
    $basketRaw = [
        [
            "sku" => "01FGY58QR8HVGNBNDCGT4H24TQ",
            "name" => "iPhone 5s",
            "measure" => "шт",
            "measureCode" => "796",
            "originCountry" => "Россия",
            "originCountryCode" => "643",
            "grossWeight" => 1010.55,
            "netWeight" => 1000.66,
            "quantity" => 3,
            "amount" => 123.96,
            "amountWoVat" => 103.3,
            "vat" => 20.66,
            "vatRate" => 16.66,
            "totalAmount" => 371.88,
            "totalVatAmount" => 61.98,
            "vatCode" => "RUS_VAT20",
            "type" => "commodity",
            "paymentType" => "full_prepayment",
            "excise" => 0,
        ],
        [
            "sku" => "01FGY58QR8HVGNBNDCGT4H24TR",
            "name" => "iPhone 5s",
            "measure" => "шт",
            "measureCode" => "796",
            "originCountry" => "Россия",
            "originCountryCode" => "643",
            "grossWeight" => 1010.55,
            "netWeight" => 1000.66,
            "quantity" => 3,
            "amount" => 123.96,
            "amountWoVat" => 103.3,
            "vat" => 20.66,
            "vatRate" => 16.66,
            "totalAmount" => 371.88,
            "totalVatAmount" => 61.98,
            "vatCode" => "RUS_VAT20",
            "type" => "commodity",
            "paymentType" => "full_prepayment",
            "excise" => 0,
        ],
        [
            "sku" => "01FGY58QR8HVGNBNDCGT4H24TS",
            "name" => "iPhone 5s",
            "measure" => "шт",
            "measureCode" => "796",
            "originCountry" => "Россия",
            "originCountryCode" => "643",
            "grossWeight" => 1010.55,
            "netWeight" => 1000.66,
            "quantity" => 3,
            "amount" => 123.96,
            "amountWoVat" => 103.3,
            "vat" => 20.66,
            "vatRate" => 16.66,
            "totalAmount" => 371.88,
            "totalVatAmount" => 61.98,
            "vatCode" => "RUS_VAT20",
            "type" => "commodity",
            "paymentType" => "full_prepayment",
            "excise" => 0,
        ],
        [
            "sku" => "01FGY58QR8HVGNBNDCGT4H24TT",
            "name" => "iPhone 5s",
            "measure" => "шт",
            "measureCode" => "796",
            "originCountry" => "Россия",
            "originCountryCode" => "643",
            "grossWeight" => 1010.55,
            "netWeight" => 1000.66,
            "quantity" => 3,
            "amount" => 123.96,
            "amountWoVat" => 103.3,
            "vat" => 20.66,
            "vatRate" => 16.66,
            "totalAmount" => 371.88,
            "totalVatAmount" => 61.98,
            "vatCode" => "RUS_VAT20",
            "type" => "commodity",
            "paymentType" => "full_prepayment",
            "excise" => 0,
        ]
    ];
    $basket=[];
    $totalAmount = 0;
    $totalVatAmount = 0;
    foreach ($basketRaw as $raw){
        $basketItem = new \Invoicebox\V2\Models\CartItem();
        $basketItem->setSKU($raw["sku"]);
        $basketItem->setName($raw["name"]);
        $basketItem->setMeasure($raw["measure"]);
        $basketItem->setMeasureCode($raw["measureCode"]);
        $basketItem->setOriginCountry($raw["originCountry"]);
        $basketItem->setOriginCountryCode($raw["originCountryCode"]);
        $basketItem->setGrossWeight($raw["grossWeight"]);
        $basketItem->setNetWeight($raw["netWeight"]);
        $basketItem->setQuantity($raw["quantity"]);
        $basketItem->setItemAmount($raw["amount"]);
        $basketItem->setItemAmountWithoutVat($raw["amountWoVat"]);
        $basketItem->setItemVatRate($raw["vatRate"]);
        $basketItem->setItemVat($raw["vat"]);
        $basketItem->setTotalAmount($raw["totalAmount"]);
        $basketItem->setTotalVatAmount($raw["totalVatAmount"]);
        $basketItem->setVatCode($raw["vatCode"]);
        $basketItem->setType($raw["type"]);
        $basketItem->setPaymentType($raw["paymentType"]);
        $basketItem->setExciseSum($raw["excise"]);
        $basket[] = $basketItem;
        $totalAmount+= $raw["totalAmount"];
        $totalVatAmount+= $raw["totalVatAmount"];
    }
    $order->setBasket($basket);
    $order->setTotalAmount($totalAmount);
    $order->setTotalVatAmount($totalVatAmount);

    $customerData = [
        "type" => "private",
        "name" => "Peter Griffin",
        "phone" => "79001112233",
        "email" => "peter.griffin@domain.com",
        "vatNumber" => "",
        "registrationAddress" => "",
    ];

    $customerItem = new \Invoicebox\V2\Models\Customer();
    $customerItem->setName($customerData["name"]);
    $customerItem->setPhone($customerData["phone"]);
    $customerItem->setEmail($customerData["email"]);
    $customerItem->setInn($customerData["vatNumber"]);
    $customerItem->setAddress($customerData["registrationAddress"]);
    $customerItem->setType($customerData["type"]);
    $order->setCustomerData($customerItem);

    $order->setUrls([
        "notificationUrl" => "ts=esr",
        "successUrl" => 123,
        "returnUrl" => null,
    ]);
    $order->setInvoiceSettings(false, []);
    $data = $IB->Order()->createOrder($order);

    $form = "<form action='https://go.invoicebox.ru/module_inbox_auto.u' method='post' name='invoicebox_form'> " .PHP_EOL;
    foreach ($data as $key=>$val){
        $form .= "<label>{$key} <input type='text' name='{$key}' value='{$val}'/></label><br> " . PHP_EOL;
    }
    $form .= "<input type=\"submit\" value=\"Submit\"></form>";
    echo "<pre>";
    var_dump($data);
    echo "</pre>";
    echo $form;
}

function getOrder(\Invoicebox\Invoicebox $IB){
    $result = $IB->Order()->getOrder("78043-37920-89015-18373");
    echo "<pre>";
    var_dump($result);
    echo "</pre>";
}

function getAllOrders(\Invoicebox\Invoicebox $IB){
    $result = $IB->Order()->getOrders(["page" => 100]);
    echo "<pre>";
    var_dump($result);
    echo "</pre>";
}

function cancelOrder(\Invoicebox\Invoicebox $IB, $id="78043-37920-89015-18373"){
    $result = $IB->Order()->cancelOrder($id, "87897");
    var_dump($result);
}

function changeOrder(\Invoicebox\Invoicebox $IB){
    //"017f4bb7-de38-2bf1-26d6-2c102472d67a"
    //"017f4bb7-de2b-bbb6-25f7-2b3efd249462"
    $data = new \Invoicebox\V2\Models\UpdateOrderRequest();
    $data->setDescription("Новое описание");
    $result = $IB->Order()->changeOrder("017f4bb7-de38-2bf1-26d6-2c102472d67a", $data);
    echo "<pre>";
    var_dump($result);
    echo "</pre>";
}

function refundOrder(\Invoicebox\Invoicebox $IB, $id=0){
    //$partial_data = new \Invoicebox\V2\Models\RefundOrderRequest(\Invoicebox\Contracts\Models\RefundOrderRequestInterface::PARTIAL_REFUND, $id,"55555", "55888", 1016.73, 20, [], "sfsfdsf");
    //$result = $IB->Order()->refundOrder($partial_data);

    $penalty_data = new \Invoicebox\V2\Models\RefundOrderRequest(\Invoicebox\Contracts\Models\RefundOrderRequestInterface::PENALTY_REFUND, $id,"25", "55888", null, null, null, "sfsdfsdfsdf", 50);
    $result = $IB->Order()->refundOrder($penalty_data);
    var_dump($result);
}

function getRefunds(\Invoicebox\Invoicebox $IB){
    $result = $IB->Order()->getRefunds(["id" => "017f025f-2f76-364e-1fbb-66c9c54cd09a"]);
    echo "<pre>";
    var_dump($result);
    echo "</pre>";
}



try{
//createOrder($IB);
//getOrder($IB);
//getAllOrders($IB);
//cancelOrder($IB);
//changeOrder($IB);
refundOrder($IB, "78043-77057-87650-92070");
//getRefunds($IB);
}
catch (\Throwable $e){
    echo "<pre>";
    var_dump($e->getMessage());
    print_r($e->getTrace());
    print_r($e->getCode());
    echo "</pre>";
    throw $e;
}