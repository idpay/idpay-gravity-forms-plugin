<?php

class IDPayVerify extends Helpers {

	public static $author = "IDPay";

	public static function reject( $entry, $form, $request, $pricing, $config ) {
		$transactionId = $request->trackId ?? '';
		$statusCode    = $request->status ?? 0;
		$statusDesc    = self::getStatus( $statusCode );
		$entryId       = self::dataGet( $entry, 'id' );
		$user          = self::loadUser();

		$entry["transaction_type"] = self::getTransactionType( $config );
		$entry["payment_date"]     = gmdate( "Y-m-d H:i:s" );
		$entry["transaction_id"]   = $transactionId;
		$entry["payment_status"]   = 'Failed';
		$entry["payment_amount"]   = 0;
		$entry["is_fulfilled"]     = 0;
		GFAPI::update_entry( $entry );

		$note = sprintf(
			__( 'وضعیت پرداخت یا عضویت :%s (کد خطا: %s) - مبلغ قابل پرداخت یا عضویت : %s', "gravityformsIDPay" ),
			$statusDesc,
			$statusCode,
			$pricing->money
		);
		$note .= print_r( $request->all, true );

		$entry = GFPersian_Payments::get_entry( $entryId );

		RGFormsModel::add_note( $entryId, $user->id, $user->username, $note );
		do_action(
			'gform_post_payment_status',
			$config,
			$entry,
			'Failed',
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
			'Failed',
			$transactionId,
			'',
			$pricing->amount,
			'',
			''
		);

		GFPersian_Payments::notification( $form, $entry );
		GFPersian_Payments::confirmation( $form, $entry, $note );

		return true;
	}

	public static function acceptPurchase( $transaction, $entry, $form, $request, $pricing, $config ) {
		$status        = self::getTransactionType( $config ) == 1 ? 'Paid' : 'Active';
		$transactionId = $request->trackId ?? '';
		$statusCode    = $transaction->status ?? 0;
		$statusDesc    = self::getStatus( $statusCode );
		$entryId       = self::dataGet( $entry, 'id' );
		$user          = self::loadUser();

		$entry["payment_date"]     = gmdate( "Y-m-d H:i:s" );
		$entry["transaction_id"]   = $transactionId;
		$entry["transaction_type"] = self::getTransactionType( $config );
		$entry["payment_status"]   = $status;
		$entry["payment_amount"]   = $transaction->amount;
		$entry["is_fulfilled"]     = 1;
		GFAPI::update_entry( $entry );

		do_action( "gform_IDPay_fulfillment", $entry, $config, $transaction->id, $pricing->amount );
		do_action( "gform_gateway_fulfillment", $entry, $config, $transaction->id, $pricing->amount );
		do_action( "gform_idpay_fulfillment", $entry, $config, $transaction->id, $pricing->amount );


		$note = sprintf(
			__( 'وضعیت پرداخت یا عضویت :%s (کد خطا: %s) - مبلغ قابل پرداخت یا عضویت : %s', "gravityformsIDPay" ),
			$statusDesc,
			$statusCode,
			$pricing->money
		);

		$note .= sprintf(
			__( ' پرداخت شما با موفقیت انجام شد. شماره سفارش: %s - کد رهگیری: %s', "gravityformsIDPay" ),
			$request->orderId,
			$request->trackId
		);

		$note .= print_r( $request->all, true );

		$entry = GFPersian_Payments::get_entry( $entryId );

		RGFormsModel::add_note( $entryId, $user->id, $user->username, $note );
		do_action(
			'gform_post_payment_status',
			$config,
			$entry,
			$status,
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
			$status,
			$transactionId,
			'',
			$pricing->amount,
			'',
			''
		);

		self::processConfirmations( $form, $request, $entry, $note, $status, $config );
		GFPersian_Payments::notification( $form, $entry );
		GFPersian_Payments::confirmation( $form, $entry, $note );

		return true;
	}

