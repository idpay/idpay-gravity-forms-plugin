<?php

class Helpers extends Keys {
	public static function exists( $array, $key ): bool {
		if ( $array instanceof ArrayAccess ) {
			return $array->offsetExists( $key );
		}

		if ( is_float( $key ) ) {
			$key = (string) $key;
		}

		return array_key_exists( $key, $array );
	}

	public static function accessible( $value ): bool {
		return is_array( $value ) || $value instanceof ArrayAccess;
	}

	public static function value( $value, ...$args ) {
		return $value instanceof Closure ? $value( ...$args ) : $value;
	}

	public static function collapse( $array ): array {
		$results = [];

		foreach ( $array as $values ) {
			if ( ! is_array( $values ) ) {
				continue;
			}

			$results[] = $values;
		}

		return array_merge( [], ...$results );
	}

	public static function dataGet( $target, $key, $default = null ) {
		if ( is_null( $key ) ) {
			return $target;
		}

		$key = is_array( $key ) ? $key : explode( '.', $key );

		foreach ( $key as $i => $segment ) {
			unset( $key[ $i ] );

			if ( is_null( $segment ) ) {
				return $target;
			}

			if ( $segment === '*' ) {
				if ( ! is_iterable( $target ) ) {
					return Helpers::value( $default );
				}

				$result = [];

				foreach ( $target as $item ) {
					$result[] = Helpers::dataGet( $item, $key );
				}

				return in_array( '*', $key ) ? Helpers::collapse( $result ) : $result;
			}

			if ( Helpers::accessible( $target ) && Helpers::exists( $target, $segment ) ) {
				$target = $target[ $segment ];
			} elseif ( is_object( $target ) && isset( $target->{$segment} ) ) {
				$target = $target->{$segment};
			} else {
				return Helpers::value( $default );
			}
		}

		return $target;
	}

	public static function checkSubmittedConfigDataAndLoadSetting() {
		$setting = Helpers::getGlobalKey( Keys::KEY_IDPAY );

		if ( isset( $_POST["gf_IDPay_submit"] ) ) {

			check_admin_referer( "update", "gf_IDPay_update" );
			$setting = [
				"enable"  => sanitize_text_field( rgpost( 'gf_IDPay_enable' ) ),
				"name"    => sanitize_text_field( rgpost( 'gf_IDPay_name' ) ),
				"api_key" => sanitize_text_field( rgpost( 'gf_IDPay_api_key' ) ),
				"sandbox" => sanitize_text_field( rgpost( 'gf_IDPay_sandbox' ) ),
				"version" => Keys::VERSION,
			];
			Helpers::setGlobalKey( Keys::KEY_IDPAY, $setting );
		}

		return $setting;
	}

	public static function Return_URL( $form_id, $entry_id ) {
		$pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

		if ( $_SERVER['SERVER_PORT'] != '80' ) {
			$pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
		} else {
			$pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		}

		$arr_params = array( 'id', 'entry', 'no', 'Authority', 'Status' );
		$pageURL    = esc_url( remove_query_arg( $arr_params, $pageURL ) );

		$pageURL = str_replace( '#038;', '&', add_query_arg( array(
			'form_id' => $form_id,
			'entry'   => $entry_id
		), $pageURL ) );

		$applyData = apply_filters( Keys::AUTHOR . '_gateway_return_url', $pageURL, $form_id, $entry_id, __CLASS__ );
		return apply_filters( Keys::AUTHOR . '_IDPay_return_url', $applyData, $form_id, $entry_id, __CLASS__ );
	}

	public static function redirect_confirmation( $url, $ajax ) {
		if ( headers_sent() || $ajax ) {
			$confirmation = "<script type=\"text/javascript\">" . apply_filters( 'gform_cdata_open', '' );
			$confirmation .= apply_filters( 'gform_cdata_open', '' );
			$confirmation .= " function gformRedirect(){document.location.href='$url';}";

			if ( ! $ajax ) {
				$confirmation .= 'gformRedirect();';
			}
			$confirmation .= apply_filters( 'gform_cdata_close', '' ) . '</script>';
		} else {
			$confirmation = array( 'redirect' => $url );
		}

		return $confirmation;
	}

