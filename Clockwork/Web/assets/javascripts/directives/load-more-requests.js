Clockwork.directive('loadMoreRequests', function ($parse) {
	return function (scope, element, attrs) {
		$(element).scrollTop($('.load-more').height() + 1)
	}
})
