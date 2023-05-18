<?php

/**
 * Plugin Name: IDPay gateway - Gravity Forms
 * Author: IDPay
 * Description: <a href="https://idpay.ir">IDPay</a> secure payment gateway for Gravity Forms.
 * Version: 1.1.2
 * Author URI: https://idpay.ir
 * Author Email: info@idpay.ir
 * Text Domain: idpay-gravity-forms
 */

if (! defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, array( 'GF_Gateway_IDPay', "add_permissions" ));
register_deactivation_hook(__FILE__, array( 'GF_Gateway_IDPay', "deactivation" ));

add_action('init', array( 'GF_Gateway_IDPay', 'init' ));

require_once('lib/IDPay_DB.php');
require_once('lib/IDPay_Chart.php');

class GF_Gateway_IDPay
{
    public static $author = "IDPay";
    private static $version = "1.0.5";
    private static $min_gravityforms_version = "1.9.10";
    private static $config = null;

    public static function alter_submit_button($button_input, $form)
    {
        $has_product = false;
        if (isset($form["fields"])) {
            foreach ($form["fields"] as $field) {
                $shipping_field = GFAPI::get_fields_by_type($form, array( 'shipping' ));
                if ($field["type"] == "product" || ! empty($shipping_field)) {
                    $has_product = true;
                    break;
                }
            }
        }

        $config = self::get_active_config($form);

        if ($has_product && ! empty($config)) {
            $button_input .= sprintf(
                '<div id="idpay-pay-id-%1$s" class="idpay-logo" style="font-size: 14px;padding: 5px 0;"><img src="%2$s" style="display: inline-block;vertical-align: middle;width: 70px;">%3$s</div>',
                $form['id'],
                plugins_url('/assets/logo.svg', __FILE__),
                __('پرداخت امن با آیدی پی', 'gravityformsIDPay')
            );
            $button_input .=
                "<script>
                gform.addAction('gform_post_conditional_logic_field_action', function (formId, action, targetId, defaultValues, isInit) {
                    gf_do_action(action, '#idpay-pay-id-'+ formId, true, defaultValues, isInit, null, formId);
                });
            </script>";
        }

        return $button_input;
    }

    private static $domain = "gravityformsIDPay";

    public static function init()
    {
        $condition1 = ! class_exists("GFPersian_Payments");
        $condition2 = ! defined('GF_PERSIAN_VERSION');
        $condition3 = version_compare(GF_PERSIAN_VERSION, '2.3.1', '<');

        if ($condition1 || $condition2 || $condition3) {
            add_action('admin_notices', array( __CLASS__, 'admin_notice_persian_gf' ));

            return false;
        }

        if (! self::is_gravityforms_supported()) {
            add_action('admin_notices', array( __CLASS__, 'admin_notice_gf_support' ));

            return false;
        }

        add_filter('members_get_capabilities', array( __CLASS__, "members_get_capabilities" ));

        if (is_admin() && self::has_access()) {
            add_filter('gform_tooltips', array( __CLASS__, 'tooltips' ));
            add_filter('gform_addon_navigation', array( __CLASS__, 'menu' ));
            add_action('gform_entry_info', array( __CLASS__, 'payment_entry_detail' ), 4, 2);
            add_action('gform_after_update_entry', array( __CLASS__, 'update_payment_entry' ), 4, 2);

            if (get_option("gf_IDPay_configured")) {
                add_filter('gform_form_settings_menu', array( __CLASS__, 'toolbar' ), 10, 2);
                add_action('gform_form_settings_page_IDPay', array( __CLASS__, 'feed_page' ));
            }

            if (rgget("page") == "gf_settings") {
                RGForms::add_settings_page(array(
                    'name'      => 'gf_IDPay',
                    'tab_label' => __('درگاه IDPay', 'gravityformsIDPay'),
                    'title'     => __('تنظیمات درگاه IDPay', 'gravityformsIDPay'),
                    'handler'   => array( __CLASS__, 'settings_page' ),
                ));
            }

            if (self::is_IDPay_page()) {
                wp_enqueue_script(array( "sack" ));
                self::setup();
            }

            add_action('wp_ajax_gf_IDPay_update_feed_active', array( __CLASS__, 'update_feed_active' ));
        }
        if (get_option("gf_IDPay_configured")) {
            add_filter("gform_disable_post_creation", array( __CLASS__, "delay_posts" ), 10, 3);
            add_filter("gform_is_delayed_pre_process_feed", array( __CLASS__, "delay_addons" ), 10, 4);
            add_filter("gform_confirmation", array( __CLASS__, "Request" ), 1000, 4);
            add_action('wp', array( __CLASS__, 'Verify' ), 5);
            add_filter("gform_submit_button", array( __CLASS__, "alter_submit_button" ), 10, 2);
        }

        add_filter("gform_logging_supported", array( __CLASS__, "set_logging_supported" ));
        add_filter('gf_payment_gateways', array( __CLASS__, 'gravityformsIDPay' ), 2);
        do_action('gravityforms_gateways');
        do_action('gravityforms_IDPay');
        add_filter('gform_admin_pre_render', array( __CLASS__, 'merge_tags_keys' ));

        return 'completed';
    }

    private static function is_gravityforms_supported(): bool|int
    {
        return class_exists("GFCommon")
            ? version_compare(GFCommon::$version, self::$min_gravityforms_version, ">=")
            : false;
    }

    protected static function has_access($required_permission = 'gravityforms_IDPay')
    {
        if (! function_exists('wp_get_current_user')) {
            include(ABSPATH . "wp-includes/pluggable.php");
        }

        return GFCommon::current_user_can_any($required_permission);
    }

    private static function is_IDPay_page()
    {

        $current_page    = in_array(trim(rgget("page")), array( 'gf_IDPay', 'IDPay' ));
        $current_view    = in_array(trim(rgget("view")), array( 'gf_IDPay', 'IDPay' ));
        $current_subview = in_array(trim(rgget("subview")), array( 'gf_IDPay', 'IDPay' ));

        return $current_page || $current_view || $current_subview;
    }

    private static function setup()
    {
        if (get_option("gf_IDPay_version") != self::$version) {
            IDPay_DB::update_table();
            update_option("gf_IDPay_version", self::$version);
        }
    }

    private static function deactivation()
    {
        delete_option("gf_IDPay_version");
    }

    public static function admin_notice_persian_gf()
    {
        $class   = 'notice notice-error';
        $message = sprintf(__("برای استفاده از نسخه جدید درگاه پرداخت آیدی پی برای گرویتی فرم، نصب بسته فارسی ساز نسخه 2.3.1 به بالا الزامی است. برای نصب فارسی ساز %sکلیک کنید%s.", "gravityformsIDPay"), '<a href="' . admin_url("plugin-install.php?tab=plugin-information&plugin=persian-gravity-forms&TB_iframe=true&width=772&height=884") . '">', '</a>');
        printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
    }

    public static function admin_notice_gf_support()
    {
        $class   = 'notice notice-error';
        $message = sprintf(__("درگاه IDPay نیاز به گرویتی فرم نسخه %s به بالا دارد. برای بروز رسانی هسته گرویتی فرم به %sسایت گرویتی فرم فارسی%s مراجعه نمایید .", "gravityformsIDPay"), self::$min_gravityforms_version, "<a href='http://gravityforms.ir/11378' target='_blank'>", "</a>");
        printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
    }

    public static function gravityformsIDPay($form, $entry)
    {
        $IDPay = array(
            'class' => ( __CLASS__ . '|' . self::$author ),
            'title' => __('IDPay', 'gravityformsIDPay'),
            'param' => array(
                'email'  => __('ایمیل', 'gravityformsIDPay'),
                'mobile' => __('موبایل', 'gravityformsIDPay'),
                'desc'   => __('توضیحات', 'gravityformsIDPay')
            )
        );

        return apply_filters(self::$author . '_gf_IDPay_detail', apply_filters(self::$author . '_gf_gateway_detail', $IDPay, $form, $entry), $form, $entry);
    }

    public static function add_permissions()
    {
        global $wp_roles;
        $editable_roles = get_editable_roles();
        foreach ((array) $editable_roles as $role => $details) {
            if ($role == 'administrator' || in_array('gravityforms_edit_forms', $details['capabilities'])) {
                $wp_roles->add_cap($role, 'gravityforms_IDPay');
                $wp_roles->add_cap($role, 'gravityforms_IDPay_uninstall');
            }
        }
    }

