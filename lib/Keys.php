<?php

class Keys {

	// GLOBAL KEYS
	public const VERSION = "2.0.0";
	public const AUTHOR = "IDPay";
	public const NONE_GATEWAY = "NoGateway";
	public const MIN_GRAVITY_VERSION = "1.9.10";
	public const VERIFY_COMPLETED = "VERIFY_COMPLETED";
	public const TRANSACTION_FINAL_STATE = 1;
	public const TRANSACTION_IN_PROGRESS_STATE = 0;

	public const PLUGIN_FOLDER = "idpay-gravity-forms-plugin";
	public const PLUGIN_INSTALL_URL = "plugin-install.php?tab=plugin-information&plugin=persian-gravity-forms&" .
	                                  "TB_iframe=true&width=772&height=884";

	// PAYMENT KEYS
	public const NO_PAYMENT = "NO_PAYMENT";
	public const SUCCESS_PAYMENT = "SUCCESS_PAYMENT";

	// PAYMENT KEYS
	public const VERIFY_SUCCESS = 100;
	public const PAYMENT_SUCCESS = 10;


	// STEP KEYS Purchase
	public const TYPE_PURCHASE = "TYPE_PURCHASE";
	public const TYPE_FREE = "TYPE_FREE";
	public const TYPE_REJECTED = "TYPE_REJECTED";

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
	public const QUERY_ALL_FEEDS = 'QUERY_ALL_FEEDS';
	public const QUERY_FORM = 'QUERY_FORM';
	public const QUERY_CHECK_META_COLUMN = 'QUERY_CHECK_META_COLUMN';
	public const QUERY_ADD_META_COLUMN = 'QUERY_ADD_META_COLUMN';
	public const QUERY_DELETE_META_COLUMN = 'QUERY_DELETE_META_COLUMN';
	public const QUERY_CLONE_META_COLUMN = 'QUERY_CLONE_META_COLUMN';

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

	// Section State
	public const STATE_NO_CHANGED = 'NO_CHANGED';
	public const STATE_UPGRADE = 'UPGRADE';
	public const STATE_NO_CONFIGURED = 'NOT-CONFIGURED';

	// Section Css
	public const CSS_NOTE_STYLE = "font-weight: bold;font-size: 16px;font-family: monospace;";

	public const CSS_MESSAGE_STYLE = 'font-weight: bold;direction: rtl;text-align:center;font-size: 20px;' .
	                                 'font-family: monospace;padding-top: 10px;padding-bottom: 10px;';

	public const CSS_CONFIRMATION_STYLE = 'direction:rtl;padding: 20px;background-color:%s;color: white;' .
	                                      'opacity: 0.83;transition: opacity 0.6s;margin-bottom: 15px;';

	// Section Hooks
	public const HOOK_1 = Keys::AUTHOR . '_gateway_return_url';
	public const HOOK_2 = Keys::AUTHOR . '_IDPay_return_url';
	public const HOOK_3 = 'gf_gateway_request_return';
	public const HOOK_4 = 'gf_IDPay_request_return';
	public const HOOK_5 = Keys::AUTHOR . '_gform_gateway_save_config';
	public const HOOK_6 = Keys::AUTHOR . '_gform_IDPay_save_config';
	public const HOOK_7 = Keys::AUTHOR . '_gf_gateway_get_config_by_entry';
	public const HOOK_8 = Keys::AUTHOR . '_gf_IDPay_get_config_by_entry';
	public const HOOK_9 = Keys::AUTHOR . '_gf_gateway_config';
	public const HOOK_10 = Keys::AUTHOR . '_gf_IDPay_config';
	public const HOOK_11 = Keys::AUTHOR . '_gateway_get_order_total';
	public const HOOK_12 = Keys::AUTHOR . '_IDPay_get_order_total';
	public const HOOK_13 = Keys::AUTHOR . '_gf_gateway_verify';
	public const HOOK_14 = Keys::AUTHOR . '_gf_IDPay_verify';
	public const HOOK_15 = 'gf_user_registration_slug';
	public const HOOK_16 = Keys::AUTHOR . '_gf_gateway_get_active_configs';
	public const HOOK_17 = Keys::AUTHOR . '_gf_IDPay_get_active_configs';
	public const HOOK_18 = Keys::AUTHOR . '_gform_custom_gateway_desc_';
	public const HOOK_19 = Keys::AUTHOR . '_gform_IDPay_gateway_desc_';
	public const HOOK_20 = 'gf_gateway_request_add_entry';
	public const HOOK_21 = Keys::AUTHOR . '_gform_custom_gateway_price';
	public const HOOK_22 = Keys::AUTHOR . '_gform_custom_gateway_price_';
	public const HOOK_23 = Keys::AUTHOR . '_gform_custom_IDPay_price';
	public const HOOK_24 = Keys::AUTHOR . '_gform_custom_IDPay_price_';
	public const HOOK_25 = Keys::AUTHOR . '_gform_gateway_price';
	public const HOOK_26 = Keys::AUTHOR . '_gform_gateway_price_';
	public const HOOK_27 = Keys::AUTHOR . '_gform_IDPay_price';
	public const HOOK_28 = Keys::AUTHOR . '_gform_IDPay_price_';
	public const HOOK_29 = Keys::AUTHOR . '_gform_form_gateway_price';
	public const HOOK_30 = Keys::AUTHOR . '_gform_form_gateway_price_';
	public const HOOK_31 = Keys::AUTHOR . '_gform_form_IDPay_price';
	public const HOOK_32 = Keys::AUTHOR . '_gform_form_IDPay_price_';
	public const HOOK_33 = Keys::AUTHOR . '_gform_gateway_price';
	public const HOOK_34 = Keys::AUTHOR . '_gform_gateway_price_';
	public const HOOK_35 = Keys::AUTHOR . '_gform_IDPay_price';
	public const HOOK_36 = Keys::AUTHOR . '_gform_IDPay_price_';
	public const HOOK_37 = Keys::AUTHOR . '_gf_rand_transaction_id';
	public const HOOK_38 = 'gform_post_payment_status';
	public const HOOK_39 = 'gform_post_payment_status_';
	public const HOOK_40 = 'gform_IDPay_fulfillment';
	public const HOOK_41 = 'gform_gateway_fulfillment';
	public const HOOK_42 = 'gform_idpay_fulfillment';
	public const HOOK_43 = 'gform_toolbar_menu';
	public const HOOK_44 = Keys::AUTHOR . '_gform_gateway_config';
	public const HOOK_45 = Keys::AUTHOR . '_gform_IDPay_config';
}