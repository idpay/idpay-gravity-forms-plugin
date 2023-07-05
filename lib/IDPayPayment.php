<?php

class IDPayPayment extends Helpers
{
    public static $author = "IDPay";

    public static function dataGet($target, $key, $default = null)
    {
        return Helpers::dataGet($target, $key, $default);
    }

    public static function processOrderCheckout($form, $entry)
    {
        $formId       = $form['id'];
        $Amount       = self::getOrderTotal($form, $entry);
        $applyFilters = [
            [ '_gform_form_gateway_price_', '_gform_form_gateway_price' ],
            [ '_gform_form_IDPay_price_', '_gform_form_IDPay_price' ],
            [ '_gform_gateway_price_', '_gform_gateway_price' ],
            [ '_gform_IDPay_price_', '_gform_IDPay_price' ],
        ];
        foreach ($applyFilters as $apply) {
            apply_filters(
                self::$author . "{$apply[0]}{$formId}",
                apply_filters(self::$author . $apply[1], $Amount, $form, $entry),
                $form,
                $entry
            );
        }

        return $Amount;
    }

    public static function doPaymentByAutoConfirmation($confirmation, $form, $entry, $ajax)
    {
        $formId  = $form['id'];
        $entryId = $entry['id'];

        if (! self::checkSubmittedForIDPay($formId) || ! self::checkFeedExists($form)) {
            return $confirmation;
        }
        $feed        = self::getFeed($form);
        $feedId      = $feed['id'];
        $feedType    = self::dataGet($feed, 'meta.type') == "subscription" ? "subscription" : "payment";
        $gravityType = self::getGravityTransactionTypeCode($feedType);
        $gatewayName = self::getGatewayName();
        $amount      = self::processOrderCheckout($form, $entry);

        gform_update_meta($entryId, 'IDPay_feed_id', $feedId);
        gform_update_meta($entryId, 'payment_type', 'form');
        gform_update_meta($entryId, 'payment_gateway', $gatewayName);

        return self::processPayment($amount, $gravityType, $feed, $entry, $form, $ajax);
    }

    public static function processPayment($amount, $gravityType, $feed, $entry, $form, $ajax)
    {
        $formId = $form['id'];

        if (self::checkTypePayment($amount) == 'Free') {
            $confirmation = self::processFreePayment($entry, $formId, $ajax);

            return [
                'transactionType' => 'Free',
                'data'            => [
                    'confirmation' => $confirmation
                ]
            ];
        } elseif (self::checkTypePayment($amount) == 'Purchase') {
            $data = self::processPurchasePayment($feed, $entry, $form);

            return [
                'transactionType' => 'Purchase',
                'data'            => [
                    'amount'      => self::fixPrice($amount, $form, $entry),
                    'gravityType' => $gravityType,
                    'mobile'      => self::dataGet($data, 'mobile'),
                    'name'        => self::dataGet($data, 'name'),
                    'mail'        => self::dataGet($data, 'mail'),
                    'description' => self::dataGet($data, 'description'),
                ]
            ];
        }
    }

    public static function checkTypePayment($amount): string
    {
        return empty($amount) || $amount == 0 ? 'Free' : 'Purchase';
    }

    public static function doPaymentByCustomConfirmation($confirmation, $form, $entry, $ajax)
    {
        $formId      = $form['id'];
        $feed        = self::getFeed($form);
        $feedType    = self::dataGet($feed, 'meta.type') == "subscription" ? "subscription" : "payment";
        $gravityType = self::getGravityTransactionTypeCode($feedType);

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
        $entry   = GFPersian_Payments::get_entry($entryId);

        do_action('gf_gateway_request_add_entry', $confirmation, $form, $entry, $ajax);
        do_action('gf_IDPay_request_add_entry', $confirmation, $form, $entry, $ajax);

        gform_update_meta($entryId, 'payment_gateway', self::getGatewayName());
        gform_update_meta($entryId, 'payment_type', 'custom');

        return self::processPayment($amount, $gravityType, $feed, $entry, $form, $ajax);
    }

    public static function processFreePayment($entry, $formId, $ajax)
    {

        $entry["payment_status"]   = null;
        $entry["is_fulfilled"]     = null;
        $entry["transaction_type"] = null;
        $entry["payment_amount"]   = null;
        $entry["payment_date"]     = null;
        $entry["payment_method"]   = "IDPay";
        GFAPI::update_entry($entry);

        $queryArgs1  = [ 'no' => 'true' ];
        $queryArgs2  = self::Return_URL($formId, $entry['id']);
        $queryParams = add_query_arg($queryArgs1, $queryArgs2);

        return self::redirect_confirmation($queryParams, $ajax);
    }

    public static function processPurchasePayment($feed, $entry, $form)
    {

        $formId  = $form['id'];
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

        return [
            'mobile'      => GFPersian_Payments::fix_mobile($Mobile),
            'name'        => $Name,
            'mail'        => $Mail,
            'description' => $Description,
        ];
    }

