<?php

/**
 * Created by PhpStorm.
 * Author: Maksim Martirosov
 * Date: 27.11.2017
 * Time: 23:29
 * Project: shiptor-woo
 */
if (!defined('ABSPATH')) {
    exit;
}

class WC_Shiptor_Tracking_Email extends WC_Email {

    public function __construct() {
        $this->id = 'shiptor_tracking';
        $this->title =  esc_html__('Shiptor Tracking Code', 'woocommerce-shiptor');
        $this->customer_email = true;
        $this->description =  esc_html__('This email is sent when configured a tracking code within an order.', 'woocommerce-shiptor');
        $this->heading =  esc_html__('Your order has been sent', 'woocommerce-shiptor');
        $this->subject =  esc_html__('[{site_title}] Your order {order_number} has been sent by Shiptor', 'woocommerce-shiptor');
        $this->message =  esc_html__('Hi there. Your recent order on {site_title} has been sent by Shiptor.', 'woocommerce-shiptor')
                . PHP_EOL . ' ' . PHP_EOL
                .  esc_html__('To track your delivery, use the following the tracking code(s): {tracking_code}', 'woocommerce-shiptor')
                . PHP_EOL . ' ' . PHP_EOL
                .  esc_html__('The delivery service is the responsibility of the Shiptor, but if you have any questions, please contact us.', 'woocommerce-shiptor');
        $this->tracking_message = $this->get_option('tracking_message', $this->message);
        $this->template_html = 'emails/shiptor-tracking-code.php';
        $this->template_plain = 'emails/plain/shiptor-tracking-code.php';

        parent::__construct();

        $this->template_base = WC_Shiptor::get_templates_path();
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' =>  esc_html__('Enable/Disable', 'woocommerce-shiptor'),
                'type' => 'checkbox',
                'label' =>  esc_html__('Enable this email notification', 'woocommerce-shiptor'),
                'default' => 'yes',
            ),
            'subject' => array(
                'title' =>  esc_html__('Subject', 'woocommerce-shiptor'),
                'type' => 'text',
                'description' => sprintf( esc_html__('This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'woocommerce-shiptor'), $this->subject),
                'placeholder' => $this->subject,
                'default' => '',
                'desc_tip' => true,
            ),
            'heading' => array(
                'title' =>  esc_html__('Email Heading', 'woocommerce-shiptor'),
                'type' => 'text',
                'description' => sprintf( esc_html__('This controls the main heading contained within the email. Leave blank to use the default heading: <code>%s</code>.', 'woocommerce-shiptor'), $this->heading),
                'placeholder' => $this->heading,
                'default' => '',
                'desc_tip' => true,
            ),
            'tracking_message' => array(
                'title' =>  esc_html__('Email Content', 'woocommerce-shiptor'),
                'type' => 'textarea',
                'description' => sprintf( esc_html__('This controls the initial content of the email. Leave blank to use the default content: <code>%s</code>.', 'woocommerce-shiptor'), $this->message),
                'placeholder' => $this->message,
                'default' => '',
                'desc_tip' => true,
            ),
            'email_type' => array(
                'title' =>  esc_html__('Email type', 'woocommerce-shiptor'),
                'type' => 'select',
                'description' =>  esc_html__('Choose which format of email to send.', 'woocommerce-shiptor'),
                'default' => 'html',
                'class' => 'email_type wc-enhanced-select',
                'options' => $this->get_custom_email_type_options(),
                'desc_tip' => true,
            ),
        );
    }

    protected function get_custom_email_type_options() {
        if (method_exists($this, 'get_email_type_options')) {
            return $this->get_email_type_options();
        }

        $types = array('plain' =>  esc_html__('Plain text', 'woocommerce-shiptor'));

        if (class_exists('DOMDocument')) {
            $types['html'] =  esc_html__('HTML', 'woocommerce-shiptor');
            $types['multipart'] =  esc_html__('Multipart', 'woocommerce-shiptor');
        }

        return $types;
    }

    public function get_tracking_message() {
        return apply_filters('woocommerce_shiptor_email_tracking_message', $this->format_string($this->tracking_message), $this->object);
    }

    public function get_tracking_code_url($tracking_code) {
        //$url = sprintf('<a href="%s#wc-shiptor-tracking">%s</a>', $this->object->get_view_order_url(), $tracking_code);
        $url = sprintf('<a href="https://shiptor.ru/tracking?tracking=%s">%s</a>', $tracking_code,  esc_html__('Shiptor Tracking: ', 'woocommerce-shiptor').$tracking_code);

        return apply_filters('woocommerce_shiptor_email_tracking_core_url', $url, $tracking_code, $this->object);
    }

    public function get_tracking_account_url() {
        $url = sprintf('<a href="%s#wc-shiptor-tracking">%s</a>', esc_url($this->object->get_view_order_url()),  esc_html__('Personal account', 'woocommerce-shiptor'));

        return apply_filters('woocommerce_shiptor_email_tracking_core_url', $url, $this->object->get_view_order_url(), $this->object);
    }

    public function get_tracking_codes($tracking_codes) {
        $html = '<ul>';
        $html .= '<li>' . $this->get_tracking_account_url() . '</li>';
        foreach ($tracking_codes as $tracking_code) {
            $html .= '<li>' . $this->get_tracking_code_url($tracking_code) . '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    public function trigger($order_id, $order = false, $tracking_code = '') {
        if ($order_id && !is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }

        if (is_object($order)) {
            $this->object = $order;

            if (method_exists($order, 'get_billing_email')) {
                $this->recipient = $order->get_billing_email();
            } else {
                $this->recipient = $order->billing_email;
            }

            $this->find[] = '{order_number}';
            $this->replace[] = $order->get_order_number();

            $this->find[] = '{date}';
            $this->replace[] = date_i18n(wc_date_format(), time());

            if (empty($tracking_code)) {
                $tracking_codes = wc_shiptor_get_tracking_codes($order);
            } else {
                $tracking_codes = array($tracking_code);
            }

            $this->find[] = '{tracking_code}';
            $this->replace[] = $this->get_tracking_codes($tracking_codes);

            if (!$this->get_recipient()) {
                return;
            }

            if ($tracking_codes) {
                $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
                $order->add_order_note(sprintf(__('%s email notification manually sent.', 'woocommerce-shiptor'), $this->title), false, true);
            }
        }
    }

    public function get_content_html() {
        ob_start();

        wc_get_template($this->template_html, array(
            'order' => $this->object,
            'email_heading' => $this->get_heading(),
            'tracking_message' => $this->get_tracking_message(),
            'sent_to_admin' => false,
            'plain_text' => false,
            'email' => $this,
                ), '', $this->template_base);

        return ob_get_clean();
    }

    public function get_content_plain() {
        ob_start();

        // Format list.
        $message = $this->get_tracking_message();
        $message = str_replace('<ul>', "\n", $message);
        $message = str_replace('<li>', "\n - ", $message);
        $message = str_replace(array('</ul>', '</li>'), '', $message);

        wc_get_template($this->template_plain, array(
            'order' => $this->object,
            'email_heading' => $this->get_heading(),
            'tracking_message' => $message,
            'sent_to_admin' => false,
            'plain_text' => true,
            'email' => $this,
                ), '', $this->template_base);

        return ob_get_clean();
    }

}

return new WC_Shiptor_Tracking_Email();
