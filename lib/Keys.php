<?php

class Keys
{
// GLOBAL KEYS

	public const VERSION = "2.0.0";
	public const AUTHOR = "IDPay";
	public const MIN_GRAVITY_VERSION = "1.9.10";
	public const PLUGIN_FOLDER = "idpay-gravity-forms-plugin";

	// PAYMENT KEYS
	public const NO_PAYMENT = "NO_PAYMENT";
	public const SUCCESS_PAYMENT = "SUCCESS_PAYMENT";


	// DB KEYS
	public const FEEDS = 'getFeeds';
	public const TRANSACTIONS = 'getTransactions';
	public const QUERY_TRANSACTIONS = 'QUERY_TRANSACTIONS';
	public const QUERY_ANALYTICS = 'QUERY_ANALYTICS';
	public const QUERY_COUNT_TRANSACTION = 'QUERY_COUNT_TRANSACTION';
	public const QUERY_COUNT_FEED = 'QUERY_COUNT_FEED';
	public const QUERY_DELETE_IDPAY = 'QUERY_DELETE_IDPAY';
	public const QUERY_FEED_BY_ID = 'QUERY_FEED_BY_ID';
	public const QUERY_DELETE_FEED = 'QUERY_DELETE_FEED';
	public const QUERY_FEED = 'QUERY_FEED';
	public const QUERY_FEEDS = 'QUERY_FEEDS';
	public const QUERY_FORM = 'QUERY_FORM';

	// VIEW KEYS
	public const VIEW_CONFIG = "config";
	public const VIEW_TRANSACTION = "transactions";
	public const VIEW_FEEDS = "index";
	public const VIEW_SETTING = "setting";

	// OPERATION KEYS
	public const PERMISSION_ADMIN = "gfIDPayAdmin";
	public const PERMISSION_UNISTALL = "gfIDPayUninstall";
	public const SETTING_PAGE_URL = '?page=gf_settings&subview=gf_IDPay';
	public const GRAVITY_MAIN_PAGE_URL = '?page=gf_edit_forms';
	public const IDPAY_PLUGIN_FILE = 'idpay-gravity-forms.php';

	public const OLD_GLOBAL_KEY_VERSION = 'gf_IDPay_version';
	public const OLD_GLOBAL_KEY_VERSION_BACKUP = 'gf_IDPay_version_backup';
	public const OLD_GLOBAL_KEY_ENABLE = 'gf_IDPay_configured';
	public const OLD_GLOBAL_KEY_ENABLE_BACKUP = 'gf_IDPay_configured_backup';
	public const OLD_GLOBAL_KEY_IDPAY = "gf_IDPay_settings_backup";
	public const KEY_IDPAY = "gf_IDPay_settings";


	public const STATE_NO_CHANGED = 'NO_CHANGED';
	public const STATE_UPGRADE = 'UPGRADE';
	public const STATE_NO_CONFIGURED = 'NOT-CONFIGURED';
}