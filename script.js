/* DOKUWIKI:include phptagengine/yui/yahoo-dom-event.js */
/* DOKUWIKI:include phptagengine/yui/autocomplete.js */
/* DOKUWIKI:include phptagengine/phptagengine.js */

function getCloudItem(tag) {
    var cloud = $('tagging_tagcloud');

    for (var i = 0 ; i < cloud.childNodes.length ; ++i) {
        if (cloud.childNodes[i].firstChild && cloud.childNodes[i].firstChild.innerHTML === tag) {
            return cloud.childNodes[i];
        }
    }
    return null;
}

var yac_tags = new YAHOO.widget.DS_JSArray(tagging_tags);

addInitEvent(function () {
    var form = $('pte_tag_edit_form_' + JSINFO.id);
    if (!form) return;

    var cloud = $('tagging_tagcloud');
    var taglist = $('pte_tags_list_' + JSINFO.id);
    var editbtn = null;
    while (taglist.hasChildNodes()) {
        var cur = taglist.childNodes[0];
        if (!cur.tagName || cur.innerHTML.match(/\s*\(none\)\s*/)) {
            taglist.removeChild(cur);
            continue;
        }
        if (cur.className === 'pte_edit') {
            editbtn = cur;
            taglist.removeChild(cur);
            continue;
        }
        var target = getCloudItem(cur.firstChild.innerHTML);
        if (target) {
            target.className += ' tagging_owntag';
            taglist.removeChild(cur);
        } else {
            cloud.appendChild(cur);
            cur.className += ' t0 tagging_owntag';
        }
    }
    taglist.appendChild(editbtn);
    form.removeChild(form.getElementsByTagName('label')[0]);
});

pte.save_tags_handler = function() {
    if (pte.req.readyState !== 4) {
        return;
    }

    if (pte.req.status !== 200) {
        return;
    }

    var result = pte.req.responseXML.getElementsByTagName('result')[0];
    if (result.getAttribute('success') !== 'y' || result.getAttribute('action') !== 'save_tags') {
        alert('Error saving tags: ' + pte.req.responseText);
        return;
    }

    var tags = result.getElementsByTagName('tags')[0].firstChild.nodeValue;
    var item = result.getAttribute('item');

    document.getElementById('pte_tags_edit_field_' + item).value = tags + ' ';
    if (tags.indexOf(' ') != -1) {
        tags = tags.split(' ');
    } else {
        tags = new Array(tags);
    }

    var cloud = $('tagging_tagcloud');

    for (var n = 0; n < cloud.childNodes.length ; ++n) {
        if (cloud.childNodes[n].id && cloud.childNodes[n].id.match(/pte_tag_/)) {
            cloud.removeChild(cloud.childNodes[n]);
        } else if (cloud.childNodes[n].className) {
            cloud.childNodes[n].className = cloud.childNodes[n].className.replace(/(^|\s+)tagging_owntag($|\s+)/, '');
        }
    }

    for (var i = 0; i < tags.length; i++) {
        var clouditem = getCloudItem(tags[i]);
        if (clouditem) {
            clouditem.className += ' tagging_owntag';
        } else {
            var tag_node = pte.create_tag_nodes([tags[i]], result)[0];
            tag_node.className += ' t0 tagging_owntag';
            cloud.appendChild(tag_node);
        }
    }
    pte.item_tag_view(item, 'view');
};

