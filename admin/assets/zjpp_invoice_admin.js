
// 開立發票
function send_orderid_to_gen_invoice(nOrder_Id, gateway_id) {
    var data = {
        'action': 'zjpp_gen_invoice',
        'oid': nOrder_Id,
        'gateway_id': gateway_id,
    };


    jQuery.blockUI({ message: null });
    // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
    jQuery.post(ajaxurl, data, function (response) {
        alert(response);
        location.reload();
    });

}

// 作廢發票
function send_orderid_to_issue_invalid(nOrder_Id, gateway_id) {

    if (confirm("確定要刪除此筆發票")) {
        var data = {
            'action': 'zjpp_invalid_invoice',
            'oid': nOrder_Id,
            'gateway_id': gateway_id,
        };

        jQuery.blockUI({ message: null });

        jQuery.post(ajaxurl, data, function (response) {
            alert(response);
            location.reload();
        });
    }

}