	public static function checkOneConfirmationExists( $confirmation, $form, $entry, $ajax ): bool {
	$applyFilter = apply_filters( 'gf_gateway_request_return', false, $confirmation, $form, $entry, $ajax );
	$applyFilter = apply_filters('gf_IDPay_request_return',$applyFilter,$confirmation,$form,$entry,$ajax);
	return ! $applyFilter;
	}

	public static function checkSubmittedForIDPay( $formId ): bool {
		return ! ( RGForms::post( "gform_submit" ) != $formId );
	}

	public static function checkFeedExists( $form ): bool {
		return ! empty( IDPayDB::getActiveFeed( $form ) );
	}

	public static function getGatewayName(): string {
		$settings = Helpers::getGlobalKey( Keys::KEY_IDPAY );
		return isset( $settings['name'] ) ? $settings["name"] : Keys::AUTHOR;
	}

	public static function getFeed( $form ) {
		$feed = IDPayDB::getActiveFeed( $form );
		return reset( $feed );
	}

	public static function fixPrice( $amount, $form, $entry ): int {
		return GFPersian_Payments::amount( $amount, 'IRR', $form, $entry );
	}

	public static function isNotApprovedPrice( $amount ): int {
		return empty( $amount ) || $amount > 500000000 || $amount < 1000;
	}

	public static function isNotApprovedGettingTransaction( $entryId, $form_id ) {

		$entry         = GFPersian_Payments::get_entry( $entryId );
		$paymentMethod = Helpers::dataGet( $entry, 'payment_method' );
		$condition1    = apply_filters( 'gf_gateway_IDPay_return', apply_filters( 'gf_gateway_verify_return', false ) );
		$condition2    = ( ! IDPayOperation::checkApprovedGravityFormVersion() ) || is_wp_error( $entry );
		$condition3    = ( ! is_numeric( (int) $form_id ) ) || ( ! is_numeric( (int) $entryId ) );
		$condition4    = empty( $paymentMethod ) || $paymentMethod != Keys::AUTHOR;

		return $condition1 || $condition2 || $condition3 || $condition4;
	}

	public static function getApiKey() {
		$settings = Helpers::getGlobalKey( Keys::KEY_IDPAY );
		$apiKey  = Helpers::dataGet($settings,'api_key','');
		return trim( $apiKey );
	}

	public static function getSandbox() {
		$settings = Helpers::getGlobalKey( Keys::KEY_IDPAY );
		return Helpers::dataGet($settings,'sandbox') ? "true" : "false";
	}

	public static function httpRequest( $url, $data ) {
		$args = [
			'body'    => json_encode( $data ),
			'headers' => [
				'Content-Type' => 'application/json',
				'X-API-KEY'    => Helpers::getApiKey(),
				'X-SANDBOX'    => Helpers::getSandbox(),
			],
			'timeout' => 30,
		];

		$number_of_connection_tries = 5;
		while ( $number_of_connection_tries ) {
			$response = wp_safe_remote_post( $url, $args );
			if ( is_wp_error( $response ) ) {
				$number_of_connection_tries --;
			} else {
				break;
			}
		}

		return $response;
	}

	public static function checkSetPriceForForm( $form, $formId ) {
		$check = false;
		if ( isset( $form['fields'] ) ) {
			$fields = Helpers::dataGet( $form, 'fields' );
			foreach ( $fields as $field ) {
				if ( $field['type'] == 'product' ) {
					$check = true;
				}
			}

			return $check;
		} elseif ( empty( $formId ) ) {
			return true;
		}

		return false;
	}

