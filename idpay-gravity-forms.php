<?php

/**
 * Plugin Name: IDPay For Wp Gravity Forms
 * Author: IDPay
 * Description: IDPay is Secure Payment Gateway For Wp Gravity Forms.
 * Version: 2.0.0
 * Author URI: https://idpay.ir
 * Author Email: info@idpay.ir
 * Text Domain: idpay-gravity-forms
 */

if (! defined('ABSPATH')) {
    exit;
}

const IDPAY_PLUGIN_CLASS = 'GF_Gateway_IDPay';
register_activation_hook(__FILE__, [ IDPAY_PLUGIN_CLASS, "addPermission" ]);
register_deactivation_hook(__FILE__, [ IDPAY_PLUGIN_CLASS, "deactivation" ]);
add_action('init', [ IDPAY_PLUGIN_CLASS, 'init']);

require_once('lib/Keys.php');
require_once('lib/Helpers.php');
require_once('lib/IDPayDB.php');
require_once('lib/JDate.php');
require_once('lib/IDPayPayment.php');
require_once('lib/IDPayVerify.php');
require_once('lib/IDPayOperation.php');
require_once('lib/IDPayView.php');

class GF_Gateway_IDPay extends Helpers
{
    public static function init()
    {
        $dictionary = Helpers::loadDictionary();
        $setting = Helpers::getGlobalKey(Keys::KEY_IDPAY);
        $enable = Helpers::dataGet($setting,'enable');
        $condition1 = ! class_exists("GFPersian_Payments");
        $condition2 = ! defined('GF_PERSIAN_VERSION');
        $condition3 = version_compare(GF_PERSIAN_VERSION, '2.3.1', '<');

        if ($condition1 || $condition2 || $condition3) {
            add_action('admin_notices', [ IDPayOperation::class, 'reportPreRequiredPersianGravityForm' ]);
            return false;
        }

        if (! IDPayOperation::checkApprovedGravityFormVersion()) {
             add_action('admin_notices', [ IDPayOperation::class, 'reportPreRequiredGravityForm' ]);
            return false;
        }

        add_filter('members_get_capabilities', [ IDPayOperation::class, "MembersCapabilities" ]);
        $adminPermission = Keys::PERMISSION_ADMIN;

        if (is_admin() && IDPayOperation::hasPermission($adminPermission)) {
            add_action('wp_ajax_gf_IDPay_update_feed_active', [ IDPayDB::class, 'SaveOrUpdateFeed' ]);
            add_filter('gform_addon_navigation', [ IDPayView::class, 'addIdpayToNavigation' ]);
            add_action('gform_entry_info', [ __CLASS__, 'showOrEditPaymentData' ], 4, 2);
            add_action('gform_after_update_entry', [ __CLASS__, 'updatePaymentData' ], 4, 2);

            if ($enable) {
                add_filter('gform_form_settings_menu', [ IDPayView::class, 'addIdpayToToolbar' ], 10, 2);
                add_action('gform_form_settings_page_IDPay', [ IDPayView::class, 'route' ]);
            }

            if (rgget("page") == "gf_settings") {
                $handler = [ IDPayView::class, 'viewSetting' ];
                RGForms::add_settings_page(
                    [
                        'name'      => 'gf_IDPay',
                        'tab_label' => $dictionary->labelSettingsTab,
                        'title'     => $dictionary->labelSettingsTitle,
                        'handler'   => $handler,
                    ]
                );
            }

            if (Helpers::checkCurrentPageForIDPAY()) {
                wp_enqueue_script('sack');
                IDPayOperation::setup();
            }
        }

        if ($enable) {
            add_filter("gform_disable_post_creation", [ __CLASS__, "setDelayedActivity" ], 10, 3);
            add_filter("gform_is_delayed_pre_process_feed", [ __CLASS__, "setDelayedGravityAddons" ], 10, 4);
            add_filter("gform_confirmation", [ IDPayPayment::class, "doPayment" ], 1000, 4);
            add_action('wp', [ IDPayVerify::class, 'doVerify' ], 5);
            add_filter("gform_submit_button", [ IDPayView::class, "renderButtonSubmitForm" ], 10, 2);
        }

        add_filter("gform_logging_supported", [ __CLASS__, "setLogSystem" ]);
        add_filter('gf_payment_gateways', [ __CLASS__, 'setDefaultSys' ], 2);
        do_action('gravityforms_gateways');
        do_action('setDefaultSys');
        add_filter('gform_admin_pre_render', [ __CLASS__, 'preRenderScript' ]);

        return 'completed';
    }

