<?php

class IDPayOperation extends Helpers {

	public static function setup() {
		$state = IDPayOperation::getStateIfPluginHasChanged();

		if ($state == Helpers::STATE_UPGRADE) {
			IDPayOperation::levelUpGlobalSetting();
		}

		if ($state == Helpers::STATE_NO_CONFIGURED) {
			IDPayOperation::redirectToSettingPage();
		}

	}

	public static function levelUpGlobalSetting() {

		$oldVersion = IDPayOperation::BackupAndUpgradeGlobalKey(
			Helpers::OLD_GLOBAL_KEY_VERSION,
			Helpers::OLD_GLOBAL_KEY_VERSION_BACKUP);

		$oldEnabled = IDPayOperation::BackupAndUpgradeGlobalKey(
			Helpers::OLD_GLOBAL_KEY_ENABLE,
			Helpers::OLD_GLOBAL_KEY_ENABLE_BACKUP);

		$oldSetting = IDPayOperation::BackupAndUpgradeGlobalKey(
			Helpers::KEY_IDPAY,
			Helpers::OLD_GLOBAL_KEY_IDPAY);

		if($oldEnabled != null){

			// save new setting structure

			IDPayOperation::upgrade();
		}
		else{
			IDPayOperation::redirectToSettingPage();
		}
	}

	public static function BackupAndUpgradeGlobalKey($key,$backupKey) {
		$oldValue = Helpers::getGlobalKey( $key );
		Helpers::setGlobalKey( $backupKey,$oldValue );
		Helpers::deleteGlobalKey( $key );
		return $oldValue;
	}


	public static function redirectToSettingPage() {
		$settingPageUrl = Helpers::SETTING_PAGE_URL;
		if(! str_contains( $_SERVER['REQUEST_URI'], Helpers::SETTING_PAGE_URL )) {
			wp_redirect( admin_url( "admin.php{$settingPageUrl}" ) );
		}
	}
	

	public static function getStateIfPluginHasChanged() {

		$setting = Helpers::getGlobalKey(Helpers::KEY_IDPAY);
		$isConfigured        = Helpers::dataGet( $setting, 'enable',false ) == false;

		// condition removed gf_IDPay_version
		if(IDPayOperation::checkNeedToUpgradeVersion($setting)){
			return Helpers::STATE_UPGRADE;
		}

		// set new style with off
		if($isConfigured){
			return Helpers::STATE_NO_CONFIGURED;
		}
			return Helpers::STATE_NO_CHANGED;

	}


	public static function upgrade() {
		IDPayDB::upgrade();
	}

	public static function uninstall() {
		$dictionary = Helpers::loadDictionary();
		$basePath   = Helpers::PLUGIN_FOLDER;
		$fileName   = Helpers::IDPAY_PLUGIN_FILE;
		$plugin     = "{$basePath}/{$fileName}";
		$condition  = ! IDPayOperation::hasPermission( Helpers::PERMISSION_UNISTALL );
		$value      = [ $plugin => time() ] + (array) Helpers::getGlobalKey( 'recently_activated' );
		if ( $condition ) {
			die( $dictionary->labelDontPermission );
		}
		IDPayDB::dropTable();
		Helpers::deleteGlobalKey( Helpers::KEY_IDPAY );
		deactivate_plugins( $plugin );
		Helpers::setGlobalKey( 'recently_activated', $value );
		$gravityMainPageUrl = Helpers::GRAVITY_MAIN_PAGE_URL;
		wp_redirect( admin_url( "admin.php{$gravityMainPageUrl}" ) );
	}

	public static function checkSubmittedUnistall() {
		$dictionary = Helpers::loadDictionary();
		if ( rgpost( "uninstall" ) ) {
			check_admin_referer( "uninstall", "gf_IDPay_uninstall" );
			IDPayOperation::uninstall();
			echo "<div class='updated fade C11'>{$dictionary->label34}</div>";
		}
	}


	public static function deactivation() {
		// correct But waiting to final //
		/*
		IDPayOperation::rollbackOldVersionSetting();
		*/
	}

	public static function rollbackOldVersionSetting() {
		// correct But waiting to final //
		/*
		update_option( Helpers::OLD_GLOBAL_KEY_VERSION, '1.0.5' );
		update_option( "gf_IDPay_configured", true );
		*/
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
		$html       = sprintf( $html, IDPayOperation::MIN_GRAVITY_VERSION );
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
				$wp_roles->add_cap( $role, Helpers::PERMISSION_ADMIN );
				$wp_roles->add_cap( $role, Helpers::PERMISSION_UNISTALL );
			}
		}
	}

	public static function MembersCapabilities( $caps ) {
		$existsPermissions = [ Helpers::PERMISSION_ADMIN, Helpers::PERMISSION_UNISTALL ];

		return array_merge( $caps, $existsPermissions );
	}

	public static function checkApprovedGravityFormVersion(): bool {
		$condition1 = class_exists( "GFCommon" );
		$condition2 = (bool) version_compare( GFCommon::$version, IDPayOperation::MIN_GRAVITY_VERSION, ">=" );

		return $condition1 && $condition2;
	}
}