	public static function acceptFree( $transaction, $entry, $form, $request, $pricing, $config ) {
		$status        = self::getTransactionType( $config ) == 1 ? 'Paid' : 'Active';
		$transactionId = $request->trackId ?? '';
		$statusCode    = $transaction->status ?? 0;
		$statusDesc    = self::getStatus( $statusCode );
		$entryId       = self::dataGet( $entry, 'id' );
		$user          = self::loadUser();


		$entry["payment_date"]     = gmdate( "Y-m-d H:i:s" );
		$entry["transaction_id"]   = $transactionId;
		$entry["transaction_type"] = self::getTransactionType( $config );
		$entry["payment_status"]   = $status;
		$entry["payment_amount"]   = null;
		$entry["payment_method"]   = "NoGateway";
		$entry["is_fulfilled"]     = null;
		GFAPI::update_entry( $entry );

		do_action( "gform_IDPay_fulfillment", $entry, $config, $transaction->id, $pricing->amount );
		do_action( "gform_gateway_fulfillment", $entry, $config, $transaction->id, $pricing->amount );
		do_action( "gform_idpay_fulfillment", $entry, $config, $transaction->id, $pricing->amount );

		gform_delete_meta( $entryId, 'payment_gateway' );
		$note = __( 'وضعیت پرداخت : رایگان - بدون درگاه پرداخت', "gravityformsIDPay" );
		$note .= print_r( $request->all, true );

		$entry = GFPersian_Payments::get_entry( $entryId );

		RGFormsModel::add_note( $entryId, $user->id, $user->username, $note );
		do_action(
			'gform_post_payment_status',
			$config,
			$entry,
			$status,
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
			$status,
			$transactionId,
			'',
			$pricing->amount,
			'',
			''
		);

		self::processConfirmations( $form, $request, $entry, $note, $status, $config );
		GFPersian_Payments::notification( $form, $entry );
		GFPersian_Payments::confirmation( $form, $entry, $note );

		return true;
	}

	public static function prepareFree( $entry, $form ) {
		$transactionId = apply_filters(
			self::$author . '_gf_rand_transaction_id',
			GFPersian_Payments::transaction_id( $entry ),
			$form,
			$entry
		);

		return (object) [
			'status'     => 'completed',
			'statusCode' => 100,
			'type'       => 'Free',
			'is_free'    => true,
			'id'         => $transactionId,
			'amount'     => 0
		];
	}

	public static function preparePurchase( $entry, $form, $request, $pricing, $config ) {
		$entryId    = self::dataGet( $entry, 'id' );
		$condition1 = $request->status != 10;
		$condition2 = $request->orderId != $entryId;
		$condition3 = self::isNotDoubleSpending( $entryId, $request->orderId, $request->id ) != true;

		if ( $condition1 || $condition2 || $condition3 ) {
			return (object) [
				'status'     => 'Failed',
				'statusCode' => 2,
				'type'       => 'Purchase',
				'is_free'    => false,
				'id'         => $request->id,
				'amount'     => $pricing->amount,
				'response'   => []
			];
		}

		$response       = self::httpRequest( 'https://api.idpay.ir/v1.1/payment/verify', [
			'id'       => $request->id,
			'order_id' => $entryId
		] );
		$http_status    = wp_remote_retrieve_response_code( $response );
		$result         = json_decode( wp_remote_retrieve_body( $response ) ) ?? null;
		$errorResponder = self::checkErrorResponse( $response, $http_status, $result );

		if ( ! $errorResponder == false ) {
			$message = self::dataGet( $errorResponder, 'message' );
			self::reject( $entry, $form, $request, $pricing, $config );

			return 'error';
		}
		$statusCode = empty( $result->status ) ? null : $result->status;
		$trackId    = empty( $result->track_id ) ? null : $result->track_id;
		$amount     = empty( $result->amount ) ? null : $result->amount;

		$condition1 = $statusCode != 100;
		$condition2 = $amount != $pricing->amount;
		$condition3 = empty( $statusCode );
		$condition4 = empty( $amount );
		$condition5 = empty( $trackId );

		if ( $condition1 || $condition2 || $condition3 || $condition4 || $condition5 ) {
			return (object) [
				'status'     => 'Failed',
				'statusCode' => $statusCode,
				'type'       => 'Purchase',
				'is_free'    => false,
				'id'         => $request->id,
				'amount'     => $pricing->amount,
				'response'   => $result
			];
		}

		return (object) [
			'status'     => 'completed',
			'statusCode' => $statusCode,
			'type'       => 'Purchase',
			'is_free'    => false,
			'id'         => $request->id,
			'amount'     => $pricing->amount,
			'response'   => $result
		];
	}

