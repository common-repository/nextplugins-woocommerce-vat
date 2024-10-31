<?php
/**
 * Plugin Name: NextPlugins Woo VAT
 * Plugin URI: https://www.nextplugins.com/woocommerce-vat
 * Description: Adds VAT field to WooCommerce checkout page.
 * Version: 1.1.4
 * Author: NextPlugins
 * Requires at least: 4.4
 * Author URI: https://www.nextplugins.com
 * Text Domain: nextplugins-woocommerce-vat
 * Domain Path: /languages/
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NextPlugins_WC_Vat {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.1.4';

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	private $helpers;

	private $options = array(
		'next_plugins_wc_vat_cleanup_options',
		'next_plugins_wc_vat_enable',
		'next_plugins_wc_vat_checkout_position',
		'next_plugins_wc_vat_account_position',
		'next_plugins_wc_vat_enable_wc_rest',
		'next_plugins_wc_vat_reg_nr_enable',
	);

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		$php_version = PHP_VERSION;
		if( !empty( $php_version ) && version_compare( $php_version, '5.3', '<' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			add_action( 'admin_notices', array( $this, 'php_notice' ) );
		}
		else
		{
			require_once 'inc/helpers.php';
			$this->helpers = new \NWV\Next_Plugins_Helpers();

			if ( ! function_exists( 'WC' ) || version_compare( WC()->version, '2.6', '>=' ) ) {
				$enabled = get_option( 'next_plugins_wc_vat_enable', 'no' );

				if ( is_admin() ) {
					require_once 'inc/admin.php';
					if ( $enabled == 'yes' ) {
						add_filter( 'woocommerce_admin_billing_fields', array(
							$this,
							'add_vat_to_billing_fields_in_order'
						) );

						add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_company_information_to_admin_orders_list' ), 11);
						add_action( 'manage_shop_order_posts_custom_column' , array( $this, 'custom_orders_list_column_content' ), 10, 2 );
					}
				}

				if ( $enabled == 'yes' ) {
					add_filter( 'woocommerce_checkout_fields', array( $this, 'add_vat_to_checkout_fields' ), 20 );
					add_filter( 'woocommerce_billing_fields', array( $this, 'add_vat_to_account_billing_fields' ), 20 );

					add_filter( 'woocommerce_order_formatted_billing_address', array(
						$this,
						'add_vat_to_formatted_billing_address'
					), 20, 2 );
					add_filter( 'woocommerce_formatted_address_replacements', array(
						$this,
						'add_vat_to_formatted_address_replacements'
					), 20, 2 );
					add_filter( 'woocommerce_localisation_address_formats', array(
						$this,
						'add_vat_to_localisation_address_formats'
					), 20, 1 );


					$rest = get_option( 'next_plugins_wc_vat_enable_wc_rest', 'no' );
					if ( $rest == 'yes' ) {
						add_action( 'rest_api_init', array( $this, 'add_vat_to_order_rest_api' ) );
					}
				}

				add_action('updated_option', array( $this, 'update_option' ), 10, 3);
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}
		}
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'nextplugins-woocommerce-vat' );

		load_textdomain( 'nextplugins-woocommerce-vat', trailingslashit( WP_LANG_DIR ) . 'plugins/nextplugins-woocommerce-vat-' . $locale . '.mo' );
		load_plugin_textdomain( 'nextplugins-woocommerce-vat', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * WooCommerce fallback notice.
	 *
	 * @return string
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>' . sprintf( __( 'NextPlugins WooCommerce VAT plugin depends on the last version of %s to work!', 'nextplugins-woocommerce-vat' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __( 'WooCommerce', 'nextplugins-woocommerce-vat' ) . '</a>' ) . '</p></div>';
	}

	/**
	 * PHP notice.
	 *
	 * @return string
	 */
	public function php_notice() {
		echo '<div class="error"><p>' . __( 'NextPlugins WooCommerce VAT plugin requires PHP 5.3 version to work!', 'nextplugins-woocommerce-vat' ) . '</p></div>';
	}

	/**
	 * Autoload options for better performance
	 *
	 * @param $option
	 * @param $old_value
	 * @param $value
	 */
	public function update_option($option, $old_value, $value)
	{
		if(in_array($option, $this->options))
		{
			update_option($option, $value, true);
		}
	}

	public function add_vat_to_order_rest_api() {
		register_rest_field( 'shop_order',
			'vat',
			array(
				'get_callback'    => array( $this, 'get_order_vat' ),
				'update_callback' => null,
				'schema'          => null,
			)
		);

		register_rest_field( 'shop_order',
			'reg_nr',
			array(
				'get_callback'    => array( $this, 'get_order_reg_nr' ),
				'update_callback' => null,
				'schema'          => null,
			)
		);
	}

	public function get_order_vat( $object, $field_name, $request ) {
		return get_post_meta( $object['id'], '_billing_vat_number', true );
	}

	public function get_order_reg_nr( $object, $field_name, $request ) {
		return get_post_meta( $object['id'], '_billing_reg_number', true );
	}

	public function add_vat_to_checkout_fields( $fields ) {
		$position = get_option( 'next_plugins_wc_vat_checkout_position', 'after_company' );
		$reg_nr = get_option( 'next_plugins_wc_vat_reg_nr_enable', 'no' );

		$reg_class = array();
		$class = array();

		if ( $position == 'in_line_company' && $reg_nr == 'no') {
			$fields['billing']['billing_company']['class'] = array( 'form-row-first' );
			$class                                         = array( 'form-row-last' );
		} elseif($reg_nr == 'yes') {
			$class = array( 'form-row-last' );
			$reg_class = array( 'form-row-first' );
		}

		$field = array(
			'label'       => __( 'VAT Nr.', 'nextplugins-woocommerce-vat' ),
			//'placeholder' => _x( 'VAT Nr.', 'placeholder', 'nextplugins-woocommerce-vat' ),
			'required'    => false,
			'class'       => $class,
			'clear'       => true
		);

		$reg_field = array(
			'label'       => __( 'Registration Nr.', 'nextplugins-woocommerce-vat' ),
			//'placeholder' => _x( 'VAT Nr.', 'placeholder', 'nextplugins-woocommerce-vat' ),
			'required'    => false,
			'class'       => $reg_class,
			'clear'       => true
		);

		if( $reg_nr == 'no' ) {
			if ( $position == 'in_line_company' || $position == 'after_company' ) {
				$fields['billing'] = $this->helpers->array_insert_after( 'billing_company', $fields['billing'], 'billing_vat_number', $field );
			} else {
				$fields['billing']['billing_vat_number'] = $field;
			}
		} else {
			if ( $position == 'after_company' ) {
				$fields['billing'] = $this->helpers->array_insert_after( 'billing_company', $fields['billing'], 'billing_reg_number', $reg_field );
				$fields['billing'] = $this->helpers->array_insert_after( 'billing_reg_number', $fields['billing'], 'billing_vat_number', $field );
			} else {
				$fields['billing']['billing_reg_number'] = $reg_field;
				$fields['billing']['billing_vat_number'] = $field;
			}
		}

		return $fields;
	}

	public function add_vat_to_account_billing_fields( $fields ) {
		if ( is_account_page() ) {
			$position = get_option( 'next_plugins_wc_vat_checkout_position', 'after_company' );
			$reg_nr = get_option( 'next_plugins_wc_vat_reg_nr_enable', 'no' );

			$reg_class = array();
			$class = array();

			if ( $position == 'in_line_company' && $reg_nr == 'no') {
				$fields['billing']['billing_company']['class'] = array( 'form-row-first' );
				$class                                         = array( 'form-row-last' );
			} elseif($reg_nr == 'yes') {
				$class = array( 'form-row-last' );
				$reg_class = array( 'form-row-first' );
			}

			$field = array(
				'label'       => __( 'VAT Nr.', 'nextplugins-woocommerce-vat' ),
				//'placeholder' => _x( 'VAT Nr.', 'placeholder', 'nextplugins-woocommerce-vat' ),
				'required'    => false,
				'class'       => $class,
				'clear'       => true
			);

			$reg_field = array(
				'label'       => __( 'Registration Nr.', 'nextplugins-woocommerce-vat' ),
				//'placeholder' => _x( 'VAT Nr.', 'placeholder', 'nextplugins-woocommerce-vat' ),
				'required'    => false,
				'class'       => $reg_class,
				'clear'       => true
			);

			if( $reg_nr == 'no' ) {
				if ( $position == 'in_line_company' || $position == 'after_company' ) {
					$fields = $this->helpers->array_insert_after( 'billing_company', $fields, 'billing_vat_number', $field );
				} else {
					$fields['billing_vat_number'] = $field;
				}
			} else {
				if ( $position == 'after_company' ) {
					$fields = $this->helpers->array_insert_after( 'billing_company', $fields, 'billing_reg_number', $reg_field );
					$fields = $this->helpers->array_insert_after( 'billing_reg_number', $fields, 'billing_vat_number', $field );
				} else {
					$fields['billing_reg_number'] = $reg_field;
					$fields['billing_vat_number'] = $field;
				}
			}
		}

		return $fields;
	}

	public function add_vat_to_formatted_billing_address( $fields, $wc ) {

		$reg_nr = get_option( 'next_plugins_wc_vat_reg_nr_enable', 'no' );

		if($reg_nr == 'yes') $fields['reg_number'] = (!empty($wc->billing_reg_number)) ? __( 'Reg. Nr.', 'nextplugins-woocommerce-vat' ).': '.$wc->billing_reg_number : '';

		$fields['vat_number'] = (!empty($wc->billing_vat_number)) ? __( 'VAT Nr.', 'nextplugins-woocommerce-vat' ).': '.$wc->billing_vat_number : '';

		return $fields;
	}

	public function add_vat_to_formatted_address_replacements( $fields, $args ) {

		$reg_nr = get_option( 'next_plugins_wc_vat_reg_nr_enable', 'no' );

		if($reg_nr == 'yes') $fields['{reg_number}'] = $args['reg_number'];

		$fields['{vat_number}'] = $args['vat_number'];

		return $fields;
	}

	public function add_vat_to_localisation_address_formats( $formats ) {

		if(version_compare( WC()->version, '2.7', '>=' ))
		{
			return array(
				'default' => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{address_2}\n{city}\n{state}\n{postcode}\n{country}",
				'AU' => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{address_2}\n{city} {state} {postcode}\n{country}",
				'AT' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'BE' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'CA' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{city} {state} {postcode}\n{country}",
				'CH' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'CL' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{state}\n{postcode} {city}\n{country}",
				'CN' => "{country} {postcode}\n{state}, {city}, {address_2}, {address_1}\n{company}\n{vat_number}\n{reg_number}\n{name}",
				'CZ' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'DE' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'EE' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'FI' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'DK' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'FR' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city_upper}\n{country}",
				'HK' => "{company}\n{vat_number}\n{reg_number}\n{first_name} {last_name_upper}\n{address_1}\n{address_2}\n{city_upper}\n{state_upper}\n{country}",
				'HU' => "{name}\n{company}\n{vat_number}\n{reg_number}\n{city}\n{address_1}\n{address_2}\n{postcode}\n{country}",
				'IN' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{city} - {postcode}\n{state}, {country}",
				'IS' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'IT' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode}\n{city}\n{state_upper}\n{country}",
				'JP' => "{postcode}\n{state}{city}{address_1}\n{address_2}\n{company}\n{vat_number}\n{reg_number}\n{last_name} {first_name}\n{country}",
				'TW' => "{company}\n{vat_number}\n{reg_number}\n{last_name} {first_name}\n{address_1}\n{address_2}\n{state}, {city} {postcode}\n{country}",
				'LI' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'NL' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'NZ' => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{address_2}\n{city} {postcode}\n{country}",
				'NO' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'PL' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'PT' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'SK' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'SI' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'ES' => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{address_2}\n{postcode} {city}\n{state}\n{country}",
				'SE' => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'TR' => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{address_2}\n{postcode} {city} {state}\n{country}",
				'US' => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{address_2}\n{city}, {state_code} {postcode}\n{country}",
				'VN' => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{city}\n{country}",
			);
		}
		else
		{
			$postcode_before_city = "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}";

			return array(
				'default' => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{address_2}\n{city}\n{state}\n{postcode}\n{country}",
				'AU'      => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{address_2}\n{city} {state} {postcode}\n{country}",
				'AT'      => $postcode_before_city,
				'BE'      => $postcode_before_city,
				'CA'      => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{city} {state} {postcode}\n{country}",
				'CH'      => $postcode_before_city,
				'CL'      => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{state}\n{postcode} {city}\n{country}",
				'CN'      => "{country} {postcode}\n{state}, {city}, {address_2}, {address_1}\n{company}\n{vat_number}\n{reg_number}\n{name}",
				'CZ'      => $postcode_before_city,
				'DE'      => $postcode_before_city,
				'EE'      => $postcode_before_city,
				'FI'      => $postcode_before_city,
				'DK'      => $postcode_before_city,
				'FR'      => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode} {city_upper}\n{country}",
				'HK'      => "{company}\n{vat_number}\n{reg_number}\n{first_name} {last_name_upper}\n{address_1}\n{address_2}\n{city_upper}\n{state_upper}\n{country}",
				'HU'      => "{name}\n{company}\n{vat_number}\n{reg_number}\n{city}\n{address_1}\n{address_2}\n{postcode}\n{country}",
				'IN'      => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{city} - {postcode}\n{state}, {country}",
				'IS'      => $postcode_before_city,
				'IT'      => "{company}\n{vat_number}\n{reg_number}\n{name}\n{address_1}\n{address_2}\n{postcode}\n{city}\n{state_upper}\n{country}",
				'JP'      => "{postcode}\n{state}{city}{address_1}\n{address_2}\n{company}\n{vat_number}\n{reg_number}\n{last_name} {first_name}\n{country}",
				'TW'      => "{company}\n{vat_number}\n{reg_number}\n{last_name} {first_name}\n{address_1}\n{address_2}\n{state}, {city} {postcode}\n{country}",
				'LI'      => $postcode_before_city,
				'NL'      => $postcode_before_city,
				'NZ'      => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{address_2}\n{city} {postcode}\n{country}",
				'NO'      => $postcode_before_city,
				'PL'      => $postcode_before_city,
				'SK'      => $postcode_before_city,
				'SI'      => $postcode_before_city,
				'ES'      => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{address_2}\n{postcode} {city}\n{state}\n{country}",
				'SE'      => $postcode_before_city,
				'TR'      => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{address_2}\n{postcode} {city} {state}\n{country}",
				'US'      => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{address_2}\n{city}, {state_code} {postcode}\n{country}",
				'VN'      => "{name}\n{company}\n{vat_number}\n{reg_number}\n{address_1}\n{city}\n{country}",
			);
		}
	}

	public function add_vat_to_billing_fields_in_order( $fields ) {

		$reg_nr = get_option( 'next_plugins_wc_vat_reg_nr_enable', 'no' );

		if($reg_nr == 'yes') {
			$fields['reg_number'] = array(
				'label' => __( 'Registration Nr.', 'nextplugins-woocommerce-vat' ),
			);
		}

		$fields['vat_number'] = array(
			'label' => __( 'VAT Nr.', 'nextplugins-woocommerce-vat' ),
		);

		return $fields;
	}

	public function add_company_information_to_admin_orders_list( $columns )
	{
		$field = __( 'Company info', 'nextplugins-woocommerce-vat' );

		$columns = $this->helpers->array_insert_after( 'order_title', $columns, 'company_info', $field );

		return $columns;
	}

	public function custom_orders_list_column_content($column, $post_id) {

		global $post, $woocommerce;

		/**
		 * @var WC_Order $the_order
		 */
		global $the_order;

		if($column == 'company_info') {
			$company = $this->get_billing_company($the_order);

			if(!empty($company)) {
				echo '<b>'.$company.'</b>';

				if(!empty($the_order->billing_reg_number)) echo '<br><b>'.__( 'Reg. Nr.', 'nextplugins-woocommerce-vat' ).':</b> '.$the_order->billing_reg_number;
				if(!empty($the_order->billing_reg_number)) echo '<br><b>'.__( 'VAT Nr.', 'nextplugins-woocommerce-vat' ).':</b> '.$the_order->billing_vat_number;

			}
		}

		return $column;
	}

	public function get_billing_company($the_order) {
		if(method_exists($the_order, 'get_billing_company')) {
			return $the_order->get_billing_company();
		} else {
			return $the_order->billing_company;
		}
	}
}

add_action( 'plugins_loaded', array( 'NextPlugins_WC_Vat', 'get_instance' ), 0 );
