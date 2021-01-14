function presspermitLoadAgentsJS(id_sfx, agent_type, context, agent_id, suppress_selection_js) {
    jQuery(document).ready(function ($) {
        id_sfx = id_sfx.replace(/:/g, '\\:');

        if (!suppress_selection_js) {
            $("#select_agents_" + id_sfx).on('click', function (e) {
                e.preventDefault();
                presspermitSelectAgents(id_sfx);
            });

            $("#agent_results_" + id_sfx).on('dblclick', function (e) {
                e.preventDefault();
                presspermitSelectAgents(id_sfx);

            });
        }

        $("#agent_results_" + id_sfx).DynamicListbox({
            ajax_url: PPAgentSelect.adminurl,
            agent_type: agent_type,
            search_id: 'agent_search_text_' + id_sfx,
            results_id: 'agent_results_' + id_sfx,
            button_id: 'agent_submit_' + id_sfx,
            ajaxhandler: PPAgentSelect.ajaxhandler,
            pp_context: context,
            topic: id_sfx,
            agent_id: agent_id
        });
    });
}

function presspermitSelectAgents(id_sfx) {
    jQuery(document).ready(function ($) {
        var agent_ids = '';

        $("#agent_results_" + id_sfx + " option:selected").each(function (i) {
            agent_ids = agent_ids + $(this).attr("value") + ',';
            $(this).remove();
        });

        presspermitEagentAjaxRequest(agent_ids, id_sfx);
    });
}

var presspermitEagentAjaxRequest = function (agent_ids, id_sfx) {
    jQuery(document).ready(function ($) {
        var data = {
            'pp_ajax_item': 'get_agent_exception_ui',
            'via_item_source': ppException.via_item_source,
            'via_item_type': ppException.via_item_type,
            'agent_ids': agent_ids,
            'item_id': ppException.item_id,
            'id_sfx': id_sfx
        };

        $.ajax({
            url: ppException.ajax_url,
            data: data,
            dataType: "html",
            success: presspermitEagentGotAjaxListbox,
            error: presspermitEagentAjaxFailure
        });
    });
}

var presspermitEagentGotAjaxListbox = function (data, txtStatus, req) {
    jQuery(document).ready(function ($) {
        var startpos = data.indexOf('<!--ppSfx-->');
        var endpos = data.indexOf('<--ppSfx-->');

        if ((startpos == -1) || (endpos <= startpos))
            return;

        var sfx = data.substr(startpos + 12, endpos - startpos - 12);
        var arr_sfx = sfx.split('|');

        startpos = data.indexOf('<!--ppResponse-->');
        endpos = data.indexOf('<--ppResponse-->');

        if ((startpos == -1) || (endpos <= startpos))
            return;

        data = data.substr(startpos + 17, endpos - startpos - 17);

        // Add agent UI dropdowns
        $('#pp_' + arr_sfx[0] + '_' + arr_sfx[1] + '_exceptions').find('table.pp-exc-' + arr_sfx[2] + ' td.pp-current-item-exceptions tbody').append(data);
        $('#pp_' + arr_sfx[0] + '_' + arr_sfx[1] + '_exceptions').find('table.pp-exc-' + arr_sfx[2] + ' td.pp-current-item-exceptions table').show().find('tfoot').show();

        $('#pp_' + arr_sfx[0] + '_' + arr_sfx[1] + '_exceptions div').scrollTop(999999);

        $('#pp_' + arr_sfx[0] + '_' + arr_sfx[1] + '_exceptions').find('table.pp-exc-' + arr_sfx[2] + ' .pp-no-exceptions').hide();
    });
}

var presspermitEagentAjaxFailure = function (XMLHttpRequest, textStatus, errorThrown) {
    /*
    if(!args.debug) return;

    $('#' + args.results_id).html('<option value="0"><b style="color:red">'+
             XMLHttpRequest.status+':'+
             (textStatus?textStatus:'')+
             (errorThrown?errorThrown:'')+'</b></option>');
    */
}

