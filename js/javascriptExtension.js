/** Thanks to https://stackoverflow.com/questions/273789/is-there-a-version-of-javascripts-string-indexof-that-allows-for-regular-expr */
String.prototype.regexIndexOf = function(regex, startpos) {
    var indexOf = this.substring(startpos || 0).search(regex);
    return (indexOf >= 0) ? (indexOf + (startpos || 0)) : indexOf;
};

/** Thanks to https://stackoverflow.com/questions/273789/is-there-a-version-of-javascripts-string-indexof-that-allows-for-regular-expr */
String.prototype.regexLastIndexOf = function(regex, startpos) {
    regex = (regex.global) ? regex : new RegExp(regex.source, "g" + (regex.ignoreCase ? "i" : "") + (regex.multiLine ? "m" : ""));
    if(typeof (startpos) == "undefined") {
        startpos = this.length;
    } else if(startpos < 0) {
        startpos = 0;
    }
    var stringToWorkWith = this.substring(0, startpos);
    var lastIndexOf = -1;
    var nextStop = 0;
    while((result = regex.exec(stringToWorkWith)) != null) {
        lastIndexOf = result.index;
        regex.lastIndex = ++nextStop;
    }
    return lastIndexOf;
};

String.prototype.oldTrimStart = String.prototype.trimStart;
String.prototype.oldTrimEnd = String.prototype.trimEnd;
String.prototype.trimStart = function(trimchar) {
    if (arguments.length == 0) {
        return this.oldTrimStart(trimchar);
    }
    trimchars = [];
    for (var i = 0; i < arguments.length; i++) {
        trimchars.push(arguments[i])
    }

    var removeCnt = 0;
    while (trimchars.indexOf(this[removeCnt]) > -1) {
        removeCnt++;
    }
    if (removeCnt > 0) {
        return this.substring(removeCnt);
    }
    return this;
};
String.prototype.trimEnd = function(trimchar) {
    if (arguments.length == 0) {
        return this.oldTrimEnd(trimchar);
    }
    trimchars = [];
    for (var i = 0; i < arguments.length; i++) {
        trimchars.push(arguments[i])
    }

    var removeCnt = 0;
    while (trimchars.indexOf(this[this.length - removeCnt - 1]) > -1) {
        removeCnt++;
    }
    if (removeCnt > 0) {
        return this.substring(0, this.length - removeCnt);
    }
    return this;
};

String.prototype.oldSplit = String.prototype.split;
String.prototype.split = function(splitmiddle, length, splitstart, splitend) {
    var ret = this;
    if (arguments.length >= 4) {
        ret = ret.trimEnd(splitend);
    }
    if (arguments.length >= 3) {
        ret = ret.trimStart(splitstart);
    }
    if (arguments.length >= 2) {
        return ret.oldSplit(splitmiddle, length);
    }
    return ret.oldSplit(splitmiddle);
};

String.prototype.sum = function() {
    var ret = 0;
    for (var i = 0; i < this.length; i++) {
        ret += this.charCodeAt(i);
    }
    return ret;
};

String.prototype.replaceAll = function(search, replacement) {
    var target = this;
    return target.split(search).join(replacement);
};

Array.prototype.oldJoin = Array.prototype.join;
Array.prototype.join = function(joinmiddle, joinstart, joinend) {
    var ret = this.oldJoin(joinmiddle);
    if (arguments.length >= 3) {
        ret += joinend;
    }
    if (arguments.length >= 2) {
        ret = joinstart + ret;
    }
    return ret;
};

Array.prototype.pushIfNotExisting = function(element) {
    if (this.indexOf(element) === -1)
    {
        this.push(element);
        return true;
    }
    return false;
}