    public static function addPermission()
    {
        IDPayOperation::addPermission();
    }

    public static function deactivation()
    {
        IDPayOperation::deactivation();
    }

    public static function setDelayedActivity($is_disabled, $form, $entry)
    {
        $config = IDPayDB::getActiveFeed($form);

        return ! empty($config) ? true : $is_disabled;
    }

    public static function setDelayedGravityAddons($is_delayed, $form, $entry, $slug)
    {
        $config     = IDPayDB::getActiveFeed($form);
        $delayedFor = Helpers::makeListDelayedAddons($config);

        if (! empty($config)) {
            if ($slug == 'gravityformsuserregistration') {
                return $delayedFor['userRegistration'];
            } elseif ($slug == 'gravityformsadvancedpostcreation') {
                return $delayedFor['postCreate'];
            } elseif ($slug == 'post-update-addon-gravity-forms') {
                return $delayedFor['postUpdate'];
            } else {
                return $is_delayed;
            }
        }

        return $is_delayed;
    }

    public static function setDefaultSys($form, $entry)
    {
        $dictionary = Helpers::loadDictionary();
        $baseClass = __CLASS__;
        $author    = Keys::AUTHOR;
        $class     = "{$baseClass}|{$author}";
        $IDPay     = [
                        'class' => $class,
                        'title' => $dictionary->labelIdpay,
                        'param' => [
                            'email'  =>  $dictionary->labelEmail,
                            'mobile' =>  $dictionary->labelMobile,
                            'desc'   =>  $dictionary->labelDesc,
                        ]
              ];

        return apply_filters(
	        Keys::AUTHOR . '_gf_IDPay_detail',
            apply_filters(Keys::AUTHOR . '_gf_gateway_detail', $IDPay, $form, $entry),
            $form,
            $entry
        );
    }

    public static function setLogSystem($plugins)
    {
        $plugins[ basename(dirname(__FILE__)) ] = Keys::AUTHOR;

        return $plugins;
    }

    public static function preRenderScript($form)
    {

        if (GFCommon::is_entry_detail()) {
            return $form;
        }
        ?>

        <script type="text/javascript">
            gform.addFilter('gform_merge_tags',
                function (mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option) {
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

    public static function showOrEditPaymentData($formId, $entry)
    {

        if (Helpers::checkEntryForIDPay($entry)) {
            if (Helpers::getTypeEntryView() == 'showView') {
                IDPayView::makeHtmlShowPaymentData($formId, $entry);
            } elseif (Helpers::getTypeEntryView() == 'editView') {
                IDPayView::makeHtmlEditPaymentData($formId, $entry);
            }
        }
    }

    public static function updatePaymentData($form, $entry_id)
    {
        check_admin_referer('gforms_save_entry', 'gforms_save_entry');
        do_action('gf_gateway_update_entry');
        $entry = GFPersian_Payments::get_entry($entry_id);

        if (Helpers::checkEntryForIDPay($entry)) {
            $dict = Helpers::loadDictionary();
            $user = Helpers::loadUser();
            $payment_status = sanitize_text_field(rgpost("payment_status"));
            $payment_amount       = sanitize_text_field(rgpost("payment_amount"));
            $payment_transaction  = sanitize_text_field(rgpost("IDPay_transaction_id"));
            $payment_date = sanitize_text_field(rgpost("payment_date"));
            $payment_time = sanitize_text_field(rgpost("payment_time"));
            $status = !empty($payment_status) ? $payment_status : null;
            $amount = !empty($payment_amount) ? $payment_amount : null;
            $trackId = !empty($payment_transaction) ? $payment_transaction : null;
            $date = !empty($payment_date) ? $payment_date : null;
            $time = !empty($payment_time) ? $payment_time : null;
            $jalaliDateTime = "{$date} {$time}";
            $date = Helpers::getMiladiDateTime($jalaliDateTime);
            $fullField = $status == 'Paid' ? 1 : 0;

            $entry["is_fulfilled"] = $fullField;
            $entry["payment_status"] = $status;
            $entry["payment_amount"] = $amount;
            $entry["payment_date"]   = $date;
            $entry["transaction_id"] = $trackId;
            GFAPI::update_entry($entry);

            $note =  sprintf($dict->report, $dict->{$status}, $amount, $trackId, $date);
            $style = Keys::CSS_MESSAGE_STYLE;
            $html = "<div style='{$style}'>{$note}</div>";
            RGFormsModel::add_note($entry_id, $user->id, $user->username, $html);
        }
    }
}
