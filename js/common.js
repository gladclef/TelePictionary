// scrolls the horizontal scrollbar so that the screen is centered
function scroll_to_center() {
	var docwidth = $(document).width()+parseInt($('body').css("margin-left"));
	var winwidth = $(window).width();
	if (docwidth <= winwidth)
		return;
	var scrollto = (docwidth-winwidth)/2;
	$('body, html').animate({scrollLeft: scrollto}, 500);
};

function set_body_min_height() {
	var win = $(window);
	var body = $("body");
	var sizeBody = function() {
		var newHeight = Math.max(parseInt(body.height()), parseInt(win.height()));
		body.height(newHeight+'px');
	}
	sizeBody();
};

function remove_from_array_by_index(arr, i) {
	return $.grep(arr, function(value, index) {
		return (index != i);
	});
};

function console_log(wt_log) {
	if (window.console)
		console.log(wt_log);
};

function get_unique_id() {
	var retval = "id";
	for (var i = 0; i < 1000000; i++) {
		if ($("#"+retval+i).length == 0)
			return retval+i;
	}
};

function get_set_of_unique_ids(i_count) {
	var i_tid = get_unique_id();
	$("<table id='"+i_tid+"'><tr><td>&nbsp;</td></tr></table>").appendTo("body");
	var jtable = $("#"+i_tid);
	var jrow = $(jtable.children()[0]);
	var a_retval = [i_tid];
	while (a_retval.length < i_count) {
		var i_nextid = get_unique_id();
		$("<td id='"+i_nextid+"'>&nbsp;</td>").appendTo(jrow);
		a_retval.push(i_nextid);
	}
	jtable.remove();
	return a_retval;
};

function kill_children(jobject) {
	var children = jobject.children();
	if (children && children.length > 0)
		for(var i = 0; i < children.length; i++)
			$(children[i]).remove();
};

function get_values_in_form(jform) {
	var a_inputs = jform.find("input");
	var a_selects = jform.find("select");
	var a_textareas = jform.find("textarea");
	var inputs = $.merge($.merge(a_inputs, a_selects), a_textareas);
	return inputs;
};

function get_parent_by_tag(s_tagname, jobject) {
	if (jobject.parent().length == 0)
		return null;
	var jparent = $(jobject.parent()[0]);
	while (jparent.prop("tagName").toLowerCase() != s_tagname.toLowerCase()) {
		if (jparent.parent().length > 0) {
			jparent = jparent.parent();
		} else {
			return null;
		}
	}
	return jparent;
};

function get_parent_by_class(s_classname, jobject) {
	if (jobject.parent().length == 0)
		return null;
	var jparent = $(jobject.parent()[0]);
	while (!jparent.hasClass(s_classname)) {
		if (jparent.parent().length > 0) {
			jparent = jparent.parent();
		} else {
			return null;
		}
	}
	return jparent;
};

function get_child_depth(jchild, jparent) {
	var parent = jchild;
	var depth = 0;
	while (!parent.is(jparent))
	{
		parent = parent.parent();
		depth++;
	}
	return depth;
};

jQuery.fn.outerHTML = function(s) {
    return s
        ? this.before(s).remove()
        : jQuery("<p>").append(this.eq(0).clone()).html();
};

$.strPad = function(string,length,character) {
	var retval = string.toString();
	if (!character) { character = '0'; }
	while (retval.length < length) {
		retval = character + retval;
	}
	return retval;
};

function parse_int(s_value) {
	if (typeof(s_value) == "number")
		return s_value;
	if (!s_value)
		return 0;
	s_value = s_value.replace(/^[^1-9]*/, '');
	if (s_value.length == 0)
		return 0;
	return parseInt(s_value);
};

function parse_float(s_value) {
	if (typeof(s_value) == "number")
		return s_value;
	if (!s_value)
		return 0;
	if (s_value.length == 0)
		return 0;
	s_value = s_value.replace(/^[^0-9]+?(\.?[0-9]+)(.*)/, function(match, p1, p2) { return p1+p2; });
	return parseFloat(s_value);
};

function get_date() {
	var d = new Date();
	var s_retval = "";
	s_retval += $.strPad(d.getFullYear(),4)+"-";
	s_retval += $.strPad(d.getMonth(),2)+"-";
	s_retval += $.strPad(d.getDate(),2)+" ";
	s_retval += $.strPad(d.getHours(),2)+":";
	s_retval += $.strPad(d.getMinutes(),2)+":";
	s_retval += $.strPad(d.getSeconds(),2);
	return s_retval;
};

function cancel_enter_keypress(e) {
	if (e.which == 13) {
		e.stopPropagation();
	}
};

function form_enter_press(element, e) {
	if (e.which == 13) {
		var jelement = $(element);
		var jform = get_parent_by_tag("form", jelement);
		var jbutton = jform.find("input[value=Submit]");
		if (jbutton.length > 0) {
			jbutton.click();
		}
		e.stopPropagation();
		return false;
	}
};

function pad_left(str, padstr, length) {
	ps = padstr;
	while (ps.length < length) {
		ps += padstr;
	}
	return ps.substring(0, length - str.length) + str;
};

function colorFade(ratio, emptyColor, midColor, fullColor) {
	ratio = Math.min(Math.max(ratio, 0), 1);
	var r1, r2, c1, c2;

	if (ratio < 0.5)
	{
		var r2 = ratio * 2;
		var r1 = 1 - r2;
		c1 = emptyColor;
		c2 = midColor;
	}
	else
	{
		var r2 = (ratio - 0.5) * 2;
		var r1 = 1 - r2;
		c1 = midColor;
		c2 = fullColor;
	}

	var retval = [0, 0, 0];
	for (var i = 0; i < 3; i++)
	{
		retval[i] = c1[i]*r1 + c2[i]*r2;
	}
	return retval;
};

function uploadFile(jelement, files) {
	var retfunc = function() { hoverFile(jelement, false); return false; };
	if (files.length !== 1) { alert("Incorrect number of image files (must be 1)."); return retfunc(); }
	window.file = files[0];
	if (file.size > 3145728) { alert("Image is too big! (must be less than 3MB)"); return retfunc(); }
	var posts = new FormData();
	$.each(jelement.attr(), function(attrName, attrVal) {
		posts.append(attrName, attrVal);
	});
	posts.append("command", "upload_file");
	posts.append("campaign_id", "<?php echo $cid; ?>");
	posts.append("character_id", "<?php echo $charid; ?>");
	posts.append("property", jelement.attr("name"));
	posts.append("file", files[0]);
	posts.append("table", (jelement.attr("table") === undefined) ? "" : jelement.attr("table"));
	posts.append("rowid", (jelement.attr("rowid") === undefined) ? "" : jelement.attr("rowid"));
	var options = {
		"contentType": false,
		"processData": false
	};
	set_html_and_fade_in(jerrors_label, "", "<span style='color:gray;font-weight:normal;'>uploading...</span>");
	send_async_ajax_call("ajax.php", posts, true, function(retval) {
		interpret_commands(retval, jerrors_label);
	}, options);
	return retfunc();
};

function fitImageSize(jImage, i_maxWidth, i_maxHeight, f_onload) {
	if (arguments.length < 4 || f_onload === undefined || f_onload === null)
		f_onload = null;

	var limitSize = function(img) {
		var i_width = parseInt(img.width);
		var i_height = parseInt(img.height);
		var ratio = 1;

		if (i_width * ratio < i_maxWidth)
		{
			ratio = i_maxWidth / i_width;
		}
		if (i_height * ratio < i_maxHeight)
		{
			ratio = i_maxHeight / i_height;
		}
		if (i_width * ratio > i_maxWidth)
		{
			ratio = Math.min(i_maxWidth / i_width, ratio);
		}
		if (i_height * ratio > i_maxHeight)
		{
			ratio = Math.min(i_maxHeight / i_height);
		}

		jImage.css({
			'width': (i_width * ratio) + 'px',
			'height': (i_height * ratio) + 'px',
			'margin-top': ((i_maxHeight - (i_height * ratio)) / 2) + 'px'
		});
	}

	jImage.off('load');
	jImage.on('load', function() {
		var img = new Image();
		img.onload = function() {
			limitSize(img);
			if (f_onload !== null) {
				f_onload(jImage);
			}
		};
		img.src = jImage.attr('src');
	});
	limitSize(jImage[0]);
	if (f_onload !== null) {
		f_onload(jImage);
	}
}