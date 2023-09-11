<?php

class IDPayOperation
{
    public static $author = "IDPay";
	public static $version = "1.0.5";
	public static $min_gravityforms_version = "1.9.10";
	public const PERMISSION_ADMIN = "gfIDPayAdmin";
	public const PERMISSION_UNISTALL = "gfIDPayUninstall";


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
		$condition = ! self::hasPermission( self::PERMISSION_UNISTALL );
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
		$url = "plugin-install.php?tab=plugin-information&plugin=persian-gravity-forms&TB_iframe=true&width=772&height=884";
		$adminUrl = admin_url( $url );
		$html = "<a href='{$adminUrl}'>{$dictionary->labelHintPersianGravity}</a>";
		$class   = 'notice notice-error';
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $html );
	}

	public static function reportPreRequiredGravityForm() {
		$dictionary = Helpers::loadDictionary('','');
		$html = "<a href='https://gravityforms.ir/11378' target='_blank'>{$dictionary->labelHintGravity}</a>";
		$html = sprintf($html,self::$min_gravityforms_version);
		$class   = 'notice notice-error';
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $html );
	}

	public static function hasPermission( $permission  ) {
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

			if ($condition1 || $condition2) {
				$wp_roles->add_cap( $role, self::PERMISSION_ADMIN );
				$wp_roles->add_cap( $role, self::PERMISSION_UNISTALL );
			}
		}
	}

	public static function MembersCapabilities($caps) {
		$existsPermissions = [ self::PERMISSION_ADMIN, self::PERMISSION_UNISTALL ];
		return array_merge( $caps, $existsPermissions);
	}

	public static function checkApprovedGravityFormVersion(): bool
	{
		$condition1 = class_exists("GFCommon");
		$condition2 = (bool) version_compare(GFCommon::$version, self::$min_gravityforms_version, ">=");

		return $condition1 && $condition2;
	}



}
