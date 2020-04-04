if (window.serverStats === undefined) window.serverStats = {};

window["pushPull.js"] = true;

function initPushPull(onmessageCallback, pushObj, onerror, onclose)
{
	//create a new long-poll object
	var addr = "https://bbean.us/TelePictionary/communication/longPoll/server.php";
	var lastSendTime = 0;
	var latestEvents = serverStats['latestEvents'];
	var sendRate = 50; // 50ms minimum delay between sent messages
	var delayedUpdated = null;
	var pollXhrs = [];
	var pushTimer = null;
	var pushVals = {};
	var startTime = Date.now();
	var pollInterval = null;
	var pollData = null;
	var noPoll = null;

	window.addEventListener("beforeunload", function (e) {
		clearInterval(pollInterval);
		for (var i = 0; i < pollXhrs.length; i++) {
			if (pollXhrs[i] !== null) {
				pollXhrs[i].abort(); // todo
			}
		}
	});

	getCommand = function(data)
	{
		var o_data = null;
		try
		{
			o_data = JSON.parse(data);
		}
		catch (error)
		{
			// pass
		}
		if (o_data != null && o_data.command !== undefined && o_data.command != "error") {
			return o_data;
		} else {
			return {
				command: 'showError',
				action: data
			}
		}
	}

	pushObj.pushData = function(data)
	{
		pushTimer = null;
		$.ajax({
			'url': addr,
			'async': true,
			'cache': false,
			'data': data,
			'type': "POST",
			'timeout': 10000,
			'success': function(data) {
				o_command = getCommand(data);
				commands[o_command.command](o_command.action);
			},
			'error': function(xhr, ajaxOptions, thrownError) {
				if (parseInt(xhr.status) == 0 && thrownError) {
					if ((thrownError+"").indexOf("NETWORK_ERR") > -1) {
						console.error("network error encountered");
						return;
					}
				}
				console.error("Error sending request: ("+xhr.status+") "+thrownError);
			}
		});
	};

	pushObj.pushEvent = {};
	pushObj.pushEvent = function(s_command, o_data)
	{
		// limit the outgoing message rate
		// limit by time per message type
		var time = (new Date()).getTime();
		if (time - lastSendTime < sendRate)
		{
			if (delayedUpdated === undefined)
				delayedUpdated = null;

			clearTimeout(delayedUpdated);
			delayedUpdated = setTimeout(function() {
				pushObj.pushEvent(s_command, o_data);
			}, sendRate - (time - lastSendTime));
			return;
		}
		lastSendTime = time;

		// prepare json data
		var data = {
			'command': 'pushEvent',
			'event': JSON.stringify({
				'command': s_command,
				'action': o_data
			})
		};

		// convert and send data to server
		pushObj.pushData(data);
	};

	pushPullInterpret = function(data) {
		data = data.trimStart('\0', '\n');
		o_command = getCommand(data);

		if (o_command.command == 'noPoll')
		{
			// get the no-poll timeout
			i_noPollTime = 3;
			try
			{
				i_noPollTime = parseInt(o_command.action)
			}
			catch (error)
			{
				// pass
			}
			if (i_noPollTime < 0 || i_noPollTime > 10)
			{
				i_noPollTime = 3;
			}

			// set no-pull and set a timeout to unset no-poll
			noPoll = true;
			setTimeout(function() {
				noPoll = null;
			}, i_noPollTime * 1000);

			return true;
		}

		return false;
	};
	
	pollData = function() {
		if (noPoll != null)
		{
			return;
		}

		if (pollXhrs.length < 1 || pollXhrs[0] == null) {

			//prepare json data
			var data = {
				'command': 'pull',
				'latestEvents': JSON.stringify(latestEvents)
			};

			// send ajax request
			var jqXHR = $.ajax({
				'url': addr,
				'async': true,
				'cache': false,
				'data': data,
				'type': "POST",
				'timeout': 10000,
				'success': function(data) {
					pollXhrs[0] = null;
					if (!pushPullInterpret(data))
					{
						onmessageCallback(getCommand(data), true);
					}
				},
				'error': function(xhr, ajaxOptions, thrownError) {
					pollXhrs[0] = null;
					if (parseInt(xhr.status) == 0 && thrownError) {
						if ((thrownError+"").indexOf("NETWORK_ERR") > -1) {
							console.error("network error encountered");
							return;
						}
					}
					console.error("Error sending request: ("+xhr.status+") "+thrownError);
				}
			});
			pollXhrs[0] = jqXHR;
		}
	};
	pollInterval = setInterval(pollData, 10);
}