jQuery(document).ready(function ($) {
    $('.postbox-header').each(function () {
        if ($(this).find('[data-toggle="tooltip"]').length > 0) {
            $(this).find('.hndle, .handlediv').off('click.postboxes');

            $(this).on('click', '.hndle, .handlediv', function(e) {
                if ($(e.target).closest('[data-toggle="tooltip"].click').length === 0) {
                    var postbox = $(this).closest('.postbox');
                    postbox.toggleClass('closed');
                }
            });
        
            $(this).on('click', '[data-toggle="tooltip"].click', function (event) {
                if ($(event.target).is('[data-toggle="tooltip"].click') || $(event.target).closest('[data-toggle="tooltip"].click').length) {
                    event.preventDefault();
                    event.stopPropagation();
                    $(this).toggleClass('is-active');
                }
            });
        }
    });

    $("div.pp-user-meta-search select").each(function () {  // deal with browser retention of dropdown selection prior to page reload
        if ($(this).val()) {
            $(this).parent().show();
            $(this).siblings().show();
        }
    });

    $(".pp-user-meta-search select").on('click', function (e) {
        $(this).siblings().show();
    });

    $(".pp-user-meta-search select").on('change', function (e) {
        $(this).parent().find('input').focus();
    });

    $("span.pp-usermeta-field-more").on('click', function (e) {
        $(this).parent().next('div.pp-user-meta-search').show().find('select').focus();
        $(this).parent().next('div.pp-user-meta-search').children().show();
        $(this).hide();
    });

    $('div.pp-user-meta-search input').on('keydown', function (e) {
        // this will catch pressing enter and call find function
        if (e.keyCode == 13) {
            $(this).closest('td').find('button.pp-agent-search-submit').click();
            //ajax_request($(this).val());
            e.preventDefault();
        }
    });
});

jQuery(document).ready(function ($) {
    $(".pp-hidden-subdiv h3").on('click', function (e) {
        e.preventDefault();
        $(this).siblings(".hide-if-js").show();
    });

    $('span.pp-alert').each(function () {
        var msg = $(this).html();
        if (msg) {
            $('<div id="message" class="error fade">' + msg + '</div>').insertAfter('#wpbody h2');
        }
    });
});

function presspermitEscapeID(myid) {
    return CSS.escape(myid);
}

function presspermitShowElement(classAttrib, $) {
    if (-1 == classAttrib.indexOf(' ')) {
        $('#' + classAttrib).show();
    } else {
        ppClass = presspermitMatchClass(classAttrib);
        if (ppClass)
            $('#' + ppClass).show();
    }
}

function presspermitShowClass(classAttrib, $) {
    if (-1 == classAttrib.indexOf(' ')) {
        $('.' + classAttrib).show();
    } else {
        ppClass = presspermitMatchClass(classAttrib);
        if (ppClass)
            $('.' + ppClass).show();
    }
}

function presspermitMatchClass(classAttrib, $) {
    var elemClasses = classAttrib.split(' ');
    for (i = 0; i < elemClasses.length; i++) {
        if (elemClasses[i].indexOf("pp-") == 0 || elemClasses[i].indexOf("pp_") == 0 || elemClasses[i].indexOf("-pp_") >= 0) {
            return elemClasses[i];
            break;
        }
    }

    return false;
}
