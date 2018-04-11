class Filter
{
	constructor (tags, map, $timeout) {
		this.tags = tags
		this.map = map

		this.shown = false
		this.sortedBy = undefined
		this.sortedDesc = false
		this.input = ''

		this.$timeout = $timeout
	}

	toggle (ev) {
		ev.stopPropagation()

		this.shown = ! this.shown

		if (this.shown) {
			this.$timeout(() => {
				let table = ev.target
				while (table = table.parentNode) {
					if (table.tagName == 'TABLE') break
				}

				table.querySelector('.filter input').focus()
			})
		}
	}

	sortBy (column) {
		if (this.sortedBy == column) {
			this.sortedDesc = ! this.sortedDesc
		} else {
			this.sortedBy = column
			this.sortedDesc = true
		}
	}

	filter () {
		return (item) => {
			let { terms, tags } = this.tokenize(this.input)
			let searchable = this.map ? this.map(item) : item

			return this.matchesTerms(searchable, terms) && this.matchesTags(item, tags)
		}
	}

	matchesTerms (item, terms) {
		if (! terms.length) return true

		if (typeof item == 'object' && item !== null) {
			return Object.values(item).find(item => this.matchesTerms(item, terms))
		}

		if (typeof item != 'string') return false

		return terms.find(term => item.toLowerCase().includes(term.toLowerCase()))
	}

	matchesTags (item, tags) {
		if (! Object.keys(tags).length) return true

		return Object.keys(tags).every(tag => {
			tag = this.tags.find(current => current.tag == tag)

			if (! tag) return false

			if (tag.type == 'number' || tag.type == 'date') {
				return tags[tag.tag].every(tagValue => this.isTagApplicable(tag, item, tagValue))
			} else {
				return tags[tag.tag].find(tagValue => this.isTagApplicable(tag, item, tagValue))
			}
		})
	}

	isTagApplicable (tag, item, tagValue) {
		if (tag.apply) {
			return tag.apply(item, tagValue)
		}

		item = tag.map ? tag.map(item) : item[tag.tag]

		if (tag.type == 'number') {
			let match

			if (match = tagValue.match(/^<(\d+(?:\.\d+)?)$/)) {
				return item < parseFloat(match[1])
			} else if (match = tagValue.match(/^>(\d+(?:\.\d+)?)$/)) {
				return parseFloat(match[1]) < item
			} else if (match = tagValue.match(/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/)) {
				return parseFloat(match[1]) < item && item < parseFloat(match[2])
			}

			return item == tagValue
		} else if (tag.type == 'date') {
			let match

			if (match = tagValue.match(/^<(.+)$/)) {
				return moment(item).isBefore(
					match[1].match(/^\d+:\d+(:\d+)?$/) ? moment().format('YYYY-MM-DD ') + match[1] : match[1]
				)
			} else if (match = tagValue.match(/^>(.+)$/)) {
				return moment(item).isAfter(
					match[1].match(/^\d+:\d+(:\d+)?$/) ? moment().format('YYYY-MM-DD ') + match[1] : match[1]
				)
			}

			return moment(item).isSame(tagValue)
		} else {
			return typeof item == 'string' && item.toLowerCase().includes(tagValue.toLowerCase())
		}
	}

	tokenize (input) {
		let terms = []
		let tags = {}

		let pattern = /(\w+:)?("[^"]*"|[^\s]+)/g
		let match

		while (match = pattern.exec(input)) {
			let tag = match[1] ? match[1].substr(0, match[1].length - 1) : undefined
			let value = match[2]

			if (match = value.match(/^"(.+?)"$/)) {
				value = match[1]
			}

			if (tag) {
				if (! tags[tag]) tags[tag] = []
				tags[tag].push(value)
			} else {
				terms.push(value)
			}
		}

		return { terms, tags }
	}
}