    public static function rejectPaymentTransaction($entry, $form, $Message = '')
    {
        $entryId      = $entry['id'];
        $formId       = $form['id'];
        $Message      = ! empty($Message) ? $Message : __('خطایی رخ داده است. به نظر میرسد این خطا به علت مبلغ ارسالی اشتباه باشد', 'gravityformsIDPay');
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
        $nl2br    = ! ( ! empty($form['confirmation']) && rgar($form['confirmation'], 'disableAutoformat') );
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

    public static function rejectVerifyTransaction($entry, $form, $transaction_type, $request, $pricing, $config)
    {
        $Status        = 'Failed';
        $transactionId = $request->trackId ?? '';
        $statusCode    = $request->status ?? 0;
        $statusDesc    = self::getStatus($statusCode);
        $entryId       = self::dataGet($entry, 'id');
        $user          = self::loadUser();

        $entry["payment_date"]     = gmdate("Y-m-d H:i:s");
        $entry["transaction_id"]   = $transactionId;
        $entry["transaction_type"] = $transaction_type;
        $entry["payment_status"]   = "Failed";
        $entry["payment_amount"]   = 0;
        $entry["is_fulfilled"]     = 0;
        GFAPI::update_entry($entry);

        $note = sprintf(
            __('وضعیت پرداخت :%s (کد خطا: %s) - مبلغ قابل پرداخت : %s', "gravityformsIDPay"),
            $statusDesc,
            $statusCode,
            $pricing->money
        );
        $note .= print_r($request->all, true);

        $entry = GFPersian_Payments::get_entry($entryId);

        RGFormsModel::add_note($entryId, $user->id, $user->username, $note);
        do_action(
            'gform_post_payment_status',
            $config,
            $entry,
            strtolower($Status),
            $transactionId,
            '',
            $pricing->amount,
            '',
            ''
        );
        do_action(
            'gform_post_payment_status_' . __CLASS__,
            $config,
            $form,
            $entry,
            strtolower($Status),
            $transactionId,
            '',
            $pricing->amount,
            '',
            ''
        );

        GFPersian_Payments::notification($form, $entry);
        GFPersian_Payments::confirmation($form, $entry, $note);

        return true;
    }

    public static function checkErrorPaymentResponse($response, $http_status, $result)
    {

        if (is_wp_error($response)) {
            $error = $response->get_error_message();

            return [
                'message' => sprintf(__('خطا هنگام ایجاد تراکنش. پیام خطا: %s', 'gravityformsIDPay'), $error)
            ];
        } elseif ($http_status != 201 || empty($result) || empty($result->id) || empty($result->link)) {
            return [
                'message' => sprintf('خطا هنگام ایجاد تراکنش. : %s (کد خطا: %s)', $result->error_message, $result->error_code)
            ];
        }

        return false;
    }

    public static function checkErrorVerifyResponse($response, $http_status, $result)
    {

        if (is_wp_error($response)) {
            $error = $response->get_error_message();

            return [
                'message' => sprintf(__('خطا هنگام تایید تراکنش. پیام خطا: %s', 'gravityformsIDPay'), $error)
            ];
        } elseif ($http_status != 200 || empty($result) || empty($result->status) || empty($result->track_id)) {
            return [
                'message' => sprintf('خطا هنگام تایید تراکنش. : %s (کد خطا: %s)', $result->error_message, $result->error_code)
            ];
        }

        return false;
    }

    public static function prepareFreeTransactionVerify($entry, $form)
    {
        $transactionId = apply_filters(
            self::$author . '_gf_rand_transaction_id',
            GFPersian_Payments::transaction_id($entry),
            $form,
            $entry
        );

        return (object) [
            'status'  => 'completed',
            'type'    => 'Free',
            'is_free' => true,
            'id'      => $transactionId
        ];
    }

    public static function preparePurchaseTransactionVerify(
        $entry,
        $form,
        $request,
        $transaction_type,
        $pricing,
        $config
    ) {

        $entryId    = self::dataGet($entry, 'id');
        $condition1 = $request->status != 10;
        $condition2 = $request->orderId != $entryId;
        $condition3 = self::isNotDoubleSpending($entryId, $request->orderId, $request->id) != true;

        if ($condition1 || $condition2 || $condition3) {
            return (object) [
                'status'  => 'Failed',
                'type'    => 'Purchase',
                'is_free' => false,
                'id'      => $request->id
            ];
        }

        $response = self::httpRequest('https://api.idpay.ir/v1.1/payment/verify', [
	        'id'       => $request->id,
	        'order_id' => $entryId
        ]);
        $http_status = wp_remote_retrieve_response_code($response);
        $result         = json_decode(wp_remote_retrieve_body($response)) ?? null;
        $errorResponder = self::checkErrorVerifyResponse($response, $http_status, $result);

        if (! $errorResponder == false) {
            $message = self::dataGet($errorResponder, 'message');
            return self::rejectVerifyTransaction($entry, $form, $transaction_type, $request, $pricing, $config);
        }
            $verify_status   = empty($result->status) ? null : $result->status;
            $verify_track_id = empty($result->track_id) ? null : $result->track_id;
            $verify_amount   = empty($result->amount) ? null : $result->amount;
            $Transaction_ID  = ! empty($verify_track_id) ? $verify_track_id : '-';

        if ($verify_status != 100 ||$verify_amount != $pricing->amount) {
            return (object) [
                'status'  => 'Failed',
                'type'    => 'Purchase',
                'is_free' => false,
                'id'      => $request->id
            ];
        }

        return (object) [
            'status'  => 'completed',
            'type'    => 'Purchase',
            'is_free' => false,
            'id'      => $request->id
        ];
    }

    public static function doPayment($confirmation, $form, $entry, $ajax)
    {
        $entryId = $entry['id'];
        $formId  = $form['id'];

        if (! self::checkOneConfirmationExists($confirmation, $form, $entry, $ajax)) {
            return $confirmation;
        }

        $resp = null;
        if ($confirmation == 'custom') {
            $resp = self::doPaymentByCustomConfirmation($confirmation, $form, $entry, $ajax);
        } else {
            $resp = self::doPaymentByAutoConfirmation($confirmation, $form, $entry, $ajax);
        }

        if (self::dataGet($resp, 'transactionType') == 'Free') {
            return self::dataGet($resp, 'data.confirmation');
        } elseif (self::dataGet($resp, 'transactionType') == 'Purchase') {
            $data   = self::dataGet($resp, 'data');
            $amount = self::dataGet($data, 'amount');
            date_default_timezone_set("Asia/Tehran");

            $entry["payment_amount"]   = (float) $amount;
            $entry["payment_date"]     = date_create()->format('Y-m-d H:i:s');
            $entry["transaction_id"]   = null;
            $entry["payment_status"]   = "Processing";
            $entry["payment_method"]   = "IDPay";
            $entry["is_fulfilled"]     = 0;
            $entry["transaction_type"] = self::dataGet($data, 'gravityType');
            GFAPI::update_entry($entry);
            $entry      = GFPersian_Payments::get_entry($entryId);
            $ReturnPath = self::Return_URL($formId, $entryId);

            if (self::isNotApprovedPrice($amount)) {
                return self::rejectPaymentTransaction($entry, $form);
            }

            $paymentDto = [
                'order_id' => $entryId,
                'amount'   => $amount,
                'callback' => $ReturnPath,
                'name'     => self::dataGet($data, 'name'),
                'mail'     => self::dataGet($data, 'mail'),
                'desc'     => self::dataGet($data, 'description'),
                'phone'    => self::dataGet($data, 'mobile'),
            ];

            $response       = self::httpRequest('https://api.idpay.ir/v1.1/payment', $paymentDto);
            $http_status    = wp_remote_retrieve_response_code($response);
            $result         = json_decode(wp_remote_retrieve_body($response)) ?? null;
            $errorResponder = self::checkErrorPaymentResponse($response, $http_status, $result);

            if (! $errorResponder == false) {
                $message = self::dataGet($errorResponder, 'message');

                return self::rejectPaymentTransaction($entry, $form, $message);
            }

            gform_update_meta($entryId, "IdpayTransactionId:$entryId", $result->id);

            return self::redirect_confirmation($result->link, $ajax);
        }

        return $confirmation;
    }

    public static function doVerify()
    {
        $formId  = (int) sanitize_text_field(rgget('form_id'));
        $entryId = (int) sanitize_text_field(rgget('entry'));
        $entry   = GFPersian_Payments::get_entry($entryId);

        if (self::isNotApprovedGettingTransaction($entry, $formId)) {
            return;
        }

        $form        = RGFormsModel::get_form_meta($formId);
        $paymentType = gform_get_meta($entryId, 'payment_type');
        gform_delete_meta($entryId, 'payment_type');
        $config = self::loadConfig($entry, $form, $paymentType);
        if (empty($config)) {
            return;
        }

        $userData         = self::loadUser();
        $transaction_type = self::getTransactionType($config);
        $pricing = self::getPriceOrder($paymentType, $entry, $form);
        $request = self::getRequestData();

        if (self::checkTypeVerify() == 'Purchase' && empty($data)) {
            return self::rejectVerifyTransaction($entry, $form, $transaction_type, $request, $pricing, $config);
        }

        if (self::checkTypeVerify() == 'Free') {
            $transaction = self::prepareFreeTransactionVerify($entry, $form);
        } elseif (self::checkTypeVerify() == 'Purchase') {
            $transaction = self::preparePurchaseTransactionVerify($entry, $form);
        }
    }
}