	public static function updateConfigAndRedirectPage( $feedId, $data ) {
		$idpayConfig = apply_filters( Keys::AUTHOR . '_gform_gateway_save_config', $data );
		$idpayConfig = apply_filters( Keys::AUTHOR . '_gform_IDPay_save_config', $idpayConfig );
		$formId = Helpers::dataGet( $idpayConfig, 'form_id' );
		$meta = Helpers::dataGet( $idpayConfig, 'meta' );
		$feedId  = IDPayDB::updateFeed($feedId,$formId,$meta);

		if ( ! headers_sent() ) {
			wp_redirect( admin_url( 'admin.php?page=gf_IDPay&view=edit&id=' . $feedId . '&updated=true' ) );
		} else {
			echo "<script type='text/javascript'>window.onload = function () { top.location.href = '" .
			     admin_url( 'admin.php?page=gf_IDPay&view=edit&id=' . $feedId . '&updated=true' ) . "'; };</script>";
		}
		exit;
	}

	public static function setStylePage() {
		if ( is_rtl() ) {
			echo '<style type="text/css">table.gforms_form_settings th {text-align: right !important}</style>';
		}
		if ( ! defined( 'ABSPATH' ) ) {
			exit;
		}
		$styles = [ 'jquery-ui-styles', 'gform_admin_IDPay', 'wp-pointer' ];
		wp_register_style( 'gform_admin_IDPay', GFCommon::get_base_url() . '/assets/css/dist/admin.css' );
		wp_print_styles( $styles );

		return true;
	}

	public static function readDataFromRequest( $config ) {
		return [
			"form_id" => absint( rgpost( "IDPay_formId" ) ),
			"meta"    => [
				"description"         => rgpost( "IDPay_description" ),
				"payment_description" => rgpost( "IDPay_payment_description" ),
				"payment_email"       => rgpost( "IDPay_payment_email" ),
				"payment_mobile"      => rgpost( "IDPay_payment_mobile" ),
				"payment_name"        => rgpost( "IDPay_payment_name" ),
				"confirmation"        => rgpost( "IDPay_payment_confirmation" ),
				"addon"               => [
					"post_create"       => [
						"success_payment" => (bool) rgpost( "IDPay_addon_post_create_success_payment" ),
						"no_payment"      => false,
					],
					"post_update"       => [
						"success_payment" => (bool) rgpost( "IDPay_addon_post_update_success_payment" ),
						"no_payment"      => false,
					],
					"user_registration" => [
						"success_payment" => (bool) rgpost( "IDPay_addon_user_reg_success_payment" ),
						"no_payment"      => (bool) rgpost( "IDPay_addon_user_reg_no_payment" ),
					],
				],
			]
		];
	}

	public static function makeUpdateMessageBar( ) {
		$dict         = Helpers::loadDictionary();
		$style = Keys::CSS_MESSAGE_STYLE;
		$label = $dict->labelUpdateFeed;
		$html = "<div class='updated fade' style='{$style}'>{$label}</div>";
		echo $html;
		return true;
	}

	public static function getVal( $form, $fieldName, $selectedValue ) {

		$fields = null;
		if ( ! empty( $form ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $item ) {
				$condition1 = isset( $item['inputs'] ) && is_array( $item['inputs'] );
				$condition2 = ! rgar( $item, 'displayOnly' );
				if ( $condition1 ) {
					foreach ( $item['inputs'] as $input ) {
						$id       = Helpers::dataGet( $input, 'id' );
						$label    = GFCommon::get_label( $item, $id );
						$fields[] = [ 'id' => $id, 'label' => $label ];
					}
				} elseif ( $condition2 ) {
					$id       = Helpers::dataGet( $item, 'id' );
					$label    = GFCommon::get_label( $item );
					$fields[] = [ 'id' => $id, 'label' => $label ];
				}
			}
		}

		if ( $fields != null && is_array( $fields ) ) {
			$str = "<select name='{$fieldName}' id='{$fieldName}'>";
			$str .= "<option value=''></option>";
			foreach ( $fields as $field ) {
				$id       = Helpers::dataGet( $field, 'id' );
				$label    = esc_html( GFCommon::truncate_middle( Helpers::dataGet( $field, 'label' ), 40 ) );
				$selected = $id == $selectedValue ? "selected='selected'" : "";
				$str      .= "<option value='{$id}' {$selected} >{$label}</option>";
			}
			$str .= "</select>";

			return $str;
		}

		return '';
	}

