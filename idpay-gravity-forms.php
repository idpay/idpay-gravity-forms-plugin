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

require_once('lib/IDPayDB.php');
require_once('lib/Helpers.php');
require_once('lib/IDPayPayment.php');
require_once('lib/IDPayVerify.php');
require_once('lib/IDPay_Chart.php');

class GF_Gateway_IDPay extends Helpers
{
    public static $author = "IDPay";
    public static $version = "1.0.5";
    public static $min_gravityforms_version = "1.9.10";
    public static $domain = "gravityformsIDPay";

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

        if (is_admin() && self::hasPermission()) {
            add_filter('gform_tooltips', array( __CLASS__, 'tooltips' ));
            add_filter('gform_addon_navigation', array( __CLASS__, 'menu' ));
            add_action('gform_entry_info', array( __CLASS__, 'payment_entry_detail' ), 4, 2);
            add_action('gform_after_update_entry', array( __CLASS__, 'update_payment_entry' ), 4, 2);

            if (get_option("gf_IDPay_configured")) {
                add_filter('gform_form_settings_menu', array( __CLASS__, 'toolbar' ), 10, 2);
                add_action('gform_form_settings_page_IDPay', array( __CLASS__, 'loadFeedList' ));
            }

            if (rgget("page") == "gf_settings") {
                RGForms::add_settings_page(array(
                    'name'      => 'gf_IDPay',
                    'tab_label' => __('درگاه IDPay', 'gravityformsIDPay'),
                    'title'     => __('تنظیمات درگاه IDPay', 'gravityformsIDPay'),
                    'handler'   => array( __CLASS__, 'loadSettingPage' ),
                ));
            }

            if (self::is_IDPay_page()) {
                wp_enqueue_script('sack');
                self::setup();
            }

            add_action('wp_ajax_gf_IDPay_update_feed_active', array( __CLASS__, 'update_feed_active' ));
        }
        if (get_option("gf_IDPay_configured")) {
            add_filter("gform_disable_post_creation", array( __CLASS__, "delay_posts" ), 10, 3);
            add_filter("gform_is_delayed_pre_process_feed", array( __CLASS__, "delay_addons" ), 10, 4);
            add_filter("gform_confirmation", array( __CLASS__, "doPayment" ), 1000, 4);
            add_action('wp', array( __CLASS__, 'doVerify' ), 5);
            add_filter("gform_submit_button", array( __CLASS__, "alter_submit_button" ), 10, 2);
        }

        add_filter("gform_logging_supported", array( __CLASS__, "set_logging_supported" ));
        add_filter('gf_payment_gateways', array( __CLASS__, 'gravityformsIDPay' ), 2);
        do_action('gravityforms_gateways');
        do_action('gravityforms_IDPay');
        add_filter('gform_admin_pre_render', array( __CLASS__, 'merge_tags_keys' ));

