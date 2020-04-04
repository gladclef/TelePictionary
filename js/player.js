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
	var i_id = o_player.i_id;
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

playerFuncs.updatePlayerName = function(i_id)
{
	// TODO
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

playerFuncs.clearPlayers();