	public static function generateFeedSelectForm( $formId ) {
		$dict = Helpers::loadDictionary();
		$gfAllForms             = RGFormsModel::get_forms();
		$visibleFieldFormSelect = rgget( 'id' ) || rgget( 'fid' ) ? 'style="display:none !important"' : '';
		$label                  = $dict->labelSelectForm;
		$optionsForms           = "<option value=''>{$label}</option>";
		foreach ( $gfAllForms as $current_form ) {
			$title        = esc_html( $current_form->title );
			$val          = absint( $current_form->id );
			$isSelected   = absint( $current_form->id ) == $formId ? 'selected="selected"' : '';
			$optionsForms = $optionsForms . "<option value={$val} {$isSelected}>{$title}</option>";
		}

		return (object) [
			'options' => $optionsForms,
			'visible' => $visibleFieldFormSelect
		];
	}

	public static function loadDictionary() {
		$basePath   = Helpers::getBasePath();
		$fullPath   = "{$basePath}/lang/fa.php";
		$dictionary = require $fullPath;

		return (object) $dictionary;
	}

	public static function checkSupportedGravityVersion() {
		$dict   = Helpers::loadDictionary();
		$label1 = Keys::MIN_GRAVITY_VERSION;
		$labelGravity = $dict->labelGravityFarsi;
		$label2 = "<a href='http://gravityforms.ir' target='_blank'>{$labelGravity}</a>";
		if ( ! IDPayOperation::checkApprovedGravityFormVersion() ) {
			return sprintf( $dict->labelNotSupprt, $label1, $label2 );
		}

		return true;
	}

	public static function getTypeFeed( $setting ) {
		$dict   = Helpers::loadDictionary();
		$label        = $dict->labelBuySell;
		$activeAddons = Helpers::makeListDelayedAddons( $setting );

		if ( Helpers::dataGet( $activeAddons, 'postCreate' ) ) {
			$label .= $dict->labelPostCreate;
		}
		if ( Helpers::dataGet( $activeAddons, 'postUpdate' ) ) {
			$label .= $dict->labelPostUpdate;
		}
		if ( Helpers::dataGet( $activeAddons, 'userRegistration' ) ) {
			$label .= $dict->labelUserReg;
		}

		return $label;
	}

	public static function makeListDelayedAddons( $config ) {
		$config           = Helpers::dataGet( $config, 'meta.addon' );
		$postCreate       = Helpers::dataGet( $config, 'post_create' );
		$postUpdate       = Helpers::dataGet( $config, 'post_update' );
		$userRegistration = Helpers::dataGet( $config, 'user_registration' );

		return [
			'postCreate'       => $postCreate['success_payment'] || $postCreate['no_payment'],
			'postUpdate'       => $postUpdate['success_payment'] || $postUpdate['no_payment'],
			'userRegistration' => $userRegistration['success_payment'] || $userRegistration['no_payment'],
		];
	}

	public static function checkSubmittedOperation() {
		$dict   = Helpers::loadDictionary();
		$style = Keys::CSS_MESSAGE_STYLE;
		if ( rgpost( 'action' ) == "delete" ) {
			check_admin_referer( "list_action", "gf_IDPay_list" );
			$id = absint( rgpost( "action_argument" ) );
			IDPayDB::deleteFeed( $id );

			return "<div class='updated fade' style='{$style}'>{$dict->labelDeleteFeed}</div>";
		} elseif ( ! empty( $_POST["bulk_action"] ) ) {
			check_admin_referer( "list_action", "gf_IDPay_list" );
			$selected_feeds = rgpost( "feed" );
			if ( is_array( $selected_feeds ) ) {
				foreach ( $selected_feeds as $feed_id ) {
					IDPayDB::deleteFeed( $feed_id );
				}

				return "<div class='updated fade' style='{$style}'>{$dict->labelDeleteFeeds}</div>";
			}
		}

		return '';
	}