        return 'completed';
    }

    public static function doPayment($confirmation, $form, $entry, $ajax)
    {
        return IDPayPayment::doPayment($confirmation, $form, $entry, $ajax);
    }

    public static function doVerify()
    {
       return IDPayVerify::doVerify();

       // Refactor Until This Line

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

                if (! empty($pid) && ! empty($porder_id) && $porder_id == $entryId &&
                     self::isNotDoubleSpending($entryId, $order_id, $id) == true) {
                    $__params = $pricing->amount . $pid;
                    if (GFPersian_Payments::check_verification($entry, __CLASS__, $__params)) {
                        return;
                    }

                    $data = [
                        'id'       => $pid,
                        'order_id' => $entryId
                    ];

                    $response = self::httpRequest('https://api.idpay.ir/v1.1/payment/verify', $data);

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

                        if (empty($verify_status) || $verify_status != 100 || empty($verify_track_id) ||
                             empty($verify_amount) || $verify_amount != $pricing->amount) {
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
        $transaction_id = apply_filters(
            self::$author . '_gf_real_transaction_id',
            $transaction_id,
            $Status,
            $form,
            $entry
        );

        $entry["payment_date"]     = gmdate("Y-m-d H:i:s");
        $entry["transaction_id"]   = $transaction_id;
        $entry["transaction_type"] = $transaction_type;
        $status_code               = sanitize_text_field($status_id);

        if ($Status == 'completed') {
            $entry["is_fulfilled"]   = 1;
            $entry["payment_amount"] = $pricing->amount;

            if ($transaction_type == 2) {
                $entry["payment_status"] = "Active";
                RGFormsModel::add_note(
                    $entryId,
                    $userData->id,
                    $userData->username,
                    __(
                        "تغییرات اطلاعات فیلدها فقط در همین پیام ورودی اعمال خواهد شد و بر روی وضعیت کاربر تاثیری نخواهد داشت .",
                        "gravityformsIDPay"
                    )
                );
            } else {
                $entry["payment_status"] = "Paid";
            }

            if ($free == true) {
                unset($entry["payment_amount"]);
                unset($entry["payment_method"]);
                unset($entry["is_fulfilled"]);
                gform_delete_meta($entryId, 'payment_gateway');
                $message = $Note = sprintf(__('وضعیت پرداخت : رایگان - بدون نیاز به درگاه پرداخت', "gravityformsIDPay"));
            } else {
                $message = sprintf(__(' پرداخت شما با موفقیت انجام شد. شماره سفارش: %s - کد رهگیری: %s', "gravityformsIDPay"), $result->order_id, $result->track_id);
                $Note    = sprintf(__(' وضعیت تراکنش: %s - کد رهگیری: %s - شماره کارت: %s شماره کارت هش شده:%s', "gravityformsIDPay"),
                    self::getStatus($result->status), $result->track_id, $result->payment->card_no, $result->payment->hashed_card_no);
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

            do_action("gform_IDPay_fulfillment", $entry, $config, $transaction_id, $pricing->amount);
            do_action("gform_gateway_fulfillment", $entry, $config, $transaction_id, $pricing->amount);
            do_action("gform_idpay_fulfillment", $entry, $idpay_config, $transaction_id, $pricing->amount);
        }

        else {

            $entry["payment_status"] = ( $Status == 'cancelled' ) ? "Cancelled" : "Failed";
            $entry["payment_amount"] = 0;
            $entry["is_fulfilled"]   = 0;
            GFAPI::update_entry($entry);

            $message = $Note = sprintf(__('وضعیت پرداخت :%s (کد خطا: %s) - مبلغ قابل پرداخت : %s', "gravityformsIDPay"),
                self::getStatus($status_code), $status_code, $pricing->money);
            $Note    .= print_r($params, true);
        }

        $entry = GFPersian_Payments::get_entry($entryId);

        RGFormsModel::add_note($entryId, $userData->id, $userData->username, $Note);
        do_action('gform_post_payment_status', $config, $entry,
            strtolower($Status), $transaction_id, '', $pricing->amount, '', '');
        do_action('gform_post_payment_status_' . __CLASS__, $config, $form, $entry,
            strtolower($Status), $transaction_id, '', $pricing->amount, '', '');

        if (apply_filters(self::$author . '_gf_IDPay_verify',
            apply_filters(self::$author . '_gf_gateway_verify', ( $paymentType != 'custom' ), $form, $entry),
            $form, $entry)) {
            foreach ($form['confirmations'] as $key => $value) {
                $form['confirmations'][ $key ]['message'] = self::_payment_entry_detail(
                        $message, $Status, $config, $value['message']);
            }

            if (! empty($idpay_config['meta'])) {
                if (in_array("delay_post-update-addon-gravity-forms", $idpay_config['meta'])) {
                    $addon = call_user_func(array( 'ACGF_PostUpdateAddOn', 'get_instance' ));
                    $feeds = $addon->get_feeds($formId);
                    foreach ($feeds as $feed) {
                        $addon->process_feed($feed, $entry, $form);
                    }
                }

                if (in_array("delay_gravityformsadvancedpostcreation", $idpay_config['meta'])) {
                    $addon = call_user_func(array( 'GF_Advanced_Post_Creation', 'get_instance' ));
                    $feeds = $addon->get_feeds($formId);
                    foreach ($feeds as $feed) {
                        $addon->process_feed($feed, $entry, $form);
                    }
                }

                if (in_array("delay_gravityformsuserregistration", $idpay_config['meta'])) {
                    $addon = call_user_func(array( 'GF_User_Registration', 'get_instance' ));
                    $feeds = $addon->get_feeds($formId);
                    foreach ($feeds as $feed) {
                        $addon->process_feed($feed, $entry, $form);
                    }
                }
            }

            GFPersian_Payments::notification($form, $entry);
            GFPersian_Payments::confirmation($form, $entry, $Note);
        }
    }



    public static function loadFeedList()
    {
        GFFormSettings::page_header();
        require_once(self::get_base_path() . '/pages/FeedList.php');
        GFFormSettings::page_footer();
    }

    public static function routeFeedPage()
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

    public static function loadSettingPage()
    {
        require_once(self::get_base_path() . '/pages/Setting.php');
    }

//    private static function is_gravityforms_supported(): bool {
//        $condition1 = class_exists( "GFCommon" );
//        $condition2 = (bool) version_compare( GFCommon::$version, self::$min_gravityforms_version, ">=" );
//        return $condition1 && $condition2;
//    }

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

        $config = IDPayDB::getActiveFeed($form);

        if ($has_product && ! empty($config)) {
            $button_input .= sprintf(
                '<div id="idpay-pay-id-%1$s" class="idpay-logo" style="font-size: 14px;padding: 5px 0;"><img src="%2$s" style="display: inline-block;vertical-align: middle;width: 70px;">%3$s</div>',
                $form['id'],
                plugins_url('/lib/logo.svg', __FILE__),
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

    protected static function hasPermission($permission = 'gravityforms_IDPay')
    {
        if (! function_exists('wp_get_current_user')) {
            include(ABSPATH . "wp-includes/pluggable.php");
        }

        return GFCommon::current_user_can_any($permission);
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
            IDPayDB::update_table();
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
                "callback"   => array( __CLASS__, "routeFeedPage" ),
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

        $config = IDPayDB::getActiveFeed($form);

        if (! empty($config) && is_array($config) && $config) {
            return true;
        }

        return $is_disabled;
    }

    public static function delay_addons($is_delayed, $form, $entry, $slug)
    {

        $config = IDPayDB::getActiveFeed($form);

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
        $feed = IDPayDB::get_feed($id);
        IDPayDB::update_feed($id, $feed["form_id"], sanitize_text_field(rgpost("is_active")), $feed["meta"]);
    }

    public static function payment_entry_detail($formId, $entry)
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
                $form           = RGFormsModel::get_form_meta($formId);
                $payment_amount = self::getOrderTotal($form, $entry);
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

        $userData = self::loadUser();

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
            case "Paid":
            case "Active":
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

        RGFormsModel::add_note($entry["id"], $userData->id, $userData->username, sprintf(__("اطلاعات تراکنش به صورت دستی ویرایش شد . وضعیت : %s - مبلغ : %s - کد رهگیری : %s - تاریخ : %s", "gravityformsIDPay"), $new_status, GFCommon::to_money($entry["payment_amount"], $entry["currency"]), $payment_transaction, $entry["payment_date"]));
    }

    public static function uninstall()
    {
        if (! self::hasPermission("gravityforms_IDPay_uninstall")) {
            die(__("شما مجوز کافی برای این کار را ندارید . سطح دسترسی شما پایین تر از حد مجاز است . ", "gravityformsIDPay"));
        }
        IDPayDB::drop_tables();
        delete_option("gf_IDPay_settings");
        delete_option("gf_IDPay_configured");
        delete_option("gf_IDPay_version");
        $plugin = basename(dirname(__FILE__)) . "/index.php";
        deactivate_plugins($plugin);
        update_option('recently_activated', array( $plugin => time() ) + (array) get_option('recently_activated'));
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
}
