let api = chrome || browser

function onMessage(message, sender, callback) {
	if (message.action == 'getJSON') {
		let xhr = new XMLHttpRequest()

		xhr.open('GET', message.url, true)

		xhr.onreadystatechange = function() {
			if (xhr.readyState != 4) return

			if (xhr.status != 200) {
				callback([])
				console.log('Error getting Clockwork metadata:')
				console.log(xhr.responseText)
				return
			}

			try {
				callback(JSON.parse(xhr.responseText))
			} catch (e) {
				callback([])
				console.log('Invalid Clockwork metadata:')
				console.log(xhr.responseText)
			}
		}

		Object.keys(message.headers || {}).forEach(headerName => {
			xhr.setRequestHeader(headerName, message.headers[headerName])
		})

		xhr.send()
	} else if (message.action == 'getLastClockworkRequestInTab') {
		callback(lastClockworkRequestPerTab[message.tabId])
	}

	return true
}

api.runtime.onMessage.addListener(onMessage)

// track last clockwork-enabled request per tab
let lastClockworkRequestPerTab = {}

api.webRequest.onCompleted.addListener(
	request => {
		if (request.responseHeaders.find((x) => x.name.toLowerCase() == 'x-clockwork-id')) {
			lastClockworkRequestPerTab[request.tabId] = { url: request.url, headers: request.responseHeaders }
		}
	},
	{ urls: [ '<all_urls>' ], types: [ 'main_frame' ] },
	[ 'responseHeaders' ]
)

api.tabs.onRemoved.addListener((tabId) => delete lastClockworkRequestPerTab[tabId])

// chrome.devtools.network.onRequestFinished replacement for Firefox
api.webRequest.onCompleted.addListener(
	request => {
		// ignore requests executed from extension itself
		if (request.documentUrl && request.documentUrl.match(new RegExp('^moz-extension://'))) return

		api.runtime.sendMessage({ action: 'requestCompleted', request: request })
	},
	{ urls: [ '<all_urls>' ] },
	[ 'responseHeaders' ]
)
