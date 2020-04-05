if (window.commands === undefined) window.commands = {};
window['commands.js'] = true;

/******************************************************************************
 *                     G E N E R A L   C O M M A N D S                        *
 *****************************************************************************/

commands.success = function()
{
	// nothing explicit to do
}

commands.showError = function(s_error) {
	if ($.type(s_error) == "object") {
		s_error = s_error.action;
	}
	var jGeneralError = $(".generalError");
	jGeneralError.html("Error: " + s_error);
	jGeneralError.show(200);

	clearTimeout(commands.generalErrorTimeout);
	commands.generalErrorTimeout = setTimeout(function() {
		jGeneralError.hide();
	}, 5000);
}

commands.composite = function(a_commands)
{
	var firstError = null;
	var foundErrorCommand = false;

	for (var i = 0; i < a_commands.length; i++)
	{
		o_command = a_commands[i];
		try
		{
			commands[o_command.command](o_command.action);
			if (o_command.command == 'showError')
				foundErrorCommand = true;
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
		commands.showError(firstError);
	}
}

commands.currentContent = null;
commands.showContent = function(s_content) {
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
}

/******************************************************************************
 *                A P P L I C A T I O N   C O M M A N D S                     *
 *****************************************************************************/

commands.clearPlayers = function()
{
	playerFuncs.clearPlayers();
}

commands.addPlayer = function(o_player)
{
	playerFuncs.addPlayer(o_player);
	game.addPlayer(o_player);
}

commands.setLocalPlayer = function(i_id)
{
	playerFuncs.setLocalPlayer(i_id);
}

commands.setPlayer1 = function(i_id)
{
	playerFuncs.setPlayer1(i_id);
	game.setPlayer1(i_id);
}

commands.updateGame = function(o_game)
{
	game.updateGame(o_game);
}

commands.joinGame = function(o_game)
{
	outgoingMessenger.pushData({
		command: 'joinGame',
		roomCode: o_game.roomCode
	});
}