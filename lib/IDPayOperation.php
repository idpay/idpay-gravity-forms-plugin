<?php

class IDPayOperation extends Helpers {

	public static function setup() {
		$state = IDPayOperation::getStateIfPluginHasChanged();


		if ( $state == Keys::STATE_UPGRADE ) {
			IDPayOperation::levelUpGlobalKeyAndSetting();
			IDPayOperation::levelUpDatabase();
			IDPayOperation::redirectToSettingPage();
		}

		if ( $state == Keys::STATE_NO_CONFIGURED ) {
			IDPayOperation::redirectToSettingPage();
		}

	}

	public static function levelUpGlobalKeyAndSetting() {

		$oldVersion = IDPayOperation::makeBackupGlobalKey(
			Keys::OLD_GLOBAL_KEY_VERSION,
			Keys::OLD_GLOBAL_KEY_VERSION_BACKUP );

		$oldEnabled = IDPayOperation::makeBackupGlobalKey(
			Keys::OLD_GLOBAL_KEY_ENABLE,
			Keys::OLD_GLOBAL_KEY_ENABLE_BACKUP );

		$oldSetting = IDPayOperation::makeBackupGlobalKey(
			Keys::KEY_IDPAY,
			Keys::OLD_GLOBAL_KEY_IDPAY );

		IDPayOperation::levelUpSetting( $oldSetting, $oldEnabled );
	}

	public static function levelUpDatabase() {
		IDPayDB::upgrade();
		if ( IDPayDB::checkMetaOldColumnExist() == 0 ) {
			IDPayDB::addMetaColumn();
			IDPayDB::makeBackupMetaColumn();
			IDPayOperation::levelUpFeed();
		}
	}

	public static function levelUpFeed() {
		$feeds = IDPayDB::getAllFeeds();
		foreach ( $feeds as $feed ) {
			$id      = Helpers::dataGet( $feed, 'id' );
			$formId  = Helpers::dataGet( $feed, 'form_id' );
			$oldMeta = Helpers::dataGet( $feed, 'meta' );
			$meta    = [
				"description"         => Helpers::dataGet( $oldMeta, 'desc_pm' ),
				"payment_description" => Helpers::dataGet( $oldMeta, 'customer_fields_desc' ),
				"payment_email"       => Helpers::dataGet( $oldMeta, 'customer_fields_email' ),
				"payment_mobile"      => Helpers::dataGet( $oldMeta, 'customer_fields_mobile' ),
				"payment_name"        => Helpers::dataGet( $oldMeta, 'customer_fields_name' ),
				"confirmation"        => Helpers::dataGet( $oldMeta, 'confirmation' ),
				"addon"               => [
					"post_create"       => [
						"success_payment" => (bool) ! empty( Helpers::dataGet( $oldMeta, 'addon' ) ),
						"no_payment"      => false,
					],
					"post_update"       => [
						"success_payment" => (bool) ! empty( Helpers::dataGet( $oldMeta, 'addon' ) ),
						"no_payment"      => false,
					],
					"user_registration" => [
						"success_payment" => (bool) Helpers::dataGet( $oldMeta, 'type' ) == 'subscription',
						"no_payment"      => (bool) Helpers::dataGet( $oldMeta, 'type' ) != 'subscription',
					],
				],
			];
			IDPayDB::updateFeed( $id, $formId, $meta );
		}
	}


	public static function levelUpSetting( $oldSetting, $oldEnabled ) {
		$setting = [
			"enable"  => ! empty( $oldEnabled ) ? 'on' : '',
			"name"    => Helpers::dataGet( $oldSetting, 'gname' ),
			"api_key" => Helpers::dataGet( $oldSetting, 'api_key' ),
			"sandbox" => Helpers::dataGet( $oldSetting, 'sandbox' ),
			"version" => Keys::VERSION,
		];
		Helpers::setGlobalKey( Keys::KEY_IDPAY, $setting );
	}


	public static function levelDownGlobalKeyAndSetting() {

		$oldVersion = IDPayOperation::makeRestoreGlobalKey(
			Keys::OLD_GLOBAL_KEY_VERSION,
			Keys::OLD_GLOBAL_KEY_VERSION_BACKUP );

		$oldEnabled = IDPayOperation::makeRestoreGlobalKey(
			Keys::OLD_GLOBAL_KEY_ENABLE,
			Keys::OLD_GLOBAL_KEY_ENABLE_BACKUP );

		$oldSetting = IDPayOperation::makeRestoreGlobalKey(
			Keys::KEY_IDPAY,
			Keys::OLD_GLOBAL_KEY_IDPAY );
	}

	public static function levelDownFeed() {
		IDPayDB::makeRestoreMetaColumn();
		IDPayDB::deleteMetaColumn();
	}

	public static function makeRestoreGlobalKey( $key, $backupKey ) {
		$newValue = Helpers::getGlobalKey( $backupKey );
		Helpers::setGlobalKey( $key, $newValue );
		Helpers::deleteGlobalKey( $backupKey );

		return $newValue;
	}

