<?php

class IDPayVerify extends Helpers
{
    public static $author = "IDPay";

    public static function dataGet($target, $key, $default = null)
    {
        return Helpers::dataGet($target, $key, $default);
    }

	public static function doVerify()
	{
		$request = self::getRequestData();

		if (self::isNotApprovedGettingTransaction($request->entryId, $request->formId)) {
			return 'Error';
		}

		$entry   = GFPersian_Payments::get_entry($request->entryId);
		$form        = RGFormsModel::get_form_meta($request->formId);
		$paymentType = gform_get_meta($request->entryId, 'payment_type');
		gform_delete_meta($request->entryId, 'payment_type');
		$config = self::loadConfig($entry, $form, $paymentType);
		if (empty($config)) {
			return 'Error';
		}

		$transaction_type = self::getTransactionType($config);
		$pricing = self::getPriceOrder($paymentType, $entry, $form);

		if (self::checkTypeVerify() == 'Purchase' && !self::checkApprovedVerifyData($request)) {
			return self::rejectVerifyTransaction($entry, $form, $transaction_type, $request, $pricing, $config);
		}

		if (self::checkTypeVerify() == 'Free') {
			$transaction = self::prepareFreeTransactionVerify($entry, $form);
		} elseif (self::checkTypeVerify() == 'Purchase') {
			$transaction = self::preparePurchaseTransactionVerify($entry, $form,$request,$pricing,$config);
			if($transaction == 'Error') {
				return 'Error';
			}

		}
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
		$entry["payment_status"]   = $Status;
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
			'statusCode'  => 0,
			'type'    => 'Free',
			'is_free' => true,
			'id'      => $transactionId
		];
	}

	public static function preparePurchaseTransactionVerify(
		$entry,
		$form,
		$request,
		$pricing,
		$config
	) {

		$transaction_type = self::getTransactionType($config);
		$entryId    = self::dataGet($entry, 'id');
		$condition1 = $request->status != 10;
		$condition2 = $request->orderId != $entryId;
		$condition3 = self::isNotDoubleSpending($entryId, $request->orderId, $request->id) != true;

		if ($condition1 || $condition2 || $condition3) {
			return (object) [
				'status'  => 'Failed',
				'statusCode'  => 0,
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
			self::rejectVerifyTransaction($entry, $form, $transaction_type, $request, $pricing, $config);
			return 'Error';
		}
		$verify_status   = empty($result->status) ? null : $result->status;
		$verify_track_id = empty($result->track_id) ? null : $result->track_id;
		$verify_amount   = empty($result->amount) ? null : $result->amount;
		$Transaction_ID  = ! empty($verify_track_id) ? $verify_track_id : '-';

		if ($verify_status != 100 ||$verify_amount != $pricing->amount) {
			return (object) [
				'status'  => 'Failed',
				'statusCode'  => $verify_status,
				'type'    => 'Purchase',
				'is_free' => false,
				'id'      => $request->id
			];
		}

		return (object) [
			'status'  => 'completed',
			'statusCode'  => 100,
			'type'    => 'Purchase',
			'is_free' => false,
			'id'      => $request->id
		];
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

}
