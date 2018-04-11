class PrettyJason
{
	constructor (data) {
		if (! (data instanceof Object)) {
			data = this.parseJson(data)
		}

		if (! (data instanceof Object)) {
			throw new PrettyJasonException('Input does not contain serialized object.')
		}

		this.data = data
	}

	parseJson (data) {
		try {
			return JSON.parse(data)
		} catch (e) {
			throw new PrettyJasonException('Input is not a valid JSON string.', e)
		}
	}

	print (target) {
		target.innerHTML = this.generateHtml()
	}

	generateHtml () {
		return this.createElement('ul', { class: 'pretty-jason' }, [
			this.createElement('li', {}, [
				this.createElement('span', {
					data: { rendered: true },
					click: ev => this.objectNodeClickedCallback(ev.currentTarget)
				}, [
					this.createElement('span', { class: 'pretty-jason-icon', html: '<i class="pretty-jason-icon-closed"></i>' }),
					this.createElement('span', { text: this.data.__class__ || 'Object ' })
				]),
				this.generateHtmlPreview(this.data),
				this.generateHtmlNode(this.data)
			])
		])
	}

	generateHtmlNode (data) {
		return this.createElement('ul', { style: { display: 'none' } }, Object.keys(data)
			.filter(key => key != '__class__')
			.map(key => {
				let value = data[key]
				let valueType = this.getValueType(value)

				if (valueType == 'object') {
					return this.createElement('li', { data: { key } }, [
						this.createElement('span', {
							click: ev => this.objectNodeClickedCallback(ev.currentTarget)
						}, [
							this.createElement('span', { class: 'pretty-jason-icon', html: '<i class="pretty-jason-icon-closed"></i>' }),
							this.createElement('span', { class: 'pretty-jason-key', text: `${key}: ` }),
							this.createElement('span', {
								class: 'pretty-jason-value',
								text: value.__class__ || 'Object'
							})
						])
					])
				}

				return this.createElement('li', { key }, [
					this.createElement('span', {}, [
						this.createElement('span', { class: 'pretty-jason-icon' }),
						this.createElement('span', { class: 'pretty-jason-key', text: `${key}: ` }),
						this.createElement('span', {
							class: `pretty-jason-value-${valueType}`,
							text: valueType == 'string' ? `"${value}"` : value
						})
					])
				])
			})
		)
	}

	generateHtmlPreview (data) {
		return this.createElement('span', { class: 'pretty-jason-preview' }, Object.keys(data)
			.filter(key => key != '__class__')
			.slice(0, 3)
			.map(key => {
				let value = data[key]
				let valueType = this.getValueType(value)

				if (valueType == 'object') {
					return this.createElement('span', { class: 'pretty-jason-preview-item' }, [
						this.createElement('span', { class: 'pretty-jason-key', text: `${key}: ` }),
						this.createElement('span', {
							class: 'pretty-jason-value',
							text: value.__class__ || 'Object'
						})
					])
				}

				return this.createElement('span', { class: 'pretty-jason-preview-item' }, [
					this.createElement('span', { class: 'pretty-jason-key', text: `${key}: ` }),
					this.createElement('span', {
						class: `pretty-jason-value-${valueType}`,
						text: valueType == 'string' ? `"${value}"` : value
					})
				])
			})
			.concat(Object.keys(data).length > 3 ? [
				this.createElement('span', { class: 'pretty-jason-preview-item', text: '...' })
			] : [])
		)
	}

	getValueType (value) {
		if (value === null) {
			return 'null'
		} else if (value === undefined) {
			return 'undefined'
		}

		return typeof value
	}

	objectNodeClickedCallback (node) {
		this.renderObjectNode(node)

		let list = node.parentNode.querySelector('ul')
		let icon = node.querySelector('i')

		icon.classList.remove('pretty-jason-icon-closed', 'pretty-jason-icon-open')

		if (list.style.display == 'none') {
			list.style.display = 'block'
			icon.classList.add('pretty-jason-icon-open')
		} else {
			list.style.display = 'none'
			icon.classList.add('pretty-jason-icon-closed')
		}
	};

	renderObjectNode (node) {
		if (node.dataset.rendered) return

		let path = []

		let parent = node
		while (parent = parent.parentNode) {
			if (parent.tagName != 'LI' || ! parent.dataset.key) continue
			if (parent.classList.contains('pretty-jason')) break

			let segment = parent.dataset.key

			path.unshift(! isNaN(parseInt(segment, 10)) ? parseInt(segment, 10) : segment)
		}

		node.parentNode.append(this.generateHtmlNode(this.getDataFromPath(path)))
		node.dataset.rendered = true
	}

	getDataFromPath (path) {
		let data = this.data
		let segment

		while ((segment = path.shift()) !== undefined) {
			data = data[segment]
		}

		return data
	}

	createElement (name, options, children) {
		let element = document.createElement(name)

		if (options.html) element.innerHTML = options.html
		if (options.text) element.innerText = options.text

		if (options.class) element.classList.add(options.class)

		if (options.style instanceof Object) {
			Object.keys(options.style).forEach(key => element.style[key] = options.style[key])
		}

		if (options.data instanceof Object) {
			Object.keys(options.data).forEach(key => element.dataset[key] = options.data[key])
		}

		if (options.click) element.addEventListener('click', options.click)

		if (children instanceof Array) children.forEach(child => element.append(child))

		return element
	}
}

class PrettyJasonException {
	constructor (message, exception){
		this.message = message
		this.exception = exception
	}
}