	public static function makeBackupGlobalKey( $key, $backupKey ) {
		$oldValue = Helpers::getGlobalKey( $key );
		Helpers::setGlobalKey( $backupKey, $oldValue );
		Helpers::deleteGlobalKey( $key );

		return $oldValue;
	}

	public static function redirectToSettingPage() {
		$settingPageUrl = Keys::SETTING_PAGE_URL;
		if ( ! str_contains( $_SERVER['REQUEST_URI'], Keys::SETTING_PAGE_URL ) ) {
			wp_redirect( admin_url( "admin.php{$settingPageUrl}" ) );
		}
	}

	public static function getStateIfPluginHasChanged() {

		$setting      = Helpers::getGlobalKey( Keys::KEY_IDPAY );
		$isConfigured = Helpers::dataGet( $setting, 'enable', false ) == false;

		if ( IDPayOperation::checkNeedToUpgradeVersion( $setting ) ) {
			return Keys::STATE_UPGRADE;
		}

		return $isConfigured ? Keys::STATE_NO_CONFIGURED : Keys::STATE_NO_CHANGED;
	}

	public static function uninstall() {
		$dictionary = Helpers::loadDictionary();
		$condition  = ! IDPayOperation::hasPermission( Keys::PERMISSION_UNISTALL );
		$plugin     = IDPayOperation::getPluginManifest();
		if ( $condition ) {
			die( $dictionary->labelDontPermission );
		}
		IDPayDB::dropTable();
		IDPayOperation::disablePlugin( $plugin );
		IDPayOperation::setSystemLog( $plugin );
		IDPayOperation::removeAllGlobalKey();
		IDPayOperation::levelDownFeed();
		IDPayOperation::redirectToGravityMainPage();
	}

	public static function redirectToGravityMainPage() {
		$gravityMainPageUrl = Keys::GRAVITY_MAIN_PAGE_URL;
		wp_redirect( admin_url( "admin.php{$gravityMainPageUrl}" ) );
	}

	public static function getPluginManifest() {
		$basePath = Keys::PLUGIN_FOLDER;
		$fileName = Keys::IDPAY_PLUGIN_FILE;

		return "{$basePath}/{$fileName}";
	}

	public static function disablePlugin( $plugin ) {
		deactivate_plugins( $plugin );
	}

	public static function setSystemLog( $plugin ) {
		$value = [ $plugin => time() ] + (array) Helpers::getGlobalKey( 'recently_activated' );
		Helpers::setGlobalKey( 'recently_activated', $value );
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

		IDPayOperation::levelDownGlobalKeyAndSetting();
		IDPayOperation::levelDownFeed();
	}

	public static function removeAllGlobalKey() {
		Helpers::deleteGlobalKey( Keys::OLD_GLOBAL_KEY_VERSION );
		Helpers::deleteGlobalKey( Keys::OLD_GLOBAL_KEY_VERSION_BACKUP );
		Helpers::deleteGlobalKey( Keys::OLD_GLOBAL_KEY_ENABLE );
		Helpers::deleteGlobalKey( Keys::OLD_GLOBAL_KEY_ENABLE_BACKUP );
		Helpers::deleteGlobalKey( Keys::OLD_GLOBAL_KEY_IDPAY );
		Helpers::deleteGlobalKey( Keys::KEY_IDPAY );
	}

	public static function reportPreRequiredPersianGravityForm() {
		$dictionary = Helpers::loadDictionary();
		$url        = Keys::PLUGIN_INSTALL_URL;
		$adminUrl   = admin_url( $url );
		$html       = "<a href='{$adminUrl}'>{$dictionary->labelHintPersianGravity}</a>";
		$class      = 'notice notice-error';
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $html );
	}

	public static function reportPreRequiredGravityForm() {
		$dictionary = Helpers::loadDictionary();
		$html       = "<a href='https://gravityforms.ir/11378' target='_blank'>";
		$html       .= "{$dictionary->labelHintGravity}</a>";
		$html       = sprintf( $html, Keys::MIN_GRAVITY_VERSION );
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
				$wp_roles->add_cap( $role, Keys::PERMISSION_ADMIN );
				$wp_roles->add_cap( $role, Keys::PERMISSION_UNISTALL );
			}
		}
	}

	public static function MembersCapabilities( $caps ) {
		$existsPermissions = [ Keys::PERMISSION_ADMIN, Keys::PERMISSION_UNISTALL ];

		return array_merge( $caps, $existsPermissions );
	}

	public static function checkApprovedGravityFormVersion(): bool {
		$condition1 = class_exists( "GFCommon" );
		$condition2 = (bool) version_compare( GFCommon::$version, Keys::MIN_GRAVITY_VERSION, ">=" );

		return $condition1 && $condition2;
	}
}
