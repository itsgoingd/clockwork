Clockwork.directive('shortenedText', function () {
	return {
		link: function (scope, element, attrs) {
			$(element).on('click', function () {
				$(this).html($(this).attr('shortened-text'))
			})
		}
	}
})
