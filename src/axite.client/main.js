window.DEBUG = true

if (window.DEBUG) {
	window.requirejs.config({urlArgs: "bust=" +  (new Date()).getTime()})
}

require(["ax/axite", "/templates/poll.js", "i18n/uk.lang"], function (Axite, Poll) {
	window.poll = Poll
	window.axite = new Axite()
})