	public static function checkNeedToUpgradeVersion( $setting ) {
		$hasOldVersionKey = ! empty( Helpers::getGlobalKey( Keys::OLD_GLOBAL_KEY_VERSION ) );
		$version          = Helpers::dataGet( $setting, 'version' );

		return $version != Keys::VERSION || $hasOldVersionKey;
	}

	public static function loadConfigByEntry( $entry ) {
		$feed_id = gform_get_meta( $entry["id"], "IDPay_feed_id" );
		$feed    = ! empty( $feed_id ) ? IDPayDB::getFeed( $feed_id ) : '';
		$return  = ! empty( $feed ) ? $feed : false;

		$applyFilter = apply_filters(Keys::AUTHOR . '_gf_gateway_get_config_by_entry',$return,$entry);
		$applyFilter = apply_filters(Keys::AUTHOR . '_gf_IDPay_get_config_by_entry',$applyFilter,$entry);
		return $applyFilter;
	}

	public static function loadConfig( $entry, $form, $paymentType ) {
		$config = null;

		if ( $paymentType != 'custom' ) {
			$config = Helpers::loadConfigByEntry( $entry );
			if ( empty( $config ) ) {
				return null;
			}
		} elseif ( $paymentType != 'form' ) {
			$applyFilter = apply_filters( Keys::AUTHOR . '_gf_gateway_config', [], $form, $entry );
			$config = apply_filters(Keys::AUTHOR . '_gf_IDPay_config',	$applyFilter,$form,	$entry);
		}

		return $config;
	}

	public static function loadUser() {
		$dict   = Helpers::loadDictionary();
		global $current_user;
		$user_data = get_userdata( $current_user->ID );
		if ( $current_user && $user_data ) {
			$user_id   = $current_user->ID;
			$user_name = $user_data->display_name;
		} else {
			$user_name = $dict->labelGuest;
			$user_id   = 0;
		}

		return (object) [
			'id'       => $user_id,
			'username' => $user_name
		];
	}

	public static function getOrderTotal( $form, $entry ) {
		$total = GFCommon::get_order_total( $form, $entry );
		$total = ( ! empty( $total ) && $total > 0 ) ? $total : 0;

		$applyFilter = apply_filters( Keys::AUTHOR . '_gateway_get_order_total', $total, $form, $entry );
		$applyFilter = apply_filters(Keys::AUTHOR . '_IDPay_get_order_total',	$applyFilter,$form,$entry);
		return $applyFilter;
	}

	public static function getPriceOrder( $paymentType, $entry, $form ) {
		$entryId  = Helpers::dataGet( $entry, 'id' );
		$formId   = Helpers::dataGet( $form, 'id' );
		$currency = Helpers::dataGet( $entry, 'currency' );

		if ( $paymentType == 'custom' ) {
			$amount = gform_get_meta( $entryId, 'IDPay_part_price_' . $formId );
		} else {
			$amount = Helpers::getOrderTotal( $form, $entry );
			$amount = GFPersian_Payments::amount( $amount, 'IRR', $form, $entry );
		}

		return (object) [
			'amount' => $amount,
			// 'money'  => GFCommon::to_money($amount, $currency)
		];
	}

	public static function checkTypeVerify() {
		return Helpers::dataGet( $_GET, 'no' ) == 'true' ? 'Free' : 'Purchase';
	}

