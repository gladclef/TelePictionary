loadJqueryExtensions = function()
{
    if (window.jqueryExtension === undefined) window.jqueryExtension = {};

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
    
    $.fn.border = function() {
        return {
            'top': parseInt(this.css('border-top-width')),
            'right': parseInt(this.css('border-right-width')),
            'bottom': parseInt(this.css('border-bottom-width')),
            'left': parseInt(this.css('border-left-width')),
        }
    };
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
    $.fn.marginTop = function() { return getCssInt(this, "margin-top").val; };
    $.fn.marginBottom = function() { return getCssInt(this, "margin-bottom").val; };
    $.fn.marginRight = function() { return getCssInt(this, "margin-right").val; };
    $.fn.marginLeft = function() { return getCssInt(this, "margin-left").val; };
    $.fn.marginTopPlusBottom = function() {
        var top = getCssInt(this, "margin-top");
        var jobj = top.obj;
        return jobj.marginBottom() + top.val;
    };
    $.fn.marginLeftPlusRight = function() {
        var left = getCssInt(this, "margin-left");
        var jobj = left.obj;
        return jobj.marginRight() + left.val;
    };
    $.fn.margin = function() {
        var top = getCssInt(this, "margin-top");
        var jobj = top.obj;
        return {
            "top": top.val,
            "right": jobj.marginRight(),
            "bottom": jobj.marginBottom(),
            "left": jobj.marginLeft()
        };
    };
    $.fn.fullWidth = function(b_includePadding, b_includeMargin, b_includeBorder) {
        var me = $(this);
        var ret = me.width();
        if (b_includePadding)
            ret += me.paddingLeftPlusRight();
        if (b_includeMargin)
            ret += me.marginLeftPlusRight();
        if (b_includeBorder) {
            var border = me.border();
            ret += border.left + border.right;
        }
        return ret;
    };
    $.fn.fullHeight = function(b_includePadding, b_includeMargin, b_includeBorder) {
        var me = $(this);
        var ret = me.height();
        if (b_includePadding)
            ret += me.paddingTopPlusBottom();
        if (b_includeMargin)
            ret += me.marginTopPlusBottom();
        if (b_includeBorder) {
            var border = me.border();
            ret += border.top + border.bottom;
        }
        return ret;
    };

    // These three functions help find the fixed position relative to the window
    // From https://stackoverflow.com/questions/12293151/how-to-get-fixed-position-of-an-element
    function Point(x, y) {
        return {
            'x': x,
            'y': y,
            'left': x,
            'top': y
        };
    }
    $.fn.outerOffset = function () {
        /// <summary>Returns an element's offset relative to its outer size; i.e., the sum of its left and top margin, padding, and border.</summary>
        /// <returns type="Object">Outer offset</returns>
        var margin = this.margin();
        var padding = this.padding();
        var border = this.border();
        return Point(
            margin.left + padding.left + border.left,
            margin.top + padding.top + border.top
        );
    };
    $.fn.fixedPosition = function () {
        /// <summary>Returns the "fixed" position of the element; i.e., the position relative to the browser window.</summary>
        /// <returns type="Object">Object with 'x' and 'y' properties.</returns>
        var offset = this.offset();
        var $doc = $(document);
        var bodyOffset = $(document.body).outerOffset();
        return Point(offset.left - $doc.scrollLeft() + bodyOffset.left, offset.top - $doc.scrollTop() + bodyOffset.top);
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

    window.jqueryExtension.smoothScrollIntervals = [];
    $.fn.smoothScroll = function(i_scrollPos, i_duration, s_easing, b_horizontalScroll) {
        var me = this;
        if (arguments.length < 2 || i_duration === undefined || i_duration === null)
            i_duration = 200;
        if (arguments.length < 3 || s_easing === undefined || s_easing === null)
            s_easing = 'swing';
        if (arguments.length < 4 || b_horizontalScroll === undefined || b_horizontalScroll === null)
            b_horizontalScroll = false;

        // get the smooth scroll index
        var i_idx = me.attr('smoothScrollIdx');
        if (i_idx === undefined) {
            i_idx = window.jqueryExtension.smoothScrollIntervals.length;
            window.jqueryExtension.smoothScrollIntervals[i_idx] = 0;
            window.jqueryExtension.smoothScrollIntervals[i_idx+1] = 0;
            me.attr('smoothScrollIdx', i_idx);
        } else {
            i_idx = parseInt(i_idx)
        }
        if (b_horizontalScroll) {
            i_idx++;
        }

        var i_intervalCount = 0;
        var s_scrollFunc = (b_horizontalScroll) ? 'scrollLeft' : 'scrollTop';
        var i_startScroll = me[s_scrollFunc]();
        var i_scrollAmount = i_scrollPos - i_startScroll;
        clearInterval(window.jqueryExtension.smoothScrollIntervals[i_idx]);
        if (i_duration > 0) {
            window.jqueryExtension.smoothScrollIntervals[i_idx] = setInterval(function() {
                var i_ellapsedMs = i_intervalCount * 30;
                var f_progress = i_ellapsedMs / i_duration;
                var f_pos = f_progress;

                if (f_progress >= 1) {
                    me[s_scrollFunc](i_scrollPos);
                    clearInterval(window.jqueryExtension.smoothScrollIntervals[i_idx]);
                } else {
                    if (s_easing === 'swing') {
                        f_pos = $.easing.swing(f_progress);
                    }
                    var i_intermediaryScrollPos = i_startScroll + (f_pos * i_scrollAmount);
                    me[s_scrollFunc](i_intermediaryScrollPos);
                }
                i_intervalCount++;
            }, 30);
        } else {
            me[s_scrollFunc](i_scrollPos);
        }
    };

    $.easing.spring = function(f_progress) {
        var f_end = Math.PI * 1.3;
        var f_sin = Math.sin(-0.5*Math.PI + f_progress*f_end);

        // scale f_sin to be in the range 0-1
        f_sin = (f_sin + 1.0) / 2.0;

        // scale f_sin so that at f_progress it has reached the end
        var f_endSin = Math.sin(-0.5*Math.PI + f_end);
        f_endSin = (f_endSin + 1.0) / 2;
        f_sin *= (1.0 / f_endSin);

        return f_sin;
    };

    var oldMerge = $.merge;
    $.merge = function(first, second, ...others) {
        var ret = oldMerge(first, second);
        for (var i = 0; i < others.length; i++) {
            ret = oldMerge(ret, others[i]);
        }
        return ret;
    }
};

a_toExec[a_toExec.length] = {
    "name": "jqueryExtension.js",
    "dependencies": ["jQuery"],
    "function": function() {
        var loadCallback = function()
        {
            if (window.jQuery) {
                loadJqueryExtensions();
            } else {
                setTimeout(loadCallback, 1);
            }
        };
        loadCallback();
    }
};