<?php

class IDPayVerify extends Helpers {

	public static function doVerify() {

		$request = Helpers::getRequestData();
		$condition1 =  empty( $request );
		$entryId = !empty($request) ? $request->entryId : null;
		$formId = !empty($request) ? $request->formId : null;
		$condition2 =  IDPayVerify::isNotApprovedGettingTransaction( $entryId, $formId );

		if ( $condition1 || $condition2 ) {
			return 'error';
		}

		$entry       = GFPersian_Payments::get_entry( $request->entryId );
		$form        = RGFormsModel::get_form_meta( $request->formId );
		$paymentType = gform_get_meta( $request->entryId, 'payment_type' );
		gform_delete_meta( $request->entryId, 'payment_type' );
		$config = IDPayVerify::loadConfig( $entry, $form, $paymentType );
		if ( empty( $config ) ) {
			return 'error';
		}

		$pricing = Helpers::getPriceOrder( $paymentType, $entry, $form );

		$condition1 = IDPayVerify::checkTypeVerify() == 'Purchase';
		$condition2 = ! IDPayVerify::checkApprovedVerifyData( $request );

		if ( $condition1 && $condition2 ) {
			IDPayVerify::reject( $entry, $form, $request, $pricing, $config );
			IDPayVerify::processAddons( $form, $entry, $config, Keys::NO_PAYMENT );

			return 'error';
		}

		$transaction = (object) [];
		if ( IDPayVerify::checkTypeVerify() == 'Free' ) {
			$transaction = IDPayVerify::prepareFree( $entry, $form );
		}
		if ( IDPayVerify::checkTypeVerify() == 'Purchase' ) {
			$transaction = IDPayVerify::preparePurchase( $entry, $form, $request, $pricing, $config );
		}

		if ( $transaction == 'error' ) {
			IDPayVerify::processAddons( $form, $entry, $config, Keys::NO_PAYMENT );

			return 'error';
		} elseif ( $transaction->statusCode != 100 ) {
			$transactionData = Helpers::dataGet( $transaction, 'response' );
			$request         = IDPayVerify::appendDataToRequest( $request, $transactionData );
			IDPayVerify::reject( $entry, $form, $request, $pricing, $config );
			IDPayVerify::processAddons( $form, $entry, $config, Keys::NO_PAYMENT );

			return 'error';
		}

		if ( IDPayVerify::checkTypeVerify() == 'Free' ) {
			IDPayVerify::acceptFree( $transaction, $entry, $form, $request, $pricing, $config );
		}
		if ( IDPayVerify::checkTypeVerify() == 'Purchase' ) {
			IDPayVerify::acceptPurchase( $transaction, $entry, $form, $request, $pricing, $config );
		}

		IDPayVerify::processAddons( $form, $entry, $config, Keys::SUCCESS_PAYMENT );

		return true;
	}