	public static function getRequestData() {
		$method  = ! empty( rgpost( 'status' ) ) ? 'POST' : 'GET';
		$keys    = [ 'status', 'track_id', 'id', 'order_id' ];
		$request = [];
		$all     = $method == 'POST' ? $_POST : $_GET;
		foreach ( $keys as $key ) {
			$value = $method == 'POST' ? rgpost( $key ) : rgget( $key );
			if ( empty( $value ) ) {
				return null;
			}
			$request[ $key ] = $value;
		}

		return (object) [
			'id'      => Helpers::dataGet( $request, 'id' ),
			'status'  => Helpers::dataGet( $request, 'status' ),
			'trackId' => Helpers::dataGet( $request, 'track_id' ),
			'orderId' => Helpers::dataGet( $request, 'order_id' ),
			'formId'  => (int) Helpers::dataGet( $_GET, 'form_id' ),
			'entryId' => (int) Helpers::dataGet( $_GET, 'entry' ),
			'all'     => $all,
		];
	}

	public static function checkApprovedVerifyData( $request ) {
		$keys = [ 'status', 'id', 'trackId', 'orderId', 'formId', 'entryId' ];
		foreach ( $keys as $key ) {
			if ( Helpers::dataGet( $request, $key ) == null ) {
				return false;
			}
		}

		return true;
	}

	public static function getStatus( $statusCode ) {
		$dict = Helpers::loadDictionary();
		$key = "Code{$statusCode}";
		$default = Helpers::dataGet($dict,'Code0');
		return  isset($dict->{$key}) ? Helpers::dataGet($dict,$key) : $default;
	}

	public static function isNotDoubleSpending( $reference_id, $order_id, $transaction_id ) {
		$relatedTransaction = gform_get_meta( $reference_id, "IdpayTransactionId:$order_id", false );
		if ( ! empty( $relatedTransaction ) ) {
			return $transaction_id == $relatedTransaction;
		}

		return false;
	}

	public static function processConfirmations( &$form, $entry, $note, $status, $config ) {
		$paymentType      = gform_get_meta( Helpers::dataGet($entry,'id'), 'payment_type' );
		$hasCustomPayment = ( $paymentType != 'custom' );
		$confirmPrepare   = apply_filters( Keys::AUTHOR . '_gf_gateway_verify', $hasCustomPayment, $form, $entry );
		$confirmations    = apply_filters( Keys::AUTHOR . '_gf_IDPay_verify', $confirmPrepare, $form, $entry );
		if ( $confirmations ) {
			foreach ( $form['confirmations'] as $key => $value ) {
				$message                                  = Helpers::dataGet( $value, 'message' );
				$payment                                  = Helpers::makeHtmlConfirmation( $note, $status, $config, $message );
				$form['confirmations'][ $key ]['message'] = $payment;
			}
		}
	}

	public static function makeHtmlConfirmation( $note, $status, $config, $message ) {
		$key    = '{idpay_payment_result}';
		$type   = $status == 'Failed' ? 'Failed' : 'Success';
		$color  = $type == 'Failed' ? '#f44336' : '#4CAF50';
		$style  = "direction:rtl;padding: 20px;background-color:{$color};color: white;" .
		          "opacity: 0.83;transition: opacity 0.6s;margin-bottom: 15px;";
		$output = "<div  style='{$style}'>{$note}</div>";

		return empty( Helpers::dataGet( $config, 'meta.confirmation' ) ) ? $output : str_replace( $key, $output, $message );
	}

	public static function checkTypePayment( $amount ): string {
		return empty( $amount ) || $amount == 0 ? 'Free' : 'Purchase';
	}


