<?php ?>
<script type="text/javascript">
    function GF_SwitchFid(fid) {
        jQuery("#IDPay_wait").show();
        document.location = "?page=gf_IDPay&view=edit&fid=" + fid;
    }
</script>

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
            jQuery(img).attr('title', '<?php _e( "درگاه غیر فعال است", "gravityformsIDPay" ) ?>').attr('alt', '<?php _e( "درگاه غیر فعال است", "gravityformsIDPay" ) ?>');
        } else {
            img.src = img.src.replace("active0.png", "active1.png");
            jQuery(img).attr('title', '<?php _e( "درگاه فعال است", "gravityformsIDPay" ) ?>').attr('alt', '<?php _e( "درگاه فعال است", "gravityformsIDPay" ) ?>');
        }
        var mysack = new sack(ajaxurl);
        mysack.execute = 1;
        mysack.method = 'POST';
        mysack.setVar("action", "gf_IDPay_update_feed_active");
        mysack.setVar("gf_IDPay_update_feed_active", "<?php echo wp_create_nonce( "gf_IDPay_update_feed_active" ) ?>");
        mysack.setVar("feed_id", feed_id);
        mysack.setVar("is_active", is_active ? 0 : 1);
        mysack.onError = function () {
            alert('<?php _e( "خطای Ajax رخ داده است", "gravityformsIDPay" ) ?>')
        };
        mysack.runAJAX();
        return true;
    }
</script>


