<?php
Helpers::prepareFrontEndTools();
Helpers::checkSupportedGravityVersion();

/* Load Dictionary And Check Submitted */
$operation         = Helpers::checkSubmittedOperation();
$dictionary        = Helpers::loadDictionary('', '');
$html            = [
  'A' =>  "<a class='add-new-h2' href='admin.php?page=gf_IDPay&view=edit'>{$dictionary->add}</a>",
  'B' => "<tr><td colspan='5' style='padding:20px;'>{$dictionary->label31}</td></tr>",
  'C' => "<tr><td colspan='5' style='padding:20px;'>{$dictionary->feedNotExists}</td></tr>",
];
$addOption         = get_option("gf_IDPay_configured") == true ? $html['A'] : '';
$list_action       = wp_nonce_field('list_action', 'gf_IDPay_list');
$addFeedOption     = ! get_option("gf_IDPay_configured") ? $html['B'] : '';
/* Load Dictionary And Check Submitted */


/* Load Data And Pagination Section */
$filters               = (object) [];
$feeds                 = IDPayDB::getWithPaginate(IDPayDB::FEEDS, $filters);
$data                  = $feeds->data;
$checkDataExists       = ! empty($data) && count($data) > 0;
$checkSettingsNotExits = ! ( $checkDataExists ) ? $html['C']  : '' ;
/* Load Data And Pagination Section */
?>

<?php echo $operation ?>
<div class="wrap">
    <h2>
        <?php echo $dictionary->label22 ?>
        <?php echo $addOption ?>
        <a class="button button-primary C1" href="admin.php?page=gf_settings&subview=gf_IDPay"
        ><?php echo $dictionary->label26 ?></a>
    </h2>

    <form id="confirmation_list_form" method="post">
        <?php echo $list_action ?>
        <input type="hidden" id="action" name="action"/>
        <input type="hidden" id="action_argument" name="action_argument"/>
        <div class="tablenav">
            <div class="alignleft actions C2">
                <label class="hidden" for="bulk_action"><?php echo $dictionary->label23 ?></label>
                <select name="bulk_action" id="bulk_action">
                    <option value=''><?php echo $dictionary->label24 ?></option>
                    <option value='delete'><?php echo $dictionary->label25 ?></option>
                </select>
                <input type="submit" class="button" value="اعمال"/>
                <button class='button' disabled style="color : black !important;">
                    <?php echo $dictionary->labelCountFeed . $feeds->query->count; ?>
                </button>
            </div>
        </div>


        <table class="wp-list-table widefat fixed striped toplevel_page_gf_edit_forms">
            <thead>
            <tr>
                <th scope="col" id="cb" class="manage-column column-cb check-column C3">
                    <input type="checkbox"/></th>
                <th scope="col" id="active" class="manage-column Cw50">
                    <?php echo $dictionary->label27 ?></th>
                <th scope="col" class="manage-column Cw65">
                    <?php echo $dictionary->label28 ?></th>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label29 ?></th>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label30 ?></th>
                <th scope="col" class="manage-column"><?php echo $dictionary->label32 ?></th>
            </tr>
            </thead>
            <tbody class="list:user user-list">

            <?php
            echo $addFeedOption;
            if ($checkDataExists) {
                foreach ($data as $setting) {
                    $settingId     = $setting["id"];
                    $settingFormId = $setting["form_id"];
                    $imageOption   = Helpers::getStatusFeedImage($setting);
                    ?>
                    <tr class='author-self status-inherit'>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="feed[]" value="<?php echo $settingId ?>"/>
                        </th>

                        <td>
                            <img class="C4 Cw25" src="<?php echo $imageOption->image ?>"
                                 alt="<?php echo $imageOption->active ?>"
                                 title="<?php echo $imageOption->active ?>"
                                 onclick="ToggleActive(this, <?php echo $settingId ?>);"/>
                        </td>

                        <td><?php echo $settingId ?></td>
                        <td class="column-date">
                            <?php echo Helpers::getTypeFeed($setting); ?>
                        </td>

                        <td>
                            <strong>
                                <a class="row-title"
                                   href="admin.php?page=gf_IDPay&view=edit&id=<?php echo $settingId ?>"
                                   title="<?php echo $dictionary->label55 ?>">
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
        <?php echo $feeds->meta; ?>
        <br>
        <div class="C5">
            <button class='button C6' disabled>
                <?php echo $dictionary->labelCountFeed . $feeds->query->count; ?>
            </button>
        </div>
    </form>
</div>