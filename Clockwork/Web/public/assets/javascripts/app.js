let Clockwork = angular.module('Clockwork', [])
	.factory('requests', () => new Requests)
	.factory('updateNotification', () => new UpdateNotification)
