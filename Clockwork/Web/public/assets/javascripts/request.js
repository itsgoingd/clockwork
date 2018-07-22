class Request
{
	constructor (data) {
		Object.assign(this, data)

		this.responseDurationRounded = this.responseDuration ? Math.round(this.responseDuration) : 0
		this.databaseDurationRounded = this.databaseDuration ? Math.round(this.databaseDuration) : 0
		this.memoryUsageFormatted = this.memoryUsage ? this.formatBytes(this.memoryUsage) : undefined

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
		this.performanceMetrics = this.processPerformanceMetrics(this.performanceMetrics)
		this.timeline = this.processTimeline(this.timelineData)
		this.views = this.processViews(this.viewsData)
		this.userData = this.processUserData(this.userData)

		this.errorsCount = this.getErrorsCount()
		this.warningsCount = this.getWarningsCount()
	}

	static placeholder (id, request, parent) {
		return Object.assign(new Request({
			loading: true,
			id: id,
			uri: request ? (new URI(request.url)).pathname() : '/',
			controller: 'Waiting...',
			method: request ? request.method : 'GET',
			responseStatus: '?',
			parent
		}), {
			responseDurationRounded: '?',
			databaseDurationRounded: '?'
		})
	}

	resolve (request) {
		Object.assign(this, request, { loading: false, error: undefined })
		return this
	}

	resolveWithError (error) {
		Object.assign(this, { loading: false, error })
		return this
	}

	extend (data, fields) {
		fields.forEach(field => this[field] = data[field])
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
			query.trace = this.processStackTrace(query.trace)

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
			query.trace = this.processStackTrace(query.trace)

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
			event.objectEvent = (event.data instanceof Object && event.event == event.data.__class__)
			event.time = event.time ? new Date(event.time * 1000) : undefined
			event.fullPath = event.file && event.line ? event.file.replace(/^\//, '') + ':' + event.line : undefined
			event.shortPath = event.fullPath ? event.fullPath.split(/[\/\\]/).pop() : undefined
			event.trace = this.processStackTrace(event.trace)

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
			message.context = message.context instanceof Object && Object.keys(message.context).filter(key => key != '__type__').length ? message.context : undefined
			message.fullPath = message.file && message.line ? message.file.replace(/^\//, '') + ':' + message.line : undefined
			message.shortPath = message.fullPath ? message.fullPath.split(/[\/\\]/).pop() : undefined
			message.trace = this.processStackTrace(message.trace)

			return message
		})
	}

	processPerformanceMetrics (data) {
		if (! data) {
			return [
				{ name: 'Database', value: this.databaseDurationRounded, style: 'style2' },
				{ name: 'Cache', value: this.cacheTime, style: 'style3' },
				{ name: 'Other', value: this.responseDurationRounded - this.databaseDurationRounded - this.cacheTime, style: 'style1' }
			].filter(metric => metric.value !== null && metric.value !== undefined)
		}

		data = data.filter(metric => metric instanceof Object)
			.map((metric, index) => {
				metric.style = 'style' + (index + 2)
				return metric
			})

		let metricsSum = data.reduce((sum, metric) => { return sum + metric.value }, 0)

		data.push({ name: 'Other', value: this.responseDurationRounded - metricsSum, style: 'style1' })

		return data
	}

	processTimeline (data) {
		if (! (data instanceof Object)) return []

		return Object.values(data).map((entry, i) => {
			entry.style = 'style' + (i % 4 + 1)
			entry.startPercentual = (entry.start - this.time) * 1000 / this.responseDuration * 100
			entry.durationPercentual = entry.duration / this.responseDuration * 100

			entry.barLeft = `${entry.startPercentual}%`
			entry.barWidth = entry.startPercentual + entry.durationPercentual < 100
				? `${entry.durationPercentual}%` : `${100 - entry.startPercentual}%`

			entry.labelAlign = 'left'
			entry.labelLeft = entry.barLeft
			entry.labelRight = 'auto'

			if (entry.startPercentual > 50) {
				entry.labelAlign = 'right'
				entry.labelLeft = 'auto'
				entry.labelRight = entry.durationPercentual < 1
					? `calc(100% - ${entry.barLeft} - 8px)` : `calc(100% - ${entry.barLeft} - ${entry.barWidth})`
			}

			entry.durationRounded = Math.round(entry.duration)
			if (entry.durationRounded === 0) entry.durationRounded = '< 1'

			return entry
		})
	}

	processViews (data) {
		if (! (data instanceof Object)) return []

		return Object.values(data).filter(view => view.data instanceof Object).map(view => view.data)
	}

	processUserData (tabs) {
		if (! (tabs instanceof Object)) return []

		let stripMeta = ([ key, section ]) => key != '__meta'
		let labeledValues = (labels) => ([ key, value ]) => ({ key: labels[key] || key, value })

		return Object.entries(tabs).filter(([ key, tab ]) => {
			return (tab instanceof Object) || tab.__meta || tab.__meta.title
		}).map(([ key, tab ]) => {
			return {
				key,
				title: tab.__meta.title,
				sections: Object.entries(tab).filter(stripMeta).map(([ key, section ]) => {
					let labels = section.__meta.labels || {}
					let data = section.__meta.showAs == 'counters'
						? Object.entries(section).filter(stripMeta).map(labeledValues(labels))
						: Object.entries(section).filter(stripMeta).map(([ key, value ]) => {
							return Object.entries(value).map(labeledValues(labels))
						})

					return {
						data,
						showAs: section.__meta.showAs,
						title: section.__meta.title
					}
				})
			}
		})
	}

	// processUserData (data) {
	// 	if (! (data instanceof Object)) return []
	//
	// 	return Object.entries(data).map(([ key, data ]) => {
	// 		if (! (data instanceof Object) || ! data.__meta || ! data.__meta.title) return
	//
	// 		return {
	// 			key,
	// 			data: Object.entries(data).map(([ key, item ]) => {
	// 				if (key == '__meta' || ! (item instanceof Object)) return
	//
	// 				let labels = item.__meta.labels || {}
	// 				let data
	//
	// 				if (item.__meta.showAs == 'counters') {
	// 					data = Object.entries(item).filter(([ key, value ]) => key != '__meta').map(([ key, value ]) => {
	// 						return [ labels[key] || key, value ]
	// 					})
	// 				} else {
	// 					data = Object.entries(item).filter(([ key, value ]) => key != '__meta').map(([ key, value ]) => {
	// 						return Object.entries(value).map(([ key, value ]) => {
	// 							return [ labels[key] || key, value ]
	// 						})
	// 					})
	// 				}
	//
	// 				return {
	// 					data,
	// 					showAs: item.__meta.showAs,
	// 					title: item.__meta.title
	// 				}
	// 			}).filter(Boolean),
	// 			title: data.__meta.title
	// 		}
	// 	}).filter(Boolean)
	// }

	processStackTrace (trace) {
		if (! trace) return undefined

		return trace.map(frame => {
			frame.fullPath = frame.file && frame.line ? frame.file.replace(/^\//, '') + ':' + frame.line : undefined
			frame.shortPath = frame.fullPath ? frame.fullPath.split(/[\/\\]/).pop() : undefined

			return frame
		})
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

	formatBytes (bytes) {
		let units = [ 'B', 'kB', 'MB', 'GB', 'TB', 'PB' ]
		let pow = Math.floor(Math.log(bytes) / Math.log(1024))

		return `${Math.round(bytes / Math.round(Math.pow(1024, pow)))} ${units[pow]}`
	}
}