    public static function members_get_capabilities($caps)
    {
        return array_merge($caps, array( "gravityforms_IDPay", "gravityforms_IDPay_uninstall" ));
    }

    public static function tooltips($tooltips)
    {
        $tooltips["gateway_name"] = __("تذکر مهم : این قسمت برای نمایش به بازدید کننده می باشد و لطفا جهت جلوگیری از مشکل و تداخل آن را فقط یکبار تنظیم نمایید و از تنظیم مکرر آن خود داری نمایید .", "gravityformsIDPay");

        return $tooltips;
    }

    public static function menu($menus)
    {
        $permission = "gravityforms_IDPay";
        if (! empty($permission)) {
            $menus[] = array(
                "name"       => "gf_IDPay",
                "label"      => __("IDPay", "gravityformsIDPay"),
                "callback"   => array( __CLASS__, "IDPay_page" ),
                "permission" => $permission
            );
        }

        return $menus;
    }

    public static function toolbar($menu_items)
    {
        $menu_items[] = array(
            'name'  => 'IDPay',
            'label' => __('IDPay', 'gravityformsIDPay')
        );

        return $menu_items;
    }

    public static function set_logging_supported($plugins)
    {
        $plugins[ basename(dirname(__FILE__)) ] = "IDPay";

        return $plugins;
    }

    public static function delay_posts($is_disabled, $form, $entry)
    {

        $config = self::get_active_config($form);

        if (! empty($config) && is_array($config) && $config) {
            return true;
        }

        return $is_disabled;
    }

    public static function get_active_config($form)
    {

        if (! empty(self::$config)) {
            return self::$config;
        }

        $configs = IDPay_DB::get_feed_by_form($form["id"], true);

        $configs = apply_filters(self::$author . '_gf_IDPay_get_active_configs', apply_filters(self::$author . '_gf_gateway_get_active_configs', $configs, $form), $form);

        $return = false;

        if (! empty($configs) && is_array($configs)) {
            foreach ($configs as $config) {
                if (self::has_IDPay_condition($form, $config)) {
                    $return = $config;
                }
                break;
            }
        }

        self::$config = apply_filters(self::$author . '_gf_IDPay_get_active_config', apply_filters(self::$author . '_gf_gateway_get_active_config', $return, $form), $form);

        return self::$config;
    }

    public static function has_IDPay_condition($form, $config)
    {

        if (empty($config['meta'])) {
            return false;
        }

        if (empty($config['meta']['IDPay_conditional_enabled'])) {
            return true;
        }

        if (! empty($config['meta']['IDPay_conditional_field_id'])) {
            $condition_field_ids = $config['meta']['IDPay_conditional_field_id'];
            if (! is_array($condition_field_ids)) {
                $condition_field_ids = array( '1' => $condition_field_ids );
            }
        } else {
            return true;
        }

        if (! empty($config['meta']['IDPay_conditional_value'])) {
            $condition_values = $config['meta']['IDPay_conditional_value'];
            if (! is_array($condition_values)) {
                $condition_values = array( '1' => $condition_values );
            }
        } else {
            $condition_values = array( '1' => '' );
        }

        if (! empty($config['meta']['IDPay_conditional_operator'])) {
            $condition_operators = $config['meta']['IDPay_conditional_operator'];
            if (! is_array($condition_operators)) {
                $condition_operators = array( '1' => $condition_operators );
            }
        } else {
            $condition_operators = array( '1' => 'is' );
        }

        $type = ! empty($config['meta']['IDPay_conditional_type']) ? strtolower($config['meta']['IDPay_conditional_type']) : '';
        $type = $type == 'all' ? 'all' : 'any';

        foreach ($condition_field_ids as $i => $field_id) {
            if (empty($field_id)) {
                continue;
            }

            $field = RGFormsModel::get_field($form, $field_id);
            if (empty($field)) {
                continue;
            }

            $value    = ! empty($condition_values[ '' . $i . '' ]) ? $condition_values[ '' . $i . '' ] : '';
            $operator = ! empty($condition_operators[ '' . $i . '' ]) ? $condition_operators[ '' . $i . '' ] : 'is';

            $is_visible     = ! RGFormsModel::is_field_hidden($form, $field, array());
            $field_value    = RGFormsModel::get_field_value($field, array());
            $is_value_match = RGFormsModel::is_value_match($field_value, $value, $operator);
            $check          = $is_value_match && $is_visible;

            if ($type == 'any' && $check) {
                return true;
            } elseif ($type == 'all' && ! $check) {
                return false;
            }
        }

        if ($type == 'any') {
            return false;
        } else {
            return true;
        }
    }

    public static function delay_addons($is_delayed, $form, $entry, $slug)
    {

        $config = self::get_active_config($form);

        if (! empty($config["meta"]) && is_array($config["meta"]) && $config = $config["meta"]) {
            $user_registration_slug = apply_filters('gf_user_registration_slug', 'gravityformsuserregistration');

            if ($slug != $user_registration_slug && ! empty($config["addon"]) && $config["addon"] == 'true') {
                $flag = true;
            } elseif ($slug == $user_registration_slug && ! empty($config["type"]) && $config["type"] == "subscription") {
                $flag = true;
            }

            if (! empty($flag)) {
                $fulfilled = gform_get_meta($entry['id'], $slug . '_is_fulfilled');
                $processed = gform_get_meta($entry['id'], 'processed_feeds');

                $is_delayed = empty($fulfilled) && rgempty($slug, $processed);
            }
        }

        return $is_delayed;
    }

    public static function checkSetPriceForForm($form, $formId): bool
    {
        $check = false;
        if (isset($form["fields"])) {
            foreach ($form["fields"] as $field) {
                $shipping_field = GFAPI::get_fields_by_type($form, array( 'shipping' ));
                if ($field["type"] == "product" || ! empty($shipping_field)) {
                    $check = true;
                }
            }

            return $check;
        } elseif (empty($formId)) {
            return true;
        }

        return false;
    }

    private static function get_form_fields($form)
    {
        $fields = array();
        if (is_array($form["fields"])) {
            foreach ($form["fields"] as $field) {
                if (isset($field["inputs"]) && is_array($field["inputs"])) {
                    foreach ($field["inputs"] as $input) {
                        $fields[] = array( $input["id"], GFCommon::get_label($field, $input["id"]) );
                    }
                } elseif (! rgar($field, 'displayOnly')) {
                    $fields[] = array( $field["id"], GFCommon::get_label($field) );
                }
            }
        }

        return $fields;
    }