	public static function prepareFree( $entry, $form ) {
		$entryData = GFPersian_Payments::transaction_id( $entry );
		$transactionId = apply_filters(Keys::HOOK_37,$entryData,$form,$entry);

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
		$entryId    = Helpers::dataGet( $entry, 'id' );
		$condition1 = $request->status != 10;
		$condition2 = $request->orderId != $entryId;
		$condition3 = Helpers::isNotDoubleSpending( $entryId, $request->orderId, $request->id ) != true;

		if ( $condition1 || $condition2 || $condition3 ) {
			return (object) [
				'status'     => 'Failed',
				'statusCode' => $request->status,
				'type'       => 'Purchase',
				'is_free'    => false,
				'id'         => $request->id,
				'amount'     => $pricing->amount,
				'response'   => []
			];
		}

		$response       = IDPayVerify::httpRequest( 'https://api.idpay.ir/v1.1/payment/verify', [
			'id'       => $request->id,
			'order_id' => $entryId
		] );
		$http_status    = wp_remote_retrieve_response_code( $response );
		$result         = json_decode( wp_remote_retrieve_body( $response ) ) ?? null;
		$errorResponder = IDPayVerify::checkErrorResponse( $response, $http_status, $result );

		if ( ! $errorResponder == false ) {
			$message = Helpers::dataGet( $errorResponder, 'message' );
			IDPayVerify::reject( $entry, $form, $request, $pricing, $config );

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

	public static function acceptPurchase( $transaction, $entry, $form, $request, $pricing, $config ) {
		$dict = Helpers::loadDictionary();
		$status        = 'Paid';
		$transactionId = $request->trackId ?? '';
		$statusCode    = $transaction->status ?? 0;
		$statusDesc    = IDPayVerify::getStatus( $statusCode );
		$entryId       = Helpers::dataGet( $entry, 'id' );
		$user          = IDPayVerify::loadUser();

		$entry["payment_date"]     = gmdate( "Y-m-d H:i:s" );
		$entry["payment_amount"]   = $transaction->amount;
		$entry["transaction_id"]   = $transactionId;
		$entry["payment_status"]   = $status;
		$entry["transaction_type"] = null;
		$entry["is_fulfilled"]     = 1;
		GFAPI::update_entry( $entry );

		IDPayVerify::sendSetFullFillTransactionGravityCore($entry,$config,$form,$transaction,$pricing);

		$noteAdmin = sprintf($dict->labelAcceptPurchase,$request->orderId,$request->trackId);
		$noteUser = sprintf($dict->labelAcceptPurchase,$request->orderId,$request->trackId);

		$noteAdmin =  Helpers::makePrintVariableNote($request->all,$noteAdmin);

		$entry = GFPersian_Payments::get_entry( $entryId );
		RGFormsModel::add_note( $entryId, $user->id, $user->username, $noteAdmin );

		IDPayVerify::sendSetFinalPriceGravityCore($config,$entry,$form,$status,$transactionId,$pricing);

		Helpers::processConfirmations( $form, $entry, $noteUser, $status, $config );
		GFPersian_Payments::notification( $form, $entry );
		GFPersian_Payments::confirmation( $form, $entry, $noteUser );

		return true;
	}

	public static function acceptFree( $transaction, $entry, $form, $request, $pricing, $config ) {
		$dict = Helpers::loadDictionary();
		$status        = 'Paid';
		$transactionId = $request->trackId ?? '';
		$statusCode    = $transaction->status ?? 0;
		$statusDesc    = IDPayVerify::getStatus( $statusCode );
		$entryId       = Helpers::dataGet( $entry, 'id' );
		$user          = IDPayVerify::loadUser();

		$entry["payment_date"]     = gmdate( "Y-m-d H:i:s" );
		$entry["transaction_id"]   = $transactionId;
		$entry["payment_method"]   = "NoGateway";
		$entry["payment_status"]   = $status;
		$entry["payment_amount"]   = 0;
		$entry["transaction_type"] = null;
		$entry["is_fulfilled"]     = null;
		GFAPI::update_entry( $entry );

		IDPayVerify::sendSetFullFillTransactionGravityCore($entry,$config,$form,$transaction,$pricing);

		gform_delete_meta( $entryId, 'payment_gateway' );
		$noteUser  = $dict->labelAcceptedFree;
		$noteAdmin  = $dict->labelAcceptedFree;

		$noteAdmin =  Helpers::makePrintVariableNote($request->all,$noteAdmin);

		$entry = GFPersian_Payments::get_entry( $entryId );

		RGFormsModel::add_note( $entryId, $user->id, $user->username, $noteAdmin );

		IDPayVerify::sendSetFinalPriceGravityCore($config,$entry,$form,$status,$transactionId,$pricing);
		Helpers::processConfirmations( $form, $entry, $noteUser, $status, $config );
		GFPersian_Payments::notification( $form, $entry );
		GFPersian_Payments::confirmation( $form, $entry, $noteUser );

		return true;
	}

	public static function reject( $entry, $form, $request, $pricing, $config ) {
		$dict = Helpers::loadDictionary();
		$transactionId = $request->trackId ?? '';
		$statusCode    = $request->status ?? 0;
		$statusDesc    = IDPayVerify::getStatus( $statusCode );
		$entryId       = Helpers::dataGet( $entry, 'id' );
		$user          = IDPayVerify::loadUser();
		$status        = 'Failed';

		$entry["payment_date"]     = gmdate( "Y-m-d H:i:s" );
		$entry["transaction_id"]   = $transactionId;
		$entry["payment_status"]   = $status;
		$entry["transaction_type"] = null;
		$entry["payment_amount"]   = (string) $pricing->amount;
		$entry["is_fulfilled"]     = 0;
		GFAPI::update_entry( $entry );

		$noteAdmin = sprintf($dict->labelRejectNote,$statusDesc,$statusCode);
		$noteUser = sprintf($dict->labelRejectNote,$statusDesc,$statusCode);

		$noteAdmin =  Helpers::makePrintVariableNote($request->all,$noteAdmin);

		$entry = GFPersian_Payments::get_entry( $entryId );
		RGFormsModel::add_note( $entryId, $user->id, $user->username, $noteAdmin );

		IDPayVerify::sendSetFinalPriceGravityCore($config,$entry,$form,$status,$transactionId,$pricing);
		Helpers::processConfirmations( $form, $entry, $noteUser, $status, $config );
		GFPersian_Payments::notification( $form, $entry );
		GFPersian_Payments::confirmation( $form, $entry, $noteUser );

		return true;
	}

}
