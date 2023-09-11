<?php
//Section Load Necessary Variables
Helpers::prepareFrontEndTools();
Helpers::setStylePage();
$isSubmitDataForUpdate = false;
$gfStatusBarMessage    = '';
$fId                   = rgget('fid');
$settingId             = rgempty("IDPay_setting_id");
$feedId                = ! $settingId ? rgpost("IDPay_setting_id") : absint(rgget("id"));
$config                = ! empty($feedId) ? IDPayDB::getFeed($feedId) : null;
$formId                = Helpers::calcFormId($fId, $config);
$form                  = ! empty($formId) ? IDPayDB::getForm($formId) : null;
$formTitle             = ! empty($form) ? $form['form_title'] : '';
$dictionary            = Helpers::loadDictionary($feedId, $formTitle);
//End Section

// Section Check Updated Fields And Updating
if (! rgempty("gf_IDPay_submit")) {
    check_admin_referer("update", "gf_IDPay_feed");
    $config                = Helpers::readDataFromRequest($config);
    $gfStatusBarMessage    = Helpers::generateStatusBarMessage($formId);
    $isSubmitDataForUpdate = true;
    Helpers::updateConfigAndRedirectPage($feedId, $config);
}
// End Section

//Section Check Security And Validate
$form                = ! empty($formId) ? RGFormsModel::get_form_meta($formId) : [];
$setUpdatedMessage   = rgget('updated') == 'true' && Helpers::makeUpdateMessageBar($feedId);
$menu_items          = apply_filters('gform_toolbar_menu', GFForms::get_toolbar_menu_items($formId), $formId);
$formMeta            = GFFormsModel::get_form_meta($formId);
$hasPriceFieldInForm = Helpers::checkSetPriceForForm($form, $formId);
// End Section

// LoadConfigValues
$gfSysFieldName_CustomerName   = 'IDPay_payment_name';
$gfSysFieldName_CustomerEmail  = 'IDPay_payment_email';
$gfSysFieldName_CustomerDesc   = 'IDPay_payment_description';
$gfSysFieldName_CustomerMobile = 'IDPay_payment_mobile';
$defaultDescription            = "پرداخت برای فرم شماره {form_id} با عنوان فرم {form_title}";
$isPostCreateSuccessPay        = Helpers::dataGet($config, 'meta.addon.post_create.success_payment');
$isPostUpdateSuccessPay        = Helpers::dataGet($config, 'meta.addon.post_update.success_payment');
$isUserRegSuccessPay           = Helpers::dataGet($config, 'meta.addon.user_registration.success_payment');
$isUserRegNoPay                = Helpers::dataGet($config, 'meta.addon.user_registration.no_payment');
$isUseCustomConfirmation       = Helpers::dataGet($config, 'meta.confirmation');
$description                   = Helpers::dataGet($config, 'meta.description');
$selectedCustomerName          = Helpers::dataGet($config, 'meta.payment_name');
$selectedCustomerEmail         = Helpers::dataGet($config, 'meta.payment_email');
$selectedCustomerDesc          = Helpers::dataGet($config, 'meta.payment_description');
$selectedCustomerMobile        = Helpers::dataGet($config, 'meta.payment_mobile');
$customerName                  = Helpers::getVal($form, $gfSysFieldName_CustomerName, $selectedCustomerName);
$customerEmail                 = Helpers::getVal($form, $gfSysFieldName_CustomerEmail, $selectedCustomerEmail);
$customerDesc                  = Helpers::getVal($form, $gfSysFieldName_CustomerDesc, $selectedCustomerDesc);
$customerMobile                = Helpers::getVal($form, $gfSysFieldName_CustomerMobile, $selectedCustomerMobile);

$isCheckedPostCreateSuccessPay  = $isPostCreateSuccessPay == true ? "checked='checked'" : "";
$isCheckedPostUpdateSuccessPay  = $isPostUpdateSuccessPay == true ? "checked='checked'" : "";
$isCheckedUserRegSuccessPay     = $isUserRegSuccessPay == true ? "checked='checked'" : "";
$isCheckedUserRegNoPay          = $isUserRegNoPay == true ? "checked='checked'" : "";
$isCheckedUseCustomConfirmation = $isUseCustomConfirmation == "true" ? "checked='checked'" : "";
$descriptionText                = ! empty($description) ? $description : $defaultDescription;
do_action(Helpers::$author . '_gform_gateway_config', $config, $form);
do_action(Helpers::$author . '_gform_IDPay_config', $config, $form);
// End Section : LoadConfigValues

/* Section FeedFormSelect
  - load all forms to select for define or update this feed
  - And Manage show or hide for display in form for user
*/
$gfFormFeedSelect       = Helpers::generateFeedSelectForm($formId);
$VisibleFieldFormSelect = $gfFormFeedSelect->visible;
$VisibleConfigForm      = $gfFormFeedSelect->visible == '' ? 'style="display:none !important"' : '';
$optionsForms           = $gfFormFeedSelect->options;
/* End Section */
?>


<div class="wrap gforms_edit_form gf_browser_gecko"></div>
<h2 class="gf_admin_page_title">
    <?php echo $dictionary->label1 ?>
    <?php if (! empty($formId)) { ?>
        <span class="gf_admin_page_subtitle">
            <span class="gf_admin_page_formid"><?php echo $dictionary->label3 ?></span>
            <span class="gf_admin_page_formid"><?php echo $dictionary->label2 ?></span>
        </span>
    <?php } ?>
</h2>

<a class="button add-new-h2" href="admin.php?page=gf_settings&subview=gf_IDPay"
   style="margin:8px 9px;"><?php echo $dictionary->label4 ?>
</a>
<?php if ($isSubmitDataForUpdate) {
    echo $gfStatusBarMessage;
} ?>

<div id="gform_tab_group" class="gform_tab_group vertical_tabs">
    <div id="gform_tab_container_<?php echo $formId ?: 1 ?>" class="gform_tab_container">
        <div class="gform_tab_content" id="tab_settings">
            <div id="form_settings" class="gform_panel gform_panel_form_settings">

                <form method="post" action="" id="gform_form_settings">
                    <?php wp_nonce_field("update", "gf_IDPay_feed") ?>
                    <input type="hidden" value="<?php echo $feedId ?>" name="IDPay_setting_id"/>

                    <!-- Form Select Form For Feed -->
                    <table <?php echo $VisibleFieldFormSelect ?> class="form-table gforms_form_settings">
                        <tbody>
                        <tr style="">
                            <th><?php echo $dictionary->label5 ?></th>
                            <td>
                                <select id="IDPay_formId" name="IDPay_formId"
                                        onchange="GF_SwitchFid(jQuery(this).val());">
                                    <!-- Options --><?php echo $optionsForms ?><!-- Options -->
                                </select>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <!-- Form Select Form For Feed -->

                    <!-- Form Other Configs Fields -->
                    <?php if (empty($hasPriceFieldInForm)) { ?>
                        <div class="gf_IDPay_invalid_form gfIDPayInvalidProduct" id="gf_IDPay_invalid_product_form">
                            <?php echo $dictionary->label7 ?></div>
                    <?php } else { ?>
                        <table <?php echo $VisibleConfigForm ?>
                                class="form-table gforms_form_settings"
                                id="IDPay_field_group">
                            <tbody>

                            <tr>
                                <th><br><br><?php echo $dictionary->label12 ?><br><br></th>
                                <td class="IDPay_customer_fields_name">
                                    <br><span class="description"><?php echo $dictionary->labelSelectGravity ?></span>
                                    <br><br>
                                    <?php echo $customerName ?>
                                    <hr>
                                </td>
                            </tr>

                            <tr>
                                <th><br><br><?php echo $dictionary->label13 ?><br><br></th>
                                <td class="IDPay_customer_fields_email">
                                    <br><span class="description"><?php echo $dictionary->labelSelectGravity ?></span>
                                    <br><br>
                                    <?php echo $customerEmail; ?>
                                    <hr>
                                </td>
                            </tr>

                            <tr>
                                <th><br><br><?php echo $dictionary->label14 ?><br><br></th>
                                <td class="IDPay_customer_fields_desc">
                                    <br><span class="description"><?php echo $dictionary->labelSelectGravity ?></span>
                                    <br><br>
                                    <?php echo $customerDesc; ?>
                                    <hr>
                                </td>
                            </tr>

                            <tr>
                                <th><br><br><?php echo $dictionary->label15 ?><br><br></th>
                                <td class="IDPay_customer_fields_mobile">
                                    <br><span class="description"><?php echo $dictionary->labelSelectGravity ?></span>
                                    <br><br>
                                    <?php echo $customerMobile; ?>
                                    <hr>
                                </td>
                            </tr>

                            <tr>
                                <th><br><br><?php echo $dictionary->label10 ?><br><br></th>
                                <td>
                                    <hr>
                                    <br><span class="description"><?php echo $dictionary->label11 ?></span>
                                    <br><span class="description"><?php echo $dictionary->label11_2 ?></span>
                                    <br><span class="description"><?php echo $dictionary->label11_3 ?></span>
                                    <br><br>

                                    <textarea name="IDPay_description" type="text" cols="75" rows="5"
                                              id="IDPay_description"><?php echo $descriptionText ?></textarea>
                                    <hr>
                                </td>
                            </tr>

                            <tr>
                                <th><br><br><?php echo $dictionary->label16 ?><br><br></th>
                                <td>
                                    <br><span class="description"><?php echo $dictionary->label17 ?></span>
                                    <br><span class="description"><?php echo $dictionary->label17_2 ?></span>
                                    <br><span class="description"><?php echo $dictionary->label17_4 ?></span>
                                    <br><br>
                                    <input type="checkbox" <?php echo $isCheckedPostCreateSuccessPay; ?>
                                           name="IDPay_addon_post_create_success_payment"
                                           id="IDPay_addon_post_create_success_payment"
                                           value="true"/><?php echo $dictionary->label17_5 ?>
                                    <br><br>
                                    <input type="checkbox" <?php echo $isCheckedPostUpdateSuccessPay; ?>
                                           name="IDPay_addon_post_update_success_payment"
                                           id="IDPay_addon_post_update_success_payment"
                                           value="true"/><?php echo $dictionary->label17_6 ?>
                                    <br><br>
                                    <input type="checkbox" <?php echo $isCheckedUserRegSuccessPay; ?>
                                           name="IDPay_addon_user_reg_success_payment"
                                           id="IDPay_addon_user_reg_success_payment"
                                           value="true"/><?php echo $dictionary->label17_7 ?>
                                    <br><br>
                                    <input type="checkbox" <?php echo $isCheckedUserRegNoPay; ?>
                                           name="IDPay_addon_user_reg_no_payment"
                                           id="IDPay_addon_user_reg_no_payment"
                                           value="true"/><?php echo $dictionary->label17_8 ?>
                                    <hr>
                                </td>
                            </tr>


                            <tr>
                                <th><br><?php echo $dictionary->label18 ?><br><br></th>
                                <td>
                                    <br><span class="description"><?php echo $dictionary->label19 ?></span>
                                    <br><span class="description"><?php echo $dictionary->label19_2 ?></span>
                                    <br><span class="description"><?php echo $dictionary->label19_3 ?></span>
                                    <br><br>

                                    <input type="checkbox" name="IDPay_payment_confirmation"
                                           id="IDPay_payment_confirmation" <?php echo $isCheckedUseCustomConfirmation ?>
                                           value="true"/>

                                    <hr>
                                    <br>
                                    <br><span class="description"><?php echo $dictionary->label19_4 ?></span>
                                    <br><span class="description"><?php echo $dictionary->label19_5 ?></span>
                                    <br><span class="description"><?php echo $dictionary->label19_6 ?></span>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <input value="<?php echo $dictionary->label21 ?>" type="submit"
                                           name="gf_IDPay_submit" class="button-primary gfbutton"/>
                                </td>
                            </tr>

                            </tbody>
                        </table>
                        <!-- Form Other Configs Fields -->
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>
</div>