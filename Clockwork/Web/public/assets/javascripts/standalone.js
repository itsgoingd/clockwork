class Standalone
{
	constructor ($scope, $http, profiler, requests) {
		this.$scope = $scope
		this.$http = $http
		this.profiler = profiler
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
			let isProfiling = this.profiler.isProfiling

			let makeRequest = () => {
				return this.$http({ method: method.toLowerCase(), url, data, headers })
					.then(data => {
						if (isProfiling) this.profiler.enableProfiling()

						return data.data
					})
					.catch(data => {
						if (data.status == 403) {
							throw { error: 'requires-authentication', message: data.data.message, requires: data.data.requires }
						} else {
							throw { error: 'Server returned an error response.' }
						}
					})
			}

			return isProfiling ? this.profiler.disableProfiling().then(makeRequest) : makeRequest()
		})
	}

	setCookie (name, value, expiration) {
		document.cookie = `${name}=${value};path=/;max-age=${expiration}`

		return Promise.resolve()
	}

	getCookie (name) {
		let matches = document.cookie.match(new RegExp(`(?:^| )${name}=(?<value>[^;]*)`))

		return Promise.resolve(! matches ? undefined : matches.groups.value)
	}

	startPollingRequests () {
		this.requests.loadLatest().then(() => {
			if (! this.requests.last()) throw new Error

			this.lastRequestId = this.requests.last().id

			this.$scope.refreshRequests(this.requests.last())

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
			if (! this.$scope.preserveLog) {
				this.requests.setItems(this.requests.all().slice(-1))
			}

			if (this.requests.last()) {
				if (this.lastRequestId != this.requests.last().id) {
					this.$scope.refreshRequests(this.requests.last())
				}

				this.lastRequestId = this.requests.last().id
			}

			setTimeout(() => this.pollRequests(), 1000)
		}).catch(() => {
			setTimeout(() => this.pollRequests(), 1000)
		})
	}
}
