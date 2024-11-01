
var $ = jQuery.noConflict();

$(document).ready(function () {

    $("#billing_love_code").val("");            // 捐贈碼
    $("#billing_customer_identifier").val("");      // 統一編號
    $("#billing_carruer_num").val("");              // 載具編號
    $("#billing_carruer_type").val("1");            // 載具類別
    $("#billing_invoice_type").val("p");            // 發票開立類型

    $("#billing_customer_identifier_field").slideUp();
    $("#billing_love_code_field").slideUp();
    $("#billing_carruer_num_field").slideUp();
    $("#billing_carruer_type_field").slideDown();

    $("#billing_invoice_type").change(function () {

        invoice_type = $("#billing_invoice_type").val();

        if (invoice_type == 'p') {

            $("#billing_customer_identifier_field").slideUp();
            $("#billing_love_code_field").slideUp();
            $("#billing_carruer_type_field").slideDown();

            $("#billing_customer_identifier").val("");
            $("#billing_love_code").val("");

        } else if (invoice_type == 'c') {

            $("#billing_customer_identifier_field").slideDown();
            $("#billing_love_code_field").slideUp();
            $("#billing_carruer_num_field").slideUp();
            $("#billing_carruer_type_field").slideUp();

            $("#billing_carruer_num").val("");
            $("#billing_love_code").val("");
            $("#billing_carruer_type").val("0");

        } else if (invoice_type == 'd') {

            $("#billing_customer_identifier_field").slideUp();
            $("#billing_love_code_field").slideDown();
            $("#billing_carruer_num_field").slideUp();
            $("#billing_carruer_type_field").slideUp();

            $("#billing_customer_identifier").val("");
            $("#billing_carruer_num").val("");
            $("#billing_carruer_type").val("0");

            $('#billing_love_code').val($('#love_unit').val());
        }
    });

    // 載具類別判斷
    $("#billing_carruer_type").change(function () {

        carruer_type = $("#billing_carruer_type").val();
        invoice_type = $("#billing_invoice_type").val();
        identifier = $("#billing_customer_identifier").val();

        // 無載具
        if (carruer_type == '0' || carruer_type == '1') {

            $("#billing_carruer_num_field").slideUp();

        } else if (carruer_type == '2') {

            $("#billing_carruer_num_field").slideDown();

        } else if (carruer_type == '3') {

            $("#billing_carruer_num_field").slideDown();
        }
    });
    $('#love_unit').change(function (e) {
        if (e.target.value != 'other') {
            $('#billing_love_code_input').slideUp();
            $('#billing_love_code').val(e.target.value);
        } else {
            $('#billing_love_code_input').slideDown();
            $('#billing_love_code').val("");
        }
    });
    $('#billing_love_code').val("");
});
