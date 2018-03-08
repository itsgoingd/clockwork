class Extension
{
	constructor ($scope, requests, updateNotification) {
		this.$scope = $scope
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
			$('body').addClass('dark')
		}
	}

	setMetadataUrl () {
		this.api.devtools.inspectedWindow.eval('window.location.href', url => this.requests.setRemote(url))
	}

	setMetadataClient () {
		this.requests.setClient((url, headers) => {
			return new Promise((accept, reject) => {
				this.api.runtime.sendMessage(
					{ action: 'getJSON', url, headers }, (message) => {
						message.error ? reject(message.error) : accept(message.data)
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
			this.requests.loadId(options.id, Request.placeholder(options.id, message.request)).then(() => {
				this.$scope.$apply(() => this.$scope.refreshRequests())
			})

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
					this.$scope.$apply(() => this.$scope.refreshRequests())
				})

				this.$scope.$apply(() => this.$scope.refreshRequests())
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

		return { id, path, version, headers }
	}
}
