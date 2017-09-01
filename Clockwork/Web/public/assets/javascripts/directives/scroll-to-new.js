Clockwork.directive('scrollToNew', function ($parse) {
	return function(scope, element, attrs) {
		if (scope.showIncomingRequests && scope.$last) {
			var $container = $(element).parents('.requests-container').first()
			var $parent = $(element).parent()

			$container.scrollTop($parent.height())
		}
	}
})
