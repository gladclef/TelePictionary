a_toExec[a_toExec.length] = {
"name": "commands.js",
"dependencies": ["pushPull.js"],
"function": function() {

if (window.commands === undefined)
	window.commands = {};

/******************************************************************************
 *                     G E N E R A L   C O M M A N D S                        *
 *****************************************************************************/

commands.success = function()
{
	// nothing explicit to do
}

commands.lastShownError = "";
commands.showError = function(s_error, s_errCommand, s_errAction) {
	if (arguments.length < 2) {
		s_errCommand = 'no command';
		s_errAction = 'no action';
	}
	if ($.type(s_error) == "object") {
		s_error = s_error.action;
	}
	var jGeneralError = $(".generalError");
	var jGeneralErrorReporter = $(".generalErrorReporter.orig");
	var jGeneralErrorDismisser = $(".generalErrorDismisser.orig");
	var jNewReporter = $(jGeneralErrorReporter.parent().html());
	var jNewDismisser = $(jGeneralErrorDismisser.parent().html());
	var jBody = $("body");
	var i_errorWidth = jGeneralError.fullWidth(true, false, true);
	var i_bodyWidth = jBody.fullWidth(true, false, true);
	commands.lastShownError = "Error: " + s_error;
	jGeneralError.css({
		'left': ((i_bodyWidth - i_errorWidth) / 2) + 'px'
	});
	jGeneralError.children().remove();
	jGeneralError.html(commands.lastShownError);
	jGeneralError.append(jNewReporter);
	jGeneralError.append(jNewDismisser);
	jGeneralError.append("<div style='display:none;'>" + JSON.stringify({'errCommand':s_errCommand, 'errAction':s_errAction}) + "</div>");
	jNewReporter.removeClass("orig").show();
	jNewDismisser.removeClass("orig").show();
	jGeneralError.finish().show(200);

	jNewReporter.click(function() {
		eval(jGeneralErrorReporter.attr('onclickExec'));
	});
	jNewDismisser.click(function() {
		jGeneralError.hide();
	});
}

commands.composite = function(a_commands)
{
	var firstError = null;
	var foundErrorCommand = false;
	var s_errCommand = "";
	var s_errAction = "";

	for (var i = 0; i < a_commands.length; i++)
	{
		o_command = a_commands[i];
		try
		{
			if (outgoingMessenger === undefined || !outgoingMessenger.pushPullInterpret(o_command))
			{
				if (firstError == null) {
					s_errCommand = o_command.command;
					s_errAction = JSON.stringify(o_command.action);
				}
				commands[o_command.command](o_command.action);
				if (o_command.command == 'showError')
					foundErrorCommand = true;
			}
		}
		catch (error)
		{
			console.error(error);
			if (firstError == null)
			{
				firstError = error;
			}
		}
	}

	if (firstError != null && foundErrorCommand == false)
	{
		commands.showError(firstError, s_errCommand, s_errAction);
	}
}

commands.currentContent = null;
commands.showContent = function(s_content)
{
	// show the content
	if (commands.currentContent == null)
	{
		$("#" + s_content).show();
	}
	else
	{
		$("#" + commands.currentContent).hide();
		$("#" + s_content).show();
	}
	commands.currentContent = s_content;

	// run the content-specific init function
	if (window[s_content] !== undefined && typeof(window[s_content].init) === "function")
	{
		window[s_content].init();
	}
}

/******************************************************************************
 *                A P P L I C A T I O N   C O M M A N D S                     *
 *****************************************************************************/

commands.clearPlayers = function(s_action)
{
	$.each(players.players, function(k, o_player) {
		if (s_action !== "dontClearLocal" || !playerFuncs.isLocalPlayer(o_player.id)) {
			commands.removePlayer(o_player);
		}
	});
}

commands.updatePlayer = function(o_player)
{
	playerFuncs.updatePlayer(o_player);
	game.updatePlayer(o_player);
}

commands.setLocalPlayer = function(i_id)
{
	playerFuncs.setLocalPlayer(i_id);
	game.setLocalPlayer(i_id);
}

commands.setPlayer1 = function(i_id)
{
	playerFuncs.setPlayer1(i_id);
	game.setPlayer1(i_id);
}

commands.updateGame = function(o_game)
{
	game.updateGame(o_game);
	reveal.updateGame(o_game);
}

commands.joinGame = function(o_game)
{
	outgoingMessenger.pushData({
		command: 'joinGame',
		roomCode: o_game.roomCode
	}, function(o_command, b_postProcessed) {
		if (!b_postProcessed) {
			outgoingMessenger.setNoPoll(1000);
		}
	});
}

commands.removePlayer = function(o_player)
{
	var i_localPlayer = players.localPlayer;

	// remove the player
	playerFuncs.removePlayer(o_player);
	game.removePlayer(o_player);
	reveal.removePlayer(o_player);

	// check if the removed player is me
	if (i_localPlayer == o_player.id)
	{
		commands.showContent("about");
	}
}

commands.updateCard = function(o_card)
{
	game.updateCard(o_card);
	reveal.updateCard(o_card);
}

commands.updateStory = function(o_story)
{
	game.updateStory(o_story);
	reveal.updateStory(o_story);
}

commands.setCurrentCard = function(o_card)
{
	game.updateCard(o_card);
}

}};