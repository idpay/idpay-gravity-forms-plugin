<?php
$dictionary = Helpers::loadDictionary();
$feedActive =  wp_create_nonce( "gf_IDPay_update_feed_active" );
?>

<script type="text/javascript">
    function GF_SwitchFid(fid) {
        jQuery("#IDPay_wait").show();
        document.location = "?page=gf_IDPay&view=edit&fid=" + fid;
    }

    function DeleteSetting(id) {
        jQuery("#action_argument").val(id);
        jQuery("#action").val("delete");
        jQuery("#confirmation_list_form")[0].submit();
    }

    function ToggleActive(img, feed_id) {
        var is_active = img.src.indexOf("active1.png") >= 0;
        if (is_active) {
            img.src = img.src.replace("active1.png", "active0.png");
            jQuery(img).attr('title','<?php echo $dictionary->labelOn ?>' )
                       .attr('alt','<?php echo $dictionary->labelOff ?>' );

        } else {
            img.src = img.src.replace("active0.png", "active1.png");
            jQuery(img).attr('title','<?php echo $dictionary->labelOn ?>')
                       .attr('alt','<?php echo $dictionary->labelOff ?>');
        }
        var mysack = new sack(ajaxurl);
        mysack.execute = 1;
        mysack.method = 'POST';
        mysack.setVar("action", "gf_IDPay_update_feed_active");
        mysack.setVar("gf_IDPay_update_feed_active", "<?php echo $feedActive ?>");
        mysack.setVar("feed_id", feed_id);
        mysack.setVar("is_active", is_active ? 0 : 1);
        mysack.onError = function () {alert('<?php echo $dictionary->labelAjaxErr ?>')};
        mysack.runAJAX();
        return true;
    }
</script>


