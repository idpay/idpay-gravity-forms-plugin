<?php

class IDPayOperation
{
    public static $author = "IDPay";
	public static $version = "1.0.5";

	public static function setup() {
		if ( get_option( "gf_IDPay_version" ) != self::$version ) {
			IDPayDB::upgrade();
			update_option( "gf_IDPay_version", self::$version );
		}
	}

	public static function checkSubmittedUnistall()
	{
		$dictionary = Helpers::loadDictionary('', '');
		if (rgpost("uninstall")) {
			check_admin_referer("uninstall", "gf_IDPay_uninstall");
			self::uninstall();
			echo  "<div class='updated fade C11'>{$dictionary->label34}</div>";
		}
	}

	public static function uninstall() {
		$dictionary = Helpers::loadDictionary('','');
		$plugin = basename( dirname( __FILE__ ) ) . "/index.php";
		$condition = ! self::hasPermission( "gravityforms_IDPay_uninstall" );
		$value = [ $plugin => time() ] + (array) get_option( 'recently_activated' );
		if ($condition) {
			die($dictionary->labelDontPermission);
		}
		IDPayDB::dropTable();
		delete_option( "gf_IDPay_settings" );
		delete_option( "gf_IDPay_configured" );
		delete_option( "gf_IDPay_version" );
		deactivate_plugins( $plugin );
		update_option( 'recently_activated', $value );
	}

	public static function deactivation() {
		delete_option( "gf_IDPay_version" );
	}

	public static function reportPreRequiredPersianGravityForm() {
		$dictionary = Helpers::loadDictionary('','');
		$adminUrl = admin_url( "plugin-install.php?tab=plugin-information&plugin=persian-gravity-forms&TB_iframe=true&width=772&height=884" );
		$html = "<a href='{$adminUrl}'>{$dictionary->labelHintPersianGravity}</a>";
		$class   = 'notice notice-error';
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $html );
	}

	public static function reportPreRequiredGravityForm() {
		$class   = 'notice notice-error';
		$message = sprintf( __( "درگاه IDPay نیاز به گرویتی فرم نسخه %s به بالا دارد. برای بروز رسانی هسته گرویتی فرم به %sسایت گرویتی فرم فارسی%s مراجعه نمایید .", "gravityformsIDPay" ), self::$min_gravityforms_version, "<a href='http://gravityforms.ir/11378' target='_blank'>", "</a>" );
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
	}

	public static function hasPermission( $permission = 'gravityforms_IDPay' ) {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			include( ABSPATH . "wp-includes/pluggable.php" );
		}

		return GFCommon::current_user_can_any( $permission );
	}



}
