<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Settings_Tab_NextPlugins_WC_Vat {
	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 */
	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
		add_action( 'woocommerce_settings_tabs_next_plugins_wc_vat', __CLASS__ . '::settings_tab' );
		add_action( 'woocommerce_update_options_next_plugins_wc_vat', __CLASS__ . '::update_settings' );
	}

	/**
	 * Add a new settings tab to the WooCommerce settings tabs array.
	 *
	 * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
	 *
	 * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
	 */
	public static function add_settings_tab( $settings_tabs ) {
		$settings_tabs['next_plugins_wc_vat'] = __( 'VAT Field', 'nextplugins-woocommerce-vat' );

		return $settings_tabs;
	}

	/**
	 * Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
	 *
	 * @uses woocommerce_admin_fields()
	 * @uses self::get_settings()
	 */
	public static function settings_tab() {
		woocommerce_admin_fields( self::get_settings() );
	}

	/**
	 * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
	 *
	 * @uses woocommerce_update_options()
	 * @uses self::get_settings()
	 */
	public static function update_settings() {

		if ($_POST['next_plugins_wc_vat_reg_nr_enable'] == 1) {
			if($_POST['next_plugins_wc_vat_checkout_position'] == 'in_line_company') {
				$_POST['next_plugins_wc_vat_checkout_position'] = 'after_company';
			}

			if($_POST['next_plugins_wc_vat_account_position'] == 'in_line_company') {
				$_POST['next_plugins_wc_vat_account_position'] = 'after_company';
			}
		}

		woocommerce_update_options( self::get_settings() );
	}

	/**
	 * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
	 *
	 * @return array Array of settings for @see woocommerce_admin_fields() function.
	 */
	public static function get_settings() {

		$settings = array(
			array(
				'name' => __( 'VAT Field integration', 'nextplugins-woocommerce-vat' ),
				'type' => 'title',
				'desc' => '',
				'id'   => 'next_plugins_wc_vat_section_title'
			),
			array(
				'name'    => __( 'VAT Field', 'nextplugins-woocommerce-vat' ),
				'type'    => 'checkbox',
				'id'      => 'next_plugins_wc_vat_enable',
				'desc'    => __( 'Enable', 'nextplugins-woocommerce-vat' ),
				'default' => 'no',
			),
			array(
				'name'    => __( 'Company Registration Nr. Field', 'nextplugins-woocommerce-vat' ),
				'type'    => 'checkbox',
				'id'      => 'next_plugins_wc_vat_reg_nr_enable',
				'desc'    => __( 'Enable', 'nextplugins-woocommerce-vat' ),
				'desc_tip'=> __('Some countries require company registration number in invoices which is different than VAT number', 'nextplugins-woocommerce-vat'),
				'default' => 'no',
				'custom_attributes' => array('onclick' => 'jQuery("input:radio[name=next_plugins_wc_vat_checkout_position]:nth(0)").attr(\'checked\', true);jQuery("input:radio[name=next_plugins_wc_vat_account_position]:nth(0)").attr(\'checked\', true);'),
			),
		);

		$reg_nr = get_option( 'next_plugins_wc_vat_reg_nr_enable', 'no' );

		if($reg_nr == 'no') {
			$settings[] = array(
				'title'   => __( 'Field position in checkout', 'nextplugins-woocommerce-vat' ),
				'desc'    => __( 'This controls where VAT field will be placed in checkout form.', 'nextplugins-woocommerce-vat' ),
				'id'      => 'next_plugins_wc_vat_checkout_position',
				'default' => 'after_company',
				'type'    => 'radio',
				'options' => array(
					'after_company'   => __( 'After company field', 'nextplugins-woocommerce-vat' ),
					'in_line_company' => __( 'In same line as company field', 'nextplugins-woocommerce-vat' ),
					'after_form'      => __( 'After billing form', 'nextplugins-woocommerce-vat' ),
				)
			);

			$settings[] = array(
				'title'   => __( 'Field position in account', 'nextplugins-woocommerce-vat' ),
				'desc'    => __( 'This controls where VAT field will be placed in account billing form.', 'nextplugins-woocommerce-vat' ),
				'id'      => 'next_plugins_wc_vat_account_position',
				'default' => 'after_company',
				'type'    => 'radio',
				'options' => array(
					'after_company'   => __( 'After company field', 'nextplugins-woocommerce-vat' ),
					'in_line_company' => __( 'In same line as company field', 'nextplugins-woocommerce-vat' ),
					'after_form'      => __( 'After billing form', 'nextplugins-woocommerce-vat' ),
				)
			);
		} else {
			$settings[] = array(
				'title'   => __( 'Field position in checkout', 'nextplugins-woocommerce-vat' ),
				'desc'    => __( 'This controls where VAT field will be placed in checkout form.', 'nextplugins-woocommerce-vat' ),
				'id'      => 'next_plugins_wc_vat_checkout_position',
				'default' => 'after_company',
				'type'    => 'radio',
				'options' => array(
					'after_company'   => __( 'After company field', 'nextplugins-woocommerce-vat' ),
					'after_form'      => __( 'After billing form', 'nextplugins-woocommerce-vat' ),
				)
			);

			$settings[] = array(
				'title'   => __( 'Field position in account', 'nextplugins-woocommerce-vat' ),
				'desc'    => __( 'This controls where VAT field will be placed in account billing form.', 'nextplugins-woocommerce-vat' ),
				'id'      => 'next_plugins_wc_vat_account_position',
				'default' => 'after_company',
				'type'    => 'radio',
				'options' => array(
					'after_company'   => __( 'After company field', 'nextplugins-woocommerce-vat' ),
					'after_form'      => __( 'After billing form', 'nextplugins-woocommerce-vat' ),
				)
			);
		}

		$settings[] = array(
			'name'    => __( 'REST (wc/v1 and wc/v2)', 'nextplugins-woocommerce-vat' ),
			'type'    => 'checkbox',
			'id'      => 'next_plugins_wc_vat_enable_wc_rest',
			'desc'    => __( 'Show VAT field in Woocommece Orders REST response', 'nextplugins-woocommerce-vat' ),
			'default' => 'no',
		);

		$settings[] = array(
			'title'   => __( 'Cleanup Plugin on Uninstall', 'nextplugins-woocommerce-vat' ),
			'desc'    => __( 'Remove all options', 'nextplugins-woocommerce-vat' ),
			'id'      => 'next_plugins_wc_vat_cleanup_options',
			'default' => 'no',
			'type'    => 'checkbox',
		);

		$settings['section_end'] = array(
			'type' => 'sectionend',
			'id'   => 'esce_end'
		);

		return apply_filters( 'wc_settings_tabs_next_plugins_wc_vat', $settings );
	}
}

WC_Settings_Tab_NextPlugins_WC_Vat::init();