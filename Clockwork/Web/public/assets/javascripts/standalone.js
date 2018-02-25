class Standalone
{
	constructor ($scope, $http, requests) {
		this.$scope = $scope
		this.$http = $http
		this.requests = requests
	}

	init () {
		this.setMetadataUrl()
		this.setMetadataClient()

		this.startPollingRequests()
	}

	setMetadataUrl () {
		this.requests.setRemote(
			window.location.href, { path: URI(window.location.href.split('/').slice(0, -1).join('/')).path() + '/' }
		)
	}

	setMetadataClient () {
		this.requests.setClient((url, headers) => {
			return this.$http.get(url).then(data => data.data)
		})
	}

	startPollingRequests () {
		this.requests.loadLatest().then(() => {
			this.lastRequestId = this.requests.last().id

			this.pollRequests()
		}).catch(() => {
			setTimeout(() => this.startPollingRequests(), 1000)
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
