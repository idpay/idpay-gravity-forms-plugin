<?php

class IDPayOperation {
	public static $author = "IDPay";
	public static $version = "1.0.5";
	public static $min_gravityforms_version = "1.9.10";
	public const PERMISSION_ADMIN = "gfIDPayAdmin";
	public const PERMISSION_UNISTALL = "gfIDPayUninstall";
	public const SETTING_PAGE_URL = '?page=gf_settings&subview=gf_IDPay';
	public const GRAVITY_MAIN_PAGE_URL = '?page=gf_edit_forms';
	public const IDPAY_PLUGIN_FILE = 'idpay-gravity-forms.php';
	public const STATE_NO_CHANGED = 'NO_CHANGED';
	public const STATE_UPGRADE = 'UPGRADE';
	public const STATE_NO_CONFIGURED = 'NOT-CONFIGURED';


	public static function setup() {
		$state = IDPayOperation::getStateIfPluginHasChanged();

		if ($state == self::STATE_UPGRADE) {
			self::levelUpGlobalSetting();
		}

		if ($state == self::STATE_NO_CONFIGURED) {
			$settingPageUrl = self::SETTING_PAGE_URL;
			if(! str_contains( $_SERVER['REQUEST_URI'], self::SETTING_PAGE_URL )) {
				wp_redirect( admin_url( "admin.php{$settingPageUrl}" ) );
			}
		}

	}

	public static function levelUpGlobalSetting(  ) {
		
	}
	

	public static function getStateIfPluginHasChanged() {

		$hasOldVersionKey = Helpers::getGlobalKey('gf_IDPay_version') != null;
		$setting = Helpers::getGlobalKey(Helpers::KEY_IDPAY);
		$isConfigured        = Helpers::dataGet( $setting, 'enable',false ) == false;
		$version        = Helpers::dataGet( $setting, 'version' );

		// condition removed gf_IDPay_version
		if($hasOldVersionKey || $version != self::$version){
			return self::STATE_UPGRADE;
		}

		// set new style with off
		if($isConfigured){
			return self::STATE_NO_CONFIGURED;
		}
			return self::STATE_NO_CHANGED;

	}


	public static function upgrade() {
		IDPayDB::upgrade();
	}

	public static function uninstall() {
		$dictionary = Helpers::loadDictionary();
		$basePath   = Helpers::PLUGIN_FOLDER;
		$fileName   = self::IDPAY_PLUGIN_FILE;
		$plugin     = "{$basePath}/{$fileName}";
		$condition  = ! self::hasPermission( self::PERMISSION_UNISTALL );
		$value      = [ $plugin => time() ] + (array) Helpers::getGlobalKey( 'recently_activated' );
		if ( $condition ) {
			die( $dictionary->labelDontPermission );
		}
		IDPayDB::dropTable();
		Helpers::deleteGlobalKey( Helpers::KEY_IDPAY );
		deactivate_plugins( $plugin );
		Helpers::setGlobalKey( 'recently_activated', $value );
		$gravityMainPageUrl = self::GRAVITY_MAIN_PAGE_URL;
		wp_redirect( admin_url( "admin.php{$gravityMainPageUrl}" ) );
	}

	public static function checkSubmittedUnistall() {
		$dictionary = Helpers::loadDictionary();
		if ( rgpost( "uninstall" ) ) {
			check_admin_referer( "uninstall", "gf_IDPay_uninstall" );
			self::uninstall();
			echo "<div class='updated fade C11'>{$dictionary->label34}</div>";
		}
	}


	public static function deactivation() {
		IDPayOperation::rollbackOldVersionSetting();
	}

	public static function rollbackOldVersionSetting() {
		update_option( 'gf_IDPay_version', '1.0.5' );
		update_option( "gf_IDPay_configured", true );
	}

	public static function reportPreRequiredPersianGravityForm() {
		$dictionary = Helpers::loadDictionary();
		$url        = "plugin-install.php?tab=plugin-information&plugin=persian-gravity-forms&TB_iframe=true&width=772&height=884";
		$adminUrl   = admin_url( $url );
		$html       = "<a href='{$adminUrl}'>{$dictionary->labelHintPersianGravity}</a>";
		$class      = 'notice notice-error';
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $html );
	}

	public static function reportPreRequiredGravityForm() {
		$dictionary = Helpers::loadDictionary();
		$html       = "<a href='https://gravityforms.ir/11378' target='_blank'>{$dictionary->labelHintGravity}</a>";
		$html       = sprintf( $html, self::$min_gravityforms_version );
		$class      = 'notice notice-error';
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $html );
	}

	public static function hasPermission( $permission ) {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			include( ABSPATH . "wp-includes/pluggable.php" );
		}

		return GFCommon::current_user_can_any( $permission );
	}

	public static function addPermission() {
		global $wp_roles;
		foreach ( get_editable_roles() as $role => $details ) {
			$condition1 = $role == 'administrator';
			$condition2 = in_array( 'gravityforms_edit_forms', $details['capabilities'] );

			if ( $condition1 || $condition2 ) {
				$wp_roles->add_cap( $role, self::PERMISSION_ADMIN );
				$wp_roles->add_cap( $role, self::PERMISSION_UNISTALL );
			}
		}
	}

	public static function MembersCapabilities( $caps ) {
		$existsPermissions = [ self::PERMISSION_ADMIN, self::PERMISSION_UNISTALL ];

		return array_merge( $caps, $existsPermissions );
	}

	public static function checkApprovedGravityFormVersion(): bool {
		$condition1 = class_exists( "GFCommon" );
		$condition2 = (bool) version_compare( GFCommon::$version, self::$min_gravityforms_version, ">=" );

		return $condition1 && $condition2;
	}
}
