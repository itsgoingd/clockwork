Clockwork.directive('tabs', function ($parse) {
	return {
		link: function (scope, element, attrs) {
			$(element).find('[tab-name]').on('click', function () {
				let tabs = $(this).parents('[tabs]')
				let tabName = $(this).attr('tab-name')

				tabs.find('[tab-name]').removeClass('active')
				$(this).addClass('active')

				tabs.find('[tab-content]').hide()
				tabs.find('[tab-content="' + tabName + '"]').show()
			})

			$(element).find('[tab-name].active').click()
		}
	}
})
