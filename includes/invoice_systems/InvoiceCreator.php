<?php

namespace ZJPP;

/**
 * invoice_system
 *
 * @return InvoiceSystem
 */
function invoice_system()
{
    // get invoice system settings from pref
    $prefs = get_option('ZJPP_prefs', []);
    $invoice_system = 'InvoiceSystem_' . $prefs['invoice_method'];
    // get invoice system
    $invoice_path = ZJPP_PLUGIN_DIR . '/includes/invoice_systems/' . $invoice_system . '.php';
    if (file_exists($invoice_path))
        require_once $invoice_path;
    return ('\\ZJPP\\' . $invoice_system)::instance();
}
