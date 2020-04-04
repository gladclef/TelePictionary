var crosshairs;
var windowFocus = true;
var mouseDown;
window.incomingMessenger;
window.outgoingMessenger;

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
		//console.log("windowFocus: " + windowFocus);
	}).blur(function() {
		windowFocus = false;
		//console.log("windowFocus: " + windowFocus);
	});

	$(window).mousedown(function() {
		mouseDown = true;
		//console.log("mouseDown: " + mouseDown);
	}).mouseup(function() {
		mouseDown = false;
		//console.log("mouseDown: " + mouseDown);
	});
}

function update(o_command)
{
	commands[o_command.command](o_command.action);
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
			//status['remote'][e.key] = e.value;
			update(e);
		}
		else
		{
			//status['local'][e.key] = e.value;
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
		initWatchers();
		initIncomingMessenger();
		initOutgoingMessenger();

		initPushPull(incomingMessenger, outgoingMessenger);
	}
}