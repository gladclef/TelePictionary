loadJqueryExtensions = function()
{
    /** Thanks to Ryan https://stackoverflow.com/questions/1891444/cursor-position-in-a-textarea-character-index-not-x-y-coordinates */
    $.fn.getCursorPosition = function() {
        var el = $(this).get(0);
        var pos = -1;
        if('selectionStart' in el) {
            pos = el.selectionStart;
        } else if('selection' in document) {
            el.focus();
            var Sel = document.selection.createRange();
            var SelLength = document.selection.createRange().text.length;
            Sel.moveStart('character', -el.value.length);
            pos = Sel.text.length - SelLength;
        }
        return pos;
    };

    $.fn.isSelection = function() {
        var el = $(this).get(0);
        var pos = 0;
        if('selectionStart' in el) {
            return el.selectionStart != el.selectionEnd;
        } else if('selection' in document) {
            el.focus();
            var Sel = document.selection.createRange();
            var SelLength = document.selection.createRange().text.length;
            return SelLength == 0;
        }
        return pos;
    };

    // $.attr() returns a list of all attributes
    (function(old) {
        $.fn.attr = function() {
            if(arguments.length === 0) {
                if(this.length === 0) {
                    return null;
                }

                var obj = {};
                $.each(this[0].attributes, function() {
                    if(this.specified) {
                        obj[this.name] = this.value;
                    }
                });
                return obj;
            }

            return old.apply(this, arguments);
        };
    })($.fn.attr);

    var getCssInt = function(jobj, s_valname) {
        // get instance
        if (jobj.length <= 0) {
            return { "obj": jobj, "val": -1 };
        }
        var jv = (jobj.length > 1) ? $(jobj[0]) : jobj;
        var val = parseInt(jv.css(s_valname));
        return { "obj": jv, "val": val };
    }
    
    $.fn.paddingTop = function() { return getCssInt(this, "padding-top").val; };
    $.fn.paddingBottom = function() { return getCssInt(this, "padding-bottom").val; };
    $.fn.paddingRight = function() { return getCssInt(this, "padding-right").val; };
    $.fn.paddingLeft = function() { return getCssInt(this, "padding-left").val; };
    $.fn.paddingTopPlusBottom = function() {
        var top = getCssInt(this, "padding-top");
        var jobj = top.obj;
        return jobj.paddingBottom() + top.val;
    };
    $.fn.paddingLeftPlusRight = function() {
        var left = getCssInt(this, "padding-left");
        var jobj = left.obj;
        return jobj.paddingRight() + left.val;
    };
    $.fn.padding = function() {
        var top = getCssInt(this, "padding-top");
        var jobj = top.obj;
        return {
            "top": top.val,
            "right": jobj.paddingRight(),
            "bottom": jobj.paddingBottom(),
            "left": jobj.paddingLeft()
        };
    };

    $.fn.imgDrop = function(hoverCallback, dropCallback) {
        var jobj = this;
        jobj.off('dragover');
        jobj.off('dragenter');
        jobj.off('dragleave');
        jobj.off('drop');
        jobj.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
        jobj.on('dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (hoverCallback !== undefined && hoverCallback !== null) return hoverCallback(jobj, true);
            return false;
        });
        jobj.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (hoverCallback !== undefined && hoverCallback !== null) return hoverCallback(jobj, false);
            return false;
        });
        jobj.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!e.originalEvent.dataTransfer) return false;
            var files = [];
            $.each(e.originalEvent.dataTransfer.files, function(k, f) {
                if (f.type.match(/image.*/) != null) {
                    files[files.length] = f;
                }
            });
            if (dropCallback !== undefined && dropCallback !== null) return dropCallback(jobj, files);
            return false;
        });
    };
    $.fn.noDrop = function() {
        var jobj = this;
        var stopProp = function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        };
        var applyStopProp = function(k, v) {
            jobj.on(v, stopProp);
        };
        $.each(["dragover", "dragenter", "dragleave", "drop"], applyStopProp);
    };
};

(function() {
	var loadCallback = function()
	{
		if (window.jQuery) {
			loadJqueryExtensions();
		} else {
			setTimeout(loadCallback, 1);
		}
	};
	loadCallback();
})();