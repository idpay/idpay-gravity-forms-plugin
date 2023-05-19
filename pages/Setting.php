<?php
include_once self::get_base_path() . '/lib/scripts.php';
include_once self::get_base_path() . '/lib/styles.php';
self::checkSubmittedUnistall();
$settings         = self::checkSubmittedConfigDataAndLoadSetting();
$dictionary       = self::loadDictionary('', '');
$isActive         = get_option("gf_IDPay_configured") ? "checked='checked'" : "";
$gatewayName      = gform_tooltip('gateway_name');
$gatewayTitle     = sanitize_text_field(rgar($settings, 'gname')) ? sanitize_text_field($settings["gname"]) : 'IDPay';
$apiKey           = sanitize_text_field(rgar($settings, 'api_key'));
$isActive2        = rgar($settings, 'sandbox') ? "checked='checked'" : "";
$uninstall_button = '<input  style="font-family:tahoma !important;" type="submit" name="uninstall" value="' .
                    $dictionary->label52 . '" class="button" onclick="return confirm(\'' .
                    $dictionary->label53 . '\');"/>';

if (! empty($_POST)) {
    echo '<div class="updated fade" style="padding:6px">' . $dictionary->label41 . '</div>';
} elseif (isset($_GET['subview']) && $_GET['subview'] == 'gf_IDPay' && isset($_GET['updated'])) {
    echo '<div class="updated fade" style="padding:6px">' . $dictionary->label41 . '</div>';
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
                <input style="width:350px;" type="text" id="gf_IDPay_gname" name="gf_IDPay_gname"
                       value="<?php echo $gatewayTitle; ?>"/>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="gf_IDPay_api_key"><?php echo $dictionary->label46 ?></label>
            </th>
            <td>
                <input style="width:350px;text-align:left;direction:ltr !important" type="text" id="gf_IDPay_api_key"
                       name="gf_IDPay_api_key"
                       value="<?php echo $apiKey ?>"/>
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
                <input style="font-family:tahoma !important;" type="submit" class="button-primary"
                       name="gf_IDPay_submit" value="<?php echo $dictionary->label49 ?>"/>
            </td>
        </tr>
    </table>
</form>
<form action="" method="post">
    <?php wp_nonce_field("uninstall", "gf_IDPay_uninstall"); ?>
    <?php if (self::hasPermission("gravityforms_IDPay_uninstall")) { ?>
        <div class="hr-divider"></div>
        <div class="delete-alert alert_red">
            <h3><i class="fa fa-exclamation-triangle gf_invalid"></i><?php echo $dictionary->label50 ?></h3>
            <div class="gf_delete_notice"><?php echo $dictionary->label51 ?></div>
            <?php echo apply_filters("gform_IDPay_uninstall_button", $uninstall_button); ?>
        </div>
    <?php } ?>
</form>