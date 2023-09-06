<?php
Helpers::prepareFrontEndTools();
Helpers::checkSupportedGravityVersion();

$dictionary        = Helpers::loadDictionary('', '');
$html            = [ 'A' => "<tr><td colspan='5' style='padding:20px;'>{$dictionary->transNotExists}</td></tr>"];

/* Load Data And Pagination Section */
$filters = (object) ['formId' => rgget('id') ?? 0];
$feeds                 = IDPayDB::getWithPaginate(IDPayDB::TRANSACTIONS, $filters);
$data                  = $feeds->data;
$checkDataExists       = ! empty($data) && count($data) > 0;
$checkSettingsNotExits = ! ( $checkDataExists ) ? $html['A']  : '' ;
/* Load Data And Pagination Section */
?>

<div class="wrap">
    <h2>
        <?php echo $dictionary->label37 ?>
        <a class="button button-primary C1"
           href="admin.php?page=gf_IDPay"><?php echo $dictionary->back ?></a>
    </h2>

    <form id="confirmation_list_form" method="post">
        <div class="tablenav">
            <div class="alignleft actions C2">
                <button class='button C6'>
                    <?php echo $dictionary->labelCountTrans . $feeds->query->count; ?>
                </button>
            </div>
        </div>


        <table class="wp-list-table widefat fixed striped toplevel_page_gf_edit_forms">
            <thead>
            <tr>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label29 ?></th>
            </tr>
            </thead>
            <tbody class="list:user user-list">
            <?php
            if ($checkDataExists) {
                foreach ($data as $setting) {
                    $settingId     = $setting["id"];
                    $settingFormId = $setting["form_id"];
                    ?>
                    <tr class='author-self status-inherit'>
                        <td><?php echo $settingId ?></td>
                        <td><strong></strong></td>
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
                <?php echo $dictionary->labelCountTrans . $feeds->query->count; ?>
            </button>
        </div>
    </form>
</div>