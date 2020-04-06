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
	pushObj.noPoll = null;

	stopCurrentPolls = function() {
		for (var i = 0; i < pollXhrs.length; i++) {
			if (pollXhrs[i] !== null) {
				pollXhrs[i].abort(); // todo
			}
		}
	};

	window.addEventListener("beforeunload", function (e) {
		clearInterval(pollInterval);
		stopCurrentPolls();
	});

	/**
	 * Input data argument should be of one of the following forms:
	 * { 'command': string, 'action': any }
	 * { 'f_serverTime': seconds since epoch, 'event': { 'command': string, 'action': any } }
	 * ...or the json encoded version of one of those objects.
	 *
	 * What is returned is of the type:
	 * { 'b_hasServerTime': bool, 'f_serverTime': seconds since epoch or 0, 'event': { 'command': string, 'action': any } }
	 *
	 * Anything that doesn't match the expected input type is converted to a string and used in a new 'showError' event.
	 */
	getCommand = function(data)
	{
		var o_data = null;

		// parse strings
		if (typeof(data) === "string")
		{
			data = data.trimStart('\0', '\n');
			try
			{
				o_data = JSON.parse(data);
			}
			catch (error)
			{
				// pass
			}
		}
		// Convert to string if not an object and stuff in a 'showError' event.
		// Convert to string if not an event or not a command.
		// Check for null because in javascript "null" is of type "object".
		else if (typeof(data) !== "object" || data === null ||
			     (data.command === undefined && data.f_serverTime === undefined))
		{
			o_data = {
				'command': 'showError',
				'action': "" + data
			};
		}
		else
		{
			o_data = data;
		}

		// sanity check
		if (typeof(o_data) !== "object" ||
			(o_data.command === undefined && o_data.f_serverTime === undefined))
		{
			throw "Programmer error, bad command type: " + data + "/" + o_data;
		}

		// convert to a full event type if just a command
		if (o_data.f_serverTime === undefined) {
			var eventData = o_data;
			o_data = {
				'b_hasServerTime': false,
				'f_serverTime': 0,
				'event': eventData
			};
		}
		else
		{
			o_data['b_hasServerTime'] = true;
		}

		return o_data;
	}

	pushObj.pushData = function(data)
	{
		if (pushObj.customData !== undefined && pushObj.customData !== null)
		{
			$.each(pushObj.customData, function(k, v) {
				data[k] = v;
			});
		}

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
				if (commands[o_command.event.command] !== undefined)
					commands[o_command.event.command](o_command.event.action);
				else
					commands['showError']("Unknown command type: " + data);
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

	pushObj.setNoPoll = function(i_timeoutMs) {
		pushObj.noPoll = true;
		setTimeout(function() {
			pushObj.noPoll = null;
		}, i_timeoutMs);
		stopCurrentPolls();
	};

	pushPullInterpret = function(o_command) {
		if (o_command.event.command == 'noPoll')
		{
			// get the no-poll timeout
			i_noPollTime = 3;
			try
			{
				i_noPollTime = parseInt(o_command.event.action)
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
			pushObj.setNoPoll(i_noPollTime * 1000);
			return true;
		}

		return false;
	};

	recordEvent = function(o_command)
	{
		if (o_command.b_hasServerTime)
		{
			latestEvents = latestEvents.enqueue(o_command.f_serverTime);
			while (latestEvents.length > 100)
			{
				latestEvents = latestEvents.dequeue();
			}
		}
	}
	
	pollData = function() {
		if (pushObj.noPoll != null)
		{
			return;
		}

		if (pollXhrs.length < 1 || pollXhrs[0] == null) {

			//prepare json data
			var data = {
				'command': 'pull',
				'latestEvents': JSON.stringify(latestEvents)
			};
			if (pushObj.customData !== undefined && pushObj.customData !== null)
			{
				$.each(pushObj.customData, function(k, v) {
					data[k] = v;
				});
			}

			// send ajax request
			var jqXHR = $.ajax({
				'url': addr,
				'async': true,
				'cache': false,
				'data': data,
				'type': "POST",
				'timeout': 15000,
				'success': function(data) {
					var o_command = getCommand(data);
					pollXhrs[0] = null;
					if (!pushPullInterpret(o_command))
					{
						recordEvent(o_command);
						onmessageCallback(o_command, true);
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