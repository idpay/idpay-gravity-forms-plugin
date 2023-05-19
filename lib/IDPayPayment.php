<?php

class IDPayPayment
{
    public static function getGravityTransactionTypeCode($type)
    {
        return $type == "subscription" ? 2 : 1 ;
    }

    public static function Request($confirmation, $form, $entry, $ajax)
    {
        // do_action('gf_gateway_request_1', $confirmation, $form, $entry, $ajax);
        //  do_action('gf_IDPay_request_1', $confirmation, $form, $entry, $ajax);

        $entryId = $entry['id'];
        $formId = $form['id'];

        if (! self::checkOneConfirmationExists($confirmation, $form, $entry, $ajax)) {
            return $confirmation;
        }

        if ($confirmation != 'custom') {
            if (! self::checkSubmittedForIDPay($formId) || ! self::checkConfigExists($form)) {
                return $confirmation;
            }

            $feed = IDPayDB::getActiveFeed($form);
            $feedId = $feed['id'];
            $feedType = $feed["meta"]["type"] == "subscription" ? "subscription" : "payment";
            $gatewayName = self::getGatewayName();
            $transactionType = self::getGravityTransactionTypeCode($feedType);
            $amount = self::processOrderCheckout($form, $entry);

            gform_update_meta($entryId, 'IDPay_feed_id', $feedId);
            gform_update_meta($entryId, 'payment_type', 'form');
            gform_update_meta($entryId, 'payment_gateway', $gatewayName);


            if (empty($amount) || ! $amount || $amount == 0) {
                unset(
                    $entry["payment_status"],
                    $entry["is_fulfilled"],
                    $entry["transaction_type"],
                    $entry["payment_amount"],
                    $entry["payment_date"]
                );

                $entry["payment_method"] = "IDPay";
                GFAPI::update_entry($entry);

                $queryArgs1 = ['no' => 'true'];
                $queryArgs2 = self::Return_URL($formId, $entry['id']);
                $queryParams = add_query_arg($queryArgs1, $queryArgs2);
                $redirectConfirmation = self::redirect_confirmation($queryParams, $ajax);

                return $redirectConfirmation;
            } else {
                $desc_pm = $feed["meta"]["desc_pm"];
                $desc    = $feed["meta"]["customer_fields_desc"];
                $mobile  = $feed["meta"]["customer_fields_mobile"];
                $name    = $feed["meta"]["customer_fields_name"];
                $email   = $feed["meta"]["customer_fields_email"];

                $Desc1       = ! empty($desc_pm) ? str_replace([
                    '{entry_id}',
                    '{form_title}',
                    '{form_id}'
                ], [ $entry['id'], $form['title'], $formId ], $desc_pm) : '';
                $Desc2       = ! empty($desc) ? rgpost('input_' . str_replace(".", "_", $desc)) : '';
                $Description = sanitize_text_field($Desc1 . ( ! empty($Desc1) && ! empty($Desc2) ? ' - ' : '' ) . $Desc2 . ' ');
                $Mobile      = ! empty($mobile) ? sanitize_text_field(rgpost('input_' . str_replace(".", "_", $mobile))) : '';
                $Name        = ! empty($name) ? sanitize_text_field(rgpost('input_' . str_replace(".", "_", $name))) : '';
                $Mail        = ! empty($email) ? sanitize_text_field(rgpost('input_' . str_replace(".", "_", $email))) : '';
            }
        } else {
            $amount = gform_get_meta(rgar($entry, 'id'), 'IDPay_part_price_' . $formId);
            $amount = apply_filters(self::$author . "_gform_custom_gateway_price_{$formId}", apply_filters(self::$author . "_gform_custom_gateway_price", $amount, $form, $entry), $form, $entry);
            $amount = apply_filters(self::$author . "_gform_custom_IDPay_price_{$formId}", apply_filters(self::$author . "_gform_custom_IDPay_price", $amount, $form, $entry), $form, $entry);
            $amount = apply_filters(self::$author . "_gform_gateway_price_{$formId}", apply_filters(self::$author . "_gform_gateway_price", $amount, $form, $entry), $form, $entry);
            $amount = apply_filters(self::$author . "_gform_IDPay_price_{$formId}", apply_filters(self::$author . "_gform_IDPay_price", $amount, $form, $entry), $form, $entry);

            $Description = gform_get_meta(rgar($entry, 'id'), 'IDPay_part_desc_' . $formId);
            $Description = apply_filters(self::$author . '_gform_IDPay_gateway_desc_', apply_filters(self::$author . '_gform_custom_gateway_desc_', $Description, $form, $entry), $form, $entry);

            $Name   = gform_get_meta(rgar($entry, 'id'), 'IDPay_part_name_' . $formId);
            $Mail   = gform_get_meta(rgar($entry, 'id'), 'IDPay_part_email_' . $formId);
            $Mobile = gform_get_meta(rgar($entry, 'id'), 'IDPay_part_mobile_' . $formId);

            $entryId = GFAPI::add_entry($entry);
            $entry    = GFPersian_Payments::get_entry($entryId);

            do_action('gf_gateway_request_add_entry', $confirmation, $form, $entry, $ajax);
            do_action('gf_IDPay_request_add_entry', $confirmation, $form, $entry, $ajax);

            gform_update_meta($entryId, 'payment_gateway', self::getGatewayName());
            gform_update_meta($entryId, 'payment_type', 'custom');
        }

        unset($entry["transaction_type"]);
        unset($entry["payment_amount"]);
        unset($entry["payment_date"]);
        unset($entry["transaction_id"]);



        $entry["payment_status"]   = "Processing";
        $entry["payment_method"]   = "IDPay";
        $entry["is_fulfilled"]     = 0;
        $entry["transaction_type"] = $transactionType;
        GFAPI::update_entry($entry);

        $entry      = GFPersian_Payments::get_entry($entryId);
        $ReturnPath = self::Return_URL($formId, $entryId);
        $Mobile     = GFPersian_Payments::fix_mobile($Mobile);

        do_action('gf_gateway_request_2', $confirmation, $form, $entry, $ajax);
        do_action('gf_IDPay_request_2', $confirmation, $form, $entry, $ajax);

        $amount = GFPersian_Payments::amount($amount, 'IRR', $form, $entry);
        if (empty($amount) || ! $amount || $amount > 500000000 || $amount < 1000) {
            $Message = __('مبلغ ارسالی اشتباه است.', 'gravityformsIDPay');
        } else {
            $data    = array(
                'order_id' => $entryId,
                'amount'   => $amount,
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
                gform_update_meta($entryId, "IdpayTransactionId:$entryId", $result->id);

                return self::redirect_confirmation($result->link, $ajax);
            }
        }

        $Message      = ! empty($Message) ? $Message : __('خطایی رخ داده است.', 'gravityformsIDPay');
        $confirmation = __('متاسفانه نمیتوانیم به درگاه متصل شویم. علت : ', 'gravityformsIDPay') . $Message;

        $entry                   = GFPersian_Payments::get_entry($entryId);
        $entry['payment_status'] = 'Failed';
        GFAPI::update_entry($entry);

        global $current_user;
        $user_id   = 0;
        $user_name = __('مهمان', 'gravityformsIDPay');
        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id   = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        RGFormsModel::add_note($entryId, $user_id, $user_name, sprintf(__('خطا در اتصال به درگاه رخ داده است : %s', "gravityformsIDPay"), $Message));
        GFPersian_Payments::notification($form, $entry);

        $anchor   = gf_apply_filters('gform_confirmation_anchor', $formId, 0) ? "<a id='gf_{$formId}' name='gf_{$formId}' class='gform_anchor' ></a>" : '';
        $nl2br    = ! empty($form['confirmation']) && rgar($form['confirmation'], 'disableAutoformat') ? false : true;
        $cssClass = rgar($form, 'cssClass');

        $output = "{$anchor} ";
        if (! empty($confirmation)) {
            $output .= "
                <div id='gform_confirmation_wrapper_{$formId}' class='gform_confirmation_wrapper {$cssClass}'>
                    <div id='gform_confirmation_message_{$formId}' class='gform_confirmation_message_{$formId} gform_confirmation_message'>" .
                       GFCommon::replace_variables($confirmation, $form, $entry, false, true, $nl2br) .
                       '</div>
                </div>';
        }
        $confirmation = $output;

        return $confirmation;
    }
}
