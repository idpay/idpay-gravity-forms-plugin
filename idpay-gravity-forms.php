<?php

/**
 * Plugin Name: IDPay gateway - Gravity Forms
 * Author: IDPay
 * Description: <a href="https://idpay.ir">IDPay</a> secure payment gateway for Gravity Forms.
 * Version: 1.1.2
 * Author URI: https://idpay.ir
 * Author Email: info@idpay.ir
 * Text Domain: idpay-gravity-forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook( __FILE__, [ 'GF_Gateway_IDPay', "add_permissions" ] );
register_deactivation_hook( __FILE__, [ 'GF_Gateway_IDPay', "deactivation" ] );

add_action( 'init', [ 'GF_Gateway_IDPay', 'init' ] );

require_once( 'lib/IDPayDB.php' );
require_once( 'lib/Helpers.php' );
require_once( 'lib/JDate.php' );
require_once( 'lib/IDPayPayment.php' );
require_once( 'lib/IDPayVerify.php' );
require_once( 'lib/IDPayOperation.php' );

class GF_Gateway_IDPay extends Helpers {
	public static $author = "IDPay";
	public static $version = "1.0.5";
	public static $min_gravityforms_version = "1.9.10";
	public static $domain = "gravityformsIDPay";

	public static function init() {
		$dictionary = Helpers::loadDictionary( '', '' );
		$condition1 = ! class_exists( "GFPersian_Payments" );
		$condition2 = ! defined( 'GF_PERSIAN_VERSION' );
		$condition3 = version_compare( GF_PERSIAN_VERSION, '2.3.1', '<' );

		if ( $condition1 || $condition2 || $condition3 ) {
			add_action( 'admin_notices', [ __CLASS__, 'reportPreRequiredPersianGravityForm' ] );

			return false;
		}

		if ( ! self::is_gravityforms_supported() ) {
			add_action( 'admin_notices', [ __CLASS__, 'reportPreRequiredGravityForm' ] );

			return false;
		}

		add_filter( 'members_get_capabilities', [ __CLASS__, "members_get_capabilities" ] );

		if ( is_admin() && self::hasPermission() ) {
			add_filter( 'gform_tooltips', [ __CLASS__, 'tooltips' ] );
			add_filter( 'gform_addon_navigation', [ __CLASS__, 'menu' ] );
			add_action( 'gform_entry_info', [ __CLASS__, 'payment_entry_detail' ], 4, 2 );
			add_action( 'gform_after_update_entry', [ __CLASS__, 'update_payment_entry' ], 4, 2 );

			if ( get_option( "gf_IDPay_configured" ) ) {
				add_filter( 'gform_form_settings_menu', [ __CLASS__, 'toolbar' ], 10, 2 );
				add_action( 'gform_form_settings_page_IDPay', [ __CLASS__, 'loadFeedList' ] );
			}

			if ( rgget( "page" ) == "gf_settings" ) {
				RGForms::add_settings_page(
					[
						'name'      => 'gf_IDPay',
						'tab_label' => $dictionary->labelSettingsTab,
						'title'     => $dictionary->labelSettingsTitle,
						'handler'   => [ __CLASS__, 'loadSettingPage' ],
					]
				);
			}

			if ( Helpers::checkCurrentPageForIDPAY() ) {
				wp_enqueue_script( 'sack' );
				self::setup();
			}
			add_action( 'wp_ajax_gf_IDPay_update_feed_active', [ __CLASS__, 'update_feed_active' ] );
		}
		if ( get_option( "gf_IDPay_configured" ) ) {

			/* Idont Know Working */
			add_filter( "gform_disable_post_creation", [ __CLASS__, "setDelayedActivity" ], 10, 3 );
			add_filter( "gform_is_delayed_pre_process_feed", [ __CLASS__, "setDelayedGravityAddons" ], 10, 4 );
			/* Idont Know Working */

			add_filter( "gform_confirmation", [ __CLASS__, "doPayment" ], 1000, 4 );
			add_action( 'wp', [ __CLASS__, 'doVerify' ], 5 );
			add_filter( "gform_submit_button", [ __CLASS__, "makeButtonSubmitForm" ], 10, 2 );
		}

		add_filter( "gform_logging_supported", [ __CLASS__, "set_logging_supported" ] );
		add_filter( 'gf_payment_gateways', [ __CLASS__, 'gravityformsIDPay' ], 2 );
		do_action( 'gravityforms_gateways' );
		do_action( 'gravityforms_IDPay' );
		add_filter( 'gform_admin_pre_render', [ __CLASS__, 'merge_tags_keys' ] );

		return 'completed';
	}

	public static function doPayment( $confirmation, $form, $entry, $ajax ) {
		return IDPayPayment::doPayment( $confirmation, $form, $entry, $ajax );
	}

	public static function doVerify() {
		return IDPayVerify::doVerify();
	}

	public static function setDelayedActivity( $is_disabled, $form, $entry ) {
		$config = IDPayDB::getActiveFeed( $form );

		return ! empty( $config ) ? true : $is_disabled;
	}

	public static function setDelayedGravityAddons( $is_delayed, $form, $entry, $slug ) {
		$config     = IDPayDB::getActiveFeed( $form );
		$delayedFor = self::makeListDelayedAddons( $config );

		if ( ! empty( $config ) ) {
			if ( $slug == 'gravityformsuserregistration' ) {
				return $delayedFor['userRegistration'];
			} elseif ( $slug == 'gravityformsadvancedpostcreation' ) {
				return $delayedFor['postCreate'];
			} elseif ( $slug == 'post-update-addon-gravity-forms' ) {
				return $delayedFor['postUpdate'];
			} else {
				return $is_delayed;
			}
		}

		return $is_delayed;
	}

	public static function loadFeedList() {
		GFFormSettings::page_header();
		require_once( self::getBasePath() . '/resources/views/feed/index.php' );
		GFFormSettings::page_footer();
	}

	public static function routeFeedPage() {
		$view = rgget( "view" );
		if ( $view == "edit" ) {
			require_once( self::getBasePath() . '/resources/views/feed/config.php' );
		} elseif ( $view == "stats" ) {
			require_once( self::getBasePath() . '/resources/views/feed/transactions.php' );
		} else {
			require_once( self::getBasePath() . '/resources/views/feed/index.php' );
		}
	}

	public static function loadSettingPage() {
		require_once( self::getBasePath() . '/resources/views/setting.php' );
	}

	public static function makeButtonSubmitForm( $button_input, $form ) {

        $buttonHtml = $button_input;
		$formId = self::dataGet($form,'id');
		$dictionary = Helpers::loadDictionary( '', '' );
		Helpers::prepareFrontEndTools();

		$hasPriceFieldInForm  = Helpers::checkSetPriceForForm($form, $formId);
		$ImageUrl = plugins_url( '/resources/images/logo.svg', __FILE__ );
		$config     = IDPayDB::getActiveFeed( $form );
		$formId     = $form['id'];

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

	protected static function hasPermission( $permission = 'gravityforms_IDPay' ) {
		return IDPayOperation::hasPermission($permission);
	}

	private static function setup() {
        IDPayOperation::setup();
	}

	private static function deactivation() {
		IDPayOperation::deactivation();
	}

	public static function reportPreRequiredPersianGravityForm() {
		IDPayOperation::reportPreRequiredPersianGravityForm();
	}

	public static function reportPreRequiredGravityForm() {
		IDPayOperation::reportPreRequiredGravityForm();
	}

	public static function gravityformsIDPay( $form, $entry ) {
		$baseClass = __CLASS__;
		$author    = self::$author;
		$class     = "{$baseClass}|{$author}";
		$IDPay     = [
			'class' => $class,
			'title' => __( 'IDPay', 'gravityformsIDPay' ),
			'param' => [
				'email'  => __( 'ایمیل', 'gravityformsIDPay' ),
				'mobile' => __( 'موبایل', 'gravityformsIDPay' ),
				'desc'   => __( 'توضیحات', 'gravityformsIDPay' )
			]
		];

		return apply_filters( self::$author . '_gf_IDPay_detail',
			apply_filters( self::$author . '_gf_gateway_detail', $IDPay, $form, $entry )
			, $form, $entry );
	}

	public static function add_permissions() {
		global $wp_roles;
		$editable_roles = get_editable_roles();
		foreach ( $editable_roles as $role => $details ) {
			if ( $role == 'administrator' || in_array( 'gravityforms_edit_forms', $details['capabilities'] ) ) {
				$wp_roles->add_cap( $role, 'gravityforms_IDPay' );
				$wp_roles->add_cap( $role, 'gravityforms_IDPay_uninstall' );
			}
		}
	}

	public static function members_get_capabilities( $caps ) {
		return array_merge( $caps, [ "gravityforms_IDPay", "gravityforms_IDPay_uninstall" ] );
	}

	public static function tooltips( $tooltips ) {
		$tooltips["gateway_name"] = __( "تذکر مهم : این قسمت برای نمایش به بازدید کننده می باشد و لطفا جهت جلوگیری از مشکل و تداخل آن را فقط یکبار تنظیم نمایید و از تنظیم مکرر آن خود داری نمایید .", "gravityformsIDPay" );

		return $tooltips;
	}

	public static function menu( $menus ) {
		$permission = "gravityforms_IDPay";
		if ( ! empty( $permission ) ) {
			$menus[] = [
				"name"       => "gf_IDPay",
				"label"      => __( "IDPay", "gravityformsIDPay" ),
				"callback"   => [ __CLASS__, "routeFeedPage" ],
				"permission" => $permission
			];
		}

		return $menus;
	}

	public static function toolbar( $menu_items ) {
		$menu_items[] = [
			'name'  => 'IDPay',
			'label' => __( 'IDPay', 'gravityformsIDPay' )
		];

		return $menu_items;
	}

	public static function set_logging_supported( $plugins ) {
		$plugins[ basename( dirname( __FILE__ ) ) ] = "IDPay";

		return $plugins;
	}

	public static function update_feed_active() {
		check_ajax_referer( 'gf_IDPay_update_feed_active', 'gf_IDPay_update_feed_active' );
		$id   = absint( rgpost( 'feed_id' ) );
		$feed = IDPayDB::getFeed( $id );
		IDPayDB::updateFeed( $id, $feed["form_id"], $feed["meta"] );
	}

	public static function payment_entry_detail( $formId, $entry ) {

		$payment_gateway = rgar( $entry, "payment_method" );

		if ( ! empty( $payment_gateway ) && $payment_gateway == "IDPay" ) {
			do_action( 'gf_gateway_entry_detail' );

			?>
            <hr/>
            <strong>
				<?php _e( 'اطلاعات تراکنش :', 'gravityformsIDPay' ) ?>
            </strong>
            <br/>
            <br/>
			<?php

			$transaction_type = rgar( $entry, "transaction_type" );
			$payment_status   = rgar( $entry, "payment_status" );
			$payment_amount   = rgar( $entry, "payment_amount" );

			if ( empty( $payment_amount ) ) {
				$form           = RGFormsModel::get_form_meta( $formId );
				$payment_amount = self::getOrderTotal( $form, $entry );
			}

			$transaction_id = rgar( $entry, "transaction_id" );
			$payment_date   = rgar( $entry, "payment_date" );

			$date = new DateTime( $payment_date );
			$tzb  = get_option( 'gmt_offset' );
			$tzn  = abs( $tzb ) * 3600;
			$tzh  = intval( gmdate( "H", $tzn ) );
			$tzm  = intval( gmdate( "i", $tzn ) );

			if ( intval( $tzb ) < 0 ) {
				$date->sub( new DateInterval( 'P0DT' . $tzh . 'H' . $tzm . 'M' ) );
			} else {
				$date->add( new DateInterval( 'P0DT' . $tzh . 'H' . $tzm . 'M' ) );
			}

			$payment_date = $date->format( 'Y-m-d H:i:s' );
			$payment_date = GF_jdate( 'Y-m-d H:i:s', strtotime( $payment_date ), '', date_default_timezone_get(), 'en' );

			if ( $payment_status == 'Paid' ) {
				$payment_status_persian = __( 'موفق', 'gravityformsIDPay' );
			}

			if ( $payment_status == 'Active' ) {
				$payment_status_persian = __( 'موفق', 'gravityformsIDPay' );
			}

			if ( $payment_status == 'Cancelled' ) {
				$payment_status_persian = __( 'منصرف شده', 'gravityformsIDPay' );
			}

			if ( $payment_status == 'Failed' ) {
				$payment_status_persian = __( 'ناموفق', 'gravityformsIDPay' );
			}

			if ( $payment_status == 'Processing' ) {
				$payment_status_persian = __( 'معلق', 'gravityformsIDPay' );
			}

			if ( ! strtolower( rgpost( "save" ) ) || RGForms::post( "screen_mode" ) != "edit" ) {
				echo __( 'وضعیت پرداخت : ', 'gravityformsIDPay' ) . $payment_status_persian . '<br/><br/>';
				echo __( 'تاریخ پرداخت : ', 'gravityformsIDPay' ) . '<span style="">' . $payment_date . '</span><br/><br/>';
				echo __( 'مبلغ پرداختی : ', 'gravityformsIDPay' ) . GFCommon::to_money( $payment_amount, rgar( $entry, "currency" ) ) . '<br/><br/>';
				echo __( 'کد رهگیری : ', 'gravityformsIDPay' ) . $transaction_id . '<br/><br/>';
				echo __( 'درگاه پرداخت : IDPay', 'gravityformsIDPay' );
			} else {
				$payment_string = '';
				$payment_string .= '<select id="payment_status" name="payment_status">';
				$payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status_persian . '</option>';

				if ( $transaction_type == 1 ) {
					if ( $payment_status != "Paid" ) {
						$payment_string .= '<option value="Paid">' . __( 'موفق', 'gravityformsIDPay' ) . '</option>';
					}
				}

				if ( $transaction_type == 2 ) {
					if ( $payment_status != "Active" ) {
						$payment_string .= '<option value="Active">' . __( 'موفق', 'gravityformsIDPay' ) . '</option>';
					}
				}

				if ( ! $transaction_type ) {
					if ( $payment_status != "Paid" ) {
						$payment_string .= '<option value="Paid">' . __( 'موفق', 'gravityformsIDPay' ) . '</option>';
					}

					if ( $payment_status != "Active" ) {
						$payment_string .= '<option value="Active">' . __( 'موفق', 'gravityformsIDPay' ) . '</option>';
					}
				}

				if ( $payment_status != "Failed" ) {
					$payment_string .= '<option value="Failed">' . __( 'ناموفق', 'gravityformsIDPay' ) . '</option>';
				}

				if ( $payment_status != "Cancelled" ) {
					$payment_string .= '<option value="Cancelled">' . __( 'منصرف شده', 'gravityformsIDPay' ) . '</option>';
				}

				if ( $payment_status != "Processing" ) {
					$payment_string .= '<option value="Processing">' . __( 'معلق', 'gravityformsIDPay' ) . '</option>';
				}

				$payment_string .= '</select>';

				echo __( 'وضعیت پرداخت :', 'gravityformsIDPay' ) . $payment_string . '<br/><br/>';
				?>
                <div id="edit_payment_status_details" style="display:block">
                    <table>
                        <tr>
                            <td><?php _e( 'تاریخ پرداخت :', 'gravityformsIDPay' ) ?></td>
                            <td><input type="text" id="payment_date" name="payment_date"
                                       value="<?php echo $payment_date ?>"></td>
                        </tr>
                        <tr>
                            <td><?php _e( 'مبلغ پرداخت :', 'gravityformsIDPay' ) ?></td>
                            <td><input type="text" id="payment_amount" name="payment_amount"
                                       value="<?php echo $payment_amount ?>"></td>
                        </tr>
                        <tr>
                            <td><?php _e( 'شماره تراکنش :', 'gravityformsIDPay' ) ?></td>
                            <td><input type="text" id="IDPay_transaction_id" name="IDPay_transaction_id"
                                       value="<?php echo $transaction_id ?>"></td>
                        </tr>

                    </table>
                    <br/>
                </div>
				<?php
				echo __( 'درگاه پرداخت : IDPay (غیر قابل ویرایش)', 'gravityformsIDPay' );
			}

			echo '<br/>';
		}
	}

	public static function update_payment_entry( $form, $entry_id ) {

		check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );

		do_action( 'gf_gateway_update_entry' );

		$entry = GFPersian_Payments::get_entry( $entry_id );

		$payment_gateway = rgar( $entry, "payment_method" );

		if ( empty( $payment_gateway ) ) {
			return;
		}

		if ( $payment_gateway != "IDPay" ) {
			return;
		}

		$payment_status = sanitize_text_field( rgpost( "payment_status" ) );
		if ( empty( $payment_status ) ) {
			$payment_status = rgar( $entry, "payment_status" );
		}

		$payment_amount       = sanitize_text_field( rgpost( "payment_amount" ) );
		$payment_transaction  = sanitize_text_field( rgpost( "IDPay_transaction_id" ) );
		$payment_date_Checker = $payment_date = sanitize_text_field( rgpost( "payment_date" ) );

		list( $date, $time ) = explode( " ", $payment_date );
		list( $Y, $m, $d ) = explode( "-", $date );
		list( $H, $i, $s ) = explode( ":", $time );
		$miladi = GF_jalali_to_gregorian( $Y, $m, $d );

		$date         = new DateTime( "$miladi[0]-$miladi[1]-$miladi[2] $H:$i:$s" );
		$payment_date = $date->format( 'Y-m-d H:i:s' );

		if ( empty( $payment_date_Checker ) ) {
			if ( ! empty( $entry["payment_date"] ) ) {
				$payment_date = $entry["payment_date"];
			} else {
				$payment_date = rgar( $entry, "date_created" );
			}
		} else {
			$payment_date = date( "Y-m-d H:i:s", strtotime( $payment_date ) );
			$date         = new DateTime( $payment_date );
			$tzb          = get_option( 'gmt_offset' );
			$tzn          = abs( $tzb ) * 3600;
			$tzh          = intval( gmdate( "H", $tzn ) );
			$tzm          = intval( gmdate( "i", $tzn ) );
			if ( intval( $tzb ) < 0 ) {
				$date->add( new DateInterval( 'P0DT' . $tzh . 'H' . $tzm . 'M' ) );
			} else {
				$date->sub( new DateInterval( 'P0DT' . $tzh . 'H' . $tzm . 'M' ) );
			}
			$payment_date = $date->format( 'Y-m-d H:i:s' );
		}

		$userData = self::loadUser();

		$entry["payment_status"] = $payment_status;
		$entry["payment_amount"] = $payment_amount;
		$entry["payment_date"]   = $payment_date;
		$entry["transaction_id"] = $payment_transaction;
		if ( $payment_status == 'Paid' || $payment_status == 'Active' ) {
			$entry["is_fulfilled"] = 1;
		} else {
			$entry["is_fulfilled"] = 0;
		}
		GFAPI::update_entry( $entry );

		$new_status = '';
		switch ( rgar( $entry, "payment_status" ) ) {
			case "Paid":
			case "Active":
				$new_status = __( 'موفق', 'gravityformsIDPay' );
				break;

			case "Cancelled":
				$new_status = __( 'منصرف شده', 'gravityformsIDPay' );
				break;

			case "Failed":
				$new_status = __( 'ناموفق', 'gravityformsIDPay' );
				break;

			case "processing":
				$new_status = __( 'معلق', 'gravityformsIDPay' );
				break;
		}

		RGFormsModel::add_note( $entry["id"], $userData->id, $userData->username,
			sprintf( __( "اطلاعات تراکنش به صورت دستی ویرایش شد . وضعیت : %s - مبلغ : %s - کد رهگیری : %s - تاریخ : %s", "gravityformsIDPay" ),
				$new_status, GFCommon::to_money( $entry["payment_amount"], $entry["currency"] ),
				$payment_transaction, $entry["payment_date"] ) );
	}

	protected static function get_base_url() {
		return plugins_url( null, __FILE__ );
	}

	public static function merge_tags_keys( $form ) {

		if ( GFCommon::is_entry_detail() ) {
			return $form;
		}
		?>

        <script type="text/javascript">
            gform.addFilter('gform_merge_tags', function (mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option) {
                mergeTags['gf_idpay'] = {
                    label: 'آیدی پی',
                    tags: [
                        {tag: '{idpay_payment_result}', label: 'نتیجه پرداخت آیدی پی'}
                    ]
                };
                return mergeTags;
            });
        </script>
		<?php
		return $form;
	}
}
