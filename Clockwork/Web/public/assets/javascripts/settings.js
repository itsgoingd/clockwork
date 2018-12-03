class Settings
{
	constructor (requests) {
		this.requests = requests

		this.reload()
	}

	get editor () {
		return this.settings.global.editor
	}

	set editor (value) {
		this.settings.global.editor = value
	}

	get localPathMapReal () {
		return this.getSite('localPathMap', {}).real
	}

	set localPathMapReal (value) {
		this.setSite('localPathMap', angular.merge(this.getSite('localPathMap', {}), { real: value }))
	}

	get localPathMapLocal () {
		return this.getSite('localPathMap', {}).local
	}

	set localPathMapLocal (value) {
		this.setSite('localPathMap', angular.merge(this.getSite('localPathMap', {}), { local: value }))
	}

	getSite(key, defaultValue) {
		return this.settings.site[this.requests.remoteUrl] ? this.settings.site[this.requests.remoteUrl][key] : defaultValue
	}

	setSite(key, value) {
		if (! this.settings.site[this.requests.remoteUrl]) this.settings.site[this.requests.remoteUrl] = {}

		this.settings.site[this.requests.remoteUrl][key] = value
	}

	save() {
		let settings = angular.merge(this.loadSettings(), this.settings)

		localStorage.setItem('settings', JSON.stringify(settings))
	}

	reload() {
		this.settings = this.loadSettings()
	}

	loadSettings() {
		let defaultSettings = { global: { editor: null }, site: {} }

		return angular.merge(defaultSettings, JSON.parse(localStorage.getItem('settings')));
	}
}
