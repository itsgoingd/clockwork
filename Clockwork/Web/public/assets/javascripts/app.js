let Clockwork = angular.module('Clockwork', [ 'chart.js', 'ngclipboard' ])
	.config([ '$compileProvider', ($compileProvider) => {
		$compileProvider.debugInfoEnabled(false)
		$compileProvider.commentDirectivesEnabled(false)
	} ])
	.factory('filter', [ '$timeout', ($timeout) => {
		return {
			create (tags, mapValue) { return new Filter(tags, mapValue, $timeout) }
		}
	} ])
	.factory('profiler', [ 'requests', requests => new Profiler(requests) ])
	.factory('requests', () => new Requests)
	.factory('updateNotification', () => new UpdateNotification)
	.filter('profilerMetric', [ 'profiler', profiler => profiler.metricFilter() ])
