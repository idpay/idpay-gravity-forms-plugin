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

</script>


