class Request
{
	constructor (data) {
		Object.assign(this, data)

		this.responseDurationRounded = this.responseDuration ? Math.round(this.responseDuration) : 0
		this.databaseDurationRounded = this.databaseDuration ? Math.round(this.databaseDuration) : 0

		this.processCacheStats()
		this.cacheQueries = this.processCacheQueries(this.cacheQueries)
		this.cookies = this.createKeypairs(this.cookies)
		this.databaseQueries = this.processDatabaseQueries(this.databaseQueries)
		this.emails = this.processEmails(this.emailsData)
		this.events = this.processEvents(this.events)
		this.getData = this.createKeypairs(this.getData)
		this.headers = this.processHeaders(this.headers)
		this.log = this.processLog(this.log)
		this.postData = this.createKeypairs(this.postData)
		this.sessionData = this.createKeypairs(this.sessionData)
		this.timeline = this.processTimeline(this.timelineData)
		this.views = this.processViews(this.viewsData)

		this.errorsCount = this.getErrorsCount()
		this.warningsCount = this.getWarningsCount()
	}

	static placeholder (id, request) {
		return Object.assign(new Request({
			loading: true,
			id: id,
			uri: (new URI(request.url)).pathname(),
			controller: 'Waiting...',
			method: request.method,
			responseStatus: '?'
		}), {
			responseDurationRounded: '?',
			databaseDurationRounded: '?'
		})
	}

	resolve (request) {
		Object.assign(this, request, { loading: false })
		return this
	}

	resolveWithError (error) {
		Object.assign(this, { loading: false, error })
		return this
	}

	createKeypairs (data) {
		if (! (data instanceof Object)) return []

		return Object.keys(data)
			.map(key => ({ name: key, value: data[key] }))
			.sort((a, b) => a.name.localeCompare(b.name))
	}

	processCacheStats () {
		if (this.cacheDeletes) this.cacheDeletes = parseInt(this.cacheDeletes)
		if (this.cacheHits) this.cacheHits = parseInt(this.cacheHits)
		if (this.cacheReads) this.cacheReads = parseInt(this.cacheReads)
		if (this.cacheWrites) this.cacheWrites = parseInt(this.cacheWrites)

		this.cacheMisses = this.cacheReads && this.cacheHits ? this.cacheReads - this.cacheHits : null
	}

	processCacheQueries (data) {
		if (! (data instanceof Array)) return []

		return data.map(query => {
			query.expiration = query.expiration ? this.formatTime(query.expiration) : undefined
			query.value = query.type == 'hit' || query.type == 'write' ? query.value : ''
			query.fullPath = query.file && query.line ? query.file.replace(/^\//, '') + ':' + query.line : undefined
			query.shortPath = query.fullPath ? query.fullPath.split(/[\/\\]/).pop() : undefined

			return query
		})
	}

	processDatabaseQueries (data) {
		if (! (data instanceof Array)) return []

		return data.map(query => {
			query.model = query.model || '-'
			query.shortModel = query.model ? query.model.split('\\').pop() : '-'
			query.fullPath = query.file && query.line ? query.file.replace(/^\//, '') + ':' + query.line : undefined
			query.shortPath = query.fullPath ? query.fullPath.split(/[\/\\]/).pop() : undefined

			return query
		})
	}

	processEmails (data) {
		if (! (data instanceof Object)) return []

		return Object.values(data).filter(email => email.data instanceof Object).map(email => email.data)
	}

	processEvents (data) {
		if (! (data instanceof Array)) return []

		return data.map(event => {
			event.objectEvent = (event.event == event.data.__class__)
			event.time = new Date(event.time * 1000)
			event.fullPath = event.file && event.line ? event.file.replace(/^\//, '') + ':' + event.line : undefined
			event.shortPath = event.fullPath ? event.fullPath.split(/[\/\\]/).pop() : undefined

			event.listeners = event.listeners instanceof Array ? event.listeners : []
			event.listeners = event.listeners.map(listener => {
				let shortName, matches

				if (matches = listener.match(/Closure \(.*[\/\\](.+?:\d+)-\d+\)/)) {
					shortName = 'Closure (' + matches[1] + ')'
				} else {
					shortName = listener.split(/[\/\\]/).pop()
				}

				return { name: listener, shortName }
			})

			return event
		})
	}

	processHeaders (data) {
		if (! (data instanceof Object)) return []

		return Object.keys(data)
			.map(key => {
				let value = data[key]

				key = key.split('-').map(value =>
					value.charAt(0).toUpperCase() + value.slice(1).toLowerCase()
				).join('-')

				return { name: key, value }
			})
			.reduce((flat, header) => {
				header = header.value instanceof Array
					? header.value.map(value => ({ name: header.name, value })) : [ header ]

				return flat.concat(header)
			}, [])
			.sort((a, b) => a.name.localeCompare(b.name))
	}

	processLog (data) {
		if (! (data instanceof Array)) return []

		return data.map(message => {
			message.time = new Date(message.time * 1000)
			message.context = message.context instanceof Object && Object.keys(message.context).length ? message.context : undefined
			message.fullPath = message.file && message.line ? message.file.replace(/^\//, '') + ':' + message.line : undefined
			message.shortPath = message.fullPath ? message.fullPath.split(/[\/\\]/).pop() : undefined

			return message
		})
	}

	processTimeline (data) {
		if (! (data instanceof Object)) return []

		return Object.values(data).map((entry, i) => {
			entry.style = 'style' + (i % 4 + 1)
			entry.left = (entry.start - this.time) * 1000 / this.responseDuration * 100
			entry.width = entry.duration / this.responseDuration * 100

			entry.durationRounded = Math.round(entry.duration)
			if (entry.durationRounded === 0) entry.durationRounded = '< 1'

			return entry
		})
	}

	processViews (data) {
		if (! (data instanceof Object)) return []

		return Object.values(data).filter(view => view.data instanceof Object).map(view => view.data)
	}

	getErrorsCount () {
		return this.log.reduce((count, message) => {
			return message.level == 'error' ? count + 1 : count
		}, 0)
	}

	getWarningsCount () {
		return this.log.reduce((count, message) => {
			return message.level == 'warning' ? count + 1 : count
		}, 0)
	}

	formatTime (seconds) {
		let minutes = Math.floor(seconds / 60)
		let hours = Math.floor(minutes / 60)

		seconds = seconds % 60
		minutes = minutes % 60

		let time = []

		if (hours) time.push(hours + 'h')
		if (minutes) time.push(minutes + 'min')
		if (seconds) time.push(seconds + 'sec')

		return time.join(' ')
	}
}
