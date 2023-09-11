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
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->labelRow ?></th>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label55 ?></th>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label56 ?></th>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label57 ?></th>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label58 ?></th>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label59 ?></th>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label60 ?></th>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label61 ?></th>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label62 ?></th>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label63 ?></th>
                <th scope="col" class="manage-column Cw100"><?php echo $dictionary->label64 ?></th>
            </tr>
            </thead>
            <tbody class="list:user user-list">
            <?php
            if ($checkDataExists) {
                $counter = 1 ;

                foreach ($data as $row) {
                    $created = Helpers::getJalaliDateTime(Helpers::dataGet($row, 'date_created', '-'));
                    $paymentDate = Helpers::getJalaliDateTime(Helpers::dataGet($row, 'payment_date', '-'));
                    $status = Helpers::dataGet($row, 'payment_status', '-');
                    $colorStatus = Helpers::makeStatusColor($status);

                    ?>
                    <tr class='author-self status-inherit'>
                        <td><?php echo $counter ?></td>
                        <td><?php echo Helpers::dataGet($row, 'id', '-') ?></td>
                        <td><?php echo Helpers::dataGet($row, 'form_id', '-') ?></td>
                        <td><?php echo Helpers::dataGet($row, 'transaction_id', '-') ?></td>
                        <td style="color:darkgreen"><?php echo Helpers::dataGet($row, 'payment_amount', '-') ?></td>
                        <td><?php echo Helpers::dataGet($row, 'currency', '-') ?></td>
                        <td <?php echo $colorStatus ?>><?php echo $status ?></td>
                        <td><?php echo Helpers::dataGet($row, 'payment_method', '-') ?></td>
                        <td><?php echo Helpers::dataGet($row, 'source_url', '-') ?></td>
                        <td><?php echo $created ?></td>
                        <td><?php echo $paymentDate ?></td>
                    </tr>

                    <?php
                    $counter = $counter + 1 ;
                }
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