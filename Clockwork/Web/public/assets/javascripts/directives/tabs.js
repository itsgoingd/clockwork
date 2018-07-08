Clockwork.directive('tabs', function ($parse) {
	return {
		link: function (scope, element, attrs) {
			let tabs = element[0]

			tabs.addEventListener('click', ev => {
				if (! ev.target.getAttribute('tab-name')) return

				let tabName = ev.target.getAttribute('tab-name')

				tabs.querySelectorAll('[tab-name]').forEach(el => el.classList.remove('active'))
				ev.target.classList.add('active')

				tabs.querySelectorAll('[tab-content]').forEach(el => el.style.display = 'none')
				tabs.querySelector(`[tab-content="${tabName}"]`).style.display = 'block'
			})

			tabs.querySelector('[tab-name].active').click()
		}
	}
})
