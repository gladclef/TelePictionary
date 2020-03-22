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

function draw_error(jform, s_errormsg, t_success) {
	var jerror = jform.find(".errors");
	if (t_success === true) {
		jerror.stop(true, true);
		jerror.css({color:"green", opacity:0});
		jerror.html(s_errormsg);
		jerror.animate({opacity:1}, 200);
	} else if (t_success === false) {
		jerror.stop(true, true);
		jerror.css({color:"red", opacity:0});
		jerror.html(s_errormsg);
		jerror.animate({opacity:1}, 200);
	} else {
		jerror.stop(true, true);
		jerror.css({color:"lightgrey", opacity:1});
		jerror.html(s_errormsg);
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

/**
 * Meant to be used as the second argument in a call to jQuery.each(array, callback).
 *
 * Calls the given callback in a setTimeout(callback, 0) timeout in order
 * to allow other javascript stuff to execute in a "parallel" sort of way.
 *
 * @param keyAndValue True to pass two arguments (key and value), false to just pass value.
 */
function multithreadedForEachCallback(callback, keyAndValue) {
	var c2 = callback;
	var kav = keyAndValue;
	return function(k, v) {
		var k2 = k;
		var v2 = v;
		setTimeout(function() {
			if (kav) {
				c2(k2, v2);
			} else {
				c2(v2);
			}
		}, 0);
	}
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

function findNextSibling(jelement) {
	var jparent = jelement.parent();
	if (jparent === null || jparent === undefined || jparent.length == 0)
		return null;
	
	var children = jparent.children();
	var foundSame = false;
	var foundNext = false;
	var next = null;
	$.each(children, function(k, v) {
		var jchild = $(v);
		if (foundSame && !foundNext) {
			next = $(v);
			foundNext = true;
		}
		if (jchild.is(jelement)) {
			foundSame = true;
		}
	});

	return next;
};

function findPrevSibling(jelement) {
	var jparent = jelement.parent();
	if (jparent === null || jparent === undefined || jparent.length == 0)
		return null;
	
	var children = jparent.children();
	var foundSame = false;
	var prev = null;
	$.each(children, function(k, v) {
		var jchild = $(v);
		if (jchild.is(jelement)) {
			foundSame = true;
		}
		if (!foundSame) {
			prev = $(v);
		}
	});

	return prev;
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

function prepImgBox() {
	var jbox = $("#imgbox");
	var jcloseButton = jbox.children(".closebutton");
	var jimgBox = jbox.children("img");
	jcloseButton.on('click', function() {
		jbox.hide(0);
	});
	jimgBox.attr("width", jimgBox.css("width")+"px");
	jimgBox.attr("height", jimgBox.css("height")+"px");
};

function registerImgBox(jimg) {
	jimg.off('click');
	jimg.on('click', function() {
		var jbox = $("#imgbox");
		var jimgBox = jbox.children("img");
		var joptions = jbox.children(".options");
		var juploadButton = jbox.find("input[type='file']");
		var jtags = jbox.find(".tags.appliedTags");
		var jsuggested = jbox.find(".tags.suggestedTags");
		jimgBox.attr('src', jimg.attr('src'));
		juploadButton.off('change');
		juploadButton.on('change', function(e) {
			e.preventDefault();
			e.stopPropagation();
			uploadFile(jimg, juploadButton[0].files);
			return false;
		});

		// add the tags
		if (jtags.length > 0)
		{
			var atags = jimg.attr('tags').split('||', 10000, '|', '|');
			var tagbox = jtags;
			var createTags = function(k, tagname) {
				var color = (['#ff6666', '#ffff99', '#ff99cc', '#99ff66', '#66ffcc', '#0099ff', '#9966ff', '#66ccff', '#cc9900', '#666699'])[tagname.sum() % 10];
				var borderColor = color.replaceAll('3','2').replaceAll('6','4').replaceAll('9','6').replaceAll('c','8').replaceAll('f','a');
				tagbox.append('<span class="tag" style="background-color:' + color + '; border-color:' + borderColor + '">' + tagname + '</span>');
			};
			kill_children(jtags);
			$.each(atags, createTags);
		}

		// show image box
		var top = $(window).height()/2 - jbox.height()/2;
		var left = $(window).width()/2 - jbox.width()/2;
		jbox.css({
			"top": top + "px",
			"left": left + "px"
		});
		jbox.show(0);

		// register drag-n-drop
		jimgBox.imgDrop(hoverFile, function(jelement, files) {
			var ret = uploadFile(jimg, files);
			hoverFile(jimgBox, false);
			return ret;
		});

		// once the box is visible do resizing
		setTimeout(function() {
			// get the base width and height of the image container, and the actual size of the image
			var w = jbox.width(), h = jbox.height()-joptions.height();
			var nw = jimg[0].naturalWidth, nh = jimg[0].naturalHeight;

			// fit image within the image container width and height
			var h2 = 0, w2 = 0, spacex = 0, spacey = 0;
			if (nh/h > nw/w) { // image is height bound, add margin on left and right and shrink image width
				h2 = h;
				w2 = (h/nh)*nw;
				spacex = (w - w2) / 2;
			} else { // image is width bound, add margin on top and bottom and shrink image height
				w2 = w;
				h2 = (w/nw)*nh;
				spacey = (h - h2) / 2;
			}
			jimgBox.attr("width", w2 + "px");
			jimgBox.attr("height", h2 + "px");
			jimgBox.css({
				"width": w2 + "px",
				"height": h2 + "px",
				"margin": spacey+"px " +spacex+"px "
			});
		}, 0);
	});
	jimg.css({
		"cursor": "pointer"
	});
};