jQuery(document).ready(function ($) {

    var presspermitUpdateGroupsUI = function (data, txtStatus) {
        $("#pp_new_user_groups").html(data);
    }

    var presspermitAjaxUIFailure = function (data, txtStatus) {
        return;
    }

    var presspermitAjaxUI = function (op, handler) {
        var data = {'pp_ajax_user': op};
        $.ajax({url: ppUser.ajaxurl, data: data, dataType: "html", success: handler, error: presspermitAjaxUIFailure});
    }

    $('<div id="pp_new_user_groups"></div>').insertBefore('p.submit');
    presspermitAjaxUI('new_user_groups_ui', presspermitUpdateGroupsUI);
});