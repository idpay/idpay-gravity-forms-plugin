<?php


class IDPayView
{
	public const PLUGIN_FOLDER = "idpay-gravity-forms-plugin";
	public const VIEW_CONFIG = "config";
	public const VIEW_TRANSACTION = "transactions";
	public const VIEW_FEEDS = "index";
	public const VIEW_SETTING = "setting";


	public static function route($view = null) {
		$view = empty($view) ? rgget( "view" ) : $view;
		$basePath = Helpers::getBasePath();
		$folder = '/resources/views';
		$page = self::VIEW_FEEDS;
		$page = $view == 'edit' ? self::VIEW_CONFIG : $page;
		$page = $view == 'stats' ? self::VIEW_TRANSACTION : $page;
		$page = $view == 'setting' ? self::VIEW_SETTING : $page;

		$complete = "{$basePath}{$folder}/{$page}.php";
		//GF_Gateway_IDPay::getHeaders();
		require_once($complete);
		//GFFormSettings::page_footer();
	}

	public static function addIdpayToNavigation( $menus ) {
		$handler = [ IDPayView::class, "route" ];
		$menus[] = [
			"name"       => "gf_IDPay",
			"label"      => __( "IDPay", "gravityformsIDPay" ),
			"callback"   => $handler,
			"permission" => IDPayOperation::PERMISSION_ADMIN
		];

		return $menus;
	}

	public static function addIdpayToToolbar( $menu_items ) {
		$menu_items[] = ['name'  => 'IDPay','label' => 'IDPay',];
		return $menu_items;
	}

	public static function viewSetting() {
		 IDPayView::route('setting');
	}

	public static function renderButtonSubmitForm( $button_input, $form ) {

		$buttonHtml = $button_input;
		$formId = Helpers::dataGet($form,'id');
		$dictionary = Helpers::loadDictionary( '', '' );
		Helpers::prepareFrontEndTools();

		$hasPriceFieldInForm  = Helpers::checkSetPriceForForm($form, $formId);
		$basePath = IDPayView::PLUGIN_FOLDER;
		$file = '/resources/images/logo.svg';
		$ImageUrl =  plugins_url("{$basePath}{$file}");
		$config     = IDPayDB::getActiveFeed( $form );

		if ( $hasPriceFieldInForm && ! empty( $config ) ) {
			$buttonHtml .= sprintf(
				'<div class="idpay-logo C9" id="idpay-pay-id-%1$s"><img class="C10" src="%2$s">%3$s</div>',
				$formId,
				$ImageUrl,
				$dictionary->labelPayment
			);
		}

		return $buttonHtml;
	}


}
