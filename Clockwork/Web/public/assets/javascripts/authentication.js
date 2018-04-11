class Authentication
{
	constructor ($scope, $q, requests) {
		this.$scope = $scope
		this.$q = $q
		this.requests = requests
	}

	attempt () {
		let data = { username: this.username, password: this.password }

		this.username = this.password = ''
		this.failed = false

		return this.requests.client('POST', `${this.requests.remoteUrl}auth`, data).then(data => {
			this.shown = false

			this.requests.setAuthenticationToken(data.token)

			this.requests.items.forEach(request => {
				if (request.error && request.error.error == 'requires-authentication') {
					return this.requests.loadId(request.id)
				}
			})

			this.accept()
		}).catch(e => {
			this.failed = true
		})
	}

	request (message, requires) {
		this.shown = true
		this.requires = requires
		this.message = message

		return this.$q((accept, reject) => {
			this.accept = accept
			this.reject = reject
		})
	}
}
