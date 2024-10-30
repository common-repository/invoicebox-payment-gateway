<?php

namespace InvoiceboxPaymentGateway\Includes;

use Invoicebox\Contracts\Models\CustomerInterface;

if ( !class_exists('WC_Payment_Gateway') )
{
    return;
}
if ( class_exists('WC_Payment_Legal_Gateway') )
{
    return;
}


class WC_Invoicebox_Legal_Gateway extends WC_Invoicebox_Abstract_Gateway {

    public function __construct() {

        $this->id =  "invoicebox_legal";

        parent::__construct();

        $this->method_title         = esc_html__( 'Invoicebox Legal Payment', 'invoicebox' );
        $this->method_description   = esc_html__( 'Enable your legal customer to pay for your products through ', 'invoicebox' ) . $this->method_title;

        $this->invoiceboxSettings->customerType = CustomerInterface::LEGAL_PERSON;
    }


    public function process_payment( $order_id ) {
        parent::process_payment($order_id);
        return $this->helper->process_payment();
    }

    public function payment_fields() {
        if ( $this->description ) {
            echo wpautop( wp_kses_post( $this->description ) );
        }
        include \InvoiceboxPaymentGateway\Main::$plugin_path . 'template-parts/checkout-form-fields.php';
    }

    public function validate_fields(){
        if( empty( $_POST[ esc_attr( $this->id ) . '_inn' ]) ) {
            wc_add_notice( __( 'ИНН является обязательным полем', "invoicebox" ), 'error' );
            return false;
        }
        if( empty( $_POST[ esc_attr( $this->id ) . '_kpp' ]) ) {
            wc_add_notice( __( 'КПП является обязательным полем', "invoicebox" ), 'error' );
            return false;
        }
        if( empty( $_POST[ esc_attr( $this->id ) . '_address' ]) ) {
            wc_add_notice( __( 'Юридический адрес является обязательным полем', "invoicebox" ), 'error' );
            return false;
        }
        return true;
    }
}