<?php
self::prepareFrontEndTools();
self::checkSupportedGravityVersion();

$operation                  = self::checkSubmittedOperation();
$dictionary                 = self::loadDictionary( '', '' );
$addNewHtml                 = "<a class='add-new-h2' href='admin.php?page=gf_IDPay&view=edit'>افزودن جدید</a>";
$addOption                  = get_option( "gf_IDPay_configured" ) == true ? $addNewHtml : '';
$list_action                = wp_nonce_field( 'list_action', 'gf_IDPay_list' );
$addFeedOption              = ! get_option( "gf_IDPay_configured" ) ?
	"<tr><td colspan='5' 
        style='padding:20px;'>{$dictionary->label31}</td></tr>" : '';
$checkSettingsNotExistsHtml = "<tr><td colspan='5' 
        style='padding:20px;'>شما هیچ فید مشخصی با آیدی پی ندارید . با افزودن جدید یکی بسازید</td></tr>";

/* Load Data And Pagination Section */
$pagination            = self::loadPagination( IDPayDB::METHOD_FEEDS );
$settings              = IDPayDB::getFeeds( $pagination );
$checkSettingsExits    = is_array( $settings ) && sizeof( $settings ) > 0;
$checkSettingsNotExits = is_array( $settings ) && sizeof( $settings ) > 0 ? '' : $checkSettingsNotExistsHtml;
/* Load Data And Pagination Section */

?>

<?php echo $operation ?>
<div class="wrap">
    <h2>
		<?php echo $dictionary->label22 ?>
		<?php echo $addOption ?>
        <a class="button button-primary" style="text-align: center;display: inline-block;margin: 0;"
           href="admin.php?page=gf_settings&subview=gf_IDPay"><?php echo $dictionary->label26 ?></a>
    </h2>

    <form id="confirmation_list_form" method="post">
		<?php echo $list_action ?>
        <input type="hidden" id="action" name="action"/>
        <input type="hidden" id="action_argument" name="action_argument"/>
        <div class="tablenav">
            <div class="alignleft actions" style="padding:8px 0 7px 0;">
                <label class="hidden" for="bulk_action"><?php echo $dictionary->label23 ?></label>
                <select name="bulk_action" id="bulk_action">
                    <option value=''><?php echo $dictionary->label24 ?></option>
                    <option value='delete'><?php echo $dictionary->label25 ?></option>
                </select>
                <input type="submit" class="button" value="اعمال"/>

                <button class='button' disabled style="color : black !important;">
					<?php echo $dictionary->labelCountFeed . $pagination->query->count; ?>
                </button>

            </div>
        </div>


        <table class="wp-list-table widefat fixed striped toplevel_page_gf_edit_forms">
            <thead>
            <tr>
                <th scope="col" id="cb" class="manage-column column-cb check-column"
                    style="padding:13px 3px;width:30px"><input type="checkbox"/></th>
                <th scope="col" id="active" class="manage-column" style="width:50px">
					<?php echo $dictionary->label27 ?></th>
                <th scope="col" class="manage-column" style="width:65px">
					<?php echo $dictionary->label28 ?></th>
                <th scope="col" class="manage-column" style="width:100px"><?php echo $dictionary->label29 ?></th>
                <th scope="col" class="manage-column" style="width:100px"><?php echo $dictionary->label30 ?></th>
                <th scope="col" class="manage-column"><?php echo $dictionary->label32 ?></th>
            </tr>
            </thead>
            <tbody class="list:user user-list">
			<?php
			echo $addFeedOption;
			if ( $checkSettingsExits ) {
				foreach ( $settings as $setting ) {
					$settingId     = $setting["id"];
					$settingFormId = $setting["form_id"];
					$imageOption   = (object) self::getStatusFeedImage( $setting );
					?>
                    <tr class='author-self status-inherit'>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="feed[]" value="<?php echo $settingId ?>"/>
                        </th>

                        <td>
                            <img style="cursor:pointer;width:25px" src="<?php echo $imageOption->image ?>"
                                 alt="<?php echo $imageOption->active ?>" title="<?php echo $imageOption->active ?>"
                                 onclick="ToggleActive(this, <?php echo $settingId ?>);"/>
                        </td>

                        <td><?php echo $settingId ?></td>
                        <td class="column-date">
							<?php echo self::getTypeFeed( $setting ); ?>
                        </td>

                        <td>
                            <strong>
                                <a class="row-title"
                                   href="admin.php?page=gf_IDPay&view=edit&id=<?php echo $settingId ?>"
                                   title="<?php _e( "تنظیم مجدد درگاه", "gravityformsIDPay" ) ?>">
									<?php echo $setting["form_title"] ?></a>
                            </strong>
                        </td>

                        <td>
                            <span><a title="<?php echo $dictionary->label33 ?>"
                                     href="admin.php?page=gf_IDPay&view=edit&id=<?php echo $settingId ?>">
                                    <?php echo $dictionary->label33 ?>
                                </a>|</span>
                            <span><a title="<?php echo $dictionary->label34 ?>"
                                     href="javascript: DeleteSetting(<?php echo $settingId ?>);">
                                    <?php echo $dictionary->label34 ?>
                                </a>|</span>
                            <span><a title="<?php echo $dictionary->label35 ?>"
                                     href="admin.php?page=gf_edit_forms&id=<?php echo $settingFormId ?>">
                                    <?php echo $dictionary->label35 ?>
                                </a>|</span>
                            <span><a title="<?php echo $dictionary->label36 ?>"
                                     href="admin.php?page=gf_entries&view=entries&id=<?php echo $settingFormId ?>">
                                   <?php echo $dictionary->label36 ?>
                                </a>|</span>
                            <span><a title="<?php echo $dictionary->label37 ?>"
                                     href="admin.php?page=gf_IDPay&view=stats&id=<?php echo $settingFormId ?>">
                                    <?php echo $dictionary->label37 ?>
                                </a></span>
                        </td>
                    </tr>
				<?php }
			} ?>
            <!-- End ForEach -->
			<?php echo $checkSettingsNotExits; ?>
            </tbody>
        </table>
		<?php echo $pagination->html; ?>
        <br>
        <div style='display: flex;justify-content: center'>
            <button class='button' style="color : black !important;" disabled>
				<?php echo $dictionary->labelCountFeed . $pagination->query->count; ?>
            </button>
        </div>
    </form>
</div>