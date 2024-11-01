<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Shiptor_Admin_Help {

    public function __construct() {
        add_action('current_screen', array($this, 'add_tabs'), 51);
    }

    public function add_tabs($screen) {

        if (!$screen || 'woocommerce_page_wc-settings' !== $screen->id) {
            return;
        }

        $screen->add_option('shiptor', array(
            'text' => 'TEXT'
        ));

        $screen->add_help_tab(array(
            'id' => 'shiptor_instruction_tab',
            'title' =>  esc_html__('Shiptor instructions', 'woocommerce-shiptor'),
            'content' => __return_empty_string(),
            'callback' => array($this, 'instruction_tab'),
            'priority' => 90
        ));

        $screen->set_help_sidebar($this->get_sidebar($screen));
    }

    public function instruction_tab($screen, $tab) {
        echo esc_html('Return Shiptor instruction here!');
    }

    private function get_sidebar($screen) {

        $content = array($screen->get_help_sidebar());

        $links = array(
            'about' => array(
                'link' => 'https://shiptor.ru/services/aggregator',
                'title' =>  esc_html__('About Shiptor', 'woocommerce-shiptor')
            ),
            'tariffs' => array(
                'link' => 'https://shiptor.ru/rates',
                'title' =>  esc_html__('Tariffs Shiptor', 'woocommerce-shiptor')
            ),
            'services' => array(
                'link' => 'https://shiptor.ru/services',
                'title' =>  esc_html__('Additional services Shiptor', 'woocommerce-shiptor')
            )
        );

        foreach ($links as $target => $link) {
            $content[] = sprintf('<p><a href="%s" target="_blank">%s</a></p>', add_query_arg(array('utm_source' => 'helptab', 'utm_campaign' => 'woodev', 'utm_content' => $target), esc_url($link['link'])), esc_html($link['title']));
        }

        return implode('', $content);
    }

}

return new WC_Shiptor_Admin_Help();
