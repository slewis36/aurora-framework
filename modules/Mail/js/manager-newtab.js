'use strict';

module.exports = function (oSettings) {
	require('modules/Mail/js/enums.js');

	var
		Settings = require('modules/Mail/js/Settings.js'),
		Cache = null
	;

	Settings.init(oSettings);
	
	Cache = require('modules/Mail/js/Cache.js');
	Cache.init();
	
	return {
		screens: {
			'view': function () {
				return require('modules/Mail/js/views/MessagePaneView.js');
			},
			'compose': function () {
				var CComposeView = require('modules/Mail/js/views/CComposeView.js')
				return new CComposeView();
			}
		}
	};
};
