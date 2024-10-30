<?php

namespace InvoiceboxPaymentGateway\Includes;

use InvoiceboxPaymentGateway\Includes\WC_Invoicebox_Abstract_Gateway;

use Invoicebox\Contracts\Models\CustomerInterface;

if ( !class_exists('WC_Payment_Gateway') )
{
    return;
}
if ( class_exists('WC_Invoicebox_Gateway') )
{
    return;
}

class WC_Invoicebox_Gateway extends WC_Invoicebox_Abstract_Gateway {

    public function __construct() {

        $this->id =  "invoicebox";

        $this->method_title         = esc_html__( 'Invoicebox Payment', 'invoicebox' );
        $this->method_description   = esc_html__( 'Enable your customer to pay for your products through ', 'invoicebox' ) . $this->method_title;

        parent::__construct();

        $this->invoiceboxSettings->customerType = CustomerInterface::PRIVATE_PERSON;
    }

    public function process_payment( $order_id ) {
      parent::process_payment($order_id);
      return $this->helper->process_payment();
    }

}

