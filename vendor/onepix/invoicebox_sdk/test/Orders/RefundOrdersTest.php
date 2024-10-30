<?php

use PHPUnit\Framework\TestCase;

class RefundOrdersTest extends TestCase
{
    private $IB;
    private $config;
    private $orderId;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $configFile = $configFile = __DIR__ . "/../config.json";
        $this->config = json_decode(file_get_contents($configFile),true);
        $this->IB = new \Invoicebox\Invoicebox(3, $this->config, false, true);
    }

    public function testGetRefundByID(){
        $result = $this->IB->Order()->getRefunds(["id" => "017f025f-2f76-364e-1fbb-66c9c54cd09a"]);
        $this->assertInstanceOf(\Invoicebox\Contracts\Models\RefundsOrdersResponseInterface::class, $result);
    }

    public function testGetAllRefunds(){
        $result = $this->IB->Order()->getRefunds();
        $this->assertInstanceOf(\Invoicebox\Contracts\Models\RefundsOrdersResponseInterface::class, $result);
    }

    public function testRefundOrder(){
        $this->createOrder();
        $basket = $this->IB->Order()->getRefundOrderBasket($this->orderId);
        $data = new \Invoicebox\V3\Models\RefundOrderRequest(\Invoicebox\Contracts\Models\RefundOrderRequestInterface::PARTIAL_REFUND, $this->orderId, "25", null, 6100.37, 1016.73, null, "тестовый рефанд");
        $data->setBasketRow($basket);
        $result = $this->IB->Order()->refundOrder($data);
        $this->assertInstanceOf(\Invoicebox\Contracts\Models\RefundOrderResponseInterface::class, $result);
    }

    public function createOrder(){
        $order = new \Invoicebox\V3\Models\OrderData();
        $order->setMerchantId($this->config["shop_id"]);
        $order->setOrderId(25);
        $order->setDescription(13123);
        $order->setCurrency("RUB");
        $order->setLanguage("ru");
        $datetime = new \DateTime();
        $datetime->add(new \DateInterval('P180M'));
        $order->setExpirationDate($datetime);
        $basketRaw = $this->getValidBasket();

        $basket=[];
        $totalAmount = 0;
        $totalVatAmount = 0;
        foreach ($basketRaw as $raw){
            $basketItem = new \Invoicebox\V3\Models\CartItem();
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

        $customerData = $this->getValidCustomerData();
        $customerItem = new \Invoicebox\V3\Models\Customer();
        $customerItem->setName($customerData["name"]);
        $customerItem->setPhone($customerData["phone"]);
        $customerItem->setEmail($customerData["email"]);
        $customerItem->setInn($customerData["vatNumber"]);
        $customerItem->setAddress($customerData["registrationAddress"]);
        $customerItem->setType($customerData["type"]);
        $order->setCustomerData($customerItem);

        $order->setUrls([
            "notificationUrl" => "notificationUrl",
            "successUrl" => "successUrl",
            "returnUrl" => null,
        ]);
        $order->setInvoiceSettings(false, []);

        $result = $this->IB->Order()->createOrder($order);

        $this->orderId = $result->getUuid();
    }

    private function getValidBasket(){
        return [
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
                "totalAmount" => 371.88,
                "totalVatAmount" => 61.98,
                "vatCode" => "RUS_VAT20",
                "type" => "commodity",
                "paymentType" => "full_prepayment",
                "excise" => 0,
            ]
        ];

    }

    private function getValidCustomerData(){
        return [
            "type" => "private",
            "name" => "Test Name",
            "phone" => "79999999999",
            "email" => "test@test.com",
            "vatNumber" => "",
            "registrationAddress" => "",
        ];
    }
}