	public static function processAddons( $form, $entry, $config, $type ) {

		$config = Helpers::dataGet( $config, 'meta.addon' );

		if ( $type == Keys::NO_PAYMENT ) {
			$ADDON_USER_REGESTERATION = (object) [
				'class' => 'GF_User_Registration',
				'slug'  => apply_filters( 'gf_user_registration_slug', 'gravityformsuserregistration' ) ?? 'gravityformsuserregistration',
				'run'   => Helpers::dataGet( $config, 'user_registration.no_payment' ) == true,
			];

			Helpers::runAddon( $ADDON_USER_REGESTERATION, $form, $entry );
		}

		if ( $type == Keys::SUCCESS_PAYMENT ) {
			$ADDON_USER_REGESTERATION = (object) [
				'class' => 'GF_User_Registration',
				'slug'  => apply_filters( 'gf_user_registration_slug', 'gravityformsuserregistration' ) ?? 'gravityformsuserregistration',
				'run'   => Helpers::dataGet( $config, 'user_registration.success_payment' ) == true,
			];

			$ADDON_POST_CREATION = (object) [
				'class' => 'GF_Advanced_Post_Creation',
				'slug'  => 'gravityformsadvancedpostcreation',
				'run'   => Helpers::dataGet( $config, 'post_create.success_payment' ) == true,
			];

			$ADDON_POST_UPDATE = (object) [
				'class' => 'ACGF_PostUpdateAddOn',
				'slug'  => 'post-update-addon-gravity-forms',
				'run'   => Helpers::dataGet( $config, 'post_update.success_payment' ) == true,
			];

			Helpers::runAddon( $ADDON_USER_REGESTERATION, $form, $entry );
			Helpers::runAddon( $ADDON_POST_CREATION, $form, $entry );
			Helpers::runAddon( $ADDON_POST_UPDATE, $form, $entry );
		}
	}

	public static function runAddon( $obj, $form, $entry ) {
		$formId = Helpers::dataGet( $form, 'id' );
		if ( $obj->run == true ) {
			if ( Helpers::checkExistsAddon( $obj ) ) {
				$addon = call_user_func( [ $obj->class, 'get_instance' ] );
				$feeds = $addon->getFeeds( $formId );
				foreach ( $feeds as $feed ) {
					$addon->process_feed( $feed, $entry, $form );
				}
			}
		}
	}

	public static function checkExistsAddon( $obj ) {
		try {
			$condition1 = class_exists( 'GFAddon' );
			$condition2 = method_exists( 'GFAddon', 'get_registered_addons' );

			if ( $condition1 && $condition2 ) {
				$addons = GFAddon::get_registered_addons();
				foreach ( $addons as $addon ) {
					$addon = call_user_func( array( $addon, 'get_instance' ) );
					$slug  = $addon->get_slug();
					if ( $obj->slug == $slug ) {
						return true;
					}
				}
			}

			return false;
		} catch ( Exception $ex ) {
			return false;
		}

	}


	public static function prepareFrontEndTools() {
		include_once Helpers::getBasePath() . '/resources/js/scripts.php';
		include_once Helpers::getBasePath() . '/resources/css/styles.php';
	}

	public static function getBasePath() {
		$baseDir   = WP_PLUGIN_DIR;
		$pluginDir = Keys::PLUGIN_FOLDER;

		return "{$baseDir}/{$pluginDir}";
	}

	public static function calcFormId( $fId, $config ) {
		return ! empty( $fId ) ? $fId : ( ! empty( $config ) ? $config["form_id"] : null );
	}

	public static function makeStatusColor( $status ) {
		switch ( $status ) {
			case 'Processing':
				return "style='color: orange;'";
			case 'Paid':
				return "style='color: green;'";
			case 'Failed':
				return "style='color: red;'";
			default:
				return "style='color: black;'";
		}
	}

	public static function getJalaliDateTime( $dateTime ) {
		if ( ! empty( $dateTime ) && $dateTime != '-' ) {
			$jDateConvertor = JDate::getInstance();
			$y              = DateTime::createFromFormat( 'Y-m-j H:i:s', $dateTime )->format( 'Y' );
			$m              = DateTime::createFromFormat( 'Y-m-j H:i:s', $dateTime )->format( 'm' );
			$d              = DateTime::createFromFormat( 'Y-m-j H:i:s', $dateTime )->format( 'j' );
			$time           = DateTime::createFromFormat( 'Y-m-j H:i:s', $dateTime )->format( 'H:i:s' );
			$jalaliDateTime = $jDateConvertor->gregorian_to_persian( $y, $m, $d );
			$jalaliY        = (string) $jalaliDateTime[0];
			$jalaliM        = (string) $jalaliDateTime[1];
			$jalaliD        = (string) $jalaliDateTime[2];

			return "{$jalaliY}-{$jalaliM}-{$jalaliD} {$time}";
		}

		return '-';
	}

