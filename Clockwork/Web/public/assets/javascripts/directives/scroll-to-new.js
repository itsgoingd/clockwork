Clockwork.directive('scrollToNew', function ($parse) {
	return function(scope, element, attrs) {
		if (scope.showIncomingRequests && scope.$last) {
			let container = document.querySelector('.requests-container')
			let parent = element[0].parentNode

			container.scrollTop = parent.offsetHeight
		}
	}
})
