jQuery(document).ready(function ($) {
    // ========== Begin "Set Specific Permissions" UI scripts ==========
    var presspermitItemPath = new Object;
    var presspermitAllExceptionData = [];
    var presspermitXid = -1;

    $('ul.categorychecklist ul.children li[style="display:none"]').parent().prevAll('input.menu-item-checkbox').next('span').html(' + ');

    $('.menu-item-checkbox').on('click', function () {
        const clickedCheckbox = $(this);
        if (clickedCheckbox.val() === "0" && clickedCheckbox.closest('li').find('label').text().includes("None")) {
            clickedCheckbox.closest('ul').find('.menu-item-checkbox').not(clickedCheckbox).prop('checked', false).prop('disabled', clickedCheckbox.is(':checked'));
        }
    });

    $('input.menu-item-checkbox').nextAll('span').on('click', function (e) {
        $(this).parent().children('ul.children').children('li').toggle();

        if ($(this).nextAll('ul.children').length) {
            if ($(this).html() == ' + ') {
                $(this).html(' &ndash; ');
            } else {
                $(this).html(' + ');
            }
        }
        e.preventDefault();
    });

    $(document).on('click', 'ul.categorychecklist li label', function (e) {
        $(this).prevAll('input.menu-item-checkbox').trigger('click');
    });

    $('.add-to-menu .waiting').hide();

    $("#pp_save_exceptions input.button-primary").on('click', function () {
        $('input[name="member_csv"]').val($("input#member_csv").val());
        $('input[name="group_name"]').val($("input#group_name").val());
        $('input[name="description"]').val($("input#description").val());
        $("#pp_new_x_submission_msg").html(ppRestrict.submissionMsg);
        $("#pp_new_x_submission_msg").show();
    });

    $('#agent-profile #submit').on('click', function (e) {
        // no need to submit selection inputs
        $('#pp_review_exceptions').hide();
        $('#pp_add_exception').remove();
    });

    $(document).on('click', "#pp_tbl_exception_selections .pp_clear", function (e) {
        var presspermitXid = $(this).closest('tr').find('input[name="pp_presspermitXid[]"]').val();

        if (typeof presspermitAllExceptionData[presspermitXid] != 'undefined') {
            delete presspermitAllExceptionData[presspermitXid];
        }

        $(this).closest('tr').remove();
        e.stopPropagation();
    });

    $('.pp_clear_all').on('click', function () {
        $('.pp_clear').trigger('click');
    });

    $(".menu-item-checkbox").on('click', function () {
        presspermitItemCheckboxClick('menu-item', $(this));
    });

    var presspermitItemCheckboxClick = function (data_var, t) {
        var expr = data_var + '\\[(\[^\\]\]*)';
        var re = new RegExp(expr);

        itemdata = t.closest('li').getItemData();

        if (t.closest('div.tabs-panel').parent().hasClass('hierarchical')) {
            presspermitXajaxUI('get_presspermitItemPath', presspermitXupdateItemPath, itemdata['menu-item-object-id']);
        }
    }

    var presspermitExceptionsTimer;

    var presspermitXupdateItemPath = function (data, txtStatus) {
        var item_info = data.split('\r');
        presspermitItemPath[item_info[0]] = item_info[1];
        $('input.menu-item-checkbox[value="' + item_info[0] + '"]').nextAll('label').attr('title', item_info[1]);
        clearTimeout(presspermitExceptionsTimer);
    }

    $(document).on('mouseenter', 'div.hierarchical ul.categorychecklist li label', function () {
        if ($(this).attr('title') == undefined || $(this).attr('title') == '') {
            var that = this;
            presspermitExceptionsTimer = setTimeout(function () {
                itemdata = $(that).closest('li').getItemData();
                presspermitXajaxUI('get_presspermitItemPath', presspermitXupdateItemPath, itemdata['menu-item-object-id']);
            }, 500);
        }
    });

    $(document).on('mouseleave', 'ul.categorychecklist li label', function () {
        clearTimeout(presspermitExceptionsTimer);
    });

    $(document).on('click', '.submit-add-item-exception', function (e) {
        presspermitXaddItemException('menu-item');

        /* Possible future development: mirror on initial creation of exceptions
        var mops = '';

        $('td.pp-select-x-operation input').each(function(i) {
            mops += ' <label><input type="checkbox" name="pp_add_exceptions_mirror_ops[]" value="' + $(this).val() + '" /><span>' + $(this).next('span').html() + '</span></label>';
        });

        $('div.pp_mirror_to_operations').html(mops);
        */

        return false;
    });

    var presspermitXaddItemException = function (data_var) {
        $('div.pp-ext-promo').hide();

        var items = $('#menu-settings-column').find('.tabs-panel-active .categorychecklist li input:checked');

        if (!$('input[name="pp_select_x_operation"]').val()) {
            $('#pp_item_selection_msg').html(ppRestrict.noOp);
            $('#pp_item_selection_msg').addClass('pp-error-note');
            $('#pp_item_selection_msg').show();
            return false;
        }

        if (items.length == 0) {
            $('#pp_item_selection_msg').html(ppRestrict.noItems);
            $('#pp_item_selection_msg').addClass('pp-error-note');
            $('#pp_item_selection_msg').show();
            return false;
        }

        var newrow = '', trackdata = '', hier_type = false, assign_mode_inputs = '', item_caption = '',
            any_added = false, duplicate = false, child_assign = 0, item_assign = 1;

        if ($('#pp_select_x_assign_for div').children().length > 1)
            hier_type = true;

        if (hier_type) {
            if (!$('#pp_select_x_item_assign').is(':checked'))
                item_assign = 0;

            if ($('#pp_select_x_child_assign').is(':checked'))
                child_assign = 1;
        }

        if (child_assign) {
            if (item_assign) {
                var item_lbl = jQuery.trim($('#pp_x_item_assign_label').html());
                item_lbl = item_lbl.replace(':', '');
                scope_caption = item_lbl + ', ' + jQuery.trim($('#pp_x_child_assign_label').html());
            } else
                scope_caption = jQuery.trim($('#pp_x_child_assign_label').html());
        } else {
            if (item_assign) {
                scope_caption = jQuery.trim($('#pp_x_item_assign_label').html());
            } else {
                $('#pp_item_selection_msg').html(ppRestrict.noMode);
                $('#pp_item_selection_msg').addClass('pp-error-note');
                $('#pp_item_selection_msg').show();
                return false;
            }
        }

        var for_type = $('select[name="pp_select_x_for_type"]').val();
        var op = $('input[name="pp_select_x_operation"]:checked').val();
        var via_type = $('select[name="pp_select_x_via_type"]').val();
        var mod_type = $('input[name="pp_select_x_mod_type"]:checked').val();

        var for_type_caption = $('select[name="pp_select_x_for_type"] option:selected').html()
        var op_caption = $('input[name="pp_select_x_operation"]:checked').next('span').html()
        var via_type_caption = $('select[name="pp_select_x_via_type"] :selected').html()
        var mod_type_caption = $('input[name="pp_select_x_mod_type"]:checked').next('span').html();
        var assign_for_captions = $('input[name="pp_select_x_mod_type"]:checked').next('span').html()

        var conds = $('td.pp-select-x-status').find('input[name="pp_select_x_cond[]"]:checked');

        if (conds.length == 0) {
            $('#pp_item_selection_msg').html(ppCred.noConditions);
            $('#pp_item_selection_msg').addClass('pp-error-note');
            $('#pp_item_selection_msg').show();
            return false;
        }
        $('.pp-save-exceptions').show();

        $(items).each(function (item_index) {
            var t = $(this);
            var expr = data_var + '\\[(\[^\\]\]*)';
            var re = new RegExp(expr);

            // menu-item-title, menu-item-object-id
            itemdata = t.closest('li').getItemData();

            if (typeof (itemdata['menu-item-object-id'] != 'undefined')) {
                item_caption = itemdata['menu-item-title'];

                if (hier_type) {
                    if (typeof (presspermitItemPath[itemdata['menu-item-object-id']]) != 'undefined')
                        item_caption = presspermitItemPath[itemdata['menu-item-object-id']];
                }

                if (child_assign) {
                    if (item_assign) {
                        var item_lbl = jQuery.trim($('#pp_x_item_assign_label').html());
                        item_lbl = item_lbl.replace(':', '');
                        selected_caption = item_lbl + ', ' + jQuery.trim($('#pp_x_child_assign_label').html());
                    } else
                        selected_caption = jQuery.trim($('#pp_x_child_assign_label').html());
                } else {
                    if (item_assign) {
                        selected_caption = jQuery.trim($('#pp_x_item_assign_label').html());
                    } else {
                        $('#pp_item_selection_msg').html(ppRestrict.noMode);
                        $('#pp_item_selection_msg').addClass('pp-error-note');
                        $('#pp_item_selection_msg').show();
                        return false;
                    }
                }

                $(conds).each(function () {
                    id = presspermitEscapeID(this.id);
                    var lbl = $('#pp_add_exception label[for="' + id + '"]');
                    var lblStatus = lbl.html() === '(all)' ? 'All Statuses' : lbl.html();
                    trackdata = for_type
                        + '|' + op
                        + '|' + via_type
                        + '|' + mod_type
                        + '|' + $('#' + id).val()
                        + '|' + itemdata['menu-item-object-id'];

                    if ($.inArray(trackdata, presspermitAllExceptionData) != -1) {
                        duplicate = true;
                    } else {
                        presspermitXid++;
                        presspermitAllExceptionData[presspermitXid] = trackdata;

                        if (hier_type) {
                            assign_mode_inputs = '<input type="hidden" name="pp_add_exception[' + presspermitXid + '][for_item]" value="' + item_assign + '" />'
                                + '<input type="hidden" name="pp_add_exception[' + presspermitXid + '][for_children]" value="' + child_assign + '" />';
                        } else
                            assign_mode_inputs = '';

                        newrow = 
                            '<tr><td>' + for_type_caption + '</td>'
                            + '<td>' + op_caption + '</td>'
                            + '<td>' + mod_type_caption + '</td>'
                            + '<td>' + selected_caption + '</td>'
                            + '<td>' + item_caption + '</td>'
                            + '<td>' + lblStatus + '</td>'
                            + '<td><div class="pp_clear">' + ' <a href="javascript:void(0)" class="pp_clear">' + ppRestrict.clearException + '</a></div>'
                            + '<input type="hidden" name="pp_presspermitXid[]" value="' + presspermitXid + '" />'
                            + '<input type="hidden" name="pp_add_exception[' + presspermitXid + '][for_type]" value="' + for_type + '" />'
                            + '<input type="hidden" name="pp_add_exception[' + presspermitXid + '][operation]" value="' + op + '" />'
                            + '<input type="hidden" name="pp_add_exception[' + presspermitXid + '][via_type]" value="' + via_type + '" />'
                            + '<input type="hidden" name="pp_add_exception[' + presspermitXid + '][mod_type]" value="' + mod_type + '" />'
                            + '<input type="hidden" name="pp_add_exception[' + presspermitXid + '][attrib_cond]" value="' + $('#' + id).val() + '" />'
                            + '<input type="hidden" name="pp_add_exception[' + presspermitXid + '][item_id]" value="' + itemdata['menu-item-object-id'] + '" />'
                            + assign_mode_inputs
                            + '</td></tr>';

                        $('#pp_tbl_exception_selections tbody').append(newrow);

                        any_added = true;
                    }
                });
            }
        });

        $("#pp_add_exception .menu-item-checkbox").prop('checked', false);

        if (duplicate && !any_added) {
            $('#pp_item_selection_msg').html(ppRestrict.alreadyException);
            $('#pp_item_selection_msg').addClass('pp-error-note');
            $('#pp_item_selection_msg').show();
        } else {
            $('#pp_item_selection_msg').html(ppRestrict.pleaseReview);
            $('#pp_item_selection_msg').removeClass('pp-error-note');
            $('#pp_item_selection_msg').show();
        }

        return false;
    }

    var presspermitReloadOperation = function () {
        if ($('select[name="pp_select_x_for_type"]').val()) {
            $('select[name="pp_select_x_for_type"] option.pp-opt-none').remove();  // todo: review this
            presspermitXajaxUI('get_operation_options', presspermitDrawOperations);
        } else
            $('.pp-select-x-operation').hide();
    }

    var presspermitReloadViaType = function () {
        if ($('input[name="pp_select_x_operation"]').val())
            presspermitXajaxUI('get_via_type_options', presspermitDrawViaTypes);
        else
            $('.pp-select-x-via-type').hide();
    }

    var presspermitReloadModificationType = function () {
        if ($('input[name="pp_select_x_operation"]').val()) {
            setTimeout(function () {
                presspermitXajaxUI('get_mod_options', presspermitDrawModificationTypes)
            }, 100);
        } else
            $('.pp-select-x-mod-type').hide();
    }

    var presspermitReloadAssignFor = function () {
        if ($('select[name="pp_select_x_for_type"]').find('option').length) {
            setTimeout(function () {
                presspermitXajaxUI('get_assign_for_ui', presspermitDrawAssignFor)
            }, 100);
        } else {
            $('.pp-select-x-assign-for').hide();
    }
    }

    var pressPermitNoneItemVisibility = function() {
        var mod_type = $('input[name="pp_select_x_mod_type"]:checked').val();

        if ('include' == mod_type || (('exclude' == mod_type) && ('associate' == $('input[name="pp_select_x_operation"]').val()))) {
            $('td.pp-select-items input.menu-item-checkbox[value="0"]').closest('li').show();
        } else {
            $('td.pp-select-items input.menu-item-checkbox[value="0"]').closest('li').hide();
        }
    }

    var presspermitReloadStatus = function () {
        var op = $('input[name="pp_select_x_operation"]').val();
        var mod_type = $('input[name="pp_select_x_mod_type"]:checked').val();
        if (mod_type && op) {
            setTimeout(function () {
                presspermitXajaxUI('get_status_ui', presspermitDrawStatus)
            }, 50);

            if ('include' == mod_type) {
                $('input.add-to-top').show();
                $('input.add-to-top').parent().show();
            } else {
                $('input.add-to-top').hide();
                $('input.add-to-top').parent().hide();
            }
        } else
            $('.pp-select-x-status').hide();

        pressPermitNoneItemVisibility();
    }

    $('select[name="pp_select_x_for_type"]').on('change', presspermitReloadOperation);

    $('select[name="pp_select_x_for_type"]').on('change', function () {
        $('.pp-select-items').hide();
        $('.pp-select-x-mod-type').hide();
        $('.pp-select-x-via-type').hide();
        $('.pp-select-x-status').hide();
        $('#pp_add_exception').css('width', 'auto');
    });

    $('td.pp-select-x-operation').on('click', function() {
        var sel = $(this).find('input:checked').val();
        if (sel) {
            presspermitLastOp = sel;
        }
        presspermitReloadViaType();
    });

    $('td.pp-select-x-operation').on('click', presspermitReloadModificationType);
    $('td.pp-select-x-operation').on('click', presspermitReloadStatus);

    $('td.pp-select-x-mod-type').on('click', function() {
        var sel = $(this).find('input:checked').val();
        if (sel) {
            presspermitLastModType = sel;
        }
        presspermitReloadStatus();
    });

    $('select[name="pp_select_x_via_type"]').on('change', presspermitReloadStatus);
    $('select[name="pp_select_x_via_type"]').on('change', presspermitReloadAssignFor);

    $('select[name="pp_select_x_via_type"]').on('change', function () {
        $('#pp_add_exception .postbox').hide();	// todo: review this

        if ($(this).find('option').length) {
            var pp_via_type = $(this).val();

            if (!pp_via_type) {
                pp_via_type = $('select[name="pp_select_x_for_type"]').val();
            }

            $('#select-exception-' + pp_via_type).show();
            $('.pp-select-items').show();
        } else
            $('.pp-select-items').hide();

        $('#pp_add_exception').css('width', '100%');

        $('input.menu-item-checkbox').prop('checked', false);
    });

    $('select[name="pp_select_x_via_type"]').on('click', function () {
        presspermitLastViaType = $(this).val();
    });

    var presspermitUpdateItemNoneCaption = function() {
        if ($('select[name="pp_select_x_for_type"]').val() == '_term_') {
            if ($('input[name="pp_select_x_operation"]').val() == 'associate' && $('input[name="pp_select_x_mod_type"]').val() != 'additional') {
                $('#select-exception-' + $('select[name="pp_select_x_via_type"]').val()).find('input.menu-item-checkbox[value="0"]').siblings('label').first().html(ppRestrict.noParent);
            } else {
                $('#select-exception-' + $('select[name="pp_select_x_via_type"]').val()).find('input.menu-item-checkbox[value="0"]').siblings('label').first().html(ppRestrict.none);
            }
        } else {
            if ($('input[name="pp_select_x_operation"]').val() == 'associate' && $('input[name="pp_select_x_mod_type"]').val() != 'additional') {
                $('#select-exception-' + $('select[name="pp_select_x_for_type"]').val()).find('input.menu-item-checkbox[value="0"]').siblings('label').first().html(ppRestrict.noParent);
            } else {
                $('#select-exception-' + $('select[name="pp_select_x_for_type"]').val()).find('input.menu-item-checkbox[value="0"]').siblings('label').first().html(ppRestrict.none);
            }
        }
    }

    $('input[name="pp_select_x_mod_type"]').on('change', presspermitUpdateItemNoneCaption);

    $(document).on('click', '#pp_select_x_item_assign', function(e){
        presspermitLastItemAssign = $(this).prop('checked');
    });

    $(document).on('click', '#pp_select_x_child_assign', function(e){
        presspermitLastChildAssign = $(this).prop('checked');
    });

    var presspermitLastOp = '';
    var presspermitLastModType = '';
    var presspermitLastViaType = '';
    var presspermitLastItemAssign = '';
    var presspermitLastChildAssign = '';

    var presspermitDrawOperations = function (data, txtStatus) {
        sel = $('td.pp-select-x-operation');
        sel.html(data);
        sel.triggerHandler('change');
        $('.pp-select-x-operation').show();

        if (presspermitLastOp && $('input[name="pp_select_x_operation"][value="' + presspermitLastOp + '"]').length) {
            $('input[name="pp_select_x_operation"][value="' + presspermitLastOp + '"]').click();
        } else {
            $('input[name="pp_select_x_operation"]').first().click();
        }

        presspermitXajaxUI_done();
    }

    var presspermitDrawViaTypes = function (data, txtStatus) {
        sel = $('select[name="pp_select_x_via_type"]');
        sel.html(data);
        sel.triggerHandler('change');
        $('.pp-select-x-via-type').show();

        if (presspermitLastViaType && $('select[name="pp_select_x_via_type"] option[value="' + presspermitLastViaType + '"]').length) {
            $('select[name="pp_select_x_via_type"]').val(presspermitLastViaType).change();
        }

        presspermitXajaxUI_done();
    }

    var presspermitDrawModificationTypes = function (data, txtStatus) {
        sel = $('td.pp-select-x-mod-type');
        sel.html(data);
        sel.triggerHandler('change');
        $('.pp-select-x-mod-type').show();

        if (presspermitLastModType && $('input[name="pp_select_x_mod_type"][value="' + presspermitLastModType + '"]').length) {
            $('input[name="pp_select_x_mod_type"][value="' + presspermitLastModType + '"]').click();
        } else {
            $('input[name="pp_select_x_mod_type"]').first().click();
        }

        pressPermitNoneItemVisibility();
        presspermitXajaxUI_done();
    }

    var presspermitDrawAssignFor = function (data, txtStatus) {
        dv = $('#pp_select_x_assign_for');
        dv.html(data);

        if (dv.children().length > 1)
            $('.pp-select-x-assign-for').show();
        else
            $('.pp-select-x-assign-for').hide();

        if (typeof presspermitLastItemAssign === 'boolean' && $('#pp_select_x_item_assign:visible').length) {
            $('#pp_select_x_item_assign:visible').prop('checked', presspermitLastItemAssign);
        }

        if ($('#pp_select_x_child_assign:visible').length) {
            $('#pp_select_x_child_assign:visible').prop('checked', presspermitLastChildAssign);
        }

        presspermitXajaxUI_done();
    }

    var presspermitDrawStatus = function (data, txtStatus) {
        dv = $('td.pp-select-x-status');
        dv.html(data);

        if (dv.children().length > 1)
            $('.pp-select-x-status').show();
        else
            $('.pp-select-x-status').hide();

        if ($('.pp-select-x-status input:checkbox').length == 1) {
            $('.pp-select-x-status input:checkbox').prop('checked', true);
        }

        presspermitXajaxUI_done();
    }

    var presspermitXajaxUI = function (op, handler, item_id) {
        if ('get_presspermitItemPath' != op) {
            $('#pp_add_exception select').prop('disabled', true);
            $('#pp_add_exception_waiting').show();
        }

        if (typeof item_id == 'undefined')
            item_id = 0;

        var data = {
            'pp_ajax_agent_exceptions': op,
            'pp_for_type': $('select[name="pp_select_x_for_type"]').val(),
            'pp_operation': $('input[name="pp_select_x_operation"]').val(),
            'pp_via_type': $('select[name="pp_select_x_via_type"]').val(),
            'pp_mod_type': $('input[name="pp_select_x_mod_type"]').val(),
            'pp_agent_id': ppRestrict.agentID,
            'pp_agent_type': ppRestrict.agentType,
            'pp_item_id': item_id
        };
        $.ajax({
            url: ppRestrict.ajaxurl,
            data: data,
            dataType: "html",
            success: handler,
            error: presspermitXajaxUIFailure
        });
    }

    var presspermitXajaxUI_done = function () {
        $('#pp_add_exception select').prop('disabled', false);
        $('#pp_add_exception_waiting').hide();

        $.event.trigger({type: "pp_exceptions_ui"});
    }

    var presspermitXajaxUIFailure = function (data, txtStatus) {
        $('#pp_add_exception .waiting').hide();
        return;
    }

    var presspermitExceptionsSearchTimer;
    $('.pp-quick-search').keypress(function (e) {
        var t = $(this);

        if (13 == e.which) {
            presspermitUpdateQuickSearchResults(t);
            return false;
        }

        if (presspermitExceptionsSearchTimer) clearTimeout(presspermitExceptionsSearchTimer);

        presspermitExceptionsSearchTimer = setTimeout(function () {
            presspermitUpdateQuickSearchResults(t);
        }, 400);
    }).attr('autocomplete', 'off');

    var presspermitUpdateQuickSearchResults = function (input) {
        var panel, params,
            minSearchLength = 2,
            q = input.val();

        if (q.length < minSearchLength) return;

        panel = input.parents('.tabs-panel');
        params = {
            'action': 'pp-menu-quick-search',
            'response-format': 'markup',
            'menu': $('#menu').val(),
            'menu-settings-column-nonce': $('#menu-settings-column-nonce').val(),
            'q': q,
            'type': input.attr('name')
        };

        $('img.waiting', panel).show();

        $.post(ppItems.ajaxurl, params, function (menuMarkup) {
            presspermitProcessQuickSearchResponse(menuMarkup, params, panel);
        });
    }

    /**
     * Process the quick search response into a search result
     *
     * @param string resp The server response to the query.
     * @param object req The request arguments.
     * @param jQuery panel The tabs panel we're searching in.
     */
    var presspermitProcessQuickSearchResponse = function (resp, req, panel) {
        var matched, newID,
            takenIDs = {},
            form = document.getElementById('nav-menu-meta'),
            pattern = new RegExp('menu-item\\[(\[^\\]\]*)', 'g'),
            $items = $('<div>').html(resp).find('li'),
            $item;

        if (!$items.length) {
            $('.categorychecklist', panel).html('<li><p>' + ppItems.noResultsFound + '</p></li>');
            $('img.waiting', panel).hide();
            return;
        }

        $items.each(function () {
            $item = $(this);

            // make a unique DB ID number
            matched = pattern.exec($item.html());

            if (matched && matched[1]) {
                newID = matched[1];
                while (form.elements['menu-item[' + newID + '][menu-item-type]'] || takenIDs[newID]) {
                    newID--;
                }

                takenIDs[newID] = true;
                if (newID != matched[1]) {
                    $item.html($item.html().replace(new RegExp(
                        'menu-item\\[' + matched[1] + '\\]', 'g'),
                        'menu-item[' + newID + ']'
                    ));
                }
            }
        });

        $('.categorychecklist', panel).html($items);
        $('img.waiting', panel).hide();
    }
    // ========== End "Set Specific Permissions" UI scripts ==========


    // ========== Begin "Edit Exception" Submission scripts ==========
    $('#pp_current_exceptions input').on('click', function (e) {
        $(this).closest('div.pp-current-type-roles').find('div.pp-exception-bulk-edit').show();
    });

    $('#pp_current_exceptions .pp_check_all').on('click', function (e) {
        $(this).closest('td').find('input[name="pp_edit_exception[]"][disabled!="true"]').prop('checked', $(this).is(':checked'));
    });

    var presspermitCurrentExceptionsAjaxDone = function () {
        $('#pp_current_exceptions input.submit-edit-item-exception').prop('disabled', false);
        $('#pp_current_exceptions .waiting').hide();
    }

    var presspermitRemoveExceptionsDone = function (data, txtStatus) {
        presspermitCurrentExceptionsAjaxDone();

        if (!data)
            return;

        var startpos = data.indexOf('<!--ppResponse-->');
        var endpos = data.indexOf('<--ppResponse-->');

        if ((startpos == -1) || (endpos <= startpos))
            return;

        data = data.substr(startpos + 17, endpos - startpos - 17);

        var deleted_ass_ids = data.split('|');

        $.each(deleted_ass_ids, function (index, value) {
            cbid = $('#pp_current_exceptions input[name="pp_edit_exception[]"][value="' + value + '"]').attr('id');
            $('#' + cbid).closest('label').parent().remove();

            var ass_ids = value.split(','); // some checkboxes represent both an item and child exception_item
            for (i = 0; i < ass_ids.length; ++i) {
                $('#pp_current_exceptions label[class~="from_' + ass_ids[i] + '"]').parent().remove();
            }
        });
    }

    var presspermitEditExceptionsDone = function (data, txtStatus) {
        presspermitCurrentExceptionsAjaxDone();

        if (!data)
            return;

        var startpos = data.indexOf('<!--ppResponse-->');
        var endpos = data.indexOf('<--ppResponse-->');

        if ((startpos == -1) || (endpos <= startpos))
            return;

        data = data.substr(startpos + 17, endpos - startpos - 17);

        var edit_data = data.split('~');
        var operation = edit_data[0];
        var set_class = '';

        switch (operation) {
            case 'exceptions_propagate':
                set_class = 'role_both';
                break;
            case 'exceptions_unpropagate':
                set_class = '';
                break;
            case 'exceptions_children_only':
                set_class = 'role_ch';
                break;
            case 'exceptions_mirror':
                set_class = 'exc-copied';
                set_message = ppRestrict.mirrorDone;
                break;
            case 'exceptions_convert':
                set_class = 'exc-copied';
                set_message = ppRestrict.convertDone;
                break;
            default:
                return;
        }

        var edited_eitem_ids = edit_data[1].split('|');

        $.each(edited_eitem_ids, function (index, value) {
            cbid = $('#pp_current_exceptions input[name="pp_edit_exception[]"][value="' + value + '"]').attr('id');
            
            if (('exceptions_mirror' == operation) || ('exceptions_convert' == operation)) {
                $('#' + cbid).closest('div').find('label input').attr('class', set_class);
                $('#' + cbid).prop('checked', false);
                $('#' + cbid).closest('div.pp-current-type-roles').find('div.pp-exception-bulk-edit div.mirror-confirm').html(set_message).show();
            } else {
                $('#' + cbid).closest('div').find('label').attr('class', set_class);

                // temp workaround for Ajax UI limitation
                if (('exceptions_children_only' == operation) || ('exceptions_unpropagate' == operation)) {
                    $('#' + cbid).closest('div').find('input').prop('checked', false);
                    $('#' + cbid).closest('div').find('input').prop('disabled', true);
                    $('#' + cbid).closest('div').find('label').attr('title', ppRestrict.reloadRequired);
                }
            }
        });
    }

    $('#pp_current_exceptions input.submit-edit-item-exception').on('click', function (e) {
        var action = $(this).closest('div.pp-current-type-roles').find('div.pp-exception-bulk-edit select').first().val();

        if (!action) {
            alert(ppRestrict.noAction);
            return false;
        }

        var selected_ids = new Array();
        $(this).closest('div.pp-current-exceptions').find('input[type="checkbox"]:checked').each(function () {
            selected_ids.push($(this).attr('value'));
        });

        var rids = selected_ids.join('|');

        if (!rids) {
            return false;
        }

        $(this).prop('disabled', true);
        $(this).closest('div').find('.waiting').show();

        switch (action) {
            case 'remove':
                presspermitAjaxSubmit('exceptions_remove', presspermitRemoveExceptionsDone, rids);
                break
            default:
                presspermitAjaxSubmit('exceptions_' + action, presspermitEditExceptionsDone, rids);
                break
        }

        return false;
    });

    var presspermitAjaxSubmit = function (op, handler, rids) {
        var data = {
            'pp_ajax_agent_permissions': op,
            'agent_type': ppRestrict.agentType,
            'agent_id': ppRestrict.agentID,
            'pp_eitem_ids': rids
        };
        $.ajax({
            url: ppRestrict.ajaxurl,
            data: data,
            dataType: "html",
            success: handler,
            error: presspermitAjaxSubmitFailure
        });
    }

    var presspermitAjaxSubmitFailure = function (data, txtStatus) {
        return;
    }

    $(document).on('mouseenter', 'div.pp-current-type-roles label', function () {
        var func = function (lbl) {
            $(lbl).parent().find('a').show();
        }
        window.setTimeout(func, 300, $(this));
    });

    // ========== End "Edit Exception" Submission scripts ==========
});

