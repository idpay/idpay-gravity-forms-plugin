<?php
Helpers::prepareFrontEndTools();
IDPayOperation::checkSubmittedUnistall();

$settings     = Helpers::checkSubmittedConfigDataAndLoadSetting();
$enable     = Helpers::dataGet($settings,'enable');
$dictionary   = Helpers::loadDictionary();

$condition1 = ! empty($_POST);
$condition2 = isset($_GET['subview']) && $_GET['subview'] == 'gf_IDPay' && isset($_GET['updated']);
$isActive     = $enable ? "checked='checked'" : "";
$title        = sanitize_text_field(rgar($settings, 'name'));
$gatewayTitle = $title ? sanitize_text_field($settings["name"]) : 'IDPay';
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
                <label for="gf_IDPay_enable"><?php echo $dictionary->label43 ?></label>
            </th>
            <td>
                <input <?php echo $isActive ?> type="checkbox" name="gf_IDPay_enable" id="gf_IDPay_enable"/>
                <label class="inline" for="gf_IDPay_enable"><?php echo $dictionary->label44 ?></label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="gf_IDPay_name">
                    <?php echo $dictionary->label45 ?>
                </label>
            </th>
            <td>
                <input class="Cw350" type="text" id="gf_IDPay_name"
                       name="gf_IDPay_name" value="<?php echo $gatewayTitle; ?>"/>
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