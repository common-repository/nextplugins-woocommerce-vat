<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$remove_options = get_option('next_plugins_wc_vat_cleanup_options', 'no');

if($remove_options == 'yes')
{
    $plugin_defined_options = array(
        'next_plugins_wc_vat_cleanup_options',
        'next_plugins_wc_vat_enable',
        'next_plugins_wc_vat_checkout_position',
        'next_plugins_wc_vat_account_position',
        'next_plugins_wc_vat_enable_wc_rest',
	    'next_plugins_wc_vat_reg_nr_enable',
    );

    foreach($plugin_defined_options as $option_name) {
        delete_option($option_name);
    }
}