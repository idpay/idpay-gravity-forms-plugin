<?php
include_once self::get_base_path() . '/lib/scripts.php';
include_once self::get_base_path() . '/lib/styles.php';

//Section Load Necessary Variables
self::setStylePage();
$feedId                = ! rgempty( "IDPay_setting_id" ) ? rgpost( "IDPay_setting_id" ) : absint( rgget( "id" ) );
$idpayConfig           = ! empty( $feedId ) ? IDPayDB::get_feed( $feedId ) : null;
$formId                = ! empty( rgget( 'fid' ) ) ? rgget( 'fid' ) : ( ! empty( $idpayConfig ) ? $idpayConfig["form_id"] : null );
$formName              = self::SearchFormName( $feedId );
$dictionary            = self::loadDictionary( $feedId, $formName );
$isSubmitDataForUpdate = false;
$gfStatusBarMessage    = '';
//End Section

// Section Check Updated Fields And Updating
if ( ! rgempty( "gf_IDPay_submit" ) ) {
	check_admin_referer( "update", "gf_IDPay_feed" );
	$idpayConfig           = self::readDataFromRequest( $idpayConfig );
	$idpayConfig['meta']   = self::makeSafeDataForDb( $idpayConfig );
	$gfStatusBarMessage    = self::generateStatusBarMessage( $formId );
	$isSubmitDataForUpdate = true;
	self::updateConfigAndRedirectPage( $feedId, $idpayConfig );
}
// End Section

//Section Check Security And Validate
$form                = ! empty( $formId ) ? RGFormsModel::get_form_meta( $formId ) : [];
$setUpdatedMessage   = rgget( 'updated' ) == 'true' ? self::makeUpdateMessageBar( $feedId ) : false;
$menu_items          = apply_filters( 'gform_toolbar_menu', GFForms::get_toolbar_menu_items( $formId ), $formId );
$formMeta            = GFFormsModel::get_form_meta( $formId );
$hasPriceFieldInForm = self::checkSetPriceForForm( $form, $formId );
// End Section

// LoadConfigValues
$isPostCreateSuccessPay        = self::dataGet( $idpayConfig, 'meta.post_create_success_payment' );
$isCheckedPostCreateSuccessPay = $isPostCreateSuccessPay == "true" ? "checked='checked'" : "";
$isPostUpdateSuccessPay        = self::dataGet( $idpayConfig, 'meta.post_update_success_payment' );
$isCheckedPostUpdateSuccessPay = $isPostUpdateSuccessPay == "true" ? "checked='checked'" : "";
$isUserRegSuccessPay           = self::dataGet( $idpayConfig, 'meta.user_reg_success_payment' );
$isCheckedUserRegSuccessPay    = $isUserRegSuccessPay == "true" ? "checked='checked'" : "";
$isUserRegNoPay                = self::dataGet( $idpayConfig, 'meta.user_reg_no_payment' );
$isCheckedUserRegNoPay         = $isUserRegNoPay == "true" ? "checked='checked'" : "";
$isUseCustomConfirmation        = self::dataGet( $idpayConfig, 'meta.confirmation' );
$isCheckedUseCustomConfirmation = $isUseCustomConfirmation == "true" ? "checked='checked'" : "";
$description                    = self::dataGet( $idpayConfig, "meta.desc_pm" );
$defaultDescription             = "پرداخت برای فرم شماره {form_id} با عنوان فرم {form_title}";
$descriptionText                = ! empty( $description ) ? $description : $defaultDescription;
$gfSysFieldName_CustomerName    = 'IDPay_customer_field_name';
$selectedCustomerName           = $idpayConfig["meta"]["customer_fields_name"] ?? '';
$customerName                   = self::loadSavedOrDefaultValue( $form, $gfSysFieldName_CustomerName, $selectedCustomerName );
$gfSysFieldName_CustomerEmail   = 'IDPay_customer_field_email';
$selectedCustomerEmail          = $idpayConfig["meta"]["customer_fields_email"] ?? '';
$customerEmail                  = self::loadSavedOrDefaultValue( $form, $gfSysFieldName_CustomerEmail, $selectedCustomerEmail );
$gfSysFieldName_CustomerDesc    = 'IDPay_customer_field_desc';
$selectedCustomerDesc           = $idpayConfig["meta"]["customer_fields_desc"] ?? '';
$customerDesc                   = self::loadSavedOrDefaultValue( $form, $gfSysFieldName_CustomerDesc, $selectedCustomerDesc );
$gfSysFieldName_CustomerMobile  = 'IDPay_customer_field_mobile';
$selectedCustomerMobile         = $idpayConfig["meta"]["customer_fields_mobile"] ?? '';
$customerMobile                 = self::loadSavedOrDefaultValue( $form, $gfSysFieldName_CustomerMobile, $selectedCustomerMobile );
do_action( self::$author . '_gform_gateway_config', $idpayConfig, $form );
do_action( self::$author . '_gform_IDPay_config', $idpayConfig, $form );
// End Section : LoadConfigValues

/* Section FeedFormSelect
  - load all forms to select for define or update this feed
  - And Manage show or hide for display in form for user
*/
$gfFormFeedSelect       = self::generateFeedSelectForm( $formId );
$VisibleFieldFormSelect = $gfFormFeedSelect->visible;
$VisibleConfigForm      = $gfFormFeedSelect->visible == '' ? 'style="display:none !important"' : '';
$optionsForms           = $gfFormFeedSelect->options;
/* End Section */
?>


<div class="wrap gforms_edit_form gf_browser_gecko"></div>
<h2 class="gf_admin_page_title"><?php echo $dictionary->label1 ?>
	<?php if ( ! empty( $formId ) ) { ?>
        <span class="gf_admin_page_subtitle">
            <span class="gf_admin_page_formid"><?php echo $dictionary->label2 ?></span>
            <span class="gf_admin_page_formname"><?php echo $dictionary->label3 ?></span>
        </span>
	<?php } ?>
</h2>

<a class="button add-new-h2" href="admin.php?page=gf_settings&subview=gf_IDPay"
   style="margin:8px 9px;"><?php echo $dictionary->label4 ?>
</a>
<?php if ( $isSubmitDataForUpdate ) {
	echo $gfStatusBarMessage;
} ?>

<div id="gform_tab_group" class="gform_tab_group vertical_tabs">
    <div id="gform_tab_container_<?php echo $formId ?: 1 ?>" class="gform_tab_container">
        <div class="gform_tab_content" id="tab_settings">
            <div id="form_settings" class="gform_panel gform_panel_form_settings">

                <form method="post" action="" id="gform_form_settings">
					<?php wp_nonce_field( "update", "gf_IDPay_feed" ) ?>
                    <input type="hidden" value="<?php echo $feedId ?>" name="IDPay_setting_id"/>

                    <!-- Form Select Form For Feed -->
                    <table <?php echo $VisibleFieldFormSelect ?> class="form-table gforms_form_settings">
                        <tbody>
                        <tr style="">
                            <th><?php echo $dictionary->label5 ?></th>
                            <td>
                                <select id="gf_IDPay_form" name="gf_IDPay_form"
                                        onchange="GF_SwitchFid(jQuery(this).val());">
                                    <!-- Options --><?php echo $optionsForms ?><!-- Options -->
                                </select>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <!-- Form Select Form For Feed -->

                    <!-- Form Other Configs Fields -->
					<?php if ( empty( $hasPriceFieldInForm ) ) { ?>
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

                                    <textarea name="gf_IDPay_desc_pm" type="text" cols="75" rows="5"
                                              id="gf_IDPay_desc_pm"><?php echo $descriptionText ?></textarea>
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
                                           name="gf_IDPay_post_create_success_payment"
                                           id="gf_IDPay_post_create_success_payment"
                                           value="true"/><?php echo $dictionary->label17_5 ?>
                                    <br><br>
                                    <input type="checkbox" <?php echo $isCheckedPostUpdateSuccessPay; ?>
                                           name="gf_IDPay_post_update_success_payment"
                                           id="gf_IDPay_post_update_success_payment"
                                           value="true"/><?php echo $dictionary->label17_6 ?>
                                    <br><br>
                                    <input type="checkbox" <?php echo $isCheckedUserRegSuccessPay; ?>
                                           name="gf_IDPay_user_reg_success_payment"
                                           id="gf_IDPay_user_reg_success_payment"
                                           value="true"/><?php echo $dictionary->label17_7 ?>
                                    <br><br>
                                    <input type="checkbox" <?php echo $isCheckedUserRegNoPay; ?>
                                           name="gf_IDPay_user_reg_no_payment"
                                           id="gf_IDPay_user_reg_no_payment"
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

                                    <input type="checkbox" name="gf_IDPay_confirmation"
                                           id="gf_IDPay_confirmation_true" <?php echo $isCheckedUseCustomConfirmation ?>
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