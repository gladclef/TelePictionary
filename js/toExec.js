if (window.a_toExec === undefined) window.a_toExec = [];

var execWaitingFuncs = null;
execWaitingFuncs = function()
{
	var b_allDependenciesFound = false;

	// Iterate through the waiting functions in a_toExec until one is found that has all of its dependencies met.
	// Once one such function is found, register it, execute it, and delay to allow other javascript to execute.
	for (var i = 0; i < a_toExec.length; i++)
	{
		var o_funcObj      = a_toExec[i];
		var s_name         = o_funcObj["name"];
		var a_dependencies = o_funcObj["dependencies"];
		var f_func         = o_funcObj["function"];

		b_allDependenciesFound = true;
		for (var j = 0; j < a_dependencies.length; j++)
		{
			var s_dependency = a_dependencies[j];
			if (window[s_dependency] === undefined) {
				b_allDependenciesFound = false;
				break;
			}
		};

		if (b_allDependenciesFound)
		{
			window[s_name] = true;
			f_func();

			// remove this function from a_toExec so that it doesn't execute again
			a_toExec.splice(i,1);

			// allow other javascript to execute
			break;
		}
	}

	if (b_allDependenciesFound) {
		// Run again, but from the back of the javascript queue.
		// Allows for other queued javascript (eg in <script> tags) to execute.
		setTimeout(execWaitingFuncs, 0);
	} else {
		// Run again, but in 100ms.
		// Allows time for currently unqueued a_toExec functions to be enqueued.
		// Also prevents this script from eating up all the browser's processing.
		setTimeout(execWaitingFuncs, 100);
	}
};
window.addEventListener('load', execWaitingFuncs);