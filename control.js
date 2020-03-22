var canvas;
var crosshairs;
var windowFocus = true;
var mouseDown;
var incomingMessenger;
var outgoingMessenger;

if (window.a_toExec === undefined) window.a_toExec = [];
window.status = {
	'local': {},
	'remote': {}
}

function initWatchers()
{
	// from http://stackoverflow.com/questions/3479734/javascript-jquery-test-if-window-has-focus
	$(window).focus(function() {
		windowFocus = true;
		console.log("windowFocus: " + windowFocus);
	}).blur(function() {
		windowFocus = false;
		console.log("windowFocus: " + windowFocus);
	});

	$(window).mousedown(function() {
		mouseDown = true;
		console.log("mouseDown: " + mouseDown);
	}).mouseup(function() {
		mouseDown = false;
		console.log("mouseDown: " + mouseDown);
	});
}

function initCanvas()
{
	canvas = $("#canvas_container");

	// get the desired width and height to fill the screen
	var w = parseInt($(window).width());
	var h = parseInt($(window).height());

	// set the canvas size
	canvas.css({
		"width": w + "px",
		"height": h + "px"
	});
	$("body").css({
		"overflow": "hidden"
	})

	// set the canvase style
	canvas.css({
		"background-color": "rgb(200, 200, 200)",
		"margin": "0 auto",
		"position": "fixed",
		"left": 0,
		"top": 0
	});
}

function update(e)
{
	// TODO process update
}

function processClick(e)
{
	// TODO
}

function processMouseMove(e)
{
	// TODO
}

function processTouchMove(e)
{
	// TODO
}

function localUpdate(e)
{
	update(e);
	outgoingMessenger.changed(e);
}

function initIncomingMessenger()
{
	incomingMessenger = function(e, remote)
	{
		if (remote)
		{
			status['remote'][e.key] = e.value;
			update(e);
		}
		else
		{
			status['local'][e.key] = e.value;
			update(e);
		}
	}	
}

function initOutgoingMessenger()
{
	outgoingMessenger = {};
}

a_toExec[a_toExec.length] = {
	"name": "control.js",
	"dependencies": ["control.php", "jQuery", "pushPull.js"],
	"function": function() {
		initCanvas();
		initWatchers();
		initIncomingMessenger();
		initOutgoingMessenger();

		initPushPull(incomingMessenger, outgoingMessenger);

		canvas.click(function(e) {
			processClick(e);
		});
		canvas.mousemove(function(e) {
			if (windowFocus && mouseDown) {
				processMouseDown(e);
			}
		});
		canvas[0].addEventListener("touchmove", (function(e) {
			if (windowFocus) {
				e = e.originalEvent || e;
				e = e.targetTouches || e.changedTouches || e.touches || e;
				e = e[0] || e["0"] || e;
				processTouchMove(e);
			}
		}), false);
	}
}