<script type="text/javascript">
    function DeleteSetting(id) {
        jQuery("#action_argument").val(id);
        jQuery("#action").val("delete");
        jQuery("#confirmation_list_form")[0].submit();
    }

    function ToggleActive(img, feed_id) {
        var is_active = img.src.indexOf("active1.png") >= 0;
        if (is_active) {
            img.src = img.src.replace("active1.png", "active0.png");
            jQuery(img).attr('title', '<?php _e("درگاه غیر فعال است", "gravityformsIDPay") ?>').attr('alt', '<?php _e("درگاه غیر فعال است", "gravityformsIDPay") ?>');
        } else {
            img.src = img.src.replace("active0.png", "active1.png");
            jQuery(img).attr('title', '<?php _e("درگاه فعال است", "gravityformsIDPay") ?>').attr('alt', '<?php _e("درگاه فعال است", "gravityformsIDPay") ?>');
        }
        var mysack = new sack(ajaxurl);
        mysack.execute = 1;
        mysack.method = 'POST';
        mysack.setVar("action", "gf_IDPay_update_feed_active");
        mysack.setVar("gf_IDPay_update_feed_active", "<?php echo wp_create_nonce("gf_IDPay_update_feed_active") ?>");
        mysack.setVar("feed_id", feed_id);
        mysack.setVar("is_active", is_active ? 0 : 1);
        mysack.onError = function () {
            alert('<?php _e("خطای Ajax رخ داده است", "gravityformsIDPay") ?>')
        };
        mysack.runAJAX();
        return true;
    }
</script>

<?php
self::checkSupportedGravityVersion();
$operation = self::checkSubmittedOperation();
$dictionary = self::loadDictionary('', '');
$addNewHtml =  "<a class='add-new-h2' href='admin.php?page=gf_IDPay&view=edit'>افزودن جدید</a>";
$addOption = get_option("gf_IDPay_configured") == true ? $addNewHtml : '';
$list_action = wp_nonce_field('list_action', 'gf_IDPay_list');
$settings = IDPay_DB::get_feeds();
$addFeedOption = !get_option("gf_IDPay_configured") ? "<tr><td colspan='5' style='padding:20px;'>{$dictionary->label31}</td></tr>" : '';
$checkSettingsExits = is_array($settings) && sizeof($settings) > 0 ? true : false;
$checkSettingsNotExits = is_array($settings) && sizeof($settings) > 0 ? '' :"<tr><td colspan='5' style='padding:20px;'>شما هیچ فید مشخصی با آیدی پی ندارید . با افزودن جدید یکی بسازید</td></tr>";
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
            if ($checkSettingsExits) {
                foreach ($settings as $setting) {
                    $settingId = $setting["id"];
                    $imageOption = (object) self::getStatusFeedImage($setting);
                    ?>
                    <tr class='author-self status-inherit'>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="feed[]" value="<?php echo $settingId ?>"/>
                        </th>

                        <td>
                            <img style="cursor:pointer;width:25px" src="<?php $imageOption->image ?>"
                                 alt="<?php $imageOption->active ?>" title="<?php $imageOption->active ?>"
                                 onclick="ToggleActive(this, <?php echo $settingId ?>);"/>
                        </td>

                        <td><?php echo $setting["id"] ?></td>
                        <td class="column-date">
                            <?php echo self::getTypeFeed($setting); ?>
                        </td>

                        <td>
                            <strong>
                                <a class="row-title"
                                       href="admin.php?page=gf_IDPay&view=edit&id=<?php echo $setting["id"] ?>"
                                       title="<?php _e("تنظیم مجدد درگاه", "gravityformsIDPay") ?>">
                                    <?php echo $setting["form_title"] ?></a>
                            </strong>
                        </td>

                        <td>
                            <span><a title="<?php _e("ویرایش فید", "gravityformsIDPay") ?>"
                                     href="admin.php?page=gf_IDPay&view=edit&id=<?php echo $setting["id"] ?>">
                                    <?php _e("ویرایش فید", "gravityformsIDPay") ?>
                                </a>|</span>
                            <span><a title="<?php _e("حذف فید", "gravityformsIDPay") ?>"
                                     href="javascript: DeleteSetting(<?php echo $settingId ?>);">حذف
                                </a>|</span>
                            <span><a title="<?php _e("ویرایش فرم", "gravityformsIDPay") ?>"
                                     href="admin.php?page=gf_edit_forms&id=<?php echo $setting["form_id"] ?>">
                                    <?php _e("ویرایش فرم", "gravityformsIDPay") ?>
                                </a>|</span>
                            <span><a title="<?php _e("مشاهده صندوق ورودی", "gravityformsIDPay") ?>"
                                     href="admin.php?page=gf_entries&view=entries&id=<?php echo $setting["form_id"] ?>">
                                    <?php _e("صندوق ورودی", "gravityformsIDPay") ?>
                                </a>|</span>
                            <span><a title="<?php _e("نمودارهای فرم", "gravityformsIDPay") ?>"
                                     href="admin.php?page=gf_IDPay&view=stats&id=<?php echo $setting["form_id"] ?>">
                                    <?php _e("نمودارهای فرم", "gravityformsIDPay") ?>
                                </a></span>
                        </td>
                    </tr>
                <?php }
            } ?>
                <!-- End ForEach -->
          <?php echo $checkSettingsNotExits; ?>
            </tbody>
        </table>
    </form>
</div>