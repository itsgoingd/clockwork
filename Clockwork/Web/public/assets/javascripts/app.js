let Clockwork = angular.module('Clockwork', [ 'chart.js', 'ngclipboard', 'angular-click-outside' ])
	.config([ '$compileProvider', ($compileProvider) => {
		$compileProvider.debugInfoEnabled(false)
		$compileProvider.commentDirectivesEnabled(false)
	} ])
	.config($sceProvider => $sceProvider.enabled(false))
	.factory('filter', [ '$timeout', ($timeout) => {
		return {
			create (tags, mapValue) { return new Filter(tags, mapValue, $timeout) }
		}
	} ])
	.factory('profiler', [ 'requests', requests => new Profiler(requests) ])
	.factory('requests', () => new Requests)
	.factory('settings', [ 'requests', requests => new Settings(requests) ])
	.factory('updateNotification', () => new UpdateNotification)
	.filter('profilerMetric', [ 'profiler', profiler => profiler.metricFilter() ])
