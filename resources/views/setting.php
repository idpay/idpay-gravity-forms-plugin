<?php
Helpers::prepareFrontEndTools();
IDPayOperation::checkSubmittedUnistall();

$settings     = Helpers::checkSubmittedConfigDataAndLoadSetting();
$dictionary   = Helpers::loadDictionary('', '');

$condition1 = ! empty($_POST);
$condition2 = isset($_GET['subview']) && $_GET['subview'] == 'gf_IDPay' && isset($_GET['updated']);
$isActive     = get_option("gf_IDPay_configured") ? "checked='checked'" : "";
$gatewayName  = gform_tooltip('gateway_name');
$title        = sanitize_text_field(rgar($settings, 'gname'));
$gatewayTitle = $title ? sanitize_text_field($settings["gname"]) : 'IDPay';
$apiKey       = sanitize_text_field(rgar($settings, 'api_key'));
$isActive2    = rgar($settings, 'sandbox') ? "checked='checked'" : "";
$uninstallHtml = '<input class="button" type="submit" name="uninstall" value="%s" onclick="return confirm(%s%s%s);" />';
$uninstallHtml = sprintf($uninstallHtml, $dictionary->label52, "'", $dictionary->label53, "'");
$message = "<div class='updated fade C8'>{$dictionary->label41}</div>";

if ($condition1 || $condition2) {
    echo $message;
}
?>

<form action="" method="post">
    <?php wp_nonce_field("update", "gf_IDPay_update") ?>
    <h3>
        <span>
            <i class="fa fa-credit-card"></i>
            <?php echo $dictionary->label42 ?>
        </span>
    </h3>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="gf_IDPay_configured"><?php echo $dictionary->label43 ?></label>
            </th>
            <td>
                <input <?php echo $isActive ?> type="checkbox" name="gf_IDPay_configured" id="gf_IDPay_configured"/>
                <label class="inline" for="gf_IDPay_configured"><?php echo $dictionary->label44 ?></label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="gf_IDPay_gname">
                    <?php echo $dictionary->label45 ?>
                    <?php echo $gatewayName ?>
                </label>
            </th>
            <td>
                <input class="Cw350" type="text" id="gf_IDPay_gname"
                       name="gf_IDPay_gname" value="<?php echo $gatewayTitle; ?>"/>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="gf_IDPay_api_key"><?php echo $dictionary->label46 ?></label>
            </th>
            <td>
                <input class="C7" type="text" id="gf_IDPay_api_key"
                       name="gf_IDPay_api_key" value="<?php echo $apiKey ?>"/>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="gf_IDPay_sandbox"><?php echo $dictionary->label47 ?></label>
            </th>
            <td>
                <input type="checkbox" name="gf_IDPay_sandbox" id="gf_IDPay_sandbox" <?php echo $isActive2 ?>/>
                <label class="inline" for="gf_IDPay_sandbox"><?php echo $dictionary->label48 ?></label>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <input type="submit" class="button" name="gf_IDPay_submit"
                       value="<?php echo $dictionary->label49 ?>"/>
            </td>
        </tr>
    </table>
</form>
<form action="" method="post">
    <?php wp_nonce_field("uninstall", "gf_IDPay_uninstall"); ?>
    <?php if (IDPayOperation::hasPermission(IDPayOperation::PERMISSION_UNISTALL)) { ?>
        <div class="hr-divider"></div>
        <div class="delete-alert alert_red">
            <h3><i class="fa fa-exclamation-triangle gf_invalid"></i><?php echo $dictionary->label50 ?></h3>
            <div class="gf_delete_notice"><?php echo $dictionary->label51 ?></div>
            <?php echo apply_filters("gform_IDPay_uninstall_button", $uninstallHtml); ?>
        </div>
    <?php } ?>
</form>