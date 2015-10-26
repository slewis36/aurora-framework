'use strict';

function IsPgpSupported()
{
	return !!(window.crypto && window.crypto.getRandomValues);
}

module.exports = function (oSettings) {
	var Settings = require('modules/OpenPgp/js/Settings.js');
	Settings.init(oSettings);
	
	return {
		start: function (ModulesManager) {
			if (IsPgpSupported())
			{
				ModulesManager.run('Mail', 'registerMessagePaneTopController', [require('modules/OpenPgp/js/views/MessageControlsView.js')]);
				ModulesManager.run('Mail', 'registerComposeExtraButtons', [require('modules/OpenPgp/js/views/ComposeButtonsView.js')]);
			}
		}
	};
};