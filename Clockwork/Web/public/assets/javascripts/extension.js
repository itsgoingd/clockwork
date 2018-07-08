class Extension
{
	constructor ($scope, $q, requests, updateNotification) {
		this.$scope = $scope
		this.$q = $q
		this.requests = requests
		this.updateNotification = updateNotification
	}

	get api () { return chrome || browser }

	static runningAsExtension () {
		return (typeof chrome == 'object' && chrome.devtools)
			|| (typeof browser == 'object' && browser.devtools);
	}

	init () {
		this.useProperTheme()
		this.setMetadataUrl()
		this.setMetadataClient()

		this.listenToRequests()

		this.loadLastRequest()
	}

	useProperTheme () {
		if (this.api.devtools.panels.themeName === 'dark') {
			document.querySelector('body').classList.add('dark')
		}
	}

	setMetadataUrl () {
		this.api.devtools.inspectedWindow.eval('window.location.href', url => this.requests.setRemote(url))
	}

	setMetadataClient () {
		this.requests.setClient((method, url, data, headers) => {
			return this.$q((accept, reject) => {
				this.api.runtime.sendMessage(
					{ action: 'getJSON', method, url, data, headers }, (message) => {
						message.error ? reject(message) : accept(message.data)
					}
				)
			})
		})
	}

	listenToRequests () {
		this.api.runtime.onMessage.addListener(message => {
			if (message.action !== 'requestCompleted') return;

			// skip this check in firefox 57.0 to work around a bug where request.tabId is always -1
			if (navigator.userAgent.toLowerCase().indexOf('firefox/57.0') === -1) {
				if (message.request.tabId != this.api.devtools.inspectedWindow.tabId) return
			}

			let options = this.parseHeaders(message.request.responseHeaders)

			if (! options) return

			this.updateNotification.serverVersion = options.version

			this.requests.setRemote(message.request.url, options)

			let request = Request.placeholder(options.id, message.request)
			this.requests.loadId(options.id, request).then(() => {
				this.$scope.refreshRequests()
			})

			options.subrequests.forEach(subrequest => {
				this.requests.setRemote(subrequest.url, { path: subrequest.path })
				this.requests.loadId(subrequest.id, Request.placeholder(subrequest.id, subrequest, request)).then(() => {
					this.$scope.refreshRequests()
				})
			})

			this.requests.setRemote(message.request.url, options)

			this.$scope.$apply(() => this.$scope.refreshRequests())
		})

		// handle clearing of requests list if we are not preserving log
		this.api.runtime.onMessage.addListener(message => {
			if (message.action !== 'navigationStarted') return;

			// preserve log is enabled
			if (this.$scope.preserveLog) return

			// navigation event from a different tab
			if (message.details.tabId != this.api.devtools.inspectedWindow.tabId) return

			this.requests.clear()
		})
	}

	loadLastRequest () {
		this.api.runtime.sendMessage(
			{ action: 'getLastClockworkRequestInTab', tabId: this.api.devtools.inspectedWindow.tabId },
			(request) => {
				if (! request) return

				let options = this.parseHeaders(request.responseHeaders)

				this.updateNotification.serverVersion = options.version

				this.requests.setRemote(request.url, options)
				this.requests.loadId(options.id, Request.placeholder(options.id, request)).then(() => {
					this.$scope.refreshRequests()
				})

				this.$scope.refreshRequests()
			}
		)
	}

	parseHeaders (requestHeaders) {
		let found
		let id = (found = requestHeaders.find((x) => x.name.toLowerCase() == 'x-clockwork-id'))
			? found.value : undefined
		let path = (found = requestHeaders.find((x) => x.name.toLowerCase() == 'x-clockwork-path'))
			? found.value : undefined
		let version = (found = requestHeaders.find((x) => x.name.toLowerCase() == 'x-clockwork-version'))
			? found.value : undefined

		if (! id) return

		let headers = {}
		requestHeaders.forEach((header) => {
			if (header.name.toLowerCase().indexOf('x-clockwork-header-') === 0) {
				let name = header.name.replace(/^x-clockwork-header-/i, '')
				headers[name] = header.value
			}
		})

		let subrequests = requestHeaders.filter(header => header.name.toLowerCase() == 'x-clockwork-subrequest')
			.reduce((subrequests, header) => {
				return subrequests.concat(
					header.value.split(',').map(value => {
						let data = value.trim().split(';')
						return { id: data[0], url: data[1], path: data[2] }
					})
				)
			}, [])

		return { id, path, version, headers, subrequests }
	}
}
