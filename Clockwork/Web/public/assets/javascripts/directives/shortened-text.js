Clockwork.directive('shortenedText', function () {
	return {
		link: function (scope, element, attrs) {
			element[0].addEventListener('click', ev => {
				element[0].innerHTML = element[0].getAttribute('shortened-text')
			})
		}
	}
})
