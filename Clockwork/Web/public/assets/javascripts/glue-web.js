var angularInitialized = false;

window.addEventListener('message', function(ev)
{
	if (ev.data == 'initialize' && !angularInitialized) {
		angular.bootstrap(document, [ 'Clockwork' ]);
		angularInitialized = true;
	}
}, false);