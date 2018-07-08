Clockwork.controller('PanelController', function ($scope, $q, $http, filter, requests, updateNotification)
{
	$scope.requests = []
	$scope.request = null

	$scope.timelineLegend = []
	$scope.timelineView = 'chart'

	$scope.loadingMoreRequests = false
	$scope.preserveLog = true
	$scope.requestsListCollapsed = false
	$scope.showIncomingRequests = true

	$scope.expandedEvents = []

	$scope.init = function () {
		key('⌘+k, ctrl+l', () => $scope.$apply(() => $scope.clear()))

		if (Extension.runningAsExtension()) {
			$scope.$integration = new Extension($scope, $q, requests, updateNotification)
		} else {
			$scope.$integration = new Standalone($scope, $http, requests)
		}

		$scope.$integration.init()

		this.initFilters()

		this.authentication = new Authentication($scope, $q, requests)
	}

	$scope.initFilters = function () {
		$scope.headersFilter = filter.create([
			{ tag: 'name' }
		])

		$scope.getDataFilter = filter.create([
			{ tag: 'name' }
		])

		$scope.postDataFilter = filter.create([
			{ tag: 'name' }
		])

		$scope.cookiesFilter = filter.create([
			{ tag: 'name' }
		])

		$scope.eventsFilter = filter.create([
			{ tag: 'time', type: 'date' },
			{ tag: 'file', map: item => item.shortPath }
		])

		$scope.databaseQueriesFilter = filter.create([
			{ tag: 'model' },
			{ tag: 'type', apply: (item, tagValue) => {
				let types = [ 'select', 'update', 'insert', 'delete' ]
				if (types.includes(tagValue.toLowerCase())) {
					return item.query.match(new RegExp(`^${tagValue.toLowerCase()}`, 'i'))
				}
			} },
			{ tag: 'file', map: item => item.shortPath },
			{ tag: 'duration', type: 'number' }
		])

		$scope.cacheQueriesFilter = filter.create([
			{ tag: 'action', apply: (item, tagValue) => {
				let actions = [ 'read', 'write', 'delete', 'miss' ]
				if (actions.includes(tagValue.toLowerCase())) {
					return item.type.toLowerCase() == tagValue.toLowerCase()
				}
			} },
			{ tag: 'key' },
			{ tag: 'file', map: item => item.shortPath }
		])

		$scope.logFilter = filter.create([
			{ tag: 'time', type: 'date' },
			{ tag: 'level' },
			{ tag: 'file', map: item => item.shortPath }
		], item => item.message)

		$scope.sessionFilter = filter.create([
			{ tag: 'name' }
		])

		$scope.viewsFilter = filter.create([
			{ tag: 'name' }
		])

		$scope.emailsFilter = filter.create([
			{ tag: 'to' }
		])

		$scope.routesFilter = filter.create([
			{ tag: 'method', apply: (item, tagValue) => {
				let methods = [ 'get', 'post', 'put', 'delete', 'head', 'patch' ]
				if (methods.includes(tagValue.toLowerCase())) {
					return item.method.toLowerCase() == tagValue.toLowerCase()
				}
			} },
			{ tag: 'uri' }
		])

		$scope.timelineFilter = filter.create([
			{ tag: 'duration', type: 'number' }
		], item => item.description)
	}

	$scope.initUserDataFilters = function () {
		$scope.userDataFilter = {}

		$scope.request.userData.forEach(tab => {
			$scope.userDataFilter[tab.key] = tab.sections.map(section => {
				if (section.showAs != 'table') return

				return filter.create(section.data[0].map(item => ({ tag: item.key })))
			})
		})
	}

	$scope.clear = function () {
		requests.clear()

		$scope.requests = []
		$scope.request = null

		$scope.timelineLegend = []

		$scope.showIncomingRequests = true

		$scope.expandedEvents = []
	}

	$scope.refreshRequests = function (activeRequest) {
		$scope.requests = requests.all()

		if (! $scope.preserveLog) {
			$scope.showRequest($scope.requests[0].id)
		} else if ($scope.showIncomingRequests && $scope.requests.length) {
			$scope.showRequest(activeRequest ? activeRequest.id : $scope.requests[$scope.requests.length - 1].id)
			$scope.showIncomingRequests = true
		}
	}

	$scope.showRequest = function (id) {
		$scope.request = requests.findId(id)

		$scope.updateNotification = updateNotification.show(requests.remoteUrl)
		$scope.performanceMetricsChartValues = $scope.getPerformanceMetricsChartValues()
		$scope.performanceMetricsChartColors = $scope.getPerformanceMetricsChartColors()
		$scope.performanceMetricsChartOptions = $scope.getPerformanceMetricsChartOptions()
		$scope.databaseQueriesStats = $scope.getDatabaseQueriesStats()
		$scope.timelineLegend = $scope.generateTimelineLegend()

		this.initUserDataFilters()

		if ($scope.request && $scope.request.error && $scope.request.error.error == 'requires-authentication') {
			$scope.authentication.request($scope.request.error.message, $scope.request.error.requires)
		}

		$scope.showIncomingRequests = (id == $scope.requests[$scope.requests.length - 1].id)
	}

	$scope.getRequestClass = function (id) {
		return $scope.request && $scope.request.id == id ? 'selected' : ''
	}

	$scope.getPerformanceMetricsChartValues = function () {
		return $scope.request.performanceMetrics.map(metric => metric.value)
	}

	$scope.getPerformanceMetricsChartColors = function () {
		let colors = {
			style1: { light: '#78b1de', dark: '#649dca' },
			style2: { light: '#e79697', dark: '#d38283' },
			style3: { light: '#b1ca6d', dark: '#9db659' },
			style4: { light: '#ba94e6', dark: '#a680d2' }
		}
		let theme = document.querySelector('body').classList.contains('dark') ? 'dark' : 'light'

		return $scope.request.performanceMetrics.map(metric => colors[metric.style][theme])
	}

	$scope.getPerformanceMetricsChartOptions = function () {
		return {
			aspectRatio: 1,
			tooltips: { enabled: false },
			hover: { mode: null },
			elements: { arc: { borderColor: document.querySelector('body').classList.contains('dark') ? '#1f1f1f' : '#fff' } }
		}
	}

	$scope.showDatabaseConnectionColumn = function () {
		if (! $scope.request) return

		let connnections = $scope.request.databaseQueries
			.map(query => query.connection)
			.filter((connection, i, connections) => connections.indexOf(connection) == i)

		return connnections.length > 1
	}

	$scope.getDatabaseQueriesStats = function () {
		return {
			queries: $scope.request.databaseQueries.length,
			selects: $scope.request.databaseQueries.filter(query => query.query.match(/^select /i)).length,
			inserts: $scope.request.databaseQueries.filter(query => query.query.match(/^insert /i)).length,
			updates: $scope.request.databaseQueries.filter(query => query.query.match(/^update /i)).length,
			deletes: $scope.request.databaseQueries.filter(query => query.query.match(/^delete /i)).length,
			other: $scope.request.databaseQueries.filter(query => ! query.query.match(/^(select|insert|update|delete) /i)).length
		}
	}

	$scope.showCacheTab = function () {
		let cacheProps = [ 'cacheReads', 'cacheHits', 'cacheWrites', 'cacheDeletes', 'cacheTime' ]

		if (! $scope.request) return

		return cacheProps.some(prop => $scope.request[prop]) || $scope.request.cacheQueries.length
	}

	$scope.showCacheQueriesConnectionColumn = function () {
		return $scope.request && $scope.request.cacheQueries.some(query => query.connection)
	}

	$scope.showCacheQueriesDurationColumn = function () {
		return $scope.request && $scope.request.cacheQueries.some(query => query.duration)
	}

	$scope.setTimelineView = function (view) {
		$scope.timelineView = view
	}

	$scope.generateTimelineLegend = function () {
		if (! $scope.request || $scope.request.loading) return []

		let items = []
		let maxWidth = $scope.getTimelineWidth()
		let labelCount = Math.floor(maxWidth / 80)
		let step = $scope.request.responseDuration / maxWidth
		let j

		for (j = 1; j < labelCount + 1; j++) {
			items.push({
				left: ((j * 80 - 44) / maxWidth * 100).toString(),
				time: Math.round(j * 80 * step).toString()
			})
		}

		if (maxWidth - ((j - 1) * 80) > 45) {
			items.push({
				left: ((maxWidth - 38) / maxWidth * 100).toString(),
				time: Math.round(maxWidth * step).toString()
			});
		}

		return items
	}

	$scope.getTimelineWidth = function () {
		let timelineShown = document.querySelector('[tab-content="performance"]').style.display !== 'none'

		if (! timelineShown) document.querySelector('[tab-content="performance"]').style.display = 'block'

		let width = document.querySelector('.timeline-table').offsetWidth

		if (! timelineShown) document.querySelector('[tab-content="performance"]').style.display = 'none'

		return width - 8
	}

	$scope.loadMoreRequests = function () {
		$scope.loadingMoreRequests = true

		requests.loadPrevious(10).then(() => {
			$scope.requests = requests.all()
			$scope.loadingMoreRequests = false
		})
	}

	$scope.toggleRequestsList = function () {
		$scope.requestsListCollapsed = ! $scope.requestsListCollapsed
	}

	$scope.togglePreserveLog = function () {
		$scope.preserveLog = ! $scope.preserveLog
	}

	$scope.isEventExpanded = function (event) {
		return $scope.expandedEvents.indexOf(event) !== -1
	}

	$scope.toggleEvent = function (event) {
		if ($scope.isEventExpanded(event)) {
			$scope.expandedEvents = $scope.expandedEvents.filter(item => item != event)
		} else {
			$scope.expandedEvents.push(event)
		}
	}

	$scope.closeUpdateNotification = function () {
		$scope.updateNotification = null

		updateNotification.ignoreUpdate(requests.remoteUrl)
	}

	angular.element(window).bind('resize', () => {
		$scope.$apply(() => $scope.timelineLegend = $scope.generateTimelineLegend())
    })
})
