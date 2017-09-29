class Requests
{
	constructor () {
		this.items = []
	}

	// returns all requests up to the first placeholder, or everything if there are no placeholders
	all () {
		let placeholder = this.items.find(item => item.loading)

		return placeholder ? this.items.slice(0, this.items.indexOf(placeholder)) : this.items
	}

	// return request by id
	findId (id) {
		return this.items.find(request => request.id == id)
	}

	// loads request by id, inserts a placeholder to the items array which is replaced once the metadata is retrieved
	loadId (id) {
		let request = this.findId(id)

		if (request) return Promise.resolve(request)

		let placeholder = new Request({ id: id, loading: true })
		this.items.push(placeholder)

		return this.callRemote(this.remoteUrl + id).then(data => {
			if (data[0]) {
				this.items[this.items.indexOf(placeholder)] = data[0]
			} else {
				this.items.splice(this.items.indexOf(placeholder), 1)
			}

			return Promise.resolve(data[0])
		})
	}

	loadLatest () {
		return this.callRemote(this.remoteUrl + 'latest').then(data => {
			this.items.push(...data)
		})
	}

	// loads requests after the last request, if the count isn't specified loads all requests
	loadNext (count, id) {
		if (! id && ! this.items.length) return

		id = id || this.last().id

		return this.callRemote(this.remoteUrl + id + '/next' + (count ? '/' + count : '')).then(data => {
			this.items.push(...data)
		})
	}

	// loads requests before the first request, if the count isn't specified loads all requests
	loadPrevious (count, id) {
		if (! id && ! this.items.length) return

		id = id || this.first().id

		return this.callRemote(this.remoteUrl + id + '/previous' + (count ? '/' + count : '')).then(data => {
			this.items.unshift(...data)
		})
	}

	clear () {
		this.items = []
	}

	first () {
		return this.items[0]
	}

	last () {
		return this.items[this.items.length - 1]
	}

	setClient (client) {
		this.client = client
	}

	setRemote (url, options) {
		options = options || {}
		options.path = options.path || '/__clockwork/'

		url = new URI(url)

		let [ pathname, query ] = options.path.split('?')
		url.pathname(pathname || '')
		url.query(query || '')

		this.remoteUrl = url.toString()
		this.remoteHeaders = options.headers || {}
	}

	callRemote (url) {
		return new Promise((accept, reject) => {
			this.client(url, this.remoteHeaders, data => {
				accept((data instanceof Array ? data : [ data ]).map(data => new Request(data)))
			})
		})
	}
}
