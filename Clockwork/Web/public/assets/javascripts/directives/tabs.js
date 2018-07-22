Clockwork.directive('tabs', function ($parse) {
	return {
		link: function (scope, element, attrs) {
			let tabs = element[0]
			let namespace = tabs.getAttribute('tabs')
			let namespacePrefix = namespace ? `${namespace}.` : ''

			let attributeStartsWith = (attribute, prefix) => {
				return (element) => element.getAttribute(attribute).startsWith(prefix)
			}

			let showTab = (tabEl) => {
				let tabName = tabEl.getAttribute('tab-name')

				if (! tabEl.getAttribute('tab-name')) return

				if (namespace && ! tabName.startsWith(namespacePrefix)) return

				tabs.querySelectorAll(`[tab-name^="${namespacePrefix}"]`)
					.forEach(el => el.classList.remove('active'))
				tabEl.classList.add('active')

				tabs.querySelectorAll(`[tab-content^="${namespacePrefix}"]`)
					.forEach(el => el.style.display = 'none')
				tabs.querySelector(`[tab-content="${tabName}"]`).style.display = 'block'
			}

			tabs.addEventListener('click', ev => showTab(ev.target))

			showTab(tabs.querySelector(`[tab-name^="${namespacePrefix}"].active`))
		}
	}
})
