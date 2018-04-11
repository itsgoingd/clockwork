class Standalone
{
	constructor ($scope, $http, requests) {
		this.$scope = $scope
		this.$http = $http
		this.requests = requests
	}

	init () {
		this.useProperTheme()
		this.setMetadataUrl()
		this.setMetadataClient()

		this.startPollingRequests()
	}

	// appending ?dark to the query string will cause dark theme to be used, ?dark=1 or ?dark=0 can be used to
	// permanently activate or deactivate dark theme in this browser
	useProperTheme () {
		let wantsDarkTheme = URI(window.location.href).query(true).dark

		if (wantsDarkTheme == '1' || wantsDarkTheme == '0')	{
			localStorage.setItem('use-dark-theme', wantsDarkTheme)
			wantsDarkTheme = wantsDarkTheme == '1'
		} else if (localStorage.getItem('use-dark-theme')) {
			wantsDarkTheme = localStorage.getItem('use-dark-theme') == '1'
		} else {
			wantsDarkTheme = wantsDarkTheme === null
		}

		if (wantsDarkTheme) {
			document.querySelector('body').classList.add('dark')
		}
	}

	setMetadataUrl () {
		this.requests.setRemote(
			window.location.href, { path: URI(window.location.href.split('/').slice(0, -1).join('/')).path() + '/' }
		)
	}

	setMetadataClient () {
		this.requests.setClient((method, url, data, headers) => {
			return this.$http({ method: method.toLowerCase(), url, data, headers })
				.then(data => data.data)
				.catch(data => {
					if (data.status == 403) {
						throw { error: 'requires-authentication', message: data.data.message, requires: data.data.requires }
					} else {
						throw { error: 'Server returned an error response.' }
					}
				})
		})
	}

	startPollingRequests () {
		this.requests.loadLatest().then(() => {
			this.lastRequestId = this.requests.last().id

			this.pollRequests()
		}).catch(error => {
			if (error.error == 'requires-authentication') {
				this.$scope.authentication.request(error.message, error.requires).then(() => {
					this.startPollingRequests()
				})
			} else {
				setTimeout(() => this.startPollingRequests(), 1000)
			}
		})
	}

	pollRequests () {
		this.requests.loadNext(null, this.lastRequestId).then(() => {
			if (this.requests.last()) this.lastRequestId = this.requests.last().id

			if (! this.$scope.preserveLog) {
				this.requests.setItems(this.requests.all().slice(-1))
			}

			this.$scope.refreshRequests()

			setTimeout(() => this.pollRequests(), 1000)
		}).catch(() => {
			setTimeout(() => this.pollRequests(), 1000)
		})
	}
}
