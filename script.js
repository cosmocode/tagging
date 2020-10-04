/* DOKUWIKI:include script/editable.js */
/* DOKUWIKI:include script/admin.js */
/* DOKUWIKI:include script/search.js */

jQuery(function () {

    const url = DOKU_BASE + 'lib/exe/ajax.php';
    const requestParams = {
        'id':     JSINFO.id,
        'sectok': JSINFO.sectok
    };

    /**
     * Trigger a backend action via AJAX
     *
     * @param {object} params Required "call" is the DokuWiki event name, plus optional data object(s)
     * @returns {*}
     */
    const callBackend = function(params, successCallback, failureCallback) {
        const mergedParams = jQuery.extend(
            {},
            requestParams,
            params
        );

        return jQuery.ajax({
                url     : url,
                data    : mergedParams,
                type    : 'POST'
            })
            .done(jQuery.proxy(function(response) {
                successCallback(response);
            }, this))
            .fail(jQuery.proxy(function(xhr) {
                var msg = typeof xhr === 'string' ? xhr : xhr.responseText || xhr.statusText || 'Unknown error';
                failureCallback(msg);
            }, this));
    };

    /**
     * Add JavaScript confirmation to the User Delete button
     */
    jQuery('#tagging__del').click(function () {
        return confirm(LANG.del_confirm);
    });

    var $form = jQuery('#tagging__edit');
    if (!$form.length) return;

    var $btn = jQuery('form.btn_tagging_edit');
    var $btns = jQuery('#tagging__edit_buttons_group');

    $btn.submit(function (e) {
        $btns.hide();
        $form.show();
        var $input = $form.find('textarea');
        var len = $input.val().length;
        $input.focus();
        try {
            $input[0].setSelectionRange(len, len);
        } catch (ex) {
            // ignore stupid IE
        }

        e.preventDefault();
        e.stopPropagation();
        return false;
    });

    var $admin_toggle_btn = jQuery('#tagging__edit_toggle_admin')
            .checkboxradio()
            .click(function () {
                jQuery('div.plugin_tagging_edit ul.tagging_cloud a').editable('toggleDisabled');
            }),
        add_editable = function () {
            //no editable button - we are not the admin
            if ($admin_toggle_btn.length === 0) return;

            jQuery('div.plugin_tagging_edit ul.tagging_cloud a')
                .editable({
                    disabled: !$admin_toggle_btn[0].checked,
                    label:    LANG.plugins.tagging.admin_change_tag,
                    url:      DOKU_BASE + 'lib/exe/ajax.php?call=plugin_tagging_admin_change',
                    params:   {
                        'call':   'plugin_tagging_admin_change',
                        'id':     JSINFO.id,
                        'sectok': JSINFO.sectok
                    },
                    success:
                              function (response) {
                                  jQuery('div.plugin_tagging_edit ul.tagging_cloud').html(response.html_cloud);
                                  $form.find('textarea').val(response.tags_edit_value);
                                  add_editable();
                              }
                });
        };

    add_editable();

    jQuery('#tagging__edit_save').click(function (e) {
        jQuery('div.plugin_tagging_edit ul.tagging_cloud').load(
            DOKU_BASE + 'lib/exe/ajax.php',
            $form.serialize(),
            add_editable
        );
        $btns.show();
        $form.hide();

        e.preventDefault();
        e.stopPropagation();
        return false;
    });

    jQuery('#tagging__edit_cancel').click(function (e) {
        $btns.show();
        $form.hide();

        e.preventDefault();
        e.stopPropagation();
        return false;
    });

    jQuery('.btn_tagging_edit button, #tagging__edit_save, #tagging__edit_cancel').button();

    /**
     * below follows auto completion as described on  http://jqueryui.com/autocomplete/#multiple-remote
     */

    function split(val) {
        return val.split(/,\s*/);
    }

    function extractLast(term) {
        return split(term).pop();
    }

    $form.find('textarea')
    // don't navigate away from the field on tab when selecting an item
        .bind('keydown', function (event) {
            if (event.keyCode === jQuery.ui.keyCode.TAB &&
                jQuery(this).data('ui-autocomplete').menu.active) {
                event.preventDefault();
            }
        })
        .autocomplete({
            source: function (request, response) {
                jQuery.getJSON(DOKU_BASE + 'lib/exe/ajax.php?call=plugin_tagging_autocomplete', {
                    term: extractLast(request.term)
                }, response);
            },
            search: function () {
                // custom minLength
                var term = extractLast(this.value);
                if (term.length < 2) {
                    return false;
                }
                return true;
            },
            focus:  function () {
                // prevent value inserted on focus
                return false;
            },
            select: function (event, ui) {
                var terms = split(this.value);
                // remove the current input
                terms.pop();
                // add the selected item
                terms.push(ui.item.value);
                // add placeholder to get the comma-and-space at the end
                terms.push('');
                this.value = terms.join(', ');
                return false;
            }
        });

    /**
     * Below follows code for the edit dialog
     */
    jQuery('.plugin_tagging__edit').click(plugin_tagging_edit);

    /**
     * Menu item on-click function:
     * Gets the tag list and creates the edit dialog.
     */
    function plugin_tagging_edit() {
        jQuery.ajax({
            url: DOKU_BASE + 'lib/exe/ajax.php',
            type: 'POST',
            data: {
                call: 'plugin_tagging_get'
            },
            dataType: 'json',

            success: function (data) {
                tags = get_tags_from_response(data);
                plugin_tagging_show_edit_dialog(tags);
            },

            error: function (xhr, status, error) {
            }
        });

    }

    /**
     * Build HTML code for the tags list.
     *
     * @param {array} tags Array of tags
     * @returns {string} HTML code
     */
    function edit_dialog_build_tag_list(tags) {
        var table = '<div><table id="tag_list">';
        if (tags && tags.length > 0) {
            tags.forEach(function (tag) {
                var htmlTag = jQuery(tag).html();
                var button = '<a class="tagging_delete_button ui-button ui-widget ui-corner-all" href="javascript:void(0);" data-tag="' + htmlTag + '">'
                    + LANG.plugins.tagging.edit_dialog_button_delete + '</a>';
                table += '<tr id="' + htmlTag + '"><td>' + tag + '</td><td>' + button + '</td></tr>';
            });
        } else {
            table += '<tr><td>' + LANG.plugins.tagging.notags + '</td></tr>';
        }
        table += '</table></div>';

        return table;
    }

    /**
     * Create and show the edit tags dialog.
     * Tags can be added and removed from the current page.
     * Closing the dialog refreshes the browser page.
     *
     * @param {array} tags Array of tags
     * @returns {string} HTML code
     */
    function plugin_tagging_show_edit_dialog(tags) {
        var content = '<div id="tagging__edit_dialog" title="' + LANG.plugins.tagging.edit_dialog_title +'"></div>';

        dialog = jQuery(content).dialog({
            resizable: false,
            width: 480,
            height: 'auto',
            modal: true,
            buttons: {
                Close: function() {
                    jQuery(this).dialog('close');
                }
            },
            close: function( event, ui ) {
                jQuery(this).dialog('destroy');
                location.reload();
            }
        });
        jQuery(dialog).append('<p>' + LANG.plugins.tagging.edit_dialog_text_list + '</p>');

        table = edit_dialog_build_tag_list(tags);
        jQuery(dialog).append(table);

        jQuery('.tagging_delete_button').button({
            icon: "ui-icon-trash"
        });

        var button = '<a class="tagging_add_button ui-button ui-widget ui-corner-all" href="javascript:void(0);" >'
            + LANG.plugins.tagging.edit_dialog_button_add + '</a>';
        var input = '<label for="tagname">' + LANG.plugins.tagging.edit_dialog_label_add + '</label><br>'
            + '<input type="text" id="new_tag_name" name="tagname">' + button;
        jQuery(dialog).append(input);

        jQuery('.tagging_add_button').button({
            icon: "ui-icon-plus"
        });

        jQuery('.tagging_add_button').click(edit_dialog_add_tag);
        jQuery('.tagging_delete_button').click(edit_dialog_delete_tag);

        jQuery('#new_tag_name').keyup(function (event) {
            if (event.which === 13) {
                edit_dialog_add_tag();
            } else {
                setTimeout(edit_dialog_validate_input, 250);
            }
        });

        return dialog;
    }

    /**
     * Callback function for validation of the input field '#new_tag_name'.
     *
     * @returns {boolean} true if valid, false otherwise
     */
    function edit_dialog_validate_input() {
        var tag = jQuery('#new_tag_name').val(),
            valid = true;

        if (tag.length > 0) {
            var $cells = jQuery('#tag_list td:first-child');
            for (var cell of $cells) {
                if (tag === cell.textContent) {
                    // Ignore duplicates.
                    valid = false;
                    break;
                }
            }
        } else {
            valid = false;
        }

        var input = jQuery('#new_tag_name');
        if (valid) {
            input.addClass('valid_input');
            input.removeClass('invalid_input');
        } else {
            input.removeClass('valid_input');
            input.addClass('invalid_input');
        }

        return valid;
    }

    /**
     * The function updates the tag list in the edit dialog.
     *
     * @param {array} tags Array of tags
     */
    function edit_dialog_update_tags(tags) {
        table = edit_dialog_build_tag_list(tags);
        jQuery('#tag_list').replaceWith(table);
        jQuery('.tagging_add_button').click(edit_dialog_add_tag);
        jQuery('.tagging_delete_button').click(edit_dialog_delete_tag);
    }

    /**
     * Reads tags from the given Jquery ajax response and returns
     * them as an array (might be empty).
     *
     * @param {object} response Ajax response object
     * @returns {array} Array of tags
     */
    function get_tags_from_response(response) {
        if (response.tags && response.tags.length > 0) {
            tags = response.tags.split(/,\s*/);
        } else {
            tags = [];
        }
        return tags;
    }

    /**
     * Callback function for the add button.
     * Adds a new tag.
     */
    function edit_dialog_add_tag() {
        var tag = jQuery('#new_tag_name').val();

        if (edit_dialog_validate_input()) {
            // Clear input field
            jQuery('#new_tag_name').val('');

            result = callBackend({call: 'plugin_tagging_add_tag', tag: tag},
                function (response) {
                    tags = get_tags_from_response(response);
                    edit_dialog_update_tags(tags);
                },
                function (error) {
                });
        }
    }

    /**
     * Callback function for the delete button.
     * Removes the clicked tag.
     */
    function edit_dialog_delete_tag() {
        var tag = jQuery(this).closest("td").prev().html();

        result = callBackend({call: 'plugin_tagging_remove_tag', tag: tag},
            function (response) {
                tags = get_tags_from_response(response);
                edit_dialog_update_tags(tags);
            },
            function (error) {
            });
    }
});
