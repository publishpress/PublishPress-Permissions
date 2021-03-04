(function ($) {
    $.fn.DynamicListbox = function (args) {
        /*
        var args = {
            search_id: 
            ,button_id: 
            ,results_id: 
            ,ajax_url:''	
            ,agent_type: 'user'
            ,agent_id: ''
            ,topic: ''
            ,pp_context: ''
            ,ajaxhandler: 'got_ajax_listbox'
        };
        */

        $('#' + args.search_id).on('keydown', function (e) {
            // this will catch pressing enter and call find function
            if (e.keyCode == 13) {
                ajax_request($(this).val());
                e.preventDefault();
            }
        });

        $('input.pp-user-meta-field').on('keydown', function (e) {
            if (e.keyCode == 13) {
                ajax_request($('#' + args.search_id).val());
                e.preventDefault();
            }
        });

        $('div.pp-search-box-with-icon-wrapper .dashicons-search').on('click', function(e) {
            ajax_request($('#' + args.search_id).val());
        });

        $("#" + args.button_id).on('click', function () {
            ajax_request($('#' + args.search_id).val());
        });

        var ajax_request = function (stext) {
            $("#" + args.button_id).closest('div').find('.waiting').show();
            $("#" + args.button_id).prop('disabled', true);
            $("#" + args.search_id).prop('disabled', true);

            if (stext == null || stext == 'undefined') stext = '';

            if ($('#pp_search_role_' + args.topic).length)
                var roletext = $('#pp_search_role_' + args.topic).val();
            else
                var roletext = '';

            umkey = [];
            umval = [];
            for (i = 0; i < 6; i++) {
                if ($('#pp_search_user_meta_key_' + i + '_' + args.topic).length) {
                    umkey[i] = $('#pp_search_user_meta_key_' + i + '_' + args.topic).val();
                    umval[i] = $('#pp_search_user_meta_val_' + i + '_' + args.topic).val();
                } else {
                    umkey[i] = '';
                    umval[i] = '';
                }
            }

            var data = {
                'pp_agent_search': stext,
                'pp_role_search': roletext,
                'pp_agent_type': args.agent_type,
                'pp_agent_id': args.agent_id,
                'pp_topic': args.topic,
                'pp_usermeta_key': umkey,
                'pp_usermeta_val': umval,
                'pp_omit_admins': ppListbox.omit_admins,
                'pp_metagroups': ppListbox.metagroups,
                'pp_operation': args.op,
                'pp_context': args.pp_context
            };

            $.ajax({url: args.ajax_url, data: data, dataType: "html", success: got_ajax_listbox, error: ajax_failure});
        }

        var got_ajax_listbox = function (data, txtStatus) {
            //Set listbox contents to Ajax response
            $('#' + args.results_id).html(data);

            if (typeof document.all == 'undefined') // triggers removal of agents who already have a dropdown (but IE chokes on trigger call)
                $('#' + args.results_id).trigger('jchange');

            $("#" + args.button_id).closest('div').find('.waiting').hide();

            $("#" + args.button_id).prop('disabled', false);
            $("#" + args.search_id).prop('disabled', false);
        }

        var ajax_failure = function (XMLHttpRequest, textStatus, errorThrown) {
            if (!args.debug) return;

            $('#' + args.results_id).html('<option value="0"><b style="color:red">' +
                XMLHttpRequest.status + ':' +
                (textStatus ? textStatus : '') +
                (errorThrown ? errorThrown : '') + '</b></option>');
        }
    }
})(jQuery); 
