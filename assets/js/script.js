jQuery(document).ready(function ($){

    if($("input[name='billing_phone']").length>0){
        var element = document.querySelector("input[name='billing_phone']");
        var maskOptions = {
            mask: '+{7}(000)000-00-00'
        };
        var mask = IMask(element, maskOptions);
    }
});