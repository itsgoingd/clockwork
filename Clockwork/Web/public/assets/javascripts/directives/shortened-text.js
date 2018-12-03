Clockwork.directive('shortenedText', function () {
	return {
		link: function (scope, element, attrs) {
			element[0].addEventListener('click', ev => {
				if (element[0].dataset.shortTextExpanded == '1') return

				ev.preventDefault()

				element[0].innerHTML = element[0].getAttribute('shortened-text')
				element[0].dataset.shortTextExpanded = '1'
			})
		}
	}
})
