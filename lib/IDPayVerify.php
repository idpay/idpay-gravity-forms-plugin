<?php

class IDPayVerify extends Helpers {

	public static function doVerify() {

		$request = Helpers::getRequestData();
		$entryId = ! empty( $request ) ? $request->entryId : null;
		$formId  = ! empty( $request ) ? $request->formId : null;
		$transaction = (object) [];

		if ( empty( $request ) || empty( $entryId ) || empty( $formId ) ) {
			return Keys::VERIFY_COMPLETED;
		}

		$entry       = GFPersian_Payments::get_entry( $request->entryId );
		$form        = RGFormsModel::get_form_meta( $request->formId );
		$paymentType = gform_get_meta( $request->entryId, 'payment_type' );
		gform_delete_meta( $request->entryId, 'payment_type' );
		$config = IDPayVerify::loadConfig( $entry, $form, $paymentType );
		if ( empty( $config ) ) {
			return Keys::VERIFY_COMPLETED;
		}

		$pricing = Helpers::getPriceOrder( $paymentType, $entry, $form );

		if ( Helpers::isNotApprovedGettingTransaction( $entryId, $formId ) ) {
			IDPayVerify::completeVerify( Keys::TYPE_REJECTED, $transaction, $entry, $form, $request, $pricing, $config );

			return Keys::VERIFY_COMPLETED;
		}

		if ( Helpers::checkNotApprovedVerifyData( $request ) ) {
			IDPayVerify::completeVerify( Keys::TYPE_REJECTED, $transaction, $entry, $form, $request, $pricing, $config );
			IDPayVerify::processAddons( $form, $entry, $config, Keys::NO_PAYMENT );

			return Keys::VERIFY_COMPLETED;
		}

		$transaction = IDPayVerify::prepare( $entry, $form, $request, $pricing, $config );

		if ( $transaction == 'error' ) {
			IDPayVerify::processAddons( $form, $entry, $config, Keys::NO_PAYMENT );

			return Keys::VERIFY_COMPLETED;
		}

		$transactionData = (array) Helpers::dataGet( $transaction, 'response' );
		$request         = IDPayVerify::appendDataToRequest( $request, $transactionData );
		$condition = $transaction->statusCode == 100;
		$rj = Keys::TYPE_REJECTED;
		$type =  $condition ? (IDPayVerify::checkTypeVerify() == 'Free' ? Keys::TYPE_FREE : Keys::TYPE_PURCHASE ) : $rj;
		$paymentStatus =   $condition ? Keys::NO_PAYMENT : Keys::SUCCESS_PAYMENT;

		IDPayVerify::completeVerify( $type, $transaction, $entry, $form, $request, $pricing, $config );
		IDPayVerify::processAddons( $form, $entry, $config, $paymentStatus );

		return Keys::VERIFY_COMPLETED;
	}

	public static function prepare( $entry, $form, $request, $pricing, $config ) {

		if ( IDPayVerify::checkTypeVerify() == 'Free' ) {
			$entryData     = GFPersian_Payments::transaction_id( $entry );
			$transactionId = apply_filters( Keys::HOOK_37, $entryData, $form, $entry );

			return (object) [
				'status'     => 'completed',
				'statusCode' => 100,
				'type'       => 'Free',
				'is_free'    => true,
				'id'         => $transactionId,
				'amount'     => 0
			];
		}

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
		$errorResponder = Helpers::checkErrorResponse( $response, $http_status, $result );

		if ( ! $errorResponder == false ) {
			$message = Helpers::dataGet( $errorResponder, 'message' );
			IDPayVerify::completeVerify( Keys::TYPE_REJECTED, [], $entry, $form, $request, $pricing, $config );

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

	public static function completeVerify( $type, $transaction, $entry, $form, $request, $pricing, $config ) {
		$dict          = Helpers::loadDictionary();
		$transactionId = $request->trackId ?? '';
		$message       = Helpers::getMessageWithCode( $type, $transaction, $request,$entry );
		$messageCode   = $message->code;
		$messageDesc   = $message->description;
		$entryId       = Helpers::dataGet( $entry, 'id' );
		$user          = IDPayVerify::loadUser();
		$status        = $type != Keys::TYPE_REJECTED ? 'Paid' : 'Failed';
		$paymentMethod = $type == Keys::TYPE_FREE ? Keys::NONE_GATEWAY : Keys::AUTHOR;
		$fullFilled    = Keys::TRANSACTION_FINAL_STATE;
		$amount        = isset( $pricing ) ? $pricing->amount : 0;
		$date          = gmdate( "Y-m-d H:i:s" );

		$entry["payment_date"]   = $date;
		$entry["transaction_id"] = $transactionId;
		$entry["payment_method"] = $paymentMethod;
		$entry["payment_status"] = $status;
		$entry["payment_amount"] = $amount;
		$entry["is_fulfilled"]   = $fullFilled;
		GFAPI::update_entry( $entry );

		if ( $type != Keys::TYPE_REJECTED ) {
			IDPayVerify::sendSetFullFillTransactionGravityCore( $entry, $config, $form, $transaction, $pricing );
		}

		if ( $type != Keys::TYPE_FREE ) {
			gform_delete_meta( $entryId, 'payment_gateway' );
		}

		$orderId = isset( $request ) && ! empty( (array) $request ) ? $request->orderId : '';
		$trackId = isset( $request ) && ! empty( (array) $request ) ? $request->trackId : '';


		$notesCollectionUser = [
			Keys::TYPE_FREE     => $dict->labelAcceptedFree,
			Keys::TYPE_PURCHASE => sprintf( $dict->labelAcceptPurchase, $orderId, $trackId ),
			Keys::TYPE_REJECTED => sprintf( $dict->labelRejectNote, $messageDesc, $messageCode ),
		];

		$notesCollectionAdmin = [
			Keys::TYPE_FREE     => $dict->labelAcceptedFree,
			Keys::TYPE_PURCHASE => sprintf( $dict->labelAcceptPurchase, $orderId, $trackId ),
			Keys::TYPE_REJECTED => sprintf( $dict->labelRejectNote, $messageDesc, $messageCode ),
		];

		$noteUser  = $notesCollectionUser[ $type ];
		$noteAdmin = $notesCollectionAdmin[ $type ];
		$allData   = isset( $request ) && ! empty( (array) $request ) ? $request->all : [];
		$noteAdmin = Helpers::makePrintVariableNote( $allData, $noteAdmin );

		$entry = GFPersian_Payments::get_entry( $entryId );
		RGFormsModel::add_note( $entryId, $user->id, $user->username, $noteAdmin );
		IDPayVerify::sendSetFinalPriceGravityCore( $config, $entry, $form, $status, $transactionId, $pricing );
		Helpers::processConfirmations( $form, $entry, $noteUser, $status, $config );
		GFPersian_Payments::notification( $form, $entry );
		GFPersian_Payments::confirmation( $form, $entry, $noteUser );

		return true;
	}

}