	public static function checkErrorResponse( $response, $http_status, $result ) {

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();

			return [
				'message' => sprintf( __( 'خطا هنگام تایید تراکنش. پیام خطا: %s', 'gravityformsIDPay' ), $error )
			];
		} elseif ( $http_status != 200 || empty( $result ) || empty( $result->status ) || empty( $result->track_id ) ) {
			return [
				'message' => sprintf( 'خطا هنگام تایید تراکنش. : %s (کد خطا: %s)', $result->error_message, $result->error_code )
			];
		}

		return false;
	}

	public static function doVerify() {

		$request = self::getRequestData();

		if ( empty($request) || self::isNotApprovedGettingTransaction( $request->entryId, $request->formId ) ) {
			return 'error';
		}

		$entry       = GFPersian_Payments::get_entry( $request->entryId );
		$form        = RGFormsModel::get_form_meta( $request->formId );
		$paymentType = gform_get_meta( $request->entryId, 'payment_type' );
		gform_delete_meta( $request->entryId, 'payment_type' );
		$config = self::loadConfig( $entry, $form, $paymentType );
		if ( empty( $config ) ) {
			return 'error';
		}

		$pricing = self::getPriceOrder( $paymentType, $entry, $form );

		if ( self::checkTypeVerify() == 'Purchase' && ! self::checkApprovedVerifyData( $request ) ) {
			self::reject( $entry, $form, $request, $pricing, $config );
			self::processAddons( $form, $entry, $config, self::NO_PAYMENT );

			return 'error';
		}

		$transaction = (object) [];
		if ( self::checkTypeVerify() == 'Free' ) {
			$transaction = self::prepareFree( $entry, $form );
		}
		if ( self::checkTypeVerify() == 'Purchase' ) {
			$transaction = self::preparePurchase( $entry, $form, $request, $pricing, $config );
		}

		if ( $transaction == 'error' ) {
			self::processAddons( $form, $entry, $config, self::NO_PAYMENT );

			return 'error';
		} elseif ( $transaction->statusCode != 100 ) {
			$data    = (object) [
				'id'      => self::dataGet( $request, 'id' ),
				'status'  => self::dataGet( $transaction, 'statusCode' ),
				'trackId' => self::dataGet( $request, 'track_id' ),
				'orderId' => self::dataGet( $request, 'order_id' ),
				'formId'  => (int) self::dataGet( $_GET, 'form_id' ),
				'entryId' => (int) self::dataGet( $_GET, 'entry' ),
				'all'     => self::dataGet( $transaction, 'response' ),
			];
			$request = $data;
			self::reject( $entry, $form, $request, $pricing, $config );
			self::processAddons( $form, $entry, $config, self::NO_PAYMENT );

			return 'error';
		}

		if ( self::checkTypeVerify() == 'Free' ) {
			self::acceptFree( $transaction, $entry, $form, $request, $pricing, $config );
		}
		if ( self::checkTypeVerify() == 'Purchase' ) {
			self::acceptPurchase( $transaction, $entry, $form, $request, $pricing, $config );
		}

		self::processAddons( $form, $entry, $config, self::SUCCESS_PAYMENT );

		return true;
	}
}
