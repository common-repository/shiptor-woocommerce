<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Admin_Shiptor_Setup_Wizard extends WC_Admin_Setup_Wizard {

    private $step = '';
    private $steps = array();

    public function __construct() {
        if (current_user_can('manage_woocommerce')) {
            add_action('admin_menu', array($this, 'admin_menus'));
            add_action('admin_init', array($this, 'setup_wizard'));
        }
    }

    public function admin_menus() {
        add_dashboard_page('', '', 'manage_options', 'shiptor-setup', '');
    }

    public function setup_wizard() {
        if (empty($_GET['page']) || 'shiptor-setup' !== $_GET['page']) {
            return;
        }

        $default_steps = array(
            'shiptor_setup' => array(
                'name' =>  esc_html__('Shiptor setup', 'woocommerce-shiptor'),
                'view' => array($this, 'shiptor_setup'),
                'handler' => array($this, 'shiptor_setup_save'),
            ),
            'shipping' => array(
                'name' =>  esc_html__('Shipping', 'woocommerce-shiptor'),
                'view' => array($this, 'setup_shipping'),
                'handler' => array($this, 'setup_shipping_save'),
            ),
            'activate' => array(
                'name' =>  esc_html__('Activate', 'woocommerce-shiptor'),
                'view' => array($this, 'setup_activate'),
                'handler' => array($this, 'setup_activate_save'),
            ),
            'next_steps' => array(
                'name' =>  esc_html__('Ready!', 'woocommerce-shiptor'),
                'view' => array($this, 'setup_ready'),
                'handler' => '',
            ),
        );

        $this->steps = $default_steps;
        $this->step = isset($_GET['step']) ? sanitize_key($_GET['step']) : current(array_keys($this->steps));

        if (!empty($_POST['save_step']) && isset($this->steps[$this->step]['handler'])) {
            call_user_func($this->steps[$this->step]['handler'], $this);
        }

        ob_start();
        $this->setup_wizard_header();
        $this->setup_wizard_steps();
        $this->setup_wizard_content();
        $this->setup_wizard_footer();
        exit;
    }

    public function shiptor_setup() {

    }

    public function setup_shipping() {

    }

    public function setup_activate() {

    }

    public function setup_ready() {

    }

    public function shiptor_setup_save() {

    }

    public function setup_shipping_save() {

    }

    public function setup_activate_save() {

    }

}

new WC_Admin_Shiptor_Setup_Wizard();
?>