jQuery(document).ready(function ($){

    let payment_ids = ["invoicebox", "invoicebox_legal"];
    let fields = [
        "shop_id",
        "shop_code",
        "token",
        "key",
        "user",
        "password",
    ];


    payment_ids.forEach(function (payment_id, index, array){
        showSettings(payment_id);
    });


    function showSettings(payment_id){

            let apiV = jQuery('#woocommerce_' + payment_id + '_apiversion').val();

            let notApiV = "";
            if(!apiV) {
                apiV = "2";
                notApiV = "3";
            }
            else {
                if(apiV == "2") notApiV = "3";
                else  notApiV = "2";
            }
        woocommerce_invoicebox_password_v2
        console.log(apiV)
            if(apiV === "2" || apiV === 2) {
                jQuery("#woocommerce_invoicebox_apiversion_v3").parents('tr').hide();
            } else {
                jQuery("#woocommerce_invoicebox_apiversion_v3").parents('tr').show();
            }
            if(jQuery('#woocommerce_' + payment_id + '_testmode').prop('checked')) {

               fields.forEach(function (value, index, array) {
                   if(jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + notApiV).length>0) jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + notApiV).parents('tr').hide();
                   if(jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + apiV).length>0) jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + apiV).parents('tr').hide();

                   if(jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + notApiV + '_test').length>0) jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + notApiV + '_test').parents('tr').hide();
                   if(jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + apiV + '_test').length>0) jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + apiV + '_test').parents('tr').show();


               });

            } else {

                fields.forEach(function (value, index, array) {
                    if(jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + notApiV).length>0) jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + notApiV).parents('tr').hide();
                    if(jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + apiV).length>0) jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + apiV).parents('tr').show();

                    if(jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + notApiV + '_test').length>0) jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + notApiV + '_test').parents('tr').hide();
                    if(jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + apiV + '_test').length>0) jQuery('#woocommerce_' + payment_id + '_' + value + '_v' + apiV + '_test').parents('tr').hide();
                });

            }

    }

    payment_ids.forEach(function (payment_id, index, array){
        jQuery('#woocommerce_' + payment_id + '_testmode').on('click', () => {showSettings(payment_id)});
        jQuery('#woocommerce_' + payment_id + '_apiversion').on('change', () => {showSettings(payment_id)});
    });
});