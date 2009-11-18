// Copyright (c) 2006 Alex King. All rights reserved.
// http://www.alexking.org/software/phptagengine/
//
// Released under the LGPL license
// http://www.opensource.org/licenses/lgpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// **********************************************************************

pte.pipe = function(url, handler, content) {
	if (typeof content == 'undefined') {
		content = '';
	}
	pte.req = false;
	if (window.XMLHttpRequest) {
// branch for native XMLHttpRequest object
		try {
			pte.req = new XMLHttpRequest();
		} 
		catch(e) {
			pte.req = false;
		}
	} 
	else if (window.ActiveXObject) {
// branch for IE/Windows ActiveX version
		try {
			pte.req = new ActiveXObject("Msxml2.XMLHTTP");
		} 
		catch(e) {
			try {
				pte.req = new ActiveXObject("Microsoft.XMLHTTP");
			} 
			catch(e) {
				pte.req = false;
			}
		}
	}
	if (pte.req) {
// debug
// prompt('piping:', url);
		pte.req.onreadystatechange = handler;
		pte.req.open("GET", url, true);
		pte.req.send(content);
	}
};

pte.save_tags = function(user, item, tags, type) {
	pte.item_tag_view(item, 'saving');
	var url = this.ajax_handler + (this.ajax_handler.match(/\?/) ? '&' : '?') + 'pte_action=save_tags'
		+ '&user=' + encodeURIComponent(user)
		+ '&item=' + encodeURIComponent(item)
		+ '&tags=' + encodeURIComponent(tags)
		+ '&type=' + encodeURIComponent(type)
		;
	pte.pipe(url, pte.save_tags_handler);
};

pte.save_tags_handler = function() {
	if (pte.req.readyState == 4) {
		if (pte.req.status == 200) {
			var result = pte.req.responseXML.getElementsByTagName('result')[0];
			if (result.getAttribute('success') == 'y' && result.getAttribute('action') == 'save_tags') {
				var tags = result.getElementsByTagName('tags')[0].firstChild.nodeValue;
				var item = result.getAttribute('item');
				
				var tags_node = document.getElementById('pte_tags_list_' + item);
				tags_node.innerHTML = '';

				if (tags == '') {
					pte.clear_tags_display(item);
				}
				else {
					document.getElementById('pte_tags_edit_field_' + item).value = tags + ' ';
					if (tags.indexOf(' ') != -1) {
						tags = tags.split(' ');
					}
					else {
						tags = new Array(tags);
					}
					if (tags.length > 0) {
						var tag_nodes = pte.create_tag_nodes(tags, result);
						for (var i = 0; i < tag_nodes.length; i++) {
							tags_node.appendChild(tag_nodes[i]);
						}
					}
				}
				tags_node.appendChild(pte.create_edit_node(item));
				
				pte.item_tag_view(item, 'view');
			}
			else {
// TODO
alert('Error saving tags: ' + pte.req.responseText);
			}
		}
	}
};

pte.remove_tag = function(item, tag, type) {
	var url = pte.ajax_handler + '?pte_action=remove_tag&item=' + encodeURIComponent(item)
		+ '&tag=' + encodeURIComponent(tag) + '&type=' + encodeURIComponent(type);
	pte.pipe(url, pte.remove_tag_handler);
};

pte.remove_tag_handler = function() {
	if (pte.req.readyState == 4) {
		if (pte.req.status == 200) {
			var result = pte.req.responseXML.getElementsByTagName('result')[0];
			if (result.getAttribute('success') == 'y' && result.getAttribute('action') == 'remove_tag') {
				var item = result.getAttribute('item');
				var tag = result.getElementsByTagName('tag')[0].firstChild.nodeValue;
				var parent = document.getElementById('pte_tags_list_' + item);
				var child = document.getElementById('pte_tag_' + item + '_' + tag);
				parent.removeChild(child);
			}
		}
	}
};

pte.edit_tag = function(tag) {
	var url = pte.ajax_handler + '?pte_action=edit_tag&tag=' + encodeURIComponent(tag);
	pte.pipe(url, pte.edit_tag_handler);
};

pte.edit_tag_handler = function() {
	if (pte.req.readyState == 4) {
		if (pte.req.status == 200) {
			var result = pte.req.responseXML.getElementsByTagName('result')[0];
			if (result.getAttribute('success') == 'y' && result.getAttribute('action') == 'edit_tag') {
				var old_tag = result.getElementsByTagName('old_tag')[0].firstChild.nodeValue;
				var new_tag = result.getElementsByTagName('new_tag')[0].firstChild.nodeValue;
				var old_tag_node = document.getElementById('pte_tag_' + old_tag);
				var new_tag_node = document.getElementById('pte_tag_' + new_tag);
				if (new_tag_node) {
					old_tag_node.parentNode.removeChild(old_tag_node);
				}
				else if (old_tag_node) {
					old_tag_node.id = 'pte_tag_' + new_tag;
					old_tag_node.getElementsByTagName['a'][0].innerHTML = new_tag;
				}
			}
		}
	}
};

pte.delete_tag = function(tag) {
	var url = pte.ajax_handler + '?pte_action=delete_tag&tag=' + encodeURIComponent(tag);
	pte.pipe(url, pte.delete_tag_handler);
};

pte.delete_tag_handler = function() {
	if (pte.req.readyState == 4) {
		if (pte.req.status == 200) {
			var result = pte.req.responseXML.getElementsByTagName('result')[0];
			if (result.getAttribute('success') == 'y' && result.getAttribute('action') == 'delete_tag') {
				var tag = result.getElementsByTagName('tag')[0].firstChild.nodeValue;
				var tag_node = document.getElementById('pte_tag_' + tag);
				if (tag_node) {
					tag_node.parentNode.removeChild(tag_node);
				}
			}
		}
	}
};

pte.create_tag_nodes = function(tags, result) {
	var tag_nodes = new Array();
	var item = result.getAttribute('item');
	var type = result.getAttribute('type');
	for (var i = 0; i < tags.length; i++) {
		var tag_link = document.createElement('a');
		tag_link.innerHTML = tags[i];
		tag_link.href = pte.get_tag_browse_url(tags[i], result.getAttribute('type'));

		var tag_node = document.createElement('li');
		tag_node.id = 'pte_tag_' + item + '_' + tags[i];
		tag_node.appendChild(tag_link);
		tag_node.appendChild(document.createTextNode(' '));

		if (pte.show_remove_links) {
			var remove_link = document.createElement('a');
			remove_link.innerHTML = pte.button_display('delete');
			remove_link.href = "javascript:void(pte.remove_tag('" + item + "', '" + tags[i] + "', '" + type + "'));";
			tag_node.appendChild(remove_link);
		}

		tag_nodes.push(tag_node);
	}
	return tag_nodes;
};

pte.get_tag_browse_url = function(tag, type) {
	var url = pte.tag_browse_url.replace('<tag>', tag);
	return url.replace('<type>', type);
};

pte.item_tag_view = function(item, display) {
	document.getElementById('pte_tags_list_' + item).style.display = 'none';
	document.getElementById('pte_tags_edit_' + item).style.display = 'none';
	document.getElementById('pte_tags_saving_' + item).style.display = 'none';
	switch (display) {
		case 'view':
			display = 'list';
		case 'edit':
		case 'saving':
			document.getElementById('pte_tags_' + display + '_' + item).style.display = 'inline';
			if (display == 'edit') {
				document.getElementById('pte_tags_edit_field_' + item).focus();
			}
			break;
	}
};

pte.clear_tags_display = function(item) {
// clear view
	var tags = document.getElementById('pte_tags_list_' + item);
	tags.innerHTML = '';
	banner = document.createElement('li');
	banner.innerHTML = pte.strings['data_none'];
	tags.appendChild(banner);
	pte.item_tag_view(item, 'view');
// clear edit
	document.getElementById('pte_tags_edit_field_' + item).value = '';
};

pte.button_display = function(type) {
	var display = '';
	switch (type) {
		case 'edit':
			var scase = pte.edit_button_display;
			var url = pte.edit_button_image_url;
			break;
		case 'delete':
			var scase = pte.delete_button_display;
			var url = pte.delete_button_image_url;
			break;
		default:
			return display;
	}
	switch (scase) {
		case 'text':
			if (pte.strings['action_' + type + '_text_icon']) {
				display = pte.strings['action_' + type + '_text_icon'];
			}
			else {
				display = pte.strings['action_' + type];
			}
			break;
		case 'image':
			display = '<img src="' + url + '" alt="' + pte.slash(pte.strings['action_' + type], '"') + '" class="pte_button_' + type + '" />';
			break;
	}
	return display;
};

pte.create_edit_node = function(item) {
	var edit_link = document.createElement('a');
	edit_link.innerHTML = '[' + pte.button_display('edit') + ']';
	edit_link.href = "javascript:void(pte.item_tag_view('" + item + "', 'edit'));";

	var edit_node = document.createElement('li');
	edit_node.className = 'pte_edit';
	edit_node.appendChild(edit_link);

	return edit_node;
};

pte.slash = function(str, escape) {
	return str.replace(escape, '\\' + escape);
};
