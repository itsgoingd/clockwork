Clockwork.directive('prettyPrint', function ($parse) {
	return {
		restrict: 'E',
		replace: true,
		transclude: false,
		scope: { data: '=data' },
		link: function (scope, element, attrs) {
			let data = scope.data
			let rendered = document.createElement('div')

			if (data === true) {
				rendered.innerHTML = '<i>true</i>'
			} else if (data === false) {
				rendered.innerHTML = '<i>false</i>'
			} else if (data === undefined) {
				rendered.innerHTML = '<i>undefined</i>'
			} else if (data === null) {
				rendered.innerHTML = '<i>null</i>'
			} else if (typeof data === 'number') {
				rendered.innerText = data
			} else {
				try {
					rendered.append((new PrettyJason(data)).generateHtml())
				} catch (e) {
					rendered.innerText = data
				}
			}

			element.replaceWith(rendered)
		}
	}
})
