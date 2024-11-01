<?php

/**
 * Plugin Name: Step pay
 * Description: A payment gateway for Splittypay Step Checkout.
 * Version: 1.6.0
 * Author: Splitty Pay
 * Author URI: https://www.splittypay.com/
 * Developer: Marco Cattaneo
 * Text Domain: woocommerce-gateway-splittypay-step-checkout
 * Domain Path: /languages
 *
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 *
 * Copyright: © 2009-2015 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + Splitty Pay gateway
 */
function wc_splittypay_step_add_to_gateways( $gateways ) {
    $gateways[] = 'WC_Splittypay_Step_Gateway';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_splittypay_step_add_to_gateways' );

function wc_splittypay_step_option_update_min( $value, $old_value, $name ) {
    update_option( 'global_minamount', $value );
    return $value;
}
add_filter( 'pre_update_option_minamount', 'wc_splittypay_step_option_update_min', 10, 3 );

function wc_splittypay_step_option_update_max( $value, $old_value, $name ) {
    update_option( 'global_maxamount', $value );
    return $value;
}
add_filter( 'pre_update_option_maxamount', 'wc_splittypay_step_option_update_max', 10, 3 );

function wc_splittypay_step_option_update_label( $value, $old_value, $name ) {
    update_option( 'global_productlabel', $value );
    return $value;
}
add_filter( 'pre_update_option_productlabel', 'wc_splittypay_step_option_update_label', 10, 3 );

function wc_splittypay_step_option_update_price( $value, $old_value, $name ) {
    update_option( 'global_productlabelprice', $value );
    return $value;
}
add_filter( 'pre_update_option_productlabelprice', 'wc_splittypay_step_option_update_price', 10, 3 );

/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_splittypay_step_gateway_plugin_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=splittypay_step_gateway' ) . '">' . __( 'Configure', 'woocommerce' ) . '</a>'
    );
    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_splittypay_step_gateway_plugin_links' );

function conditional_payment_gateways( $available_gateways ) {
    global $woocommerce;
    $cart_amount   = $woocommerce->cart->total;
    $min_amount    = get_option( 'global_minamount', 20 );
    $max_amount    = get_option( 'global_maxamount', 500 );
    if ($cart_amount < $min_amount || $cart_amount > $max_amount) {
        unset($available_gateways['splittypay_step_gateway']);
    }
    return $available_gateways;
}
add_filter('woocommerce_available_payment_gateways', 'conditional_payment_gateways', 10, 1);

