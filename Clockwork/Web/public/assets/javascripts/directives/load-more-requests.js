Clockwork.directive('loadMoreRequests', function ($parse) {
	return function (scope, element, attrs) {
		element[0].scrollTop = document.querySelector('.load-more').offsetHeight + 1
	}
})
