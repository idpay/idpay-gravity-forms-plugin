<?php


class IDPayView extends Helpers {
	public static function route( $view = null ) {
		$view     = empty( $view ) ? rgget( "view" ) : $view;
		$basePath = Helpers::getBasePath();
		$folder   = '/resources/views';
		$page     = Keys::VIEW_FEEDS;
		$page     = $view == 'edit' ? Keys::VIEW_CONFIG : $page;
		$page     = $view == 'stats' ? Keys::VIEW_TRANSACTION : $page;
		$page     = $view == 'setting' ? Keys::VIEW_SETTING : $page;

		$complete = "{$basePath}{$folder}/{$page}.php";
		require_once( $complete );
	}

	public static function addIdpayToNavigation( $menus ) {
		$handler = [ IDPayView::class, "route" ];
		$menus[] = [
			"name"       => "gf_IDPay",
			"label"      => Keys::AUTHOR,
			"callback"   => $handler,
			"permission" => Keys::PERMISSION_ADMIN
		];

		return $menus;
	}

	public static function addIdpayToToolbar( $menu_items ) {
		$menu_items[] = [ 'name' => 'IDPay', 'label' => 'IDPay', ];

		return $menu_items;
	}

	public static function viewSetting() {
		IDPayView::route( 'setting' );
	}

	public static function renderButtonSubmitForm( $button_input, $form ) {

		$buttonHtml = $button_input;
		$formId     = Helpers::dataGet( $form, 'id' );
		$dictionary = Helpers::loadDictionary();
		Helpers::prepareFrontEndTools();

		$hasPriceFieldInForm = Helpers::checkSetPriceForForm( $form, $formId );
		$basePath            = Keys::PLUGIN_FOLDER;
		$file                = '/resources/images/logo.svg';
		$ImageUrl            = plugins_url( "{$basePath}{$file}" );
		$config              = Helpers::getFeed( $form );

		if ( $hasPriceFieldInForm && ! empty( $config ) ) {
			$html       = '<div class="idpay-logo C9" id="idpay-pay-id-%1$s">';
			$html       .= '<img class="C10" src="%2$s">%3$s</div>';
			$buttonHtml .= sprintf( $html, $formId, $ImageUrl, $dictionary->labelPayment );
		}

		return $buttonHtml;
	}

	public static function makeHtmlShowPaymentData( $formId, $entry ) {
		$dict    = Helpers::loadDictionary();
		$form    = RGFormsModel::get_form_meta( $formId );
		$entryId = Helpers::dataGet( $entry, 'id' );
		$style   = Keys::CSS_MESSAGE_STYLE;
		$html    = "<div style='{$style}'><hr/>";
		$html    .= "<strong>{$dict->labelTransactionData}</strong>";
		$html    .= "<br/><br/>";

		$currency = Helpers::dataGet( $entry, 'currency' );
		$amount   = Helpers::dataGet( $entry, 'payment_amount' );
		$amount   = empty( $amount ) ? $amount : Helpers::getOrderTotal( $form, $entry );
		$amount   = GFCommon::to_money( $amount, $currency );
		$date     = Helpers::dataGet( $entry, 'payment_date' );
		$date     = Helpers::getJalaliDateTime( $date );
		$status   = Helpers::dataGet( $entry, 'payment_status' );
		$status   = ! empty( $status ) ? $dict->{$status} : $dict->noStatus;
		$trackId  = Helpers::dataGet( $entry, 'transaction_id' );
		$transId  = gform_get_meta( $entryId, "IdpayTransactionId:{$entryId}", false );

		$html .= "{$dict->transId}{$transId}<br/><br/>";
		$html .= "{$dict->track}{$trackId}<br/><br/>";
		$html .= "{$dict->status}{$status}<br/><br/>";
		$html .= "{$dict->money}{$amount}<br/><br/>";
		$html .= "{$dict->currecny}{$currency}<br/><br/>";
		$html .= "{$dict->date}<span>{$date}</span><br/><br/>";
		$html .= "{$dict->ipg}<br/><br/><br/><br/>";
		$html .= "{$dict->noteVariable}<br/><hr/><br/></div>";

		echo $html;
	}

	public static function makeHtmlEditPaymentData( $formId, $entry ) {
		$dict    = Helpers::loadDictionary();
		$form    = RGFormsModel::get_form_meta( $formId );
		$entryId = Helpers::dataGet( $entry, 'id' );
		$style   = Keys::CSS_MESSAGE_STYLE;
		$html    = "<div style='{$style}'><hr/>";
		$html    .= "<strong>{$dict->labelTransactionData}</strong>";
		$html    .= "<br/><br/>";

		$currency = Helpers::dataGet( $entry, 'currency' );
		$amount   = Helpers::dataGet( $entry, 'payment_amount' );
		$amount   = empty( $amount ) ? $amount : Helpers::getOrderTotal( $form, $entry );
		$date     = Helpers::dataGet( $entry, 'payment_date' );
		$date     = Helpers::getJalaliDateTime( $date );
		list( $date, $time ) = explode( " ", $date );
		$status  = Helpers::dataGet( $entry, 'payment_status' );
		$status  = (object) [
			'En' => $status,
			'Fr' => ! empty( $status ) ? $dict->{$status} : $dict->noStatus,
		];
		$trackId = Helpers::dataGet( $entry, 'transaction_id' );
		$transId = gform_get_meta( $entryId, "IdpayTransactionId:{$entryId}", false );

		$statusOptions = "<select id='payment_status' name='payment_status'>";
		$statusOptions .= "<option value='{$status->En}' selected>{$status->Fr}</option>";
		$statusOptions .= $status->En == 'Paid' ? '' : "<option value='Paid'>{$dict->Paid}</option>";
		$statusOptions .= $status->En == 'Failed' ? '' : "<option value='Failed'>{$dict->Failed}</option>";
		$statusOptions .= $status->En == 'Processing' ? '' : "<option value='Processing'>{$dict->Processing}</option>";
		$statusOptions .= '</select>';

		$date    = "<input type='text' id='payment_date' name='payment_date' value='{$date}' />";
		$time    = "<input type='text' id='payment_time' name='payment_time' value='{$time}' />";
		$amount  = "<input type='text' id='payment_amount' name='payment_amount' value='{$amount}' />";
		$trackId = "<input type='text' id='IDPay_transaction_id' name='IDPay_transaction_id' value='{$trackId}' />";

		$html .= "{$dict->transId}{$transId}<br/><br/>";
		$html .= "{$dict->track}{$trackId}<br/><br/>";
		$html .= "{$dict->status}{$statusOptions}<br/><br/>";
		$html .= "{$dict->money}{$amount}<br/><br/>";
		$html .= "{$dict->currecny}{$currency}<br/><br/>";
		$html .= "{$dict->date}{$date}<br/><br/>";
		$html .= "{$dict->time}{$time}<br/><br/>";
		$html .= "{$dict->ipg}<br/><br/><br/><br/>";
		$html .= "{$dict->noteVariable}<br/><hr/><br/></div>";

		echo $html;
	}
}