function plugin_load_textdomain() {
	load_plugin_textdomain( 'woocommerce-gateway-splittypay-step-checkout', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'plugin_load_textdomain' );

/**
 * Offline Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       WC_Splittypay_Gateway
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     WooCommerce/Classes/Payment
 * @author      Marco Cattaneo.
 */
add_action( 'plugins_loaded', 'wc_splittypay_step_gateway_init', 12 );
function wc_splittypay_step_gateway_init() {
    class WC_Splittypay_Step_Gateway extends WC_Payment_Gateway {

        public $config = array(
            "baseUrl"           => 'https://app.step.splittypay.com/#',
            "postOrderUrl"      => 'https://api.step.splittypay.com/orders?token=',
            "refundUrl"         => 'https://api.step.splittypay.com/orders/refund/',
            "port"              => '',
            "plugin_version"    => '1.6.0'
        );

        /**
         * Constructor for the gateway.
         */
        public function __construct() {
            // Config
            $this->id                   = 'splittypay_step_gateway';
            $this->icon                 = apply_filters('woocommerce_splittypay_step_icon', WC_HTTPS::force_https_url( 'https://splittypay-attachments-prod.s3.eu-west-1.amazonaws.com/splittypay_logo.png' ));
            $this->has_fields           = false;
            $this->method_title         = __( 'Step Pay', 'woocommerce' );
            $this->method_description   = __( 'Imposta l\'API key che ti è stata fornita per attivare i servizi Splittypay! <br>Se non hai una API key, richiedila al seguente indirizzo: <a href="https://merchants.groups.splittypay.com">API key modalità LIVE</a>. <br>Per avere una API key di TEST, invece, utilizza questo indirizzo: <a href="https://merchants.dev.groups.splittypay.com">API key modalità TEST</a>.', 'woocommerce-gateway-splittypay-step-checkout' );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->supports                 = array( 'products', 'refunds' );
            $this->title                    = __( 'Paga in 3 rate', 'woocommerce-gateway-splittypay-step-checkout' );
            $this->description              = __( 'Grazie a Splittypay puoi pagare il tuo ordine in 3 rate a tasso 0. Cliccando sul bottone verrai reindirizzato su Splittypay per completare l\'acquisto in pochi semplici step', 'woocommerce-gateway-splittypay-step-checkout' );
            $this->instructions             = $this->get_option( 'instructions', $this->description );
            $this->testmode                 = 'yes' === $this->get_option( 'testmode', 'no' );
            $this->minamount_index          = $this->get_option( 'minamount' );
            $this->maxamount_index          = $this->get_option( 'maxamount' );
            $this->minamount                = $this->get_sp_supported_orders_min_range()[$this->minamount_index];
            $this->maxamount                = $this->get_sp_supported_orders_max_range()[$this->maxamount_index];
            $this->productlabelpos          = $this->get_option( 'productlabelpos', 'woocommerce_before_add_to_cart_form' );
            $this->config['api_key']        = $this->get_option( 'api_key' );
            $this->config['api_key_test']   = $this->get_option( 'api_key_test' );

            if ( $this->testmode ) {
                $this->config['baseUrl']        = 'https://sandbox.dev.step.splittypay.com/#';
                $this->config['postOrderUrl']   = 'https://api.dev.rates.splittypay.com/orders?token=';
                $this->config['refundUrl']      = 'https://api.dev.rates.splittypay.com/orders/refund/';
                $this->config['port']           = '';
                $this->config['plugin_version'] = $this->config['plugin_version'];
                $this->description              .= ' ' . sprintf( __( 'SANDBOX ENABLED. You can use sandbox testing accounts only.', 'woocommerce'), 'https://sandbox.splittypay.it/' );
                $this->description              = trim( $this->description );
            }

            // Options
            update_option( 'global_minamount',          $this->minamount );
            update_option( 'global_maxamount',          $this->maxamount );
            update_option( 'global_productlabel',       $this->get_option( 'productlabel', 'yes' ) );
            update_option( 'global_productlabelprice',  $this->get_option( 'productlabelprice', 'yes' ) );
            update_option( 'global_modallabel',         $this->get_option( 'modallabel', 'yes' ) );

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
            add_action( 'woocommerce_api_wc_splittypay_step_gateway', array( $this, 'handle_callback') );
        }


        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Abilita il gateway di pagamento Splittypay', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'default' => 'yes'
                ),

                'testmode'    => array(
                    'title'       => __( 'Sandbox', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Abilita la sandbox di Splittypay e ricevi pagamenti solo in modalità TEST', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'default'     => 'no',
                    'description' => sprintf( __( 'La sandbox è un ambiente di TEST di Splittypay', 'woocommerce-gateway-splittypay-step-checkout' ), 'https://sandbox.splittypay.it/')
                ),

                'productlabel'    => array(
                    'title'       => __( 'Label prodotto', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Mostra l\'anteprima della rata di Splittypay sui prodotti', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'default'     => 'yes',
                    'description' => sprintf( __( 'L\'anteprima, se attiva, è visibile sui prodotti all\'interno del range di prezzo supportato da Splittypay', 'woocommerce-gateway-splittypay-step-checkout' ), 'https://sandbox.splittypay.it/'),
                ),

                'productlabelpos' => array(
                    'title'             => __( 'Posizione label prodotto', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'type'              => 'select',
                    'default'           => '',
                    'description'       => sprintf( __( 'L\'anteprima della rata può essere posizionata in diversi punti della pagina prodotto', 'woocommerce-gateway-splittypay-step-checkout' ), 'https://sandbox.splittypay.it/'),
                    'options'           => $this->get_sp_supported_product_hooks(),
                ),

                'productlabelprice'    => array(
                    'title'       => __( 'Splittypay - Prezzo prodotto', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Mostra l\'anteprima del prezzo con Splittypay sul prodotto', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'default'     => 'yes',
                    'description' => sprintf( __( 'L\'anteprima è visibile in corrispondenza del bottone di acquisto' , 'woocommerce-gateway-splittypay-step-checkout' ), 'https://sandbox.splittypay.it/'),
                ),

                'modallabel'    => array(
                    'title'       => __( 'Modale informazioni', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Mostra la modale di informazioni sulla pagina prodotto', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'default'     => 'yes',
                    'description' => sprintf( __( 'La modale, se attiva, è consultabile dalla pagina prodotto accanto all\'anteprima della rata' , 'woocommerce-gateway-splittypay-step-checkout' ), 'https://sandbox.splittypay.it/')
                ),

                'minamount' => array(
                    'title'       => __( 'Ordine minimo', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'type'        => 'select',
                    'description' => __( 'Inserisci il prezzo minimo supportato da Step Pay', 'woocommerce-gateway-splittypay-step-checkout'),
                    'default'     => 0,
                    'options'	  => $this->get_sp_supported_orders_min_range(),	
                    'desc_tip'    => true,
                    'placeholder' => __( 'Required', 'woocommerce-gateway-splittypay-step-checkout' ),
                ),

                'maxamount' => array(
                    'title'       => __( 'Ordine massimo', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'type'        => 'select',
                    'description' => __( 'Inserisci il prezzo massimo supportato da Step Pay', 'woocommerce-gateway-splittypay-step-checkout'),
                    'default'     => 480,
                    'options'	  => $this->get_sp_supported_orders_max_range(),
                    'desc_tip'    => true,
                    'placeholder' => __( 'Required', 'woocommerce-gateway-splittypay-step-checkout' ),
                ),

                'api_key' => array(
                    'title'       => __( 'API key LIVE', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'type'        => 'text',
                    'description' => __( 'Inserisci l\'API key per l\'ambiente LIVE.', 'woocommerce-gateway-splittypay-step-checkout'),
                    'default'     => '',
                    'desc_tip'    => true,
                    'placeholder' => __( 'Required', 'woocommerce-gateway-splittypay-step-checkout' ),
                ),

                'api_key_test' => array(
                    'title'       => __( 'API key TEST', 'woocommerce-gateway-splittypay-step-checkout' ),
                    'type'        => 'text',
                    'description' => __( 'Inserisci l\'API key per l\'ambiente TEST.', 'woocommerce-gateway-splittypay-step-checkout'),
                    'default'     => '',
                    'desc_tip'    => true,
                    'placeholder' => __( 'Required', 'woocommerce-gateway-splittypay-step-checkout' ),
                )
            );
        }

        /**
         * Available product page hooks.
         */
        public function get_sp_supported_product_hooks() {
            return array(
                0       =>      'woocommerce_before_single_product',
                1       =>      'woocommerce_before_single_product_summary',
                2       =>      'woocommerce_single_product_summary',
                3       =>      'woocommerce_before_add_to_cart_form', 
                4       =>      'woocommerce_before_variations_form', 
                5       =>      'woocommerce_before_add_to_cart_button', 
                6       =>      'woocommerce_before_single_variation',
                7       =>      'woocommerce_single_variation',
                8       =>      'woocommerce_before_add_to_cart_quantity',
                9       =>      'woocommerce_after_add_to_cart_quantity',
                10      =>      'woocommerce_after_single_variation', 
                11      =>      'woocommerce_after_add_to_cart_button', 
                12      =>      'woocommerce_after_variations_form', 
                13      =>      'woocommerce_after_add_to_cart_form',
                14      =>      'woocommerce_product_meta_start', 
                15      =>      'woocommerce_product_meta_end', 
                16      =>      'woocommerce_share', 
                17      =>      'woocommerce_after_single_product_summary'
            );
        }

        /**
        *
        */
        public function get_sp_supported_orders_min_range() {
        	return array(
                0 => 20,
                1 => 30,
                2 => 40,
                3 => 50,
                4 => 100,
                5 => 150,
                6 => 200,
            );
        }

        /**
        *
        */
        public function get_sp_supported_orders_max_range() {
            return array(
                0  => 500,
                1  => 600,
                2  => 700,
                3  => 800,
                4  => 900,
                5  => 1000,
                6  => 1100,
                7  => 1200,
                8  => 1300,
                9  => 1400,
                10 => 1500,
            );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( $this->instructions ) {
                echo esc_html( wpautop( wptexturize( $this->instructions ) ) );
            }
        }


        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
                echo esc_html( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
            }
        }


        /**
         * Callback action
         */
        public function handle_callback() {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $json = file_get_contents('php://input');
                $data = json_decode($json);

                $order = wc_get_order( $data->content->order_id );
                if ($data->content->order_id != null && $data->content->status != null
                    && ($data->content->status == 'COMPLETED' || $data->content->status == 'PRE_AUTHORIZED')){
                    $this->payment_complete( $order, '', __( 'IPN payment completed', 'woocommerce' ));
                    $redirect = $this->get_return_url( $order );
                    header('HTTP/1.1 200 OK');
                    echo wp_send_json(array('returnUrl'=> $redirect), 200);
                } else {
                    $this->payment_fail( $order, '', __( 'IPN payment failed', 'woocommerce' ));
                    wp_die( 'Splitty Pay Request Failure', 'Splitty Pay IPN', array( 'response' => 500 ) );
                }   
            }
        }

        protected function payment_complete( $order, $txn_id = '', $note = '' ) {
            $order->add_order_note( $note );
            $order->payment_complete();
            $order->update_status('processing', __('Processing order', 'woocommerce'));
            WC()->cart->empty_cart();
        }

        protected function payment_fail( $order, $txn_id = '', $note = '' ) {
            $order->add_order_note( $note );
            $order->update_status('cancelled', __('Canceling order', 'woocommerce'));
            WC()->cart->empty_cart();
        }

        protected function post_order($postUrl, $params){
        	$args = array(
                'body'        => $params,
                'timeout'     => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array(
                    "Cache-Control: no-cache",
                    "Content-Type: application/json"
                ),
                'cookies'     => array(),
            );
            $response = wp_remote_post( $postUrl, $args );
            return $response;
        }

        public function process_payment( $order_id ) {
            $wc_order = wc_get_order( $order_id );
            if ( $wc_order->get_total( 'view' ) >= $this->minamount && $wc_order->get_total( 'view' ) <= $this->maxamount ) {
                $order_data = array(
                    'content' => array (
                        'order_id'          => $wc_order->get_id(),
                        'order_ref'         => WC()->api_request_url( 'wc_splittypay_step_gateway' ),
                        'failure_ref'       => WC()->api_request_url( 'wc_splittypay_step_gateway' ),
                        'thank_you_page'    => $wc_order->get_checkout_order_received_url(),
                        'amount'            => $wc_order->get_total( 'view' )*100,
                        'plugin_version'    => $this->config['plugin_version'],
                        'cms'               => 'Wordpress',
                        'cms_min_amount'	=> $this->minamount,
						'cms_max_amount'	=> $this->maxamount
                    )
                );
                $order = json_encode($order_data);
                
                if ($this->testmode != null && $this->testmode == true) {
                    $request_token = $this->config['api_key_test'];
                } else {
                    $request_token = $this->config['api_key'];
                }
                $response = $this->post_order($this->config['postOrderUrl'] . $request_token, $order);
                $http_code = wp_remote_retrieve_response_code( $response );
                if ($http_code == 200 || $http_code == 201 || $http_code == 204) {
                    $redirectUrl = $this->config['baseUrl'] . "/auth?order_id=" . json_decode($response['body'])->key->uri . "&key=" . $request_token;
                } else {
                    $redirectUrl = $this->config['baseUrl'] . "/" . $http_code;
                }

                return array(
                    'result'   => 'success',
                    'redirect' => $redirectUrl,
                );
            } else {
            	wc_add_notice( __( 'Questo metodo di pagamento accetta ordini superiori a', 'woocommerce-gateway-splittypay-step-checkout' ).' '.$this->minamount.' '.__( 'euro ed inferiori a', 'woocommerce-gateway-splittypay-step-checkout' ).' '.$this->maxamount, 'error' );
            }
        }

        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $start_args = array(
                'body'        => '',
                'timeout'     => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array(
                    "Cache-Control: no-cache",
                    "Content-Type: application/json"
                ),
                'cookies'     => array(),
            );
            if ($this->testmode != null && $this->testmode == true) {
                $request_token = $this->config['api_key_test'];
            } else {
                $request_token = $this->config['api_key'];
            }
            $start_result = wp_remote_post( $this->config['refundUrl'] . $order_id . '?token=' . $request_token, $start_args );
            $http_code    = wp_remote_retrieve_response_code( $start_result );
            switch ($http_code){
                case 200:
                    return true;
                case 201:
                    return true;
                case 204:
                    return true;
                default:
                    return false;
            }
        }

        public function log_to_hook($data) {
            $log_data = array(
                'content' => array (
                    'data'  => $data
                )
            );
            $log = json_encode($log_data);
            $args = array(
                'body'        => $log,
                'timeout'     => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array(
                    "Cache-Control: no-cache",
                    "Content-Type: application/json"
                ),
                'cookies'     => array(),
            );
            $response = wp_remote_post( 'https://webhook.site/87c19d05-38d7-424a-b732-177ed2ec327f', $args );
        }
    }

    $Splittypay     = new WC_Splittypay_Step_Gateway();
    $product_hooks  = $Splittypay->get_sp_supported_product_hooks(); 
    function wc_splittypay_step_gateway_add_product_desc(){
        global $product;
        $showLabel          = 'yes' === get_option( 'global_productlabel', 'yes' );
        $showLabelPrice     = 'yes' === get_option( 'global_productlabelprice', 'yes' );
        $showModal          = 'yes' === get_option( 'global_modallabel', 'yes' );
        if ($showLabel && $product->get_price() >= get_option( 'global_minamount', 20 ) && $product->get_price() <= get_option( 'global_maxamount', 500 )) {
            $info_text      = '<b style="margin-right: 4px;">'.__('oppure in 3 rate da', 'woocommerce-gateway-splittypay-step-checkout').' '.number_format($product->get_price()/3, 2).'€'.' '.__('con', 'woocommerce-gateway-splittypay-step-checkout').'</b>';
            $info_button    = '<span style="cursor: pointer;" onclick="on_product_info_click()"> ⓘ </span>';
            if (!$showLabelPrice) {
                $info_text = '<b style="margin-right: 4px;">'.__('oppure in 3 rate con', 'woocommerce-gateway-splittypay-step-checkout').'</b>';
            }
            if (!$showModal) {
                $info_button = '';
            }
            echo '<div style="display: flex; align-items: center; margin-bottom: 12px; flex-wrap: wrap;">' . $info_text . '
                    <img style="width: 100px; height: 17.5px; margin-right: 4px;" src="https://splittypay-attachments-prod.s3.eu-west-1.amazonaws.com/splittypay_logo.png"> 
                    ' . $info_button . '
                </div>
                <div id="infoModal" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="display: none">
                    <div id="infoModalDialog">
                        <span style="position: absolute; top: 8px; right: 12px; font-size: 32px; cursor: pointer;" onclick="on_product_info_close()">&times;</span>
                        <h5 class="infoModalText infoModalMarginBottom">'.__('Acquista subito, paga dopo!','woocommerce-gateway-splittypay-step-checkout').'</h1>

                        <p class="infoModalText"> <b>'.__('Scegli Splittypay','woocommerce-gateway-splittypay-step-checkout').'</b> </p>
                        <p class="infoModalText infoModalMarginBottom">'.__('al pagamento','woocommerce-gateway-splittypay-step-checkout').'</p>

                        <p class="infoModalText"> <b>'.__('Crea il tuo account','woocommerce-gateway-splittypay-step-checkout').'</b> </p>
                        <p class="infoModalText infoModalMarginBottom">'.__('in pochi, semplici passi','woocommerce-gateway-splittypay-step-checkout').'</p>

                        <p class="infoModalText">'.__('Paga il tuo acquisto in','woocommerce-gateway-splittypay-step-checkout').'</p>
                        <p class="infoModalText infoModalMarginBottom"> <b>'.__('3 comode rate ad interessi 0!','woocommerce-gateway-splittypay-step-checkout').'</b> </p>

                        <div style="display: flex; justify-content: center; width: 100%;">
                            <img style="width: 150px; cursor: pointer; margin: 0 auto;" src="https://splittypay-attachments-prod.s3.eu-west-1.amazonaws.com/splittypay_logo.png" onclick="on_product_info_click()">
                        </div>
                    </div>
                </div>
                <script> 
                    function on_product_info_click() {
                        el = document.getElementById("infoModal");
                        el.style.display = "flex";
                    }

                    function on_product_info_close() {
                        el = document.getElementById("infoModal");
                        el.style.display = "none";
                    }
                </script>
                <style>
                    #infoModal {
                        width: 100%;
                        height: 100%;
                        opacity: 1;
                        position: fixed;
                        top: 0;
                        left: 0;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        z-index: 1000000;
                        background-color: rgb(0,0,0,0.7);
                    }

                    #infoModalDialog {
                        max-width: 90%;
                        padding: 32px; 
                        box-sizing: border-box;
                        background-color: #c1f3ff;
                        background-image: url("https://splittypay-attachments-prod.s3.eu-west-1.amazonaws.com/sp-hero-bg-1.png");
                        background-size: cover;
                        background-position: center;
                        position: relative;
                    }

                    .infoModalText {
                        text-align: center;
                        color: black;
                        margin-bottom: 0;
                    }

                    .infoModalMarginBottom {
                        margin-bottom: 24px !important;
                    }
                </style>';
        }
    }
    add_action( $product_hooks[$Splittypay->productlabelpos] , 'wc_splittypay_step_gateway_add_product_desc', 10 );
}
