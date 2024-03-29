<?php

class IDPayPayment extends Helpers {

	public static function doPayment( $confirmation, $form, $entry, $ajax ) {
		$entryId = Helpers::dataGet( $entry, 'id' );
		$formId  = Helpers::dataGet( $form, 'id' );

		if ( ! IDPayPayment::checkOneConfirmationExists( $confirmation, $form, $entry, $ajax ) ) {
			return $confirmation;
		}

		$resp = null;
		if ( $confirmation == 'custom' ) {
			$resp = IDPayPayment::handleCustomConfirmation( $confirmation, $form, $entry, $ajax );
		} else {
			$resp = IDPayPayment::handleAutoConfirmation( $confirmation, $form, $entry, $ajax );
		}

		if ( Helpers::dataGet( $resp, 'transactionType' ) == 'Free' ) {
			return Helpers::dataGet( $resp, 'data.confirmation' );
		} elseif ( Helpers::dataGet( $resp, 'transactionType' ) == 'Purchase' ) {
			$data   = Helpers::dataGet( $resp, 'data' );
			$amount = Helpers::dataGet( $data, 'amount' );
			date_default_timezone_set( "Asia/Tehran" );

			$entry["payment_date"]     = date_create()->format( 'Y-m-d H:i:s' );
			$entry["payment_amount"]   = (float) $amount;
			$entry["payment_status"]   = "Processing";
			$entry["payment_method"]   = Keys::AUTHOR;
			$entry["is_fulfilled"]     = Keys::TRANSACTION_IN_PROGRESS_STATE;
			$entry["transaction_id"]   = null;
			$entry["transaction_type"] = null;
			GFAPI::update_entry( $entry );
			$entry      = GFPersian_Payments::get_entry( $entryId );
			$ReturnPath = IDPayPayment::ReturnURL( $formId, $entryId );

			if ( IDPayPayment::isNotApprovedPrice( $amount ) ) {
				return IDPayPayment::reject( $entry, $form );
			}

			$dto = [
				'order_id' => $entryId,
				'amount'   => $amount,
				'callback' => $ReturnPath,
				'name'     => Helpers::dataGet( $data, 'name' ),
				'mail'     => Helpers::dataGet( $data, 'mail' ),
				'desc'     => Helpers::dataGet( $data, 'description' ),
				'phone'    => Helpers::dataGet( $data, 'mobile' ),
			];

			$response       = IDPayPayment::httpRequest( 'https://api.idpay.ir/v1.1/payment', $dto );
			$http_status    = wp_remote_retrieve_response_code( $response );
			$result         = json_decode( wp_remote_retrieve_body( $response ) ) ?? null;
			$errorResponder = IDPayPayment::checkErrorResponsePayment( $response, $http_status, $result );

			if ( ! $errorResponder == false ) {
				$message = Helpers::dataGet( $errorResponder, 'message' );

				return IDPayPayment::reject( $entry, $form, $message );
			}

			gform_update_meta( $entryId, "IdpayTransactionId:$entryId", $result->id );

			return IDPayPayment::redirectConfirmation( $result->link, $ajax );
		}

		return $confirmation;
	}

	public static function checkout( $form, $entry ) {
		$formId = Helpers::dataGet( $form, 'id' );
		$amount = IDPayPayment::getOrderTotal( $form, $entry );
		IDPayPayment::sendSetPriceGravityCore( $entry, $form, $amount );

		return $amount;
	}

	public static function handleAutoConfirmation( $confirmation, $form, $entry, $ajax ) {
		$formId  = Helpers::dataGet( $form, 'id' );
		$entryId = Helpers::dataGet( $entry, 'id' );

		if ( ! IDPayPayment::checkSubmittedForIDPay( $formId ) || ! IDPayPayment::checkFeedExists( $form ) ) {
			return $confirmation;
		}
		$feed        = IDPayPayment::getFeed( $form );
		$feedId      = Helpers::dataGet( $feed, 'id' );
		$gatewayName = IDPayPayment::getGatewayName();
		$amount      = IDPayPayment::checkout( $form, $entry );

		gform_update_meta( $entryId, 'IDPay_feed_id', $feedId );
		gform_update_meta( $entryId, 'payment_type', 'form' );
		gform_update_meta( $entryId, 'payment_gateway', $gatewayName );

		return IDPayPayment::process( $amount, $feed, $entry, $form, $ajax );
	}

	public static function process( $amount, $feed, $entry, $form, $ajax ) {
		$formId = Helpers::dataGet( $form, 'id' );

		if ( IDPayPayment::checkTypePayment( $amount ) == 'Free' ) {
			$confirmation = IDPayPayment::processFree( $entry, $formId, $ajax );

			return [
				'transactionType' => 'Free',
				'data'            => [
					'confirmation' => $confirmation
				]
			];
		} elseif ( IDPayPayment::checkTypePayment( $amount ) == 'Purchase' ) {
			$data = IDPayPayment::processPurchase( $feed, $entry, $form );

			return [
				'transactionType' => 'Purchase',
				'data'            => [
					'amount'      => IDPayPayment::fixPrice( $amount, $form, $entry ),
					'mobile'      => Helpers::dataGet( $data, 'mobile' ),
					'name'        => Helpers::dataGet( $data, 'name' ),
					'mail'        => Helpers::dataGet( $data, 'mail' ),
					'description' => Helpers::dataGet( $data, 'description' ),
				]
			];
		}
	}

	public static function handleCustomConfirmation( $confirmation, $form, $entry, $ajax ) {
		$formId  = Helpers::dataGet( $form, 'id' );
		$feed    = IDPayPayment::getFeed( $form );
		$entryId = rgar( $entry, 'id' );

		$amount = gform_get_meta( $entryId, "IDPay_part_price_{$formId}" );
		$amount = IDPayPayment::sendCustomSetPriceGravityCore( $entry, $form, $amount );


		$Description = gform_get_meta( $entryId, "IDPay_part_desc_{$formId}" );

		$applyFilter = apply_filters( Keys::HOOK_18, $Description, $form, $entry );
		$Description = apply_filters( Keys::HOOK_19, $applyFilter, $form, $entry );


		$Name   = gform_get_meta( $entryId, "IDPay_part_name_{$formId}" );
		$Mail   = gform_get_meta( $entryId, "IDPay_part_email_{$formId}" );
		$Mobile = gform_get_meta( $entryId, "IDPay_part_mobile_{$formId}" );

		$entryId = GFAPI::add_entry( $entry );
		$entry   = GFPersian_Payments::get_entry( $entryId );

		do_action( Keys::HOOK_20, $confirmation, $form, $entry, $ajax );
		do_action( Keys::HOOK_21, $confirmation, $form, $entry, $ajax );

		gform_update_meta( $entryId, 'payment_gateway', IDPayPayment::getGatewayName() );
		gform_update_meta( $entryId, 'payment_type', 'custom' );

		return IDPayPayment::process( $amount, $feed, $entry, $form, $ajax );
	}

	public static function processFree( $entry, $formId, $ajax ) {

		$entry["payment_status"]   = null;
		$entry["is_fulfilled"]     = Keys::TRANSACTION_FINAL_STATE;
		$entry["transaction_type"] = null;
		$entry["payment_amount"]   = null;
		$entry["payment_date"]     = null;
		$entry["payment_method"]   = Keys::NONE_GATEWAY;
		GFAPI::update_entry( $entry );

		$queryArgs1  = [ 'no' => 'true' ];
		$queryArgs2  = IDPayPayment::ReturnURL( $formId, $entry['id'] );
		$queryParams = add_query_arg( $queryArgs1, $queryArgs2 );

		return IDPayPayment::redirectConfirmation( $queryParams, $ajax );
	}

	public static function processPurchase( $feed, $entry, $form ) {

		$mobile = Helpers::dataGet( $feed, 'meta.payment_mobile' );
		$name   = Helpers::dataGet( $feed, 'meta.payment_name' );
		$email  = Helpers::dataGet( $feed, 'meta.payment_email' );
		$desc   = Helpers::makeCustomDescription( $entry, $form, $feed );

		$mobile = ! empty( $mobile ) ? Helpers::convertNameToTxtBoxKey( $mobile ) : '';
		$name   = ! empty( $name ) ? Helpers::convertNameToTxtBoxKey( $name ) : '';
		$email  = ! empty( $email ) ? Helpers::convertNameToTxtBoxKey( $email ) : '';
		$Mobile = GFPersian_Payments::fix_mobile( $mobile );

		return [
			'mobile'      => $Mobile,
			'name'        => $name,
			'mail'        => $email,
			'description' => $desc,
		];
	}

	public static function reject( $entry, $form, $Message = '' ) {
		$dict                    = Helpers::loadDictionary();
		$entryId                 = Helpers::dataGet( $entry, 'id' );
		$formId                  = Helpers::dataGet( $form, 'id' );
		$Message                 = ! empty( (array) $Message ) ? $Message : $dict->labelErrorPayment;
		$confirmation            = $dict->labelErrorConnectGateway . $Message;
		$entry                   = GFPersian_Payments::get_entry( $entryId );
		$entry['payment_status'] = 'Failed';
		$entry["is_fulfilled"]   = Keys::TRANSACTION_FINAL_STATE;
		GFAPI::update_entry( $entry );

		global $current_user;
		$user_id   = 0;
		$user_name = $dict->labelGuest;
		if ( $current_user && $user_data = get_userdata( $current_user->ID ) ) {
			$user_id   = $current_user->ID;
			$user_name = $user_data->display_name;
		}

		$note = sprintf( $dict->labelErrorConnectIPG, $Message );
		RGFormsModel::add_note( $entryId, $user_id, $user_name, $note );
		GFPersian_Payments::notification( $form, $entry );

		$anchor   = gf_apply_filters( 'gform_confirmation_anchor', $formId, 0 ) ?
			"<a id='gf_{$formId}' name='gf_{$formId}' class='gform_anchor' ></a>" : '';
		$nl2br    = ! ( ! empty( $form['confirmation'] ) && rgar( $form['confirmation'], 'disableAutoformat' ) );
		$cssClass = rgar( $form, 'cssClass' );

		$output = "{$anchor} ";
		if ( ! empty( $confirmation ) ) {
			$output = GFCommon::replace_variables( $confirmation, $form, $entry, false, true, $nl2br );
			$output .= "
                <div id='gform_confirmation_wrapper_{$formId}' class='gform_confirmation_wrapper {$cssClass}'>
                    <div id='gform_confirmation_message_{$formId}' 
                    class='gform_confirmation_message_{$formId} gform_confirmation_message'>" . $output . '
                    </div>
                </div>';
		}
		$confirmation = $output;

		return $confirmation;
	}

	public static function checkErrorResponsePayment( $response, $http_status, $result ) {
		$dict = Helpers::loadDictionary();
		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();

			return [
				'message' => sprintf( $dict->labelErrorCreateTransaction, $error )
			];
		} elseif ( $http_status != 201 || empty( $result ) || empty( $result->id ) || empty( $result->link ) ) {
			return [
				'message' => sprintf( $dict->labelErrorCreatingTransaction, $result->error_message, $result->error_code )
			];
		}

		return false;
	}

	public static function sendCustomSetPriceGravityCore( $entry, $form, $amount ) {

		$formId = Helpers::dataGet( $form, 'id' );
		$hook0  = Keys::HOOK_21;
		$hook1  = Keys::HOOK_22 . $formId;
		$hook2  = Keys::HOOK_23;
		$hook3  = Keys::HOOK_24 . $formId;
		$hook4  = Keys::HOOK_25;
		$hook5  = Keys::HOOK_26 . $formId;
		$hook6  = Keys::HOOK_27;
		$hook7  = Keys::HOOK_28 . $formId;

		$applyFilter = apply_filters( $hook0, $amount, $form, $entry );
		$amount      = apply_filters( $hook1, $applyFilter, $form, $entry );
		$applyFilter = apply_filters( $hook2, $amount, $form, $entry );
		$amount      = apply_filters( $hook3, $applyFilter, $form, $entry );
		$applyFilter = apply_filters( $hook4, $amount, $form, $entry );
		$amount      = apply_filters( $hook5, $applyFilter, $form, $entry );
		$applyFilter = apply_filters( $hook6, $amount, $form, $entry );
		$amount      = apply_filters( $hook7, $applyFilter, $form, $entry );

		return $amount;

	}

	public static function sendSetPriceGravityCore( $entry, $form, $amount ) {

		$formId = Helpers::dataGet( $form, 'id' );
		$hook0  = Keys::HOOK_29;
		$hook1  = Keys::HOOK_30 . $formId;
		$hook2  = Keys::HOOK_31;
		$hook3  = Keys::HOOK_32 . $formId;
		$hook4  = Keys::HOOK_33;
		$hook5  = Keys::HOOK_34 . $formId;
		$hook6  = Keys::HOOK_35;
		$hook7  = Keys::HOOK_36 . $formId;

		$applyFilter = apply_filters( $hook0, $amount, $form, $entry );
		$amount      = apply_filters( $hook1, $applyFilter, $form, $entry );
		$applyFilter = apply_filters( $hook2, $amount, $form, $entry );
		$amount      = apply_filters( $hook3, $applyFilter, $form, $entry );
		$applyFilter = apply_filters( $hook4, $amount, $form, $entry );
		$amount      = apply_filters( $hook5, $applyFilter, $form, $entry );
		$applyFilter = apply_filters( $hook6, $amount, $form, $entry );
		$amount      = apply_filters( $hook7, $applyFilter, $form, $entry );

		return $amount;
	}
}
