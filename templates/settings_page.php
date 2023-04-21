<?php

if (! defined('ABSPATH')) {
    exit;
}
if (rgpost("uninstall")) {
    check_admin_referer("uninstall", "gf_IDPay_uninstall");
    self::uninstall();
    echo '<div class="updated fade" style="padding:20px;">' .
         __("درگاه با موفقیت غیرفعال شد و اطلاعات مربوط به آن نیز از بین رفت برای فعالسازی مجدد میتوانید از طریق افزونه های وردپرس اقدام نمایید .", "gravityformsIDPay") . '</div>';

    return;
} elseif (isset($_POST["gf_IDPay_submit"])) {
    check_admin_referer("update", "gf_IDPay_update");
    $settings = array(
        "gname" => rgpost('gf_IDPay_gname'),
        "api_key" => rgpost('gf_IDPay_api_key'),
        "sandbox" => rgpost('gf_IDPay_sandbox'),
    );
    update_option("gf_IDPay_settings", array_map('sanitize_text_field', $settings));
    if (isset($_POST["gf_IDPay_configured"])) {
        update_option("gf_IDPay_configured", sanitize_text_field($_POST["gf_IDPay_configured"]));
    } else {
        delete_option("gf_IDPay_configured");
    }
} else {
    $settings = get_option("gf_IDPay_settings");
}

if (!empty($_POST)) {
    echo '<div class="updated fade" style="padding:6px">' . __("تنظیمات ذخیره شدند .", "gravityformsIDPay") . '</div>';
} elseif (isset($_GET['subview']) && $_GET['subview'] == 'gf_IDPay' && isset($_GET['updated'])) {
    echo '<div class="updated fade" style="padding:6px">' . __("تنظیمات ذخیره شدند .", "gravityformsIDPay") . '</div>';
}
?>

<form action="" method="post">
    <?php wp_nonce_field("update", "gf_IDPay_update") ?>
    <h3>
        <span>
            <i class="fa fa-credit-card"></i>
            <?php _e("تنظیمات IDPay", "gravityformsIDPay") ?>
        </span>
    </h3>
    <table class="form-table">
        <tr>
            <th scope="row"><label
                    for="gf_IDPay_configured"><?php _e("فعالسازی", "gravityformsIDPay"); ?></label>
            </th>
            <td>
                <input type="checkbox" name="gf_IDPay_configured"
                       id="gf_IDPay_configured" <?php echo get_option("gf_IDPay_configured") ? "checked='checked'" : "" ?>/>
                <label class="inline"
                       for="gf_IDPay_configured"><?php _e("بله", "gravityformsIDPay"); ?></label>
            </td>
        </tr>
        <?php
        $gateway_title = __("IDPay", "gravityformsIDPay");
        if (sanitize_text_field(rgar($settings, 'gname'))) {
            $gateway_title = sanitize_text_field($settings["gname"]);
        }
        ?>
        <tr>
            <th scope="row">
                <label for="gf_IDPay_gname">
                    <?php _e("عنوان", "gravityformsIDPay"); ?>
                    <?php gform_tooltip('gateway_name') ?>
                </label>
            </th>
            <td>
                <input style="width:350px;" type="text" id="gf_IDPay_gname" name="gf_IDPay_gname"
                       value="<?php echo $gateway_title; ?>"/>
            </td>
        </tr>
        <tr>
            <th scope="row"><label
                    for="gf_IDPay_api_key"><?php _e("API KEY", "gravityformsIDPay"); ?></label></th>
            <td>
                <input style="width:350px;text-align:left;direction:ltr !important" type="text"
                       id="gf_IDPay_api_key" name="gf_IDPay_api_key"
                       value="<?php echo sanitize_text_field(rgar($settings, 'api_key')) ?>"/>
            </td>
        </tr>
        <tr>
            <th scope="row"><label
                    for="gf_IDPay_sandbox"><?php _e("آزمایشگاه", "gravityformsIDPay"); ?></label>
            </th>
            <td>
                <input type="checkbox" name="gf_IDPay_sandbox"
                       id="gf_IDPay_sandbox" <?php echo rgar($settings, 'sandbox') ? "checked='checked'" : "" ?>/>
                <label class="inline"
                       for="gf_IDPay_sandbox"><?php _e("بله", "gravityformsIDPay"); ?></label>
            </td>
        </tr>
        <tr>
            <td colspan="2"><input style="font-family:tahoma !important;" type="submit"
                                   name="gf_IDPay_submit" class="button-primary"
                                   value="<?php _e("ذخیره تنظیمات", "gravityformsIDPay") ?>"/></td>
        </tr>
    </table>
</form>
<form action="" method="post">
    <?php
    wp_nonce_field("uninstall", "gf_IDPay_uninstall");
    if (self::has_access("gravityforms_IDPay_uninstall")) {
        ?>
        <div class="hr-divider"></div>
        <div class="delete-alert alert_red">
            <h3>
                <i class="fa fa-exclamation-triangle gf_invalid"></i>
                <?php _e("غیر فعالسازی افزونه دروازه پرداخت IDPay", "gravityformsIDPay"); ?>
            </h3>
            <div
                class="gf_delete_notice"><?php _e("تذکر : بعد از غیرفعالسازی تمامی اطلاعات مربوط به IDPay حذف خواهد شد", "gravityformsIDPay") ?></div>
            <?php
            $uninstall_button = '<input  style="font-family:tahoma !important;" type="submit" name="uninstall" value="' . __("غیر فعال سازی درگاه IDPay", "gravityformsIDPay") . '" class="button" onclick="return confirm(\'' . __("تذکر : بعد از غیرفعالسازی تمامی اطلاعات مربوط به IDPay حذف خواهد شد . آیا همچنان مایل به غیر فعالسازی میباشید؟", "gravityformsIDPay") . '\');"/>';
            echo apply_filters("gform_IDPay_uninstall_button", $uninstall_button);
            ?>
        </div>
    <?php } ?>
</form>