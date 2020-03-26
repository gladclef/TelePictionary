if (window.a_toExec === undefined) window.a_toExec = [];

var execWaitingFuncs = null;
execWaitingFuncs = function()
{
	var b_funcStarted = true;
	while (b_funcStarted)
	{
		b_funcStarted = false;
		for (var i = 0; i < a_toExec.length; i++)
		{
			var o_funcObj = a_toExec[i];
			s_name = o_funcObj["name"];
			a_dependencies = o_funcObj["dependencies"];
			f_func = o_funcObj["function"];

			var b_allDependenciesFound = true;
			for (var j = 0; j < a_dependencies.length; j++)
			{
				var s_dependency = a_dependencies[j];
				if (window[s_dependency] === undefined) {
					b_allDependenciesFound = false;
				}
			};

			if (b_allDependenciesFound)
			{
				window[s_name] = true;
				f_func();
				b_funcStarted = true;
				a_toExec.splice(i,1);
			}
		}
	}
	setTimeout(execWaitingFuncs, 100);
};
execWaitingFuncs();