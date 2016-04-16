chrome.devtools.panels.create(
	"Clockwork",
	"assets/images/icon-toolbar.png",
	"app.html",
	function(panel) {
		console.log("Panel created.");

		var extensionId = chrome.i18n.getMessage('@@extension_id');
		console.log("Extension ID: " + extensionId);
	}
);
