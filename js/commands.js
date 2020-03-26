if (window.commands === undefined) window.commands = {};
window['commands.js'] = true;

commands.setContent = function(s_content) {
	$("#" + s_content).show(0);
}

commands.showError = function(s_error) {
	var jGeneralError = $(".generalError");
	jGeneralError.html("Error: " + s_error);
	jGeneralError.show(200);

	clearTimeout(commands.generalErrorTimeout);
	commands.generalErrorTimeout = setTimeout(function() {
		jGeneralError.hide(0);
	}, 5000);
}