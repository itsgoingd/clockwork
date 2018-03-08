class Requests
{
	constructor () {
		this.items = []
	}

	// returns all requests up to the first placeholder, or everything if there are no placeholders
	all () {
		return this.items
	}

	// return request by id
	findId (id) {
		return this.items.find(request => request.id == id)
	}

	// loads request by id, inserts a placeholder to the items array which is replaced once the metadata is retrieved
	loadId (id, placeholder) {
		let request = this.findId(id)

		if (request) return Promise.resolve(request)

		placeholder = placeholder || Request.placeholder(id)

		this.items.push(placeholder)

		return this.callRemote(this.remoteUrl + id).then(data => {
			return placeholder.resolve(data[0])
		}).catch(error => {
			placeholder.resolveWithError(error)
		})
	}

	loadLatest () {
		return this.callRemote(this.remoteUrl + 'latest').then(data => {
			this.items.push(...data)
		}).catch(error => {})
	}

	// loads requests after the last request, if the count isn't specified loads all requests
	loadNext (count, id) {
		if (! id && ! this.items.length) return

		id = id || this.last().id

		return this.callRemote(this.remoteUrl + id + '/next' + (count ? '/' + count : '')).then(data => {
			this.items.push(...data)
		}).catch(error => {})
	}

	// loads requests before the first request, if the count isn't specified loads all requests
	loadPrevious (count, id) {
		if (! id && ! this.items.length) return

		id = id || this.first().id

		return this.callRemote(this.remoteUrl + id + '/previous' + (count ? '/' + count : '')).then(data => {
			this.items.unshift(...data)
		}).catch(error => {})
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

	setItems (items) {
		this.items = items
	}

	setRemote (url, options) {
		options = options || {}
		options.path = options.path || '/__clockwork/'

		url = new URI(url)

		let [ pathname, query ] = options.path.split('?')
		url.pathname(pathname || '')
		url.query(query || '')
		url.hash('')

		this.remoteUrl = url.toString()
		this.remoteHeaders = options.headers || {}
	}

	callRemote (url) {
		return this.client(url, this.remoteHeaders).then(data => {
			return ((data instanceof Array) ? data : [ data ]).map(data => new Request(data))
		})
	}
}
