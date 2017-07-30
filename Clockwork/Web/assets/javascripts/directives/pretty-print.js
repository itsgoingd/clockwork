Clockwork.directive('prettyPrint', function ($parse) {
	return {
		restrict: 'E',
		replace: true,
		transclude: false,
		scope: { data: '=data' },
		link: function (scope, element, attrs) {
			let data = scope.data
			let jason

			if (data === true) {
				data = '<i>true</i>'
			} else if (data === false) {
				data = '<i>false</i>'
			} else if (data === undefined) {
				data = '<i>undefined</i>'
			} else if (data === null) {
				data = '<i>null</i>'
			} else if (typeof data !== 'number') {
				try {
					jason = new PrettyJason(data)
				} catch (e) {
					data = $('<div>').text(data).html()
				}
			}

			var $el = $('<div></div>');

			if (jason) {
				$el.append(jason.generateHtml())
			} else {
				$el.html(data)
			}

			element.replaceWith($el)
		}
	}
})
