if (window.a_toExec === undefined) window.a_toExec = [];

a_toExec[a_toExec.length] =
{
	"name": "control.php",
	"dependencies": ["jQuery", "jqueryExtension.js"],
	"function": function()
	{
		var startupFunctionality = function() {
			var juploadables = $(".uploadable");

			window.hoverFile = function(jobj, isHover) {
				if (isHover) {
					jobj.attr("oldborder", jobj.css("border") + "");
					jobj.css({ "border": "2px solid red" });
				} else {
					jobj.css({ "border": jobj.attr("oldborder") });
				}
			};

			juploadables.imgDrop(hoverFile, uploadFile);
			$(window).noDrop();
		};
		$(document).ready(startupFunctionality);
	}
}