	public static function getMiladiDateTime( $dateTime ) {
		if ( ! empty( $dateTime ) && $dateTime != '-' ) {
			$jDateConvertor = JDate::getInstance();
			$y              = DateTime::createFromFormat( 'Y-m-j H:i:s', $dateTime )->format( 'Y' );
			$m              = DateTime::createFromFormat( 'Y-m-j H:i:s', $dateTime )->format( 'm' );
			$d              = DateTime::createFromFormat( 'Y-m-j H:i:s', $dateTime )->format( 'j' );
			$time           = DateTime::createFromFormat( 'Y-m-j H:i:s', $dateTime )->format( 'H:i:s' );
			$jalaliDateTime = $jDateConvertor->persian_to_gregorian( $y, $m, $d );
			$jalaliY        = (string) $jalaliDateTime[0];
			$jalaliM        = (string) $jalaliDateTime[1];
			$jalaliD        = (string) $jalaliDateTime[2];

			return "{$jalaliY}-{$jalaliM}-{$jalaliD} {$time}";
		}

		return '-';
	}

	public static function checkCurrentPageForIDPAY() {
		$condition      = [ 'gf_IDPay', 'IDPay' ];
		$currentPage    = in_array( trim( rgget( 'page' ) ), $condition );
		$currentView    = in_array( trim( rgget( 'view' ) ), $condition );
		$currentSubview = in_array( trim( rgget( 'subview' ) ), $condition );

		return $currentPage || $currentView || $currentSubview;
	}

	public static function checkEntryForIDPay( $entry ) {
		$payment_gateway = rgar( $entry, "payment_method" );

		return ! empty( $payment_gateway ) && $payment_gateway == "IDPay";
	}

	public static function getTypeEntryView() {
		$condition = ! strtolower( rgpost( "save" ) ) || RGForms::post( "screen_mode" ) != "edit";

		return $condition == true ? 'showView' : 'editView';
	}

	public static function getGlobalKey( $key ) {
		$option = get_option( $key );

		return ! empty( $option ) ? $option : null;
	}

	public static function deleteGlobalKey( $key ) {
		delete_option( $key );
	}

	public static function setGlobalKey( $key, $value ) {
		update_option( $key, $value );
	}

	public static function makePrintVariableNote($variable,$note) {
		$dict = Helpers::loadDictionary();
		$variable = json_encode($variable,JSON_PRETTY_PRINT);
		$notes = $note . PHP_EOL . PHP_EOL . '<hr>'. $dict->labelShowData . PHP_EOL . '<hr>' . $variable;
		$style = Keys::CSS_NOTE_STYLE;
		$html = "<div style='{$style}'>{$notes}</div>";
		return $html;
	}

	public static function convertNameToTxtBoxKey($name) {
		return sanitize_text_field(
			rgpost( 'input_' . str_replace( ".", "_", $name ) ) );

	}

	public static function makeCustomDescription($entry,$form,$feed) {
		$formId   = $form['id'];
		$entryId = $entry['id'];
		$title   = $form['title'];
		$description = $feed["meta"]["description"];
		$desc        = $feed["meta"]["payment_description"];

		$note = ['{entry_id}','{form_title}','{form_id}'];
		$keys = [ $entryId, $title, $formId ];

		$desc1       = ! empty( $description ) ? str_replace( $note, $keys, $description ) : '';
		$desc2       = ! empty( $desc ) ? Helpers::convertNameToTxtBoxKey($desc) : '';
		$description = ( ! empty( $desc1 ) && ! empty( $desc2 ) ? ' - ' : '' );
		return sanitize_text_field( $desc1 . $description . $desc2 . ' ' );
	}


}
