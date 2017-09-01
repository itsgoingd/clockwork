Clockwork.directive('resizableColumns', function ($parse) {
	return {
		link: function (scope, element, attrs) {
			let options = { minWidth: 5 }

			if ($(element).data('resizable-columns-sync')) {
				let $target = $($(element).data('resizable-columns-sync'))

				$(element).on('column:resize', (event, resizable, $leftColumn, $rightColumn, widthLeft, widthRight) => {
					let leftColumnIndex = resizable.$table.find('.rc-column-resizing').parent().find('td, th').index($leftColumn)
					let $targetFirstRow = $target.find('tr:first')

					$($targetFirstRow.find('td, th').get(leftColumnIndex)).css('width', widthLeft + '%')
					$($targetFirstRow.find('td, th').get(leftColumnIndex + 1)).css('width', widthRight + '%')

					$target.data('resizableColumns').syncHandleWidths()
					$target.data('resizableColumns').saveColumnWidths()
				})
			}

			$(element).resizableColumns(options)
		}
	}
})
