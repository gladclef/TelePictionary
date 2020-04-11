if (window.players === undefined) window.players = { players: {} };
if (window.playerFuncs === undefined) window.playerFuncs = {};
players.localPlayer = -1;
players.player1 = -1;

playerFuncs.clearPlayers = function()
{
	players.localPlayer = -1;
	players.player1 = -1;
	players.players = {};
}

playerFuncs.addPlayer = function(o_player)
{
	var i_id = o_player.id;
	var b_old = false;

	if (players.players[i_id] !== undefined)
	{
		b_old = true;
	}

	players.players[i_id] = o_player;
	if (b_old)
	{
		playerFuncs.updatePlayerName(i_id);
	}
}

playerFuncs.getPlayer = function(i_id)
{
	if (arguments.length < 1)
	{
		if (players.localPlayer === -1)
			return undefined;
		i_id = players.localPlayer;
	}
	return players.players[i_id];
}

playerFuncs.updatePlayerName = function(i_id)
{
	// TODO
}

playerFuncs.getPlayerName = function(i_id, s_default)
{
	if (arguments.length < 2)
		s_default = "uknown";
	if (players[i_id] === undefined)
		return s_default;
	return players[i_id].name;
}

playerFuncs.setLocalPlayer = function(i_id)
{
	players.localPlayer = i_id;
	playerFuncs.updatePlayerName(i_id);
}

playerFuncs.setPlayer1 = function(i_id)
{
	players.player1 = i_id;
}

playerFuncs.isPlayer1 = function(i_id)
{
	if (arguments.length < 1)
	{
		if (players.localPlayer === -1 || players.player1 === -1)
			return false;
		i_id = players.localPlayer;
	}
	return (i_id === players.player1);
}

playerFuncs.isLocalPlayer = function(io_player)
{
	if (typeof(io_player) === "object")
	{
		if (io_player.id !== undefined && io_player.id === players.localPlayer && players.localPlayer !== -1)
		{
			return true;
		}
	}
	else if (io_player === players.localPlayer && players.localPlayer !== -1)
	{
		return true;
	}
	return false;
}

playerFuncs.removePlayer = function(o_player)
{
	// unset if player 1
	if (playerFuncs.isPlayer1(o_player.id))
	{
		players.player1Id = -1;
	}

	// delete the player
	if (playerFuncs.isLocalPlayer(o_player))
	{
		// don't delete the player if the current player
		return;
	}
	delete players.players[o_player.id];
}

playerFuncs.clearPlayers();