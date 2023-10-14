<?php

class IDPayVerify extends Helpers {

	public static function doVerify() {

		$request = Helpers::getRequestData();
		$entryId = Helpers::dataGet($request,'entryId');
		$formId  =  Helpers::dataGet($request,'formId');
		$status  =  Helpers::dataGet($request,'status');
		$transId =  Helpers::dataGet($request,'id');
		$trackId =  Helpers::dataGet($request,'trackId');

		$transaction = (object) [
			'id'         => $transId,
			'trackId'    => $trackId,
			'entryId'    => $entryId,
			'formId'     => $formId,
			'statusCode' => $status,
		];

		if ( empty( $request ) || empty( $entryId ) || empty( $formId ) ) {
			return Keys::VERIFY_COMPLETED;
		}

		$transaction->entry       = GFPersian_Payments::get_entry( $entryId );
		$transaction->form        = RGFormsModel::get_form_meta( $formId );
		$transaction->paymentType = gform_get_meta( $entryId, 'payment_type' );
		gform_delete_meta( $entryId, 'payment_type' );

		$transaction->config = Helpers::loadConfig( $transaction );

		if ( empty( $transaction->config ) ) {
			return Keys::VERIFY_COMPLETED;
		}

		$transaction->amount = Helpers::getPriceOrder( $transaction );

		if ( Helpers::isNotApprovedGettingTransaction( $entryId, $formId ) ) {
			IDPayVerify::completeVerify(Keys::TYPE_REJECTED, $transaction, $request);

			return Keys::VERIFY_COMPLETED;
		}

		if ( Helpers::checkNotApprovedVerifyData( $request ) ) {
			IDPayVerify::completeVerify(Keys::TYPE_REJECTED, $transaction, $request);
			IDPayVerify::processAddons( Keys::NO_PAYMENT, $transaction);

			return Keys::VERIFY_COMPLETED;
		}

		$transaction = IDPayVerify::prepare($transaction, $request);

		if ( $transaction->status ==  Keys::NO_PAYMENT ) {
			IDPayVerify::completeVerify( Keys::TYPE_REJECTED, $transaction, $request);
			IDPayVerify::processAddons( Keys::NO_PAYMENT, $transaction);
			return Keys::VERIFY_COMPLETED;
		}

		$transactionData = (array) Helpers::dataGet( $transaction, 'response' );
		$request         = IDPayVerify::appendDataToRequest( $request, $transactionData );
		$condition       = $transaction->statusCode == Keys::VERIFY_SUCCESS;
		$type = IDPayVerify::checkTypeVerify() == 'Free' ? Keys::TYPE_FREE : Keys::TYPE_PURCHASE;
		$type            = $condition ? $type : Keys::TYPE_REJECTED;
		$paymentStatus   = $condition ? Keys::NO_PAYMENT : Keys::SUCCESS_PAYMENT;

		IDPayVerify::completeVerify( $type, $transaction, $request);
		IDPayVerify::processAddons( $paymentStatus,$transaction);

		return Keys::VERIFY_COMPLETED;
	}


	public static function getFinalTransactionId( $request, $entry, $form ) {
		if ( IDPayVerify::checkTypeVerify() == 'Free' ) {
			$entryData = GFPersian_Payments::transaction_id( $entry );

			return apply_filters( Keys::HOOK_37, $entryData, $form, $entry );
		}

		return $request->id ?? null;
	}

	public static function prepare($transaction, $request) {
		$form        = $transaction->form;
		$entry       = $transaction->entry;

		$transaction->id =  IDPayVerify::getFinalTransactionId($request, $entry, $form);

		if ( IDPayVerify::checkTypeVerify() == 'Free' ) {
			$transaction->status =  Keys::SUCCESS_PAYMENT;
			$transaction->statusCode =  Keys::VERIFY_SUCCESS;
			$transaction->type =  Keys::TYPE_FREE;
			$transaction->response =  [];
			return $transaction;
		}

		$entryId    = Helpers::dataGet( $entry, 'id' );
		$condition1 = $transaction->statusCode != Keys::PAYMENT_SUCCESS;
		$condition2 = Helpers::isNotDoubleSpending( $entryId, $transaction->id ) != true;

		if ( $condition1 || $condition2 ) {
			$transaction->status =  Keys::NO_PAYMENT;
			$transaction->type =  Keys::TYPE_PURCHASE;
			$transaction->response =  [];
			return $transaction;
		}

		$data = [
			'id' => $transaction->id,
			'order_id' => $transaction->entryId
		];

		$httpClient = (object) [];
		$httpClient->request       = IDPayVerify::httpRequest( 'https://api.idpay.ir/v1.1/payment/verify',$data);
		$httpClient->statusCode    = (int) wp_remote_retrieve_response_code( $httpClient->request );
		$httpClient->response      = json_decode( wp_remote_retrieve_body( $httpClient->request ) ) ?? null;
		$errorResponder = IDPayVerify::checkErrorResponseVerify($httpClient);

		if ( ! $errorResponder == false ) {
			$transaction->status =  Keys::NO_PAYMENT;
			$transaction->statusCode  = $httpClient->statusCode;
			$transaction->type =  Keys::TYPE_PURCHASE;
			$transaction->description = Helpers::dataGet( $errorResponder, 'message' );
			$transaction->response =  [];
			return $transaction;
		}

		$statusCode = (int) Helpers::dataGet($httpClient->response,'status');
		$trackId = Helpers::dataGet($httpClient->response,'track_id');
		$amount = Helpers::dataGet($httpClient->response,'amount');

		$condition1 = $statusCode != Keys::VERIFY_SUCCESS;
		$condition2 = $transaction->amount != $amount  ;
		$condition3 = empty( $statusCode ) || empty( $amount ) || empty( $trackId );

		if ( $condition1 || $condition2 || $condition3) {
			$transaction->status =  Keys::NO_PAYMENT;
			$transaction->statusCode =  $statusCode ?? 0;
			$transaction->type =  Keys::TYPE_PURCHASE;
			$transaction->response = $httpClient->response;
			return $transaction;
		}

		$transaction->status =  Keys::SUCCESS_PAYMENT;
		$transaction->statusCode =  $statusCode ?? 0;
		$transaction->type =  Keys::TYPE_PURCHASE;
		$transaction->response = $httpClient->response;
		return $transaction;
	}

	public static function completeVerify( $type, $transaction, $request ) {
		$transactionId = $transaction->id;
		$form    = $transaction->form;
		$entry   = $transaction->entry;
		$config  = $transaction->config;

		$transactionIsFinal = Helpers::dataGet( $entry, 'is_fulfilled' );
		$dict          = Helpers::loadDictionary();
		$message       = Helpers::getMessageWithCode( $type, $transaction, $transactionIsFinal);
		$messageCode   = $message->code;
		$messageDesc   = $message->description;


		$entryId       = Helpers::dataGet( $entry, 'id' );
		$user          = IDPayVerify::loadUser();
		$status        = $type != Keys::TYPE_REJECTED ? 'Paid' : 'Failed';
		$paymentMethod = $type == Keys::TYPE_FREE ? Keys::NONE_GATEWAY : Keys::AUTHOR;
		$fullFilled    = Keys::TRANSACTION_FINAL_STATE;
		$amount        = Helpers::dataGet($transaction,'amount',0);
		$date          = gmdate( "Y-m-d H:i:s" );

		if ( Helpers::dataGet( $entry, 'is_fulfilled' ) == self::TRANSACTION_IN_PROGRESS_STATE ) {
			$entry["payment_date"]   = $date;
			$entry["transaction_id"] = $transactionId;
			$entry["payment_method"] = $paymentMethod;
			$entry["payment_status"] = $status;
			$entry["payment_amount"] = $amount;
			$entry["is_fulfilled"]   = $fullFilled;
			GFAPI::update_entry( $entry );

			if ( $type != Keys::TYPE_REJECTED ) {
				Helpers::sendSetFullFillTransactionGravityCore( $transaction );
			}

			if ( $type != Keys::TYPE_FREE ) {
				gform_delete_meta( $entryId, 'payment_gateway' );
			}

		}

		$transId = isset( $transaction ) && ! empty( (array) $transaction ) ? $transaction->id : '';
		$trackId = isset( $request ) && ! empty( (array) $request ) ? $request->trackId : '';


		$notesCollectionUser = [
			Keys::TYPE_FREE     => $dict->labelAcceptedFree,
			Keys::TYPE_PURCHASE => sprintf( $dict->labelAcceptPurchase, $transId, $trackId ),
			Keys::TYPE_REJECTED => sprintf( $dict->labelRejectNote, $messageDesc, $messageCode ),
		];

		$notesCollectionAdmin = [
			Keys::TYPE_FREE     => $dict->labelAcceptedFree,
			Keys::TYPE_PURCHASE => sprintf( $dict->labelAcceptPurchase, $transId, $trackId ),
			Keys::TYPE_REJECTED => sprintf( $dict->labelRejectNote, $messageDesc, $messageCode ),
		];

		$noteUser  = $notesCollectionUser[ $type ];
		$noteAdmin = $notesCollectionAdmin[ $type ];
		$allData   = isset( $request ) && ! empty( (array) $request ) ? $request->all : [];
		$noteAdmin = Helpers::makePrintVariableNote( $allData, $noteAdmin );

		$entry = GFPersian_Payments::get_entry( $entryId );
		RGFormsModel::add_note( $entryId, $user->id, $user->username, $noteAdmin );
		IDPayVerify::sendSetFinalPriceGravityCore($transaction, $status);
		Helpers::processConfirmations( $form, $entry, $noteUser, $status, $config );
		GFPersian_Payments::notification( $form, $entry );
		GFPersian_Payments::confirmation( $form, $entry, $noteUser );

		return true;
	}

	public static function checkErrorResponseVerify( $httpClient ) {
		$request = $httpClient->request;
		$httpStatus = $httpClient->statusCode;
		$response = $httpClient->response;

		$dict = Helpers::loadDictionary();
		if ( is_wp_error( $request ) ) {
			$error = $request->get_error_message();

			return [
				'message' => sprintf( $dict->labelErrorTransaction, $error )
			];
		} elseif ( $httpStatus != 200 || empty( $response->status ) || empty( $response->track_id ) ) {
			return [
				'message' => sprintf( $dict->labelErrorTransaction, $response->error_message, $response->error_code )
			];

		}

		return false;
	}

}
