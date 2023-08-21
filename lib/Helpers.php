<?php

class Helpers {

	public static $version = "1.0.5";
	public static $author = "IDPay";
	public static $domain = "gravityformsIDPay";
	public static $min_gravityforms_version = "1.9.10";

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
					return self::value( $default );
				}

				$result = [];

				foreach ( $target as $item ) {
					$result[] = self::dataGet( $item, $key );
				}

				return in_array( '*', $key ) ? self::collapse( $result ) : $result;
			}

			if ( self::accessible( $target ) && self::exists( $target, $segment ) ) {
				$target = $target[ $segment ];
			} elseif ( is_object( $target ) && isset( $target->{$segment} ) ) {
				$target = $target->{$segment};
			} else {
				return self::value( $default );
			}
		}

		return $target;
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

		return apply_filters( self::$author . '_IDPay_return_url', apply_filters( self::$author . '_gateway_return_url', $pageURL, $form_id, $entry_id, __CLASS__ ), $form_id, $entry_id, __CLASS__ );
	}

	public static function redirect_confirmation( $url, $ajax ) {
		if ( headers_sent() || $ajax ) {
			$confirmation = "<script type=\"text/javascript\">" . apply_filters( 'gform_cdata_open', '' ) . " function gformRedirect(){document.location.href='$url';}";
			if ( ! $ajax ) {
				$confirmation .= 'gformRedirect();';
			}
			$confirmation .= apply_filters( 'gform_cdata_close', '' ) . '</script>';
		} else {
			$confirmation = array( 'redirect' => $url );
		}

		return $confirmation;
	}

	public static function getGravityTransactionTypeCode( $type ): int {
		return $type == "subscription" ? 2 : 1;
	}

	public static function checkOneConfirmationExists( $confirmation, $form, $entry, $ajax ): bool {
		if ( apply_filters(
			'gf_IDPay_request_return',
			apply_filters( 'gf_gateway_request_return', false, $confirmation, $form, $entry, $ajax ),
			$confirmation,
			$form,
			$entry,
			$ajax
		) ) {
			return false;
		}

		return true;
	}

	public static function checkSubmittedForIDPay( $formId ): bool {
		if ( RGForms::post( "gform_submit" ) != $formId ) {
			return false;
		}

		return true;
	}

	public static function checkFeedExists( $form ): bool {
		return ! empty( IDPayDB::getActiveFeed( $form ) );
	}

	public static function getGatewayName(): string {
		$settings = get_option( "gf_IDPay_settings" );

		return isset( $settings['gname'] ) ? $settings["gname"] : __( 'IDPay', 'gravityformsIDPay' );
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

	public static function isNotApprovedGettingTransaction( $entry, $form_id ) {

		$entryId       = self::dataGet( $entry, 'id' );
		$paymentMethod = self::dataGet( $entry, 'payment_method' );
		$paymentDate   = self::dataGet( $entry, 'payment_date' );
		$condition1    = apply_filters( 'gf_gateway_IDPay_return', apply_filters( 'gf_gateway_verify_return', false ) );
		$condition2    = ! self::is_gravityforms_supported();
		$condition3    = ! is_numeric( $form_id );
		$condition4    = ! is_numeric( $entryId );
		$condition5    = is_wp_error( $entry );
		$condition6    = empty( $paymentDate );
		$condition7    = ! ( isset( $paymentMethod ) );
		$condition8    = $paymentMethod != 'IDPay';

		return $condition1 || $condition2 || $condition3 || $condition4 ||
		       $condition5 || $condition6 || $condition7 || $condition8;
	}

	public static function is_gravityforms_supported(): bool {
		$condition1 = class_exists( "GFCommon" );
		$condition2 = (bool) version_compare( GFCommon::$version, self::$min_gravityforms_version, ">=" );

		return $condition1 && $condition2;
	}

	public static function getApiKey() {
		$settings = get_option( "gf_IDPay_settings" );
		$api_key  = $settings["api_key"] ?? '';

		return trim( $api_key );
	}

	public static function getSandbox() {
		$settings = get_option( "gf_IDPay_settings" );

		return $settings["sandbox"] ? "true" : "false";
	}

	public static function httpRequest( $url, $data ) {
		$args = [
			'body'    => json_encode( $data ),
			'headers' => [
				'Content-Type' => 'application/json',
				'X-API-KEY'    => self::getApiKey(),
				'X-SANDBOX'    => self::getSandbox(),
			],
			'timeout' => 30,
		];

		$number_of_connection_tries = 3;
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
			$shippingField = GFAPI::get_fields_by_type( $form, [ 'shipping' ] );
			$fields        = self::dataGet( $form, 'fields' );
			if ( ! empty( $shippingField ) ) {
				foreach ( $fields as $field ) {
					if ( $field['type'] == 'product' ) {
						$check = true;
					}
				}
			}

			return $check;
		} elseif ( empty( $formId ) ) {
			return true;
		}

		return false;
	}

	public static function makeSafeDataForDb( $idpayConfig ) {
		$safeData = [];
		$metas    = self::dataGet( $idpayConfig, 'meta' );
		foreach ( $metas as $key => $val ) {
			$value            = ! is_array( $val ) ? sanitize_text_field( $val ) : array_map( 'sanitize_text_field', $val );
			$safeData[ $key ] = $value;
		}

		return $safeData;
	}

	public static function updateConfigAndRedirectPage( $feedId, $data ) {
		$idpayConfig = apply_filters( self::$author . '_gform_gateway_save_config', $data );
		$idpayConfig = apply_filters( self::$author . '_gform_IDPay_save_config', $idpayConfig );
		$feedId      = IDPayDB::update_feed(
			$feedId,
			self::dataGet( $idpayConfig, 'form_id' ),
			self::dataGet( $idpayConfig, 'is_active' ),
			self::dataGet( $idpayConfig, 'meta' ),
		);
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

	public static function SearchFormName( $feedId ) {
		$dbFeeds  = (array) IDPayDB::get_feeds();
		$formName = '';
		foreach ( $dbFeeds as $dbFeed ) {
			$dbFeedId = self::dataGet( $dbFeed, 'id' );
			if ( $dbFeedId == $feedId ) {
				$formName = self::dataGet( $dbFeed, 'form_title' );
			}
		}

		return $formName;
	}

	public static function readDataFromRequest( $config ) {
		$config["form_id"]                        = absint( rgpost( "gf_IDPay_form" ) );
		$config["is_active"]                      = true;
		$config["meta"]["type"]                   = rgpost( "gf_IDPay_type" );
		$config["meta"]["addon"]                  = rgpost( "gf_IDPay_addon" );
		$config["meta"]["desc_pm"]                = rgpost( "gf_IDPay_desc_pm" );
		$config["meta"]["customer_fields_desc"]   = rgpost( "IDPay_customer_field_desc" );
		$config["meta"]["customer_fields_email"]  = rgpost( "IDPay_customer_field_email" );
		$config["meta"]["customer_fields_mobile"] = rgpost( "IDPay_customer_field_mobile" );
		$config["meta"]["customer_fields_name"]   = rgpost( "IDPay_customer_field_name" );
		$config["meta"]["confirmation"]           = rgpost( "gf_IDPay_confirmation" );

		return $config;
	}

	public static function makeUpdateMessageBar( $oldFeedId ) {
		$feedId       = (int) rgget( 'id' ) ?? $oldFeedId;
		$message      = __( " فید {$feedId} به روز شد . %sبازگشت به لیست%s . ", "gravityformsIDPay" );
		$updatedLabel = sprintf( $message, "<a href='?page=gf_IDPay'>", "</a>" );
		echo '<div class="updated fade" style="padding:6px">' . $updatedLabel . '</div>';

		return true;
	}

	public static function loadSavedOrDefaultValue( $form, $fieldName, $selectedValue ) {
		$gravityFormFields = ! empty( $form ) ? self::get_form_fields( $form ) : null;
		if ( $gravityFormFields != null ) {
			return self::get_mapped_field_list( $fieldName, $selectedValue, $gravityFormFields );
		}

		return '';
	}

	public static function generateFeedSelectForm( $formId ) {
		$gfAllForms             = IDPayDB::get_available_forms();
		$visibleFieldFormSelect = rgget( 'id' ) || rgget( 'fid' ) ? 'style="display:none !important"' : '';
		$label                  = 'یک فرم انتخاب نمایید';
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

	public static function generateStatusBarMessage( $formId ) {
		$updateFeedLabel = __( "فید به روز شد . %sبازگشت به لیست%s.", "gravityformsIDPay" );
		$updatedFeed     = sprintf( $updateFeedLabel, "<a href='?page=gf_IDPay'>", "</a>" );
		$feedHtml        = '<div class="updated fade" style="padding:6px">' . $updatedFeed . '</div>';

		return $feedHtml;
	}

	public static function loadDictionary( $feedId, $formName ) {
		return (object) [
			'label1'             => translate( "پیکربندی درگاه IDPay", self::$domain ),
			'label2'             => sprintf( __( "فید: %s", self::$domain ), $feedId ),
			'label3'             => sprintf( __( "فرم: %s", self::$domain ), $formName ),
			'label4'             => translate( "تنظیمات کلی", self::$domain ),
			'label5'             => translate( "انتخاب فرم", self::$domain ),
			'label6'             => translate( "یک فرم انتخاب نمایید", self::$domain ),
			'label7'             => translate( "فرم انتخاب شده هیچ گونه فیلد قیمت گذاری ندارد، لطفا پس از افزودن این فیلدها مجددا اقدام نمایید.", self::$domain ),
			'label8'             => translate( "User_Registration تنظیمات", self::$domain ),
			'label9'             => translate( ' اگر این فرم وظیفه ثبت نام کاربر تنها در صورت پرداخت موفق را دارد تیک بزنید', self::$domain ),
			'label10'            => translate( "توضیحات پرداخت", self::$domain ),
			'label11'            => translate( "توضیحاتی که میتوانید در داشبورد سایت آیدی ببینید . می توانید از", self::$domain ),
			'label11_2'          => translate( "{{form_title}}  و  {{form_id}}", self::$domain ),
			'label11_3'          => translate( "نیز برای نشانه گذاری استفاده کنید", self::$domain ),
			'label12'            => translate( "نام پرداخت کننده", self::$domain ),
			'label13'            => translate( "ایمیل پرداخت کننده", self::$domain ),
			'label14'            => translate( "توضیح تکمیلی", self::$domain ),
			'label15'            => translate( "تلفن همراه پرداخت کننده", self::$domain ),
			'label16'            => translate( "مدیریت افزودنی های گراویتی", self::$domain ),
			'label17'            => translate( "این گزینه را تنها در صورتی تیک بزنید که", self::$domain ),
			'label17_2'          => translate( "شما از پلاگین های افزودنی گراویتی مانند", self::$domain ),
			'label17_3'          => translate( "(Post Update , Post Create , ...)", self::$domain ),
			'label17_4'          => translate( " استفاده می کنید و باید تنها در صورت پرداخت موفق اجرا شوند", self::$domain ),
			'label18'            => translate( "استفاده از تاییدیه های سفارشی", self::$domain ),
			'label19'            => translate( "این گزینه را در صورتی تیک بزنید که", self::$domain ),
			'label19_2'          => translate( "نمیخواهید از تاییدیه ثبت فرم پیش فرض آیدی پی استفاده کنید", self::$domain ),
			'label19_3'          => translate( "و از تاییدیه های سفارشی گراویتی استفاده می کنید", self::$domain ),
			'label19_4'          => translate( "توجه * : برای ترکیب تاییدیه های سفارشی با آیدی پی از متغیر", self::$domain ),
			'label19_5'          => translate( "idpay_payment_result", self::$domain ),
			'label19_6'          => translate( "استفاده کنید", self::$domain ),
			'label21'            => translate( "ذخیره تنظیمات", self::$domain ),
			'label22'            => translate( "فرم های IDPay", self::$domain ),
			'label23'            => translate( "اقدام دسته جمعی", self::$domain ),
			'label24'            => translate( "اقدامات دسته جمعی", self::$domain ),
			'label25'            => translate( "حذف", self::$domain ),
			'label26'            => translate( 'تنظیمات IDPay', self::$domain ),
			'label27'            => translate( 'وضعیت', self::$domain ),
			'label28'            => translate( " آیدی فید", self::$domain ),
			'label29'            => translate( "نوع تراکنش", self::$domain ),
			'label30'            => translate( "فرم متصل به درگاه", self::$domain ),
			'label31'            => translate( "برای شروع باید درگاه را فعال نمایید . به تنظیمات IDPay بروید .", self::$domain ),
			'label32'            => translate( "عملیات", self::$domain ),
			'label33'            => translate( "ویرایش فید", self::$domain ),
			'label34'            => translate( "حذف فید", self::$domain ),
			'label35'            => translate( "ویرایش فرم", self::$domain ),
			'label36'            => translate( "صندوق ورودی", self::$domain ),
			'label37'            => translate( "نمودارهای فرم", self::$domain ),
			'label38'            => translate( "افزودن فید جدید", self::$domain ),
			'label39'            => translate( "نمودار ها", self::$domain ),
			'label40'            => translate( "درگاه با موفقیت غیرفعال شد و اطلاعات مربوط به آن نیز از بین رفت برای فعالسازی مجدد میتوانید از طریق افزونه های وردپرس اقدام نمایید ", self::$domain ),
			'label41'            => translate( "تنظیمات ذخیره شدند", self::$domain ),
			'label42'            => translate( "تنظیمات IDPay", self::$domain ),
			'label43'            => translate( "فعالسازی", self::$domain ),
			'label44'            => translate( "بله", self::$domain ),
			'label45'            => translate( "عنوان", self::$domain ),
			'label46'            => translate( "API KEY", self::$domain ),
			'label47'            => translate( "آزمایشگاه", self::$domain ),
			'label48'            => translate( "بله", self::$domain ),
			'label49'            => translate( "ذخیره تنظیمات", self::$domain ),
			'label50'            => translate( "غیر فعالسازی افزونه دروازه پرداخت IDPay", self::$domain ),
			'label51'            => translate( "تذکر : بعد از غیرفعالسازی تمامی اطلاعات مربوط به IDPay حذف خواهد شد", self::$domain ),
			'label52'            => translate( "غیر فعال سازی درگاه IDPay", self::$domain ),
			'label53'            => translate( "تذکر : بعد از غیرفعالسازی تمامی اطلاعات مربوط به IDPay حذف خواهد شد . آیا همچنان مایل به غیر فعالسازی میباشید؟", self::$domain ),
			'labelSelectGravity' => translate( "از فیلدهای موجود در فرم گراویتی یکی را انتخاب کنید", self::$domain ),
			'labelNotSupprt'     => sprintf( __( "درگاه IDPay نیاز به گرویتی فرم نسخه %s دارد. برای بروز رسانی هسته گرویتی فرم به %s مراجعه نمایید.", self::$domain ), $feedId, $formName ),
		];
	}

	public static function checkSupportedGravityVersion() {
		$label1 = self::$min_gravityforms_version;
		$label2 = "<a href='http://gravityforms.ir' target='_blank'>سایت گرویتی فرم فارسی</a>";
		if ( ! self::is_gravityforms_supported() ) {
			return self::loadDictionary( $label1, $label2 )->labelNotSupprt;
		}

		return true;
	}

	public static function getStatusFeedImage( $setting ) {
		$val1   = esc_url( GFCommon::get_base_url() );
		$val2   = "/images/active";
		$val3   = intval( $setting["is_active"] );
		$val4   = ".png";
		$image  = $val1 . $val2 . $val3 . $val4;
		$active = $setting["is_active"] ? "درگاه فعال است" : "درگاه غیر فعال است";

		return (object) [
			'image'  => $image,
			'active' => $active
		];
	}

	public static function getTypeFeed( $setting ) {
		if ( isset( $setting["meta"]["type"] ) && $setting["meta"]["type"] == 'subscription' ) {
			return "خرید محصول + عضویت کاربر";
		} else {
			return "خرید محصول";
		}
	}

	public static function checkSubmittedOperation() {
		if ( rgpost( 'action' ) == "delete" ) {
			check_admin_referer( "list_action", "gf_IDPay_list" );
			$id = absint( rgpost( "action_argument" ) );
			IDPayDB::delete_feed( $id );

			return "<div class='updated fade' style='padding:6px'>فید حذف شد</div>";
		} elseif ( ! empty( $_POST["bulk_action"] ) ) {
			check_admin_referer( "list_action", "gf_IDPay_list" );
			$selected_feeds = rgpost( "feed" );
			if ( is_array( $selected_feeds ) ) {
				foreach ( $selected_feeds as $feed_id ) {
					IDPayDB::delete_feed( $feed_id );

					return "<div class='updated fade' style='padding:6px'>فید ها حذف شد</div>";
				}
			}
		}

		return '';
	}

	public static function checkSubmittedUnistall() {
		$dictionary = self::loadDictionary( '', '' );
		if ( rgpost( "uninstall" ) ) {
			check_admin_referer( "uninstall", "gf_IDPay_uninstall" );
			self::uninstall();
			echo '<div class="updated fade" style="padding:20px;">' . $dictionary->label34 . '</div>';

			return;
		}
	}

	public static function checkSubmittedConfigDataAndLoadSetting() {
		if ( isset( $_POST["gf_IDPay_submit"] ) ) {
			check_admin_referer( "update", "gf_IDPay_update" );
			$settings = [
				"gname"   => rgpost( 'gf_IDPay_gname' ),
				"api_key" => rgpost( 'gf_IDPay_api_key' ),
				"sandbox" => rgpost( 'gf_IDPay_sandbox' ),
			];
			update_option( "gf_IDPay_settings", array_map( 'sanitize_text_field', $settings ) );
			if ( isset( $_POST["gf_IDPay_configured"] ) ) {
				update_option( "gf_IDPay_configured", sanitize_text_field( $_POST["gf_IDPay_configured"] ) );
			} else {
				delete_option( "gf_IDPay_configured" );
			}

			return $settings;
		} else {
			$settings = get_option( "gf_IDPay_settings" );

			return $settings;
		}
	}

	public static function loadConfigByEntry( $entry ) {
		$feed_id = gform_get_meta( $entry["id"], "IDPay_feed_id" );
		$feed    = ! empty( $feed_id ) ? IDPayDB::get_feed( $feed_id ) : '';
		$return  = ! empty( $feed ) ? $feed : false;

		return apply_filters(
			self::$author . '_gf_IDPay_get_config_by_entry',
			apply_filters(
				self::$author . '_gf_gateway_get_config_by_entry',
				$return,
				$entry
			),
			$entry
		);
	}

	public static function loadConfig( $entry, $form, $paymentType ) {
		$config = null;

		if ( $paymentType != 'custom' ) {
			$config = self::loadConfigByEntry( $entry );
			if ( empty( $config ) ) {
				return null;
			}
		} elseif ( $paymentType != 'form' ) {
			$config = apply_filters(
				self::$author . '_gf_IDPay_config',
				apply_filters( self::$author . '_gf_gateway_config', [], $form, $entry ),
				$form,
				$entry
			);
		}

		return $config;
	}

	public static function loadUser() {
		global $current_user;
		$user_data = get_userdata( $current_user->ID );
		$user_id   = 0;
		$user_name = '';
		if ( $current_user && $user_data ) {
			$user_id   = $current_user->ID;
			$user_name = $user_data->display_name;
		} else {
			$user_name = __( 'مهمان', 'gravityformsIDPay' );
			$user_id   = 0;
		}

		return (object) [
			'id'       => $user_id,
			'username' => $user_name
		];
	}

	public static function getTransactionType( $config ) {
		return $config["meta"]["type"] == 'subscription' ? 2 : 1;
	}

	public static function getOrderTotal( $form, $entry ) {
		$total = GFCommon::get_order_total( $form, $entry );
		$total = ( ! empty( $total ) && $total > 0 ) ? $total : 0;

		return apply_filters(
			self::$author . '_IDPay_get_order_total',
			apply_filters( self::$author . '_gateway_get_order_total', $total, $form, $entry ),
			$form,
			$entry
		);
	}

	public static function getPriceOrder( $paymentType, $entry, $form ) {
		$entryId  = self::dataGet( $entry, 'id' );
		$formId   = self::dataGet( $form, 'id' );
		$currency = self::dataGet( $entry, 'currency' );

		if ( $paymentType == 'custom' ) {
			$amount = gform_get_meta( $entryId, 'IDPay_part_price_' . $formId );
		} else {
			$amount = self::getOrderTotal( $form, $entry );
			$amount = GFPersian_Payments::amount( $amount, 'IRR', $form, $entry );
		}

		return (object) [
			'amount' => $amount,
			'money'  => GFCommon::to_money( $amount, $currency )
		];
	}

	public static function checkTypeVerify() {
		return self::dataGet( $_GET, 'no' ) == 'true' ? 'Free' : 'Purchase';
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
			'id'      => self::dataGet( $request, 'id' ),
			'status'  => self::dataGet( $request, 'status' ),
			'trackId' => self::dataGet( $request, 'track_id' ),
			'orderId' => self::dataGet( $request, 'order_id' ),
			'formId'  => (int) self::dataGet( $_GET, 'form_id' ),
			'entryId' => (int) self::dataGet( $_GET, 'entry' ),
			'all'     => $all,
		];
	}

	public static function checkApprovedVerifyData( $request ) {
		$keys = [ 'status', 'id', 'trackId', 'orderId', 'formId', 'entryId' ];
		foreach ( $keys as $key ) {
			if ( self::dataGet( $request, $key ) == null ) {
				return false;
			}
		}
		return true;
	}

	public static function getStatus( $statusCode ) {
		switch ( $statusCode ) {
			case 1:
				return 'پرداخت انجام نشده است';
			case 2:
				return 'پرداخت ناموفق بوده است';
			case 3:
				return 'خطا رخ داده است';
			case 4:
				return 'بلوکه شده';
			case 5:
				return 'برگشت به پرداخت کننده';
			case 6:
				return 'برگشت خورده سیستمی';
			case 7:
				return 'انصراف از پرداخت';
			case 8:
				return 'به درگاه پرداخت منتقل شد';
			case 10:
				return 'در انتظار تایید پرداخت';
			case 100:
				return 'پرداخت یا عضویت تایید شده است';
			case 101:
				return 'پرداخت یا عضویت قبلا تایید شده است';
			case 200:
				return 'به دریافت کننده واریز شد';
			case 0:
				return 'خطا در عملیات';
			default:
				return 'خطای ناشناخته';
		}
	}

	public static function isNotDoubleSpending( $reference_id, $order_id, $transaction_id ) {
		$relatedTransaction = gform_get_meta( $reference_id, "IdpayTransactionId:$order_id", false );
		if ( ! empty( $relatedTransaction ) ) {
			return $transaction_id == $relatedTransaction;
		}

		return false;
	}
}