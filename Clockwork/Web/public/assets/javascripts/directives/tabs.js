Clockwork.directive('tabs', function ($parse) {
	return {
		link: function (scope, element, attrs) {
			let tabs = element[0]

			tabs.querySelectorAll('[tab-name]').forEach(el => {
				el.addEventListener('click', ev => {
					let tabName = ev.currentTarget.getAttribute('tab-name')

					tabs.querySelectorAll('[tab-name]').forEach(el => el.classList.remove('active'))
					ev.currentTarget.classList.add('active')

					tabs.querySelectorAll('[tab-content]').forEach(el => el.style.display = 'none')
					tabs.querySelector(`[tab-content="${tabName}"]`).style.display = 'block'
				})
			})

			tabs.querySelector('[tab-name].active').click()
		}
	}
})
