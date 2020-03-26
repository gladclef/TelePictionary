if (window.serverStats === undefined) window.serverStats = {};

window["pushPull.js"] = true;

function initPushPull(onmessageCallback, pushObj, onerror, onclose)
{
	//create a new long-poll object
	var addr = "https://bbean.us/TelePictionary/communication/longPoll/server.php";
	var myId = Math.floor(Math.random() * 100000000);
	var lastSendTime = 0;
	var latestIndexes = serverStats['latestIndexes'];
	var sendRate = 50; // 50ms minimum delay between sent messages
	var delayedUpdated = null;
	var pollXhrs = [];
	var pushTimer = null;
	var pushVals = {};
	var startTime = Date.now();
	var pollInterval = null;

	window.addEventListener("beforeunload", function (e) {
		clearInterval(pollInterval);
		for (var i = 0; i < pollXhrs.length; i++) {
			if (pollXhrs[i] !== null) {
				pollXhrs[i].abort(); // todo
			}
		}
	});

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
					commands[o_data.command](o_data);
				} else {
					commands.showError(data);
				}
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
	pushObj.pushEvent = function(e)
	{
		// limit the outgoing message rate
		// limit by time per message type
		var time = (new Date()).getTime();
		if (time - lastSendTime < sendRate)
		{
			if (delayedUpdated == null || delayedUpdated == undefined)
				delayedUpdated = {}
			if (delayedUpdated[e.key] == null || delayedUpdated[e.key] == undefined)
				delayedUpdated[e.key] = null;

			clearTimeout(delayedUpdated[e.key]);
			delayedUpdated[e.key] = setTimeout(function() {
				pushObj.pushEvent(e);
			}, sendRate - (time - lastSendTime));
			return;
		}
		lastSendTime = time;

		// add the new index to the front, and remove the oldest index from the back
		latestIndexes.unshift({
			'idx': Math.floor(Math.random() * 100000000),
			'clientId': myId,
			'time': time
		});
		latestIndexes.splice(100, 1);

		// prepare json data
		var data = {
			'command': 'pushEvent',
			'event': JSON.stringify(e),
			'key': e.key,
			'remote': false,
			'clientId': myId,
			'message_idx': latestIndexes[0].idx,
			'message_time': latestIndexes[0].time
		};

		// convert and send data to server
		pushObj.pushData(data);
	};
	
	var pollData = null;
	pollData = function() {
		if (pollXhrs.length < 1 || pollXhrs[0] == null) {

			//prepare json data
			var data = {
				'command': 'pull',
				'clientId': myId,
				'latestIndexes': JSON.stringify(latestIndexes)
			};

			// send ajax request
			var jqXHR = $.ajax({
				'url': addr,
				'async': true,
				'cache': false,
				'data': data,
				'type': "POST",
				'timeout': 2000,
				'success': function(data) {
					pollXhrs[0] = null;
					onmessageCallback(data, true);
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