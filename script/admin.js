jQuery(function () {

    const url = DOKU_BASE + 'lib/exe/ajax.php';
    const requestParams = {
        'id':     JSINFO.id,
        'sectok': JSINFO.sectok
    };

    const $actionButtons = jQuery('button.action_button');
    const $taggedPages = jQuery('a.tagslist');

    /**
     * Trigger a backend action via AJAX and refresh page on success
     *
     * @param params
     * @returns {*}
     */
    const callBackend = function(params) {
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
                location.reload();
            }, this))
            .fail(jQuery.proxy(function(xhr) {
                var msg = typeof xhr === 'string' ? xhr : xhr.responseText || xhr.statusText || 'Unknown error';
                alert(msg);
            }, this));
    };

    /**
     * Translated modal buttons
     *
     * @type {*[]}
     */
    const actionDialogButtons = [
        {
            text: LANG.plugins.tagging.admin_confirm,
            click: function () {
                const actionData = actionDialog.dialog('option', 'data');
                if (actionData.action === 'delete') {
                    callBackend({call: 'plugin_tagging_delete', tagging: {tid: [actionData.tid]}});
                } else if (actionData.action === 'rename') {
                    const $renameForm = jQuery('#tagging__rename');
                    $renameForm.submit();
                }
            }
        },
        {
            text: LANG.plugins.tagging.admin_cancel,
            click: function () {
                actionDialog.dialog('close');
            }
        },
    ];

    /**
     * Modal for further user interaction
     *
     * @type {jQuery}
     */
    const actionDialog = jQuery("#tagging__action-dialog").dialog({
        autoOpen: false,
        height: 400,
        width: 300,
        modal: true,
        buttons: actionDialogButtons,
        close: function () {
            actionDialog.html('');
        },
        open: function( event, ui ) {
            actionDialogHtml();
        }
    });

    /**
     * Modal listing pages with a given tag
     *
     * @type {jQuery}
     */
    const taggedPagesDialog = jQuery("#tagging__taggedpages-dialog").dialog({
        autoOpen: false,
        height: 400,
        width: 400,
        modal: true,
        close: function () {
            taggedPagesDialog.html('');
        },
        open: function( event, ui ) {
            taggedPagesgHtml();
        }
    });

    /**
     * Injects dialog contents that match the triggered action
     */
    const actionDialogHtml = function() {

        const actionData = actionDialog.dialog('option', 'data');
        let $renameForm;

        if (actionData.action === 'delete') {
            actionDialog.append('<h1>' + LANG.plugins.tagging.admin_delete + ' ' + actionData.tid + '</h1>');
            actionDialog.append('<p>' + LANG.plugins.tagging.admin_sure + '</p>');
        } else if (actionData.action === 'rename') {
            actionDialog.append('<h1>' + LANG.plugins.tagging.admin_rename + ' ' + actionData.tid + '</h1>');
            actionDialog.append('<p>' + LANG.plugins.tagging.admin_newtags + ' </p>');
            actionDialog.append('<form id="tagging__rename"><input type="text" name="newtags" id="tagging__newtags"></form>');

            $renameForm = jQuery('#tagging__rename');
            $renameForm.on('submit', function( event ) {
                event.preventDefault();
                const newValue = jQuery(this).find('#tagging__newtags').val();
                callBackend({call: 'plugin_tagging_rename', tagging: {oldValue: actionData.tid, newValue: newValue} });
            });
        }
        actionDialog.append('<p class="warning">' + LANG.plugins.tagging.admin_warning_all + '</p>');
    };

    /**
     * Displays tagged pages
     */
    const taggedPagesgHtml = function() {

        const data = taggedPagesDialog.dialog('option', 'data');
        const pids = data.pids.split(/,\s*/);

        taggedPagesDialog.append('<h1>Tagged pages</h1>');
        taggedPagesDialog.append('<ul>');
        pids.forEach(function (pid) {
            taggedPagesDialog.append('<li>' + pid + '</li>');
        });
        taggedPagesDialog.append('</ul>');
    };

    /**
     * Action buttons open a dialog window
     */
    $actionButtons.click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        actionDialog.dialog('option', { data: jQuery(this).data() });
        actionDialog.dialog('open');
    });

    /**
     * Tag links open a dialog window
     */
    $taggedPages.click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        taggedPagesDialog.dialog('option', { data: jQuery(this).data() });
        taggedPagesDialog.dialog('open');
    });
});
