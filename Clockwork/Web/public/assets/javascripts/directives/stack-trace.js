Clockwork.directive('stackTrace', function ($parse) {
	return {
		restrict: 'E',
		transclude: false,
		scope: { trace: '=trace', shortPath: '=shortPath', fullPath: '=fullPath' },
		templateUrl: 'assets/partials/stack-trace.html',
		controller: function ($scope, $element) {
			$scope.showPopover = false

			$scope.togglePopover = function () {
				$scope.showPopover = ! $scope.showPopover

				let popoverContainerEl = $element[0].querySelector('.popover-container')
				let popoverEl = $element[0].querySelector('.popover')
				if (window.innerWidth - popoverContainerEl.getBoundingClientRect().left < 300) {
					popoverEl.classList.add('right-aligned')
				}
			}
		}
	}
})