    private static function get_mapped_field_list($field_name, $selected_field, $fields)
    {
        $str = "<select name='$field_name' id='$field_name'><option value=''></option>";
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $field_id    = $field[0];
                $field_label = esc_html(GFCommon::truncate_middle($field[1], 40));
                $selected    = $field_id == $selected_field ? "selected='selected'" : "";
                $str         .= "<option value='" . $field_id . "' " . $selected . ">" . $field_label . "</option>";
            }
        }
        $str .= "</select>";

        return $str;
    }

    public static function update_feed_active()
    {
        check_ajax_referer('gf_IDPay_update_feed_active', 'gf_IDPay_update_feed_active');
        $id   = absint(rgpost('feed_id'));
        $feed = IDPay_DB::get_feed($id);
        IDPay_DB::update_feed($id, $feed["form_id"], sanitize_text_field(rgpost("is_active")), $feed["meta"]);
    }

    public static function payment_entry_detail($form_id, $entry)
    {

        $payment_gateway = rgar($entry, "payment_method");

        if (! empty($payment_gateway) && $payment_gateway == "IDPay") {
            do_action('gf_gateway_entry_detail');

            ?>
            <hr/>
            <strong>
                <?php _e('اطلاعات تراکنش :', 'gravityformsIDPay') ?>
            </strong>
            <br/>
            <br/>
            <?php

            $transaction_type = rgar($entry, "transaction_type");
            $payment_status   = rgar($entry, "payment_status");
            $payment_amount   = rgar($entry, "payment_amount");

            if (empty($payment_amount)) {
                $form           = RGFormsModel::get_form_meta($form_id);
                $payment_amount = self::get_order_total($form, $entry);
            }

            $transaction_id = rgar($entry, "transaction_id");
            $payment_date   = rgar($entry, "payment_date");

            $date = new DateTime($payment_date);
            $tzb  = get_option('gmt_offset');
            $tzn  = abs($tzb) * 3600;
            $tzh  = intval(gmdate("H", $tzn));
            $tzm  = intval(gmdate("i", $tzn));

            if (intval($tzb) < 0) {
                $date->sub(new DateInterval('P0DT' . $tzh . 'H' . $tzm . 'M'));
            } else {
                $date->add(new DateInterval('P0DT' . $tzh . 'H' . $tzm . 'M'));
            }

            $payment_date = $date->format('Y-m-d H:i:s');
            $payment_date = GF_jdate('Y-m-d H:i:s', strtotime($payment_date), '', date_default_timezone_get(), 'en');

            if ($payment_status == 'Paid') {
                $payment_status_persian = __('موفق', 'gravityformsIDPay');
            }

            if ($payment_status == 'Active') {
                $payment_status_persian = __('موفق', 'gravityformsIDPay');
            }

            if ($payment_status == 'Cancelled') {
                $payment_status_persian = __('منصرف شده', 'gravityformsIDPay');
            }

            if ($payment_status == 'Failed') {
                $payment_status_persian = __('ناموفق', 'gravityformsIDPay');
            }

            if ($payment_status == 'Processing') {
                $payment_status_persian = __('معلق', 'gravityformsIDPay');
            }

            if (! strtolower(rgpost("save")) || RGForms::post("screen_mode") != "edit") {
                echo __('وضعیت پرداخت : ', 'gravityformsIDPay') . $payment_status_persian . '<br/><br/>';
                echo __('تاریخ پرداخت : ', 'gravityformsIDPay') . '<span style="">' . $payment_date . '</span><br/><br/>';
                echo __('مبلغ پرداختی : ', 'gravityformsIDPay') . GFCommon::to_money($payment_amount, rgar($entry, "currency")) . '<br/><br/>';
                echo __('کد رهگیری : ', 'gravityformsIDPay') . $transaction_id . '<br/><br/>';
                echo __('درگاه پرداخت : IDPay', 'gravityformsIDPay');
            } else {
                $payment_string = '';
                $payment_string .= '<select id="payment_status" name="payment_status">';
                $payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status_persian . '</option>';

                if ($transaction_type == 1) {
                    if ($payment_status != "Paid") {
                        $payment_string .= '<option value="Paid">' . __('موفق', 'gravityformsIDPay') . '</option>';
                    }
                }

                if ($transaction_type == 2) {
                    if ($payment_status != "Active") {
                        $payment_string .= '<option value="Active">' . __('موفق', 'gravityformsIDPay') . '</option>';
                    }
                }

                if (! $transaction_type) {
                    if ($payment_status != "Paid") {
                        $payment_string .= '<option value="Paid">' . __('موفق', 'gravityformsIDPay') . '</option>';
                    }

                    if ($payment_status != "Active") {
                        $payment_string .= '<option value="Active">' . __('موفق', 'gravityformsIDPay') . '</option>';
                    }
                }

                if ($payment_status != "Failed") {
                    $payment_string .= '<option value="Failed">' . __('ناموفق', 'gravityformsIDPay') . '</option>';
                }

                if ($payment_status != "Cancelled") {
                    $payment_string .= '<option value="Cancelled">' . __('منصرف شده', 'gravityformsIDPay') . '</option>';
                }

                if ($payment_status != "Processing") {
                    $payment_string .= '<option value="Processing">' . __('معلق', 'gravityformsIDPay') . '</option>';
                }

                $payment_string .= '</select>';

                echo __('وضعیت پرداخت :', 'gravityformsIDPay') . $payment_string . '<br/><br/>';
                ?>
                <div id="edit_payment_status_details" style="display:block">
                    <table>
                        <tr>
                            <td><?php _e('تاریخ پرداخت :', 'gravityformsIDPay') ?></td>
                            <td><input type="text" id="payment_date" name="payment_date"
                                       value="<?php echo $payment_date ?>"></td>
                        </tr>
                        <tr>
                            <td><?php _e('مبلغ پرداخت :', 'gravityformsIDPay') ?></td>
                            <td><input type="text" id="payment_amount" name="payment_amount"
                                       value="<?php echo $payment_amount ?>"></td>
                        </tr>
                        <tr>
                            <td><?php _e('شماره تراکنش :', 'gravityformsIDPay') ?></td>
                            <td><input type="text" id="IDPay_transaction_id" name="IDPay_transaction_id"
                                       value="<?php echo $transaction_id ?>"></td>
                        </tr>

                    </table>
                    <br/>
                </div>
                <?php
                echo __('درگاه پرداخت : IDPay (غیر قابل ویرایش)', 'gravityformsIDPay');
            }

            echo '<br/>';
        }
    }

    public static function get_order_total($form, $entry)
    {
        $total = GFCommon::get_order_total($form, $entry);
        $total = ( ! empty($total) && $total > 0 ) ? $total : 0;

        return apply_filters(self::$author . '_IDPay_get_order_total', apply_filters(self::$author . '_gateway_get_order_total', $total, $form, $entry), $form, $entry);
    }

    public static function update_payment_entry($form, $entry_id)
    {

        check_admin_referer('gforms_save_entry', 'gforms_save_entry');

        do_action('gf_gateway_update_entry');

        $entry = GFPersian_Payments::get_entry($entry_id);

        $payment_gateway = rgar($entry, "payment_method");

        if (empty($payment_gateway)) {
            return;
        }

        if ($payment_gateway != "IDPay") {
            return;
        }

        $payment_status = sanitize_text_field(rgpost("payment_status"));
        if (empty($payment_status)) {
            $payment_status = rgar($entry, "payment_status");
        }

        $payment_amount       = sanitize_text_field(rgpost("payment_amount"));
        $payment_transaction  = sanitize_text_field(rgpost("IDPay_transaction_id"));
        $payment_date_Checker = $payment_date = sanitize_text_field(rgpost("payment_date"));

        list( $date, $time ) = explode(" ", $payment_date);
        list( $Y, $m, $d ) = explode("-", $date);
        list( $H, $i, $s ) = explode(":", $time);
        $miladi = GF_jalali_to_gregorian($Y, $m, $d);

        $date         = new DateTime("$miladi[0]-$miladi[1]-$miladi[2] $H:$i:$s");
        $payment_date = $date->format('Y-m-d H:i:s');

        if (empty($payment_date_Checker)) {
            if (! empty($entry["payment_date"])) {
                $payment_date = $entry["payment_date"];
            } else {
                $payment_date = rgar($entry, "date_created");
            }
        } else {
            $payment_date = date("Y-m-d H:i:s", strtotime($payment_date));
            $date         = new DateTime($payment_date);
            $tzb          = get_option('gmt_offset');
            $tzn          = abs($tzb) * 3600;
            $tzh          = intval(gmdate("H", $tzn));
            $tzm          = intval(gmdate("i", $tzn));
            if (intval($tzb) < 0) {
                $date->add(new DateInterval('P0DT' . $tzh . 'H' . $tzm . 'M'));
            } else {
                $date->sub(new DateInterval('P0DT' . $tzh . 'H' . $tzm . 'M'));
            }
            $payment_date = $date->format('Y-m-d H:i:s');
        }

        global $current_user;
        $user_id   = 0;
        $user_name = __("مهمان", 'gravityformsIDPay');
        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id   = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        $entry["payment_status"] = $payment_status;
        $entry["payment_amount"] = $payment_amount;
        $entry["payment_date"]   = $payment_date;
        $entry["transaction_id"] = $payment_transaction;
        if ($payment_status == 'Paid' || $payment_status == 'Active') {
            $entry["is_fulfilled"] = 1;
        } else {
            $entry["is_fulfilled"] = 0;
        }
        GFAPI::update_entry($entry);

        $new_status = '';
        switch (rgar($entry, "payment_status")) {
            case "Active":
                $new_status = __('موفق', 'gravityformsIDPay');
                break;

            case "Paid":
                $new_status = __('موفق', 'gravityformsIDPay');
                break;

            case "Cancelled":
                $new_status = __('منصرف شده', 'gravityformsIDPay');
                break;

            case "Failed":
                $new_status = __('ناموفق', 'gravityformsIDPay');
                break;

            case "Processing":
                $new_status = __('معلق', 'gravityformsIDPay');
                break;
        }

        RGFormsModel::add_note($entry["id"], $user_id, $user_name, sprintf(__("اطلاعات تراکنش به صورت دستی ویرایش شد . وضعیت : %s - مبلغ : %s - کد رهگیری : %s - تاریخ : %s", "gravityformsIDPay"), $new_status, GFCommon::to_money($entry["payment_amount"], $entry["currency"]), $payment_transaction, $entry["payment_date"]));
    }

    public static function uninstall()
    {
        if (! self::has_access("gravityforms_IDPay_uninstall")) {
            die(__("شما مجوز کافی برای این کار را ندارید . سطح دسترسی شما پایین تر از حد مجاز است . ", "gravityformsIDPay"));
        }
        IDPay_DB::drop_tables();
        delete_option("gf_IDPay_settings");
        delete_option("gf_IDPay_configured");
        delete_option("gf_IDPay_version");
        $plugin = basename(dirname(__FILE__)) . "/index.php";
        deactivate_plugins($plugin);
        update_option('recently_activated', array( $plugin => time() ) + (array) get_option('recently_activated'));
    }

    public static function checkOneConfirmationExists($confirmation, $form, $entry, $ajax)
    {
        if (apply_filters('gf_IDPay_request_return', apply_filters('gf_gateway_request_return', false, $confirmation, $form, $entry, $ajax), $confirmation, $form, $entry, $ajax)) {
            return false;
        }

        return true;
    }

    public static function checkSubmittedForIDPay($formId)
    {
        if (RGForms::post("gform_submit") != $formId) {
            return false;
        }

        return true;
    }

    public static function checkConfigExists($form)
    {
        return ! empty(self::get_active_config($form));
    }

    private static function call_gateway_endpoint($url, $args)
    {
        $number_of_connection_tries = 4;
        while ($number_of_connection_tries) {
            $response = wp_safe_remote_post($url, $args);
            if (is_wp_error($response)) {
                $number_of_connection_tries --;
                continue;
            } else {
                break;
            }
        }

        return $response;
    }

    public static function Request($confirmation, $form, $entry, $ajax)
    {
        // do_action('gf_gateway_request_1', $confirmation, $form, $entry, $ajax);
        //  do_action('gf_IDPay_request_1', $confirmation, $form, $entry, $ajax);

        if (! self::checkOneConfirmationExists($confirmation, $form, $entry, $ajax)) {
            return $confirmation;
        }
        $entry_id = $entry['id'];

        if ($confirmation != 'custom') {
            if (! self::checkSubmittedForIDPay($form['id']) || ! self::checkConfigExists($form)) {
                return $confirmation;
            }

            $config = self::get_active_config($form);
            gform_update_meta($entry_id, 'IDPay_feed_id', $config['id']);
            gform_update_meta($entry_id, 'payment_type', 'form');
            gform_update_meta($entry_id, 'payment_gateway', self::get_gname());

            $transaction_type = $config["meta"]["type"] == "subscription" ? 2 : 1;
            $Amount           = self::get_order_total($form, $entry);
            $Amount           = apply_filters(self::$author . "_gform_form_gateway_price_{$form['id']}", apply_filters(self::$author . "_gform_form_gateway_price", $Amount, $form, $entry), $form, $entry);
            $Amount           = apply_filters(self::$author . "_gform_form_IDPay_price_{$form['id']}", apply_filters(self::$author . "_gform_form_IDPay_price", $Amount, $form, $entry), $form, $entry);
            $Amount           = apply_filters(self::$author . "_gform_gateway_price_{$form['id']}", apply_filters(self::$author . "_gform_gateway_price", $Amount, $form, $entry), $form, $entry);
            $Amount           = apply_filters(self::$author . "_gform_IDPay_price_{$form['id']}", apply_filters(self::$author . "_gform_IDPay_price", $Amount, $form, $entry), $form, $entry);

            if (empty($Amount) || ! $Amount || $Amount == 0) {
                unset($entry["payment_status"], $entry["is_fulfilled"], $entry["transaction_type"], $entry["payment_amount"], $entry["payment_date"]);
                $entry["payment_method"] = "IDPay";
                GFAPI::update_entry($entry);

                return self::redirect_confirmation(add_query_arg(array( 'no' => 'true' ), self::Return_URL($form['id'], $entry['id'])), $ajax);
            } else {
                $desc_pm = $config["meta"]["desc_pm"];
                $desc    = $config["meta"]["customer_fields_desc"];
                $mobile  = $config["meta"]["customer_fields_mobile"];
                $name    = $config["meta"]["customer_fields_name"];
                $email   = $config["meta"]["customer_fields_email"];

                $Desc1       = ! empty($desc_pm) ? str_replace([
                    '{entry_id}',
                    '{form_title}',
                    '{form_id}'
                ], [ $entry['id'], $form['title'], $form['id'] ], $desc_pm) : '';
                $Desc2       = ! empty($desc) ? rgpost('input_' . str_replace(".", "_", $desc)) : '';
                $Description = sanitize_text_field($Desc1 . ( ! empty($Desc1) && ! empty($Desc2) ? ' - ' : '' ) . $Desc2 . ' ');
                $Mobile      = ! empty($mobile) ? sanitize_text_field(rgpost('input_' . str_replace(".", "_", $mobile))) : '';
                $Name        = ! empty($name) ? sanitize_text_field(rgpost('input_' . str_replace(".", "_", $name))) : '';
                $Mail        = ! empty($email) ? sanitize_text_field(rgpost('input_' . str_replace(".", "_", $email))) : '';
            }
        } else {
            $Amount = gform_get_meta(rgar($entry, 'id'), 'IDPay_part_price_' . $form['id']);
            $Amount = apply_filters(self::$author . "_gform_custom_gateway_price_{$form['id']}", apply_filters(self::$author . "_gform_custom_gateway_price", $Amount, $form, $entry), $form, $entry);
            $Amount = apply_filters(self::$author . "_gform_custom_IDPay_price_{$form['id']}", apply_filters(self::$author . "_gform_custom_IDPay_price", $Amount, $form, $entry), $form, $entry);
            $Amount = apply_filters(self::$author . "_gform_gateway_price_{$form['id']}", apply_filters(self::$author . "_gform_gateway_price", $Amount, $form, $entry), $form, $entry);
            $Amount = apply_filters(self::$author . "_gform_IDPay_price_{$form['id']}", apply_filters(self::$author . "_gform_IDPay_price", $Amount, $form, $entry), $form, $entry);

            $Description = gform_get_meta(rgar($entry, 'id'), 'IDPay_part_desc_' . $form['id']);
            $Description = apply_filters(self::$author . '_gform_IDPay_gateway_desc_', apply_filters(self::$author . '_gform_custom_gateway_desc_', $Description, $form, $entry), $form, $entry);

            $Name   = gform_get_meta(rgar($entry, 'id'), 'IDPay_part_name_' . $form['id']);
            $Mail   = gform_get_meta(rgar($entry, 'id'), 'IDPay_part_email_' . $form['id']);
            $Mobile = gform_get_meta(rgar($entry, 'id'), 'IDPay_part_mobile_' . $form['id']);

            $entry_id = GFAPI::add_entry($entry);
            $entry    = GFPersian_Payments::get_entry($entry_id);

            do_action('gf_gateway_request_add_entry', $confirmation, $form, $entry, $ajax);
            do_action('gf_IDPay_request_add_entry', $confirmation, $form, $entry, $ajax);

            gform_update_meta($entry_id, 'payment_gateway', self::get_gname());
            gform_update_meta($entry_id, 'payment_type', 'custom');
        }

        unset($entry["transaction_type"]);
        unset($entry["payment_amount"]);
        unset($entry["payment_date"]);
        unset($entry["transaction_id"]);

        $entry["payment_status"]   = "Processing";
        $entry["payment_method"]   = "IDPay";
        $entry["is_fulfilled"]     = 0;
        $entry["transaction_type"] = $transaction_type;
        GFAPI::update_entry($entry);

        $entry      = GFPersian_Payments::get_entry($entry_id);
        $ReturnPath = self::Return_URL($form['id'], $entry_id);
        $Mobile     = GFPersian_Payments::fix_mobile($Mobile);

        do_action('gf_gateway_request_2', $confirmation, $form, $entry, $ajax);
        do_action('gf_IDPay_request_2', $confirmation, $form, $entry, $ajax);

        $Amount = GFPersian_Payments::amount($Amount, 'IRR', $form, $entry);
        if (empty($Amount) || ! $Amount || $Amount > 500000000 || $Amount < 1000) {
            $Message = __('مبلغ ارسالی اشتباه است.', 'gravityformsIDPay');
        } else {
            $data    = array(
                'order_id' => $entry_id,
                'amount'   => $Amount,
                'name'     => $Name,
                'mail'     => $Mail,
                'phone'    => $Mobile,
                'desc'     => $Description,
                'callback' => $ReturnPath,
            );
            $headers = array(
                'Content-Type' => 'application/json',
                'X-API-KEY'    => self::get_api_key(),
                'X-SANDBOX'    => self::get_sandbox(),
            );
            $args    = array(
                'body'    => json_encode($data),
                'headers' => $headers,
                'timeout' => 15,
            );

            $response    = self::call_gateway_endpoint('https://api.idpay.ir/v1.1/payment', $args);
            $http_status = wp_remote_retrieve_response_code($response);
            $result      = wp_remote_retrieve_body($response);
            $result      = json_decode($result);

            if (is_wp_error($response)) {
                $error   = $response->get_error_message();
                $Message = sprintf(__('خطا هنگام ایجاد تراکنش. پیام خطا: %s', 'gravityformsIDPay'), $error);
            } elseif ($http_status != 201 || empty($result) || empty($result->id) || empty($result->link)) {
                $Message = sprintf('خطا هنگام ایجاد تراکنش. : %s (کد خطا: %s)', $result->error_message, $result->error_code);
            } else {
                // save Transaction ID to Order
                gform_update_meta($entry_id, "IdpayTransactionId:$entry_id", $result->id);

                return self::redirect_confirmation($result->link, $ajax);
            }
        }

        $Message      = ! empty($Message) ? $Message : __('خطایی رخ داده است.', 'gravityformsIDPay');
        $confirmation = __('متاسفانه نمیتوانیم به درگاه متصل شویم. علت : ', 'gravityformsIDPay') . $Message;

        $entry                   = GFPersian_Payments::get_entry($entry_id);
        $entry['payment_status'] = 'Failed';
        GFAPI::update_entry($entry);

        global $current_user;
        $user_id   = 0;
        $user_name = __('مهمان', 'gravityformsIDPay');
        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id   = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        RGFormsModel::add_note($entry_id, $user_id, $user_name, sprintf(__('خطا در اتصال به درگاه رخ داده است : %s', "gravityformsIDPay"), $Message));
        GFPersian_Payments::notification($form, $entry);

        $anchor   = gf_apply_filters('gform_confirmation_anchor', $form['id'], 0) ? "<a id='gf_{$form['id']}' name='gf_{$form['id']}' class='gform_anchor' ></a>" : '';
        $nl2br    = ! empty($form['confirmation']) && rgar($form['confirmation'], 'disableAutoformat') ? false : true;
        $cssClass = rgar($form, 'cssClass');

        $output = "{$anchor} ";
        if (! empty($confirmation)) {
            $output .= "
                <div id='gform_confirmation_wrapper_{$form['id']}' class='gform_confirmation_wrapper {$cssClass}'>
                    <div id='gform_confirmation_message_{$form['id']}' class='gform_confirmation_message_{$form['id']} gform_confirmation_message'>" .
                       GFCommon::replace_variables($confirmation, $form, $entry, false, true, $nl2br) .
                       '</div>
                </div>';
        }
        $confirmation = $output;

        return $confirmation;
    }

    public static function get_gname()
    {
        $settings = get_option("gf_IDPay_settings");
        if (isset($settings["gname"])) {
            $gname = $settings["gname"];
        } else {
            $gname = __('IDPay', 'gravityformsIDPay');
        }

        return $gname;
    }

    private static function redirect_confirmation($url, $ajax)
    {
        if (headers_sent() || $ajax) {
            $confirmation = "<script type=\"text/javascript\">" . apply_filters('gform_cdata_open', '') . " function gformRedirect(){document.location.href='$url';}";
            if (! $ajax) {
                $confirmation .= 'gformRedirect();';
            }
            $confirmation .= apply_filters('gform_cdata_close', '') . '</script>';
        } else {
            $confirmation = array( 'redirect' => $url );
        }

        return $confirmation;
    }

    private static function Return_URL($form_id, $entry_id)
    {
        $pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

        if ($_SERVER['SERVER_PORT'] != '80') {
            $pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        $arr_params = array( 'id', 'entry', 'no', 'Authority', 'Status' );
        $pageURL    = esc_url(remove_query_arg($arr_params, $pageURL));

        $pageURL = str_replace('#038;', '&', add_query_arg(array(
            'form_id' => $form_id,
            'entry'   => $entry_id
        ), $pageURL));

        return apply_filters(self::$author . '_IDPay_return_url', apply_filters(self::$author . '_gateway_return_url', $pageURL, $form_id, $entry_id, __CLASS__), $form_id, $entry_id, __CLASS__);
    }

    private static function get_api_key()
    {
        $settings = get_option("gf_IDPay_settings");
        $api_key  = isset($settings["api_key"]) ? $settings["api_key"] : '';

        return trim($api_key);
    }

    private static function get_sandbox()
    {
        $settings = get_option("gf_IDPay_settings");

        return $settings["sandbox"] ? "true" : "false";
    }

    public static function isNotDoubleSpending($reference_id, $order_id, $transaction_id)
    {
        $relatedTransaction = gform_get_meta($reference_id, "IdpayTransactionId:$order_id", false);
        if (! empty($relatedTransaction)) {
            return $transaction_id == $relatedTransaction;
        }

        return false;
    }

    public static function Verify()
    {
        $condition1 = apply_filters('gf_gateway_IDPay_return', apply_filters('gf_gateway_verify_return', false));
        $condition2 = ! self::is_gravityforms_supported();
        $condition3 = ! is_numeric(rgget('form_id'));
        $condition4 = ! is_numeric(rgget('entry'));
        if ($condition1 || $condition2 || $condition3 || $condition4) {
            return;
        }

        $form_id  = (int) sanitize_text_field(rgget('form_id'));
        $entry_id = (int) sanitize_text_field(rgget('entry'));
        $entry    = GFPersian_Payments::get_entry($entry_id);

        $condition5 = is_wp_error($entry);
        $condition6 = ! empty($entry["payment_date"]);
        $condition7 = ! ( isset($entry["payment_method"]) );
        $condition8 = $entry["payment_method"] == 'IDPay';
        if ($condition5 || $condition6 || $condition7 || $condition8) {
            return;
        }

        $form         = RGFormsModel::get_form_meta($form_id);
        $payment_type = gform_get_meta($entry["id"], 'payment_type');

        gform_delete_meta($entry['id'], 'payment_type');

        if ($payment_type != 'custom') {
            $config = self::get_config_by_entry($entry);
            if (empty($config)) {
                return;
            }
        } else {
            $config = apply_filters(self::$author . '_gf_IDPay_config', apply_filters(self::$author . '_gf_gateway_config', array(), $form, $entry), $form, $entry);
        }

        global $current_user;
        $user_id   = 0;
        $user_name = __("مهمان", "gravityformsIDPay");
        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id   = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        $transaction_type = 1;
        if (! empty($config["meta"]["type"]) && $config["meta"]["type"] == 'subscription') {
            $transaction_type = 2;
        }

        if ($payment_type == 'custom') {
            $Amount = $Total = gform_get_meta($entry["id"], 'IDPay_part_price_' . $form_id);
        } else {
            $Amount = $Total = self::get_order_total($form, $entry);
            $Amount = GFPersian_Payments::amount($Amount, 'IRR', $form, $entry);
        }
        $Total_Money = GFCommon::to_money($Total, $entry["currency"]);

        $free = false;
        if (sanitize_text_field(rgget('no')) == 'true') {
            $Status         = 'completed';
            $free           = true;
            $Transaction_ID = apply_filters(self::$author . '_gf_rand_transaction_id', GFPersian_Payments::transaction_id($entry), $form, $entry);
        }

        $status_id = ! empty(rgpost('status')) ? rgpost('status') : ( ! empty(rgget('status')) ? rgget('status') : null );
        $track_id  = ! empty(rgpost('track_id')) ? rgpost('track_id') : ( ! empty(rgget('track_id')) ? rgget('track_id') : null );
        $id        = ! empty(rgpost('id')) ? rgpost('id') : ( ! empty(rgget('id')) ? rgget('id') : null );
        $order_id  = ! empty(rgpost('order_id')) ? rgpost('order_id') : ( ! empty(rgget('order_id')) ? rgget('order_id') : null );
        $params    = ! empty(rgpost('id')) ? $_POST : $_GET;

        $Transaction_ID = ! empty($Transaction_ID) ? $Transaction_ID : ( ! empty($track_id) ? $track_id : '-' );

        if (! $free && ! empty($id) && ! empty($order_id)) {
            if ($status_id == 10) {
                $pid       = sanitize_text_field($id);
                $porder_id = sanitize_text_field($order_id);

                if (! empty($pid) && ! empty($porder_id) && $porder_id == $entry_id &&
                     self::isNotDoubleSpending($entry["id"], $order_id, $id) == true) {
                    $__params = $Amount . $pid;
                    if (GFPersian_Payments::check_verification($entry, __CLASS__, $__params)) {
                        return;
                    }
                    $data     = array(
                        'id'       => $pid,
                        'order_id' => $entry_id
                    );
                    $headers  = array(
                        'Content-Type' => 'application/json',
                        'X-API-KEY'    => self::get_api_key(),
                        'X-SANDBOX'    => self::get_sandbox()
                    );
                    $args     = array(
                        'body'    => json_encode($data),
                        'headers' => $headers,
                        'timeout' => 15,
                    );
                    $response = self::call_gateway_endpoint('https://api.idpay.ir/v1.1/payment/verify', $args);

                    $http_status = wp_remote_retrieve_response_code($response);
                    $result      = wp_remote_retrieve_body($response);
                    $result      = json_decode($result);
                    $Note        = print_r($result, true);

                    if (is_wp_error($response) || $http_status != 200) {
                        $Status = 'Failed';
                    } else {
                        $verify_status   = empty($result->status) ? null : $result->status;
                        $verify_track_id = empty($result->track_id) ? null : $result->track_id;
                        $verify_amount   = empty($result->amount) ? null : $result->amount;
                        $Transaction_ID  = ! empty($verify_track_id) ? $verify_track_id : '-';

                        if (empty($verify_status) || $verify_status != 100 || empty($verify_track_id) || empty($verify_amount) || $verify_amount != $Amount) {
                            $Status = 'Failed';
                        } else {
                            $Status = 'completed';
                        }
                    }
                } else {
                    $Status = 'cancelled';
                }
            } else {
                $Status = 'Failed';
            }
        }

        $Status         = ! empty($Status) ? $Status : 'Failed';
        $transaction_id = ! empty($Transaction_ID) ? $Transaction_ID : '';
        $transaction_id = apply_filters(self::$author . '_gf_real_transaction_id', $transaction_id, $Status, $form, $entry);

        $entry["payment_date"]     = gmdate("Y-m-d H:i:s");
        $entry["transaction_id"]   = $transaction_id;
        $entry["transaction_type"] = $transaction_type;
        $status_code               = sanitize_text_field($status_id);

        if ($Status == 'completed') {
            $entry["is_fulfilled"]   = 1;
            $entry["payment_amount"] = $Total;

            if ($transaction_type == 2) {
                $entry["payment_status"] = "Active";
                RGFormsModel::add_note($entry["id"], $user_id, $user_name, __("تغییرات اطلاعات فیلدها فقط در همین پیام ورودی اعمال خواهد شد و بر روی وضعیت کاربر تاثیری نخواهد داشت .", "gravityformsIDPay"));
            } else {
                $entry["payment_status"] = "Paid";
            }

            if ($free == true) {
                unset($entry["payment_amount"]);
                unset($entry["payment_method"]);
                unset($entry["is_fulfilled"]);
                gform_delete_meta($entry['id'], 'payment_gateway');
                $message = $Note = sprintf(__('وضعیت پرداخت : رایگان - بدون نیاز به درگاه پرداخت', "gravityformsIDPay"));
            } else {
                $message = sprintf(__(' پرداخت شما با موفقیت انجام شد. شماره سفارش: %s - کد رهگیری: %s', "gravityformsIDPay"), $result->order_id, $result->track_id);
                $Note    = sprintf(__(' وضعیت تراکنش: %s - کد رهگیری: %s - شماره کارت: %s شماره کارت هش شده:%s', "gravityformsIDPay"), self::getStatus($result->status), $result->track_id, $result->payment->card_no, $result->payment->hashed_card_no);
                $Note    .= print_r($result, true);
            }

            GFAPI::update_entry($entry);

            if (! empty($__params)) {
                GFPersian_Payments::set_verification($entry, __CLASS__, $__params);
            }

            $user_registration_slug = apply_filters('gf_user_registration_slug', 'gravityformsuserregistration');
            $idpay_config           = [ 'meta' => [] ];
            if (! empty($config["meta"]["addon"]) && $config["meta"]["addon"] == 'true') {
                if (class_exists('GFAddon') && method_exists('GFAddon', 'get_registered_addons')) {
                    $addons = GFAddon::get_registered_addons();
                    foreach ((array) $addons as $addon) {
                        if (is_callable(array( $addon, 'get_instance' ))) {
                            $addon = call_user_func(array( $addon, 'get_instance' ));
                            if (is_object($addon) && method_exists($addon, 'get_slug')) {
                                $slug = $addon->get_slug();
                                if ($slug != $user_registration_slug) {
                                    $idpay_config['meta'][ 'delay_' . $slug ] = true;
                                }
                            }
                        }
                    }
                }
            }
            if (! empty($config["meta"]["type"]) && $config["meta"]["type"] == "subscription") {
                $idpay_config['meta'][ 'delay_' . $user_registration_slug ] = true;
            }

            do_action("gform_IDPay_fulfillment", $entry, $config, $transaction_id, $Total);
            do_action("gform_gateway_fulfillment", $entry, $config, $transaction_id, $Total);
            do_action("gform_idpay_fulfillment", $entry, $idpay_config, $transaction_id, $Total);
        } else {
            $entry["payment_status"] = ( $Status == 'cancelled' ) ? "Cancelled" : "Failed";
            $entry["payment_amount"] = 0;
            $entry["is_fulfilled"]   = 0;
            GFAPI::update_entry($entry);

            $message = $Note = sprintf(__('وضعیت پرداخت :%s (کد خطا: %s) - مبلغ قابل پرداخت : %s', "gravityformsIDPay"), self::getStatus($status_code), $status_code, $Total_Money);
            $Note    .= print_r($params, true);
        }

        $entry = GFPersian_Payments::get_entry($entry_id);

        RGFormsModel::add_note($entry["id"], $user_id, $user_name, $Note);
        do_action('gform_post_payment_status', $config, $entry, strtolower($Status), $transaction_id, '', $Total, '', '');
        do_action('gform_post_payment_status_' . __CLASS__, $config, $form, $entry, strtolower($Status), $transaction_id, '', $Total, '', '');

        if (apply_filters(self::$author . '_gf_IDPay_verify', apply_filters(self::$author . '_gf_gateway_verify', ( $payment_type != 'custom' ), $form, $entry), $form, $entry)) {
            foreach ($form['confirmations'] as $key => $value) {
                $form['confirmations'][ $key ]['message'] = self::_payment_entry_detail($message, $Status, $config, $value['message']);
            }

            if (! empty($idpay_config['meta'])) {
                if (in_array("delay_post-update-addon-gravity-forms", $idpay_config['meta'])) {
                    $addon = call_user_func(array( 'ACGF_PostUpdateAddOn', 'get_instance' ));
                    $feeds = $addon->get_feeds($form_id);
                    foreach ($feeds as $feed) {
                        $addon->process_feed($feed, $entry, $form);
                    }
                }

                if (in_array("delay_gravityformsadvancedpostcreation", $idpay_config['meta'])) {
                    $addon = call_user_func(array( 'GF_Advanced_Post_Creation', 'get_instance' ));
                    $feeds = $addon->get_feeds($form_id);
                    foreach ($feeds as $feed) {
                        $addon->process_feed($feed, $entry, $form);
                    }
                }

                if (in_array("delay_gravityformsuserregistration", $idpay_config['meta'])) {
                    $addon = call_user_func(array( 'GF_User_Registration', 'get_instance' ));
                    $feeds = $addon->get_feeds($form_id);
                    foreach ($feeds as $feed) {
                        $addon->process_feed($feed, $entry, $form);
                    }
                }
            }

            GFPersian_Payments::notification($form, $entry);
            GFPersian_Payments::confirmation($form, $entry, $Note);
        }
    }

    public static function get_config_by_entry($entry)
    {
        $feed_id = gform_get_meta($entry["id"], "IDPay_feed_id");
        $feed    = ! empty($feed_id) ? IDPay_DB::get_feed($feed_id) : '';
        $return  = ! empty($feed) ? $feed : false;

        return apply_filters(self::$author . '_gf_IDPay_get_config_by_entry', apply_filters(self::$author . '_gf_gateway_get_config_by_entry', $return, $entry), $entry);
    }

    public static function getStatus($status_code)
    {
        switch ($status_code) {
            case 1:
                return 'پرداخت انجام نشده است';
                break;
            case 2:
                return 'پرداخت ناموفق بوده است';
                break;
            case 3:
                return 'خطا رخ داده است';
                break;
            case 4:
                return 'بلوکه شده';
                break;
            case 5:
                return 'برگشت به پرداخت کننده';
                break;
            case 6:
                return 'برگشت خورده سیستمی';
                break;
            case 7:
                return 'انصراف از پرداخت';
                break;
            case 8:
                return 'به درگاه پرداخت منتقل شد';
                break;
            case 10:
                return 'در انتظار تایید پرداخت';
                break;
            case 100:
                return 'پرداخت تایید شده است';
                break;
            case 101:
                return 'پرداخت قبلا تایید شده است';
                break;
            case 200:
                return 'به دریافت کننده واریز شد';
                break;
            default:
                return 'خطای ناشناخته';
                break;
        }
    }

    public static function _payment_entry_detail($messages, $payment_status, $config, $text)
    {
        if ($payment_status == 'Paid' || $payment_status == 'completed' || $payment_status == 'Active') {
            $status = 'success';
        }
        if ($payment_status == 'Cancelled' || $payment_status == 'Failed') {
            $status = 'Failed';
        }
        if ($payment_status == 'Processing') {
            $status = 'info';
        }

        $output = '';
        if ($status == 'Failed') {
            $output = '<div  style=" direction:rtl;padding: 20px;background-color: #f44336;color: white;opacity: 0.83;transition: opacity 0.6s;margin-bottom: 15px;">' . $messages . '</div>';
        }
        if ($status == 'success') {
            $output = '<div  style="direction:rtl;padding: 20px;background-color: #4CAF50;color: white;opacity: 0.83;transition: opacity 0.6s;margin-bottom: 15px;">' . $messages . '</div>';
        }
        if ($status == 'info') {
            $output = '<div  style="direction:rtl;padding: 20px;background-color: #2196F3;color: white;opacity: 0.83;transition: opacity 0.6s;margin-bottom: 15px;">' . $messages . '</div>';
        }

        if (! empty($config["meta"]["confirmation"])) {
            return str_replace('{idpay_payment_result}', $output, $text);
        }

        return $output;
    }

    public static function idpay_get_message($massage, $track_id, $order_id)
    {
        return str_replace([ "{track_id}", "{order_id}" ], [ $track_id, $order_id ], $massage);
    }

    protected static function get_base_url()
    {
        return plugins_url(null, __FILE__);
    }

    protected static function get_base_path()
    {
        $folder = basename(dirname(__FILE__));

        return WP_PLUGIN_DIR . "/" . $folder;
    }

    public static function merge_tags_keys($form)
    {

        if (GFCommon::is_entry_detail()) {
            return $form;
        }
        ?>

        <script type="text/javascript">
            gform.addFilter('gform_merge_tags', function (mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option) {
                mergeTags['gf_idpay'] = {
                    label: 'آیدی پی',
                    tags: [
                        {tag: '{idpay_payment_result}', label: 'نتیجه پرداخت آیدی پی'}
                    ]
                };
                return mergeTags;
            });
        </script>
        <?php
        return $form;
    }

    private static function makeSafeDataForDb($idpayConfig): array
    {
        $safe_data = array();
        foreach ($idpayConfig["meta"] as $key => $val) {
            if (! is_array($val)) {
                $safe_data[ $key ] = sanitize_text_field($val);
            } else {
                $safe_data[ $key ] = array_map('sanitize_text_field', $val);
            }
        }

        return $safe_data;
    }

    private static function updateConfigAndRedirectPage($feedId, $data): array
    {
        $idpayConfig = apply_filters(self::$author . '_gform_gateway_save_config', $data);
        $idpayConfig = apply_filters(self::$author . '_gform_IDPay_save_config', $idpayConfig);
        $feedId      = IDPay_DB::update_feed(
            $feedId,
            $idpayConfig["form_id"],
            $idpayConfig["is_active"],
            $idpayConfig["meta"]
        );
        if (! headers_sent()) {
            wp_redirect(admin_url('admin.php?page=gf_IDPay&view=edit&id=' . $feedId . '&updated=true'));
            exit;
        } else {
            echo "<script type='text/javascript'>window.onload = function () { top.location.href = '" . admin_url('admin.php?page=gf_IDPay&view=edit&id=' . $feedId . '&updated=true') . "'; };</script>";
            exit;
        }
    }

    private static function setStylePage()
    {
        if (is_rtl()) {
            echo '<style type="text/css">table.gforms_form_settings th {text-align: right !important}</style>';
        }
        if (! defined('ABSPATH')) {
            exit;
        }
        wp_register_style('gform_admin_IDPay', GFCommon::get_base_url() . '/assets/css/dist/admin.css');
        wp_print_styles(array( 'jquery-ui-styles', 'gform_admin_IDPay', 'wp-pointer' ));

        return true;
    }

    private static function SearchFormName($feedId)
    {
        $dbFeeds  = IDPay_DB::get_feeds();
        $formName = '';
        foreach ((array) $dbFeeds as $dbFeed) {
            if ($dbFeed['id'] == $feedId) {
                $formName = $dbFeed['form_title'];
            }
        }

        return $formName;
    }

    private static function readDataFromRequest($idpayConfig)
    {
        $idpayConfig["form_id"]                        = absint(rgpost("gf_IDPay_form"));
        $idpayConfig["is_active"]                      = true;
        $idpayConfig["meta"]["type"]                   = rgpost("gf_IDPay_type");
        $idpayConfig["meta"]["addon"]                  = rgpost("gf_IDPay_addon");
        $idpayConfig["meta"]["desc_pm"]                = rgpost("gf_IDPay_desc_pm");
        $idpayConfig["meta"]["customer_fields_desc"]   = rgpost("IDPay_customer_field_desc");
        $idpayConfig["meta"]["customer_fields_email"]  = rgpost("IDPay_customer_field_email");
        $idpayConfig["meta"]["customer_fields_mobile"] = rgpost("IDPay_customer_field_mobile");
        $idpayConfig["meta"]["customer_fields_name"]   = rgpost("IDPay_customer_field_name");
        $idpayConfig["meta"]["confirmation"]           = rgpost("gf_IDPay_confirmation");

        return $idpayConfig;
    }

    private static function makeUpdateMessageBar($oldFeedId)
    {
        $feedId       = (int) rgget('id') ?? $oldFeedId;
        $message      = __("فید به روز شد . %sبازگشت به لیست%s . ", "gravityformsIDPay");
        $updatedLabel = sprintf($message, "<a href='?page=gf_IDPay'>", "</a>");
        echo '<div class="updated fade" style="padding:6px">' . $updatedLabel . '</div>';

        return true;
    }

    private static function loadSavedOrDefaultValue($form, $fieldName, $selectedValue)
    {
        $gravityFormFields = ! empty($form) ? self::get_form_fields($form) : null;
        if ($gravityFormFields != null) {
            return self::get_mapped_field_list($fieldName, $selectedValue, $gravityFormFields);
        }

        return '';
    }

    private static function generateFeedSelectForm($formId): object
    {
        $gfAllForms             = IDPay_DB::get_available_forms();
        $visibleFieldFormSelect = rgget('id') || rgget('fid') ? 'style="display:none !important"' : '';
        $label                  = 'یک فرم انتخاب نمایید';
        $optionsForms           = "<option value=''>{$label}</option>";
        foreach ($gfAllForms as $current_form) {
            $title        = esc_html($current_form->title);
            $val          = absint($current_form->id);
            $isSelected   = absint($current_form->id) == $formId ? 'selected="selected"' : '';
            $optionsForms = $optionsForms . "<option value={$val} {$isSelected}>{$title}</option>";
        }

        return (object) [
            'options' => $optionsForms,
            'visible' => $visibleFieldFormSelect
        ];
    }

    private static function generateStatusBarMessage($formId)
    {
        $updateFeedLabel = __("فید به روز شد . %sبازگشت به لیست%s.", "gravityformsIDPay");
        $updatedFeed     = sprintf($updateFeedLabel, "<a href='?page=gf_IDPay'>", "</a>");
        $feedHtml        = '<div class="updated fade" style="padding:6px">' . $updatedFeed . '</div>';

        return $feedHtml;
    }

    private static function loadDictionary($feedId, $formName): object
    {
        return (object) [
            'label1'             => translate("پیکربندی درگاه IDPay", self::$domain),
            'label2'             => sprintf(__("فید: %s", self::$domain), $feedId),
            'label3'             => sprintf(__("فرم: %s", self::$domain), $formName),
            'label4'             => translate("تنظیمات کلی", self::$domain),
            'label5'             => translate("انتخاب فرم", self::$domain),
            'label6'             => translate("یک فرم انتخاب نمایید", self::$domain),
            'label7'             => translate("فرم انتخاب شده هیچ گونه فیلد قیمت گذاری ندارد، لطفا پس از افزودن این فیلدها مجددا اقدام نمایید.", self::$domain),
            'label8'             => translate("User_Registration تنظیمات", self::$domain),
            'label9'             => translate(' اگر این فرم وظیفه ثبت نام کاربر تنها در صورت پرداخت موفق را دارد تیک بزنید', self::$domain),
            'label10'            => translate("توضیحات پرداخت", self::$domain),
            'label11'            => translate("توضیحاتی که میتوانید در داشبورد سایت آیدی ببینید . می توانید از", self::$domain),
            'label11_2'          => translate("{{form_title}}  و  {{form_id}}", self::$domain),
            'label11_3'          => translate("نیز برای نشانه گذاری استفاده کنید", self::$domain),
            'label12'            => translate("نام پرداخت کننده", self::$domain),
            'label13'            => translate("ایمیل پرداخت کننده", self::$domain),
            'label14'            => translate("توضیح تکمیلی", self::$domain),
            'label15'            => translate("تلفن همراه پرداخت کننده", self::$domain),
            'label16'            => translate("مدیریت افزودنی های گراویتی", self::$domain),
            'label17'            => translate("این گزینه را تنها در صورتی تیک بزنید که", self::$domain),
            'label17_2'          => translate("شما از پلاگین های افزودنی گراویتی مانند", self::$domain),
            'label17_3'          => translate("(Post Update , Post Create , ...)", self::$domain),
            'label17_4'          => translate(" استفاده می کنید و باید تنها در صورت پرداخت موفق اجرا شوند", self::$domain),
            'label18'            => translate("استفاده از تاییدیه های سفارشی", self::$domain),
            'label19'            => translate("این گزینه را در صورتی تیک بزنید که", self::$domain),
            'label19_2'          => translate("نمیخواهید از تاییدیه ثبت فرم پیش فرض آیدی پی استفاده کنید", self::$domain),
            'label19_3'          => translate("و از تاییدیه های سفارشی گراویتی استفاده می کنید", self::$domain),
            'label19_4'          => translate("توجه * : برای ترکیب تاییدیه های سفارشی با آیدی پی از متغیر", self::$domain),
            'label19_5'          => translate("idpay_payment_result", self::$domain),
            'label19_6'          => translate("استفاده کنید", self::$domain),
            'label21'            => translate("ذخیره تنظیمات", self::$domain),
            'label22'            => translate("فرم های IDPay", self::$domain),
            'label23'            => translate("اقدام دسته جمعی", self::$domain),
            'label24'            => translate("اقدامات دسته جمعی", self::$domain),
            'label25'            => translate("حذف", self::$domain),
            'label26'            => translate('تنظیمات IDPay', self::$domain),
            'label27'            => translate('وضعیت', self::$domain),
            'label28'            => translate(" آیدی فید", self::$domain),
            'label29'            => translate("نوع تراکنش", self::$domain),
            'label30'            => translate("فرم متصل به درگاه", self::$domain),
            'label31'            => translate("برای شروع باید درگاه را فعال نمایید . به تنظیمات IDPay بروید .", self::$domain),
            'label32'            => translate("عملیات", self::$domain),
            'label33'            => translate("ویرایش فید", self::$domain),
            'label34'            => translate("حذف فید", self::$domain),
            'label35'            => translate("ویرایش فرم", self::$domain),
            'label36'            => translate("صندوق ورودی", self::$domain),
            'label37'            => translate("نمودارهای فرم", self::$domain),
            'label38'            => translate("افزودن فید جدید", self::$domain),
            'label39'            => translate("نمودار ها", self::$domain),
            'labelSelectGravity' => translate("از فیلدهای موجود در فرم گراویتی یکی را انتخاب کنید", self::$domain),
            'labelNotSupprt'     => sprintf(__("درگاه IDPay نیاز به گرویتی فرم نسخه %s دارد. برای بروز رسانی هسته گرویتی فرم به %s مراجعه نمایید.", self::$domain), $feedId, $formName),
        ];
    }

    public static function checkSupportedGravityVersion()
    {
        $label1 = self::$min_gravityforms_version;
        $label2 = "<a href='http://gravityforms.ir' target='_blank'>سایت گرویتی فرم فارسی</a>";
        if (! self::is_gravityforms_supported()) {
            return self::loadDictionary($label1, $label2)->labelNotSupprt;
        }

        return true;
    }

    public static function getStatusFeedImage($setting)
    {
        $val1   = esc_url(GFCommon::get_base_url());
        $val2   = "/images/active";
        $val3   = intval($setting["is_active"]);
        $val4   = ".png";
        $image  = $val1 . $val2 . $val3 . $val4;
        $active = $setting["is_active"] ? "درگاه فعال است" : "درگاه غیر فعال است";

        return (object) [
            'image'  => $image,
            'active' => $active
        ];
    }

    public static function getTypeFeed($setting)
    {
        if (isset($setting["meta"]["type"]) && $setting["meta"]["type"] == 'subscription') {
            return "عضویت";
        } else {
            return "محصول معمولی یا فرم ارسال پست";
        }
    }

    public static function checkSubmittedOperation()
    {
        if (rgpost('action') == "delete") {
            check_admin_referer("list_action", "gf_IDPay_list");
            $id = absint(rgpost("action_argument"));
            IDPay_DB::delete_feed($id);

            return "<div class='updated fade' style='padding:6px'>فید حذف شد</div>";
        } elseif (! empty($_POST["bulk_action"])) {
            check_admin_referer("list_action", "gf_IDPay_list");
            $selected_feeds = rgpost("feed");
            if (is_array($selected_feeds)) {
                foreach ($selected_feeds as $feed_id) {
                    IDPay_DB::delete_feed($feed_id);

                    return "<div class='updated fade' style='padding:6px'>فید ها حذف شد</div>";
                }
            }
        }

        return '';
    }

    public static function feed_page()
    {
        GFFormSettings::page_header();
        require_once(self::get_base_path() . '/pages/FeedList.php');
        GFFormSettings::page_footer();
    }

    public static function IDPay_page()
    {
        $view = rgget("view");
        if ($view == "edit") {
            require_once(self::get_base_path() . '/pages/FeedConfig.php');
        } elseif ($view == "stats") {
            IDPay_Chart::stats_page();
        } else {
            require_once(self::get_base_path() . '/pages/FeedList.php');
        }
    }

    public static function settings_page()
    {
        require_once(self::get_base_path() . '/templates/settings_page.php');
    }


    //Template New Function
    private static function template($feedId, $data)
    {
        return [];
    }
}
