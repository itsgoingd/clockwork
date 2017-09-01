Clockwork.directive('stupidTable', function () {
	return {
		link: function (scope, element, attrs) {
			$(element).stupidtable()
		}
	}
})
