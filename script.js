/* DOKUWIKI:include phptagengine/yui/yahoo-dom-event.js */
/* DOKUWIKI:include phptagengine/yui/autocomplete.js */
/* DOKUWIKI:include phptagengine/phptagengine.js */

addInitEvent(function () {
    var form = $('pte_tag_edit_form_start');
    if (!form) return;

    var cloud = $('tagging_tagcloud');

    var taglist = $('pte_tags_list_start');
    while (taglist.hasChildNodes()) {
        var cur = taglist.childNodes[0];
        if (!cur.tagName) {
            taglist.removeChild(cur);
            continue;
        }
        var target = null;
        for (var i = 0 ; i < cloud.childNodes.length ; ++i) {
            if (cloud.childNodes[i].firstChild && cloud.childNodes[i].firstChild.innerHTML === cur.firstChild.innerHTML) {
                target = cloud.childNodes[i];
                break;
            }
        }
        if (target) {
            target.className += ' tagging_owntag';
            taglist.removeChild(cur);
        } else {
            cloud.appendChild(cur);
            cur.className += ' t0 tagging_owntag';
        }
    }
});
