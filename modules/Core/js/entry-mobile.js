'use strict';

var $ = require('jquery');

$('body').ready(function () {
	var
		oAvaliableModules = {
			'Auth': require('modules/AuthClient/js/manager.js'),
			'Mail': require('modules/MailClient/js/manager.js'),
			'Contacts': require('modules/ContactsClient/js/manager-mobile.js'),
			'SessionTimeout': require('modules/SessionTimeoutClient/js/manager.js')
		},
		ModulesManager = require('modules/Core/js/ModulesManager.js'),
		App = require('modules/Core/js/App.js')
	;
	
	App.setMobile();
	ModulesManager.init(oAvaliableModules, App.getUserRole(), App.isPublic());
	App.init();
});
