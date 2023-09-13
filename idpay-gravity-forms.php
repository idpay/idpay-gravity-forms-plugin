<?php

/**
 * Plugin Name: IDPay For Wp Gravity Forms
 * Author: IDPay
 * Description: IDPay is Secure Payment Gateway For Wp Gravity Forms.
 * Version: 2.0.0
 * Author URI: https://idpay.ir
 * Author Email: info@idpay.ir
 * Text Domain: idpay-gravity-forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const IDPAY_PLUGIN_CLASS = 'GF_Gateway_IDPay';
register_activation_hook( __FILE__, [ IDPAY_PLUGIN_CLASS, "addPermission" ] );
register_deactivation_hook( __FILE__, [ IDPAY_PLUGIN_CLASS, "deactivation" ] );
add_action('init', [ IDPAY_PLUGIN_CLASS, 'init']);

require_once( 'lib/IDPayDB.php' );
require_once( 'lib/Helpers.php' );
require_once( 'lib/JDate.php' );
require_once( 'lib/IDPayPayment.php' );
require_once( 'lib/IDPayVerify.php' );
require_once( 'lib/IDPayOperation.php' );
require_once( 'lib/IDPayView.php' );

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
			add_action( 'admin_notices', [ IDPayOperation::class, 'reportPreRequiredPersianGravityForm' ] );
			return false;
		}

		if ( ! IDPayOperation::checkApprovedGravityFormVersion() ) {
			 add_action( 'admin_notices', [ IDPayOperation::class, 'reportPreRequiredGravityForm' ] );
			return false;
		}

		add_filter( 'members_get_capabilities', [ IDPayOperation::class, "MembersCapabilities" ] );
        $adminPermission = IDPayOperation::PERMISSION_ADMIN;

		if ( is_admin() && IDPayOperation::hasPermission($adminPermission)  ) {

			add_action( 'wp_ajax_gf_IDPay_update_feed_active', [ IDPayDB::class, 'SaveOrUpdateFeed' ] );
			add_filter( 'gform_addon_navigation', [ IDPayView::class, 'addIdpayToNavigation' ] );
			add_action( 'gform_entry_info', [ __CLASS__, 'payment_entry_detail' ], 4, 2 );
			add_action( 'gform_after_update_entry', [ __CLASS__, 'update_payment_entry' ], 4, 2 );

			if ( get_option( "gf_IDPay_configured" ) ) {
				add_filter( 'gform_form_settings_menu', [ IDPayView::class, 'addIdpayToToolbar' ], 10, 2 );
				add_action( 'gform_form_settings_page_IDPay', [ IDPayView::class, 'route' ] );
			}

			if ( rgget( "page" ) == "gf_settings" ) {
                $handler = [ IDPayView::class, 'viewSetting' ];
				RGForms::add_settings_page(
					[
						'name'      => 'gf_IDPay',
						'tab_label' => $dictionary->labelSettingsTab,
						'title'     => $dictionary->labelSettingsTitle,
						'handler'   => $handler,
					]
				);
			}

			if ( Helpers::checkCurrentPageForIDPAY() ) {
				wp_enqueue_script( 'sack' );
				IDPayOperation::setup();
			}

		}

		if ( get_option( "gf_IDPay_configured" ) ) {
			add_filter( "gform_disable_post_creation", [ __CLASS__, "setDelayedActivity" ], 10, 3 );
			add_filter( "gform_is_delayed_pre_process_feed", [ __CLASS__, "setDelayedGravityAddons" ], 10, 4 );
			add_filter( "gform_confirmation", [ IDPayPayment::class, "doPayment" ], 1000, 4 );
			add_action( 'wp', [ IDPayVerify::class, 'doVerify' ], 5 );
			add_filter( "gform_submit_button", [ IDPayView::class, "renderButtonSubmitForm" ], 10, 2 );
		}

		add_filter( "gform_logging_supported", [ __CLASS__, "setLogSystem" ] );
		add_filter( 'gf_payment_gateways', [ __CLASS__, 'setDefaultSys' ], 2 );
		do_action( 'gravityforms_gateways' );
		do_action( 'setDefaultSys' );
		add_filter( 'gform_admin_pre_render', [ __CLASS__, 'preRenderScript' ] );

		return 'completed';
	}

	public static function addPermission() {
        IDPayOperation::addPermission();
	}

	public static function deactivation() {
		IDPayOperation::deactivation();
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

	public static function setDefaultSys( $form, $entry ) {
		$dictionary = Helpers::loadDictionary('','');
		$baseClass = __CLASS__;
		$author    = self::$author;
		$class     = "{$baseClass}|{$author}";
		$IDPay     = [
                        'class' => $class,
                        'title' => $dictionary->labelIdpay,
                        'param' => [
                            'email'  =>  $dictionary->labelEmail,
                            'mobile' =>  $dictionary->labelMobile,
                            'desc'   =>  $dictionary->labelDesc,
			        ]
		      ];

		return apply_filters( self::$author . '_gf_IDPay_detail',
			apply_filters( self::$author . '_gf_gateway_detail', $IDPay, $form, $entry )
			, $form, $entry );
	}

	public static function setLogSystem( $plugins ) {
		$plugins[ basename( dirname( __FILE__ ) ) ] = "IDPay";

		return $plugins;
	}

	public static function preRenderScript( $form )
	{

		if ( GFCommon::is_entry_detail() ) {
			return $form;
		}
		?>

        <script type="text/javascript">
            gform.addFilter('gform_merge_tags',
                function (mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option) {
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

	public static function getHeaders() {
		GFFormSettings::page_header();
    }

    /* -------------------------------------- Not Refactore ---------------------------------------- */

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

	/* -------------------------------------- Not Refactore ---------------------------------------- */

}
