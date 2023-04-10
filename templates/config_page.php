<script type="text/javascript">
    function GF_SwitchFid(fid) {
        jQuery("#IDPay_wait").show();
        document.location = "?page=gf_IDPay&view=edit&fid=" + fid;
    }
</script>
<style>
    .gforms_form_settings select {width: 180px !important}
    table.gforms_form_settings th { font-weight: 600;line-height: 1.3;font-size: 14px}
    .gfIDPayInvalidProduct{background-color:#FFDFDF; margin-top:4px; margin-bottom:6px;padding:18px; border:1px dotted #C89797;}
</style>

<?php
/* style page */
if (is_rtl()) {
    echo '<style type="text/css">table.gforms_form_settings th {text-align: right !important}</style>';
}
if (! defined('ABSPATH')) {
    exit;
}
wp_register_style('gform_admin_IDPay', GFCommon::get_base_url() . '/assets/css/dist/admin.css');
wp_print_styles(array('jquery-ui-styles', 'gform_admin_IDPay', 'wp-pointer'));
/* style page */

$feedId = !rgempty("IDPay_setting_id") ? rgpost("IDPay_setting_id") : absint(rgget("id"));
$idpayConfig = !empty($feedId) ? IDPay_DB::get_feed($feedId) : null ;
$formId = !empty(rgget('fid')) ? rgget('fid') : (!empty($idpayConfig) ? $idpayConfig["form_id"] : null);
$validForm = !empty($formId) ? true : false;

if ($validForm) {
    $dbFeeds = IDPay_DB::get_feeds();
    $formName = '';
    foreach ((array)$dbFeeds as $dbFeed) {
        if ($dbFeed['id'] == $feedId) {
            $formName = $dbFeed['form_title'];
        }
    }

    $isUpdatedSubmitData = false;
    if (!rgempty("gf_IDPay_submit")) {
        check_admin_referer("update", "gf_IDPay_feed");
        $idpayConfig["form_id"] = absint(rgpost("gf_IDPay_form"));
        $idpayConfig["meta"]["type"] = rgpost("gf_IDPay_type");
        $idpayConfig["meta"]["addon"] = rgpost("gf_IDPay_addon");
        $idpayConfig["meta"]["desc_pm"] = rgpost("gf_IDPay_desc_pm");
        $idpayConfig["meta"]["customer_fields_desc"] = rgpost("IDPay_customer_field_desc");
        $idpayConfig["meta"]["customer_fields_email"] = rgpost("IDPay_customer_field_email");
        $idpayConfig["meta"]["customer_fields_mobile"] = rgpost("IDPay_customer_field_mobile");
        $idpayConfig["meta"]["customer_fields_name"] = rgpost("IDPay_customer_field_name");
        $idpayConfig["meta"]["confirmation"] = rgpost("gf_IDPay_confirmation");
        $safe_data = array();
        foreach ($idpayConfig["meta"] as $key => $val) {
            if (!is_array($val)) {
                $safe_data[$key] = sanitize_text_field($val);
            } else {
                $safe_data[$key] = array_map('sanitize_text_field', $val);
            }
        }
        $idpayConfig["meta"] = $safe_data;

        $idpayConfig = apply_filters(self::$author . '_gform_gateway_save_config', $idpayConfig);
        $idpayConfig = apply_filters(self::$author . '_gform_IDPay_save_config', $idpayConfig);

        $feedId = IDPay_DB::update_feed($feedId, $idpayConfig["form_id"], $idpayConfig["is_active"], $idpayConfig["meta"]);
        $isUpdatedSubmitData = true ;

        if (!headers_sent()) {
            wp_redirect(admin_url('admin.php?page=gf_IDPay&view=edit&id=' . $feedId . '&updated=true'));
            exit;
        } else {
            echo "<script type='text/javascript'>window.onload = function () { top.location.href = '" . admin_url('admin.php?page=gf_IDPay&view=edit&id=' . $feedId . '&updated=true') . "'; };</script>";
            exit;
        }
    }

    $form = !empty($formId) ? RGFormsModel::get_form_meta($formId) : [] ;

    if (rgget('updated') == 'true') {
        $feedId = absint(empty($feedId) && isset($_GET['id']) ? rgget('id') : $feedId);
        $updatedLabel = sprintf(__("فید به روز شد . %sبازگشت به لیست%s . ", "gravityformsIDPay"), "<a href='?page=gf_IDPay'>", "</a>");
        echo '<div class="updated fade" style="padding:6px">' .$updatedLabel . '</div>';
    }

    $menu_items = apply_filters('gform_toolbar_menu', GFForms::get_toolbar_menu_items($formId), $formId);
    $condition_field_ids = array('1' => '');
    $condition_values = array('1' => '');
    $condition_operators = array('1' => 'is');
    $title = '';
    $get_form = GFFormsModel::get_form_meta($formId);
    $current_tab = rgempty('subview', $_GET) ? 'settings' : rgget('subview');
    $current_tab = !empty($current_tab) ? $current_tab : ' ';
    $setting_tabs = GFFormSettings::get_tabs($get_form['id']);
    $has_product = self::checkSetPriceForForm($form);
    $isCheckedSubscription = rgar($idpayConfig['meta'], 'type') == "subscription" ? "checked='checked'" : "";
    $desc_pm = !empty(rgar($idpayConfig["meta"], "desc_pm")) ? rgar($idpayConfig["meta"], "desc_pm") : "پرداخت برای فرم شماره {form_id} با عنوان فرم {form_title}";

    $customerName = '';
    if (!empty($form)) {
        $form_fields = self::get_form_fields($form);
        $selected_field = !empty($idpayConfig["meta"]["customer_fields_name"]) ? $idpayConfig["meta"]["customer_fields_name"] : '';
        $customerName = self::get_mapped_field_list('IDPay_customer_field_name', $selected_field, $form_fields);
    }

    $customerEmail = '';
    if (!empty($form)) {
        $form_fields = self::get_form_fields($form);
        $selected_field = !empty($idpayConfig["meta"]["customer_fields_email"]) ? $idpayConfig["meta"]["customer_fields_email"] : '';
        $customerEmail = self::get_mapped_field_list('IDPay_customer_field_email', $selected_field, $form_fields);
    }

    $customerDesc = '';
    if (!empty($form)) {
        $form_fields = self::get_form_fields($form);
        $selected_field = !empty($idpayConfig["meta"]["customer_fields_desc"]) ? $idpayConfig["meta"]["customer_fields_desc"] : '';
        $customerDesc = self::get_mapped_field_list('IDPay_customer_field_desc', $selected_field, $form_fields);
    }

    $customerMobile = '';
    if (!empty($form)) {
        $form_fields = self::get_form_fields($form);
        $selected_field = !empty($idpayConfig["meta"]["customer_fields_mobile"]) ? $idpayConfig["meta"]["customer_fields_mobile"] : '';
        $customerMobile = self::get_mapped_field_list('IDPay_customer_field_mobile', $selected_field, $form_fields);
    }

    $selectedAddon = rgar($idpayConfig['meta'], 'addon') == "true" ? "checked='checked'" : "";

    do_action(self::$author . '_gform_gateway_config', $idpayConfig, $form);
    do_action(self::$author . '_gform_IDPay_config', $idpayConfig, $form);

    $selectedConfirmation = rgar($idpayConfig['meta'], 'confirmation') == "true" ? "checked='checked'" : "";

    $updateFeedLabel = __("فید به روز شد . %sبازگشت به لیست%s.", "gravityformsIDPay");
    $updatedFeed = sprintf($updateFeedLabel, "<a href='?page=gf_IDPay'>", "</a>");
    $feedHtml =  '<div class="updated fade" style="padding:6px">' . $updatedFeed . '</div>';

    $available_forms = IDPay_DB::get_available_forms();
    $options_forms = '';
    foreach ($available_forms as $current_form) {
        $selected = absint($current_form->id) == $formId ? 'selected="selected"' : '';
        $val = absint($current_form->id);
        $title = esc_html($current_form->title);
        $options_forms = $options_forms . "<option value={$val} {$selected}>{$title}</option>" ;
    }

    $getFormId = empty($formId) ? "style='display:none;'" : "";

/* label And translate Section */
    $domain = "gravityformsIDPay";
    $label1 = translate("پیکربندی درگاه IDPay", $domain);
    $label2 = sprintf(__("فید: %s", "gravityformsIDPay"), $feedId);
    $label3 = sprintf(__("فرم: %s", "gravityformsIDPay"), $formName);
    $label4 = translate("تنظیمات کلی", $domain);
    $label5 = translate("انتخاب فرم", $domain);
    $label6 = translate("یک فرم انتخاب نمایید", $domain);
    $label7 = translate("فرم انتخاب شده هیچ گونه فیلد قیمت گذاری ندارد، لطفا پس از افزودن این فیلدها مجددا اقدام نمایید.", $domain);
    $label8 = translate("User_Registration ثبت نام با", $domain);
    $label9 = translate(' اگر فرم جاری وظیفه ثبت نام کاربر با افزونه ذکر شده را دارد فقط برای پرداخت های موفق انجام دهد', $domain);
    $label10 = translate("توضیحات پرداخت", $domain);
    $label11 = translate("متنی که به عنوان توضیحات به آیدی پی ارسال می شود و میتوانید در داشبورد خود در سایت آیدی پی مشاهده کنید.", $domain);
    $label12 = translate("نام پرداخت کننده", $domain);
    $label13 = translate("ایمیل پرداخت کننده", $domain);
    $label14 = translate("توضیح تکمیلی", $domain);
    $label15 = translate("تلفن همراه پرداخت کننده", $domain);
    $label16 = translate("سازگاری با افزودنی ها", $domain);
    $label17 = translate("برخی افزودنی های گرویتی فرم دارای متد add_delayed_payment_support هستند. در صورتی که میخواهید این افزودنی ها تنها در صورت تراکنش موفق عمل کنند این گزینه را تیک بزنید.", $domain);
    $label18 = translate("استفاده از تاییدیه های فرم", $domain);
    $label19 = translate("به صورت پیش فرض آیدی پی از تاییدیه های گرویتی فرم استفاده نمیکند و پیام خود پلاگین را به عنوان نتیجه پرداخت به کاربر نمایش میدهد.", $domain);
    $label20 = translate("در این صورت شما می توانید از متغیر idpay_payment_result به عنوان نتیجه پرداخت در تاییدیه های گرویتی فرم استفاده کنید.", $domain);
    $label21 = translate("ذخیره", $domain);
/* label And translate Section */

    $isVisibleForm = rgget('id') || rgget('fid') ? 'display:none !important' : '';
}
?>

<?php if ($validForm) { ?>
<div class="wrap gforms_edit_form gf_browser_gecko"></div>
<h2 class="gf_admin_page_title"><?php echo $label1 ?>
    <?php if (!empty($formId)) { ?>
        <span class="gf_admin_page_subtitle">
            <span class="gf_admin_page_formid"><?php echo $label2 ?></span>
            <span class="gf_admin_page_formname"><?php echo $label3 ?></span>
        </span>
    <?php } ?>
</h2>

<a class="button add-new-h2" href="admin.php?page=gf_settings&subview=gf_IDPay" style="margin:8px 9px;"><?php echo $label4 ?></a>
    <?php if ($isUpdatedSubmitData) {
        echo $feedHtml;
    } ?>

<div id="gform_tab_group" class="gform_tab_group vertical_tabs">
    <div id="gform_tab_container_<?php echo $formId ?: 1 ?>" class="gform_tab_container">
        <div class="gform_tab_content" id="tab_<?php echo !empty($current_tab) ? $current_tab : '' ?>">
            <div id="form_settings" class="gform_panel gform_panel_form_settings">

                <form method="post" action="" id="gform_form_settings">
                    <?php wp_nonce_field("update", "gf_IDPay_feed") ?>
                    <input type="hidden" value="<?php echo $feedId ?>" name="IDPay_setting_id" />
                    <table class="form-table gforms_form_settings">
                        <tbody>
                        <tr style="<?php echo $isVisibleForm ?>">
                            <th><?php echo $label5 ?></th>
                            <td>
                                <select id="gf_IDPay_form" name="gf_IDPay_form"
                                        onchange="GF_SwitchFid(jQuery(this).val());">
                                    <option value=""><?php echo $label6 ?></option>
                                    <?php echo $options_forms ?>
                                </select>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <?php if (empty($has_product)) { ?>
                        <div id="gf_IDPay_invalid_product_form" class="gf_IDPay_invalid_form gfIDPayInvalidProduct"><?php echo $label7 ?></div>
                    <?php } else { ?>
                        <table class="form-table gforms_form_settings" <?php echo $getFormId ?> id="IDPay_field_group">
                            <tbody>
                            <tr>
                                <th><?php echo $label8 ?></th>
                                <td>
                                    <input name="gf_IDPay_type" <?php echo $isCheckedSubscription ?>
                                           value="subscription" type="checkbox" id="gf_IDPay_type_subscription"/>

                                    <label for="gf_IDPay_type"></label>
                                    <span class="description"><?php echo $label9 ?></span>
                                </td>
                            </tr>

                            <tr>
                                <th><?php echo $label10 ?></th>
                                <td>
                                    <input name="gf_IDPay_desc_pm" value="<?php echo $desc_pm ?>"
                                           type="text" id="gf_IDPay_desc_pm" class="fieldwidth-1"/>

                                    <span class="description"><?php echo $label11 ?></span>
                                </td>
                            </tr>

                            <tr>
                                <th><?php echo $label12 ?></th>
                                <td class="IDPay_customer_fields_name"><?php echo $customerName ?></td>
                            </tr>

                            <tr>
                                <th><?php echo $label13 ?></th>
                                <td class="IDPay_customer_fields_email"><?php echo $customerEmail; ?></td>
                            </tr>

                            <tr>
                                <th><?php echo $label14 ?></th>
                                <td class="IDPay_customer_fields_desc"><?php  echo  $customerDesc; ?></td>
                            </tr>

                            <tr>
                                <th><?php echo $label15 ?></th>
                                <td class="IDPay_customer_fields_mobile"><?php echo  $customerMobile ; ?></td>
                            </tr>

                            <tr>
                                <th><?php echo $label16 ?></th>
                                <td>
                                    <input type="checkbox" <?php echo $selectedAddon; ?>
                                           name="gf_IDPay_addon" id="gf_IDPay_addon_true"
                                           value="true" />

                                    <label for="gf_IDPay_addon"></label>
                                    <span class="description"><?php echo $label17 ?></span>
                                </td>
                            </tr>

                            <tr>
                                <th><?php echo $label18 ?></th>
                                <td>
                                    <input type="checkbox"  <?php echo $selectedConfirmation ?>
                                           name="gf_IDPay_confirmation"
                                           id="gf_IDPay_confirmation_true" value="true"/>

                                    <label for="gf_IDPay_confirmation"></label>
                                    <span class="description"><?php echo $label19 ?></span>
                                    <p class="description"><?php echo $label20 ?></p>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <input type="submit"  value="<?php echo $label21 ?>"
                                           class="button-primary gfbutton"
                                           name="gf_IDPay_submit"/>
                                </td>
                            </tr>

                            </tbody>
                        </table>
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php } ?>