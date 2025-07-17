<?php
/*
Plugin Name: Telegram Notifications
Description: Sends notifications to Telegram for new comments, contact form submissions, and WooCommerce orders.
Version: 1.0
Author: DARTHARTH
Author URI: https://dartharth.top
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add settings page to admin menu
add_action('admin_menu', 'tg_notifications_menu');
function tg_notifications_menu() {
    add_options_page(
        'Telegram Notifications Settings',
        'Telegram Notifications',
        'manage_options',
        'tg-notifications',
        'tg_notifications_settings_page'
    );
}

// Register settings
add_action('admin_init', 'tg_notifications_register_settings');
function tg_notifications_register_settings() {
    register_setting('tg_notifications_group', 'tg_notifications_settings', 'tg_notifications_sanitize');
    
    add_settings_section(
        'tg_notifications_main',
        'Telegram Notifications Settings',
        null,
        'tg-notifications'
    );

    add_settings_field(
        'tg_api_key',
        'Telegram Bot API Key',
        'tg_api_key_callback',
        'tg-notifications',
        'tg_notifications_main'
    );

    add_settings_field(
        'tg_chat_id',
        'Telegram Chat ID',
        'tg_chat_id_callback',
        'tg-notifications',
        'tg_notifications_main'
    );

    add_settings_field(
        'tg_comments_enabled',
        'Enable Comment Notifications',
        'tg_comments_enabled_callback',
        'tg-notifications',
        'tg_notifications_main'
    );

    add_settings_field(
        'tg_contact_form_enabled',
        'Enable Contact Form Notifications',
        'tg_contact_form_enabled_callback',
        'tg-notifications',
        'tg_notifications_main'
    );

    add_settings_field(
        'tg_contact_form_ids',
        'Contact Forms to Track',
        'tg_contact_form_ids_callback',
        'tg-notifications',
        'tg_notifications_main'
    );

    add_settings_field(
        'tg_woocommerce_enabled',
        'Enable WooCommerce Notifications',
        'tg_woocommerce_enabled_callback',
        'tg-notifications',
        'tg_notifications_main'
    );
}

// Sanitize settings
function tg_notifications_sanitize($input) {
    $new_input = array();
    $new_input['tg_api_key'] = sanitize_text_field($input['tg_api_key']);
    $new_input['tg_chat_id'] = sanitize_text_field($input['tg_chat_id']);
    $new_input['tg_comments_enabled'] = isset($input['tg_comments_enabled']) ? 1 : 0;
    $new_input['tg_contact_form_enabled'] = isset($input['tg_contact_form_enabled']) ? 1 : 0;
    $new_input['tg_contact_form_ids'] = isset($input['tg_contact_form_ids']) ? array_map('sanitize_text_field', (array)$input['tg_contact_form_ids']) : array();
    $new_input['tg_woocommerce_enabled'] = isset($input['tg_woocommerce_enabled']) ? 1 : 0;
    return $new_input;
}

// Settings fields callbacks
function tg_api_key_callback() {
    $options = get_option('tg_notifications_settings');
    $api_key = isset($options['tg_api_key']) ? $options['tg_api_key'] : '';
    echo "<input type='text' name='tg_notifications_settings[tg_api_key]' value='$api_key' size='50'>";
}

function tg_chat_id_callback() {
    $options = get_option('tg_notifications_settings');
    $chat_id = isset($options['tg_chat_id']) ? $options['tg_chat_id'] : '';
    echo "<input type='text' name='tg_notifications_settings[tg_chat_id]' value='$chat_id' size='50'>";
}

function tg_comments_enabled_callback() {
    $options = get_option('tg_notifications_settings');
    $checked = isset($options['tg_comments_enabled']) && $options['tg_comments_enabled'] ? 'checked' : '';
    echo "<input type='checkbox' name='tg_notifications_settings[tg_comments_enabled]' value='1' $checked>";
}

function tg_contact_form_enabled_callback() {
    $options = get_option('tg_notifications_settings');
    $checked = isset($options['tg_contact_form_enabled']) && $options['tg_contact_form_enabled'] ? 'checked' : '';
    echo "<input type='checkbox' name='tg_notifications_settings[tg_contact_form_enabled]' value='1' $checked>";
}

function tg_contact_form_ids_callback() {
    $options = get_option('tg_notifications_settings');
    $selected_forms = isset($options['tg_contact_form_ids']) ? (array)$options['tg_contact_form_ids'] : array();
    
    // Get all Contact Form 7 forms
    if (post_type_exists('wpcf7_contact_form')) {
        $forms = get_posts(array(
            'post_type' => 'wpcf7_contact_form',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        if ($forms) {
            echo '<p>Select the contact forms to track:</p>';
            foreach ($forms as $form) {
                $checked = in_array($form->ID, $selected_forms) ? 'checked' : '';
                echo "<label><input type='checkbox' name='tg_notifications_settings[tg_contact_form_ids][]' value='{$form->ID}' $checked> " . esc_html($form->post_title) . " (ID: {$form->ID})</label><br>";
            }
        } else {
            echo '<p>No Contact Form 7 forms found.</p>';
        }
    } else {
        echo '<p>Contact Form 7 is not installed or activated.</p>';
    }
}

function tg_woocommerce_enabled_callback() {
    $options = get_option('tg_notifications_settings');
    $checked = isset($options['tg_woocommerce_enabled']) && $options['tg_woocommerce_enabled'] ? 'checked' : '';
    echo "<input type='checkbox' name='tg_notifications_settings[tg_woocommerce_enabled]' value='1' $checked>";
}

// Settings page HTML
function tg_notifications_settings_page() {
    ?>
    <div class="wrap">
        <h1>Telegram Notifications Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('tg_notifications_group');
            do_settings_sections('tg-notifications');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Function to send Telegram message
function tg_send_message($message) {
    $options = get_option('tg_notifications_settings');
    $api_key = isset($options['tg_api_key']) ? $options['tg_api_key'] : '';
    $chat_id = isset($options['tg_chat_id']) ? $options['tg_chat_id'] : '';

    if (empty($api_key) || empty($chat_id)) {
        return;
    }

    $url = "https://api.telegram.org/bot$api_key/sendMessage";
    $data = array(
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    );

    $response = wp_remote_post($url, array(
        'body' => $data,
        'timeout' => 15
    ));
}

// Comment notification
add_action('comment_post', 'tg_notify_new_comment', 10, 2);
function tg_notify_new_comment($comment_id, $comment_approved) {
    $options = get_option('tg_notifications_settings');
    if (isset($options['tg_comments_enabled']) && $options['tg_comments_enabled'] && $comment_approved === 1) {
        $site_name = get_bloginfo('name');
        $message = "A new comment has been added to the site.*$site_name*.";
        tg_send_message($message);
    }
}

// Contact Form 7 notification
add_action('wpcf7_mail_sent', 'tg_notify_contact_form');
function tg_notify_contact_form($contact_form) {
    $options = get_option('tg_notifications_settings');
    if (isset($options['tg_contact_form_enabled']) && $options['tg_contact_form_enabled']) {
        $form_id = $contact_form->id();
        $selected_forms = isset($options['tg_contact_form_ids']) ? (array)$options['tg_contact_form_ids'] : array();
        
        if (in_array($form_id, $selected_forms)) {
            $site_name = get_bloginfo('name');
            $message = "There is a new message on the *$site_name* website in the feedback form.";
            tg_send_message($message);
        }
    }
}

// WooCommerce new order notification
add_action('woocommerce_new_order', 'tg_notify_new_order', 10, 1);
function tg_notify_new_order($order_id) {
    $options = get_option('tg_notifications_settings');
    if (isset($options['tg_woocommerce_enabled']) && $options['tg_woocommerce_enabled']) {
        $order = wc_get_order($order_id);
        $site_name = get_bloginfo('name');
        $order_number = $order->get_order_number();
        $total = $order->get_total();
        $currency = $order->get_currency();
        
        // Get customer details
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $phone = $order->get_billing_phone();
        $email = $order->get_billing_email();
        
        // Get shipping address components
        $address_parts = array();
        $address_fields = array(
            'address_1' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
            'address_2' => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
            'city' => $order->get_shipping_city() ?: $order->get_billing_city(),
            'state' => $order->get_shipping_state() ?: $order->get_billing_state(),
            'postcode' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
            'country' => $order->get_shipping_country() ?: $order->get_billing_country()
        );
        
        // Build clean address string
        foreach ($address_fields as $field => $value) {
            if (!empty($value)) {
                if ($field === 'country') {
                    $countries = WC()->countries->get_countries();
                    $value = isset($countries[$value]) ? $countries[$value] : $value;
                } elseif ($field === 'state') {
                    $states = WC()->countries->get_states($order->get_shipping_country() ?: $order->get_billing_country());
                    $value = isset($states[$value]) ? $states[$value] : $value;
                }
                $address_parts[] = $value;
            }
        }
        $address = implode(', ', array_filter($address_parts));
        
        // Get product names
        $items = $order->get_items();
        $product_names = array();
        foreach ($items as $item) {
            $product_names[] = $item->get_name();
        }
        $products_list = implode(', ', $product_names);
        
        // Format message
        $message = "Order №$order_number\n";
        $message .= "$products_list - $total $currency\n";
        $message .= "-------------------------------\n";
        $message .= "Name: $customer_name\n";
        $message .= "Address: $address\n";
        $message .= "Phone: $phone\n";
        $message .= "Email: $email";
        
        tg_send_message($message);
    }
}
