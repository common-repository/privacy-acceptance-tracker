jQuery(document).ready(function() {
    jQuery(".wapadelbutton").click( function(e) {
        e.preventDefault(); 
        row_id = jQuery(this).attr("data-wapa-id");
        row_type = jQuery(this).attr("data-wapa-type");
        nonce = jQuery("#nonce").val();
        ajaxurl = jQuery("#ajaxlink").val();

        jQuery.ajax({
            type : "post",
            dataType : "json",
            url : ajaxurl,
            data : {action: "wapa_delete", row_id : row_id, row_type: row_type, nonce: nonce},
            success: function(response) {
                jQuery("#messagearea").text(response.message + " The page will now reload");
                setTimeout(() => {  location.reload(); }, 3000);
            }
        });   
    });
});