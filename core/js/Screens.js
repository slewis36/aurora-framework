'use strict';

var
	_ = require('underscore'),
	$ = require('jquery'),
	ko = require('knockout'),
	
	ModulesManager = require('core/js/ModulesManager.js'),
	Settings = require('core/js/Settings.js'),
	
	bSingleMode = false
;

/**
 * @constructor
 */
function CScreens()
{
	var $win = $(window);
	this.resizeAll = _.debounce(function () {
		$win.resize();
	}, 100);
	
	this.oConstructors = {};
	this.oViews = {};

	this.currentScreen = ko.observable('');
	this.sDefaultScreen = '';

	this.informationScreen = ko.observable(null);
}

CScreens.prototype.init = function (bAuth)
{
	var oModulesScreens = ModulesManager.getModulesScreens(bAuth);
	
	_.each(oModulesScreens, _.bind(function (oScreens, sModuleName) {
		this.addToScreenList(sModuleName, oScreens);
	}, this));
	
	this.addToScreenList('', require('core/js/screenList.js'));
	
	if (this.oConstructors[Settings.EntryModule.toLowerCase()])
	{
		this.sDefaultScreen = Settings.EntryModule.toLowerCase();
	}
	
	if (!bSingleMode && bAuth)
	{
		this.showView('header');
	}
	
	this.initInformation();
};

CScreens.prototype.addToScreenList = function (sPrefix, oScreenList)
{
	_.each(oScreenList, _.bind(function (CScreenView, sKey) {
		var sNewKey = sKey.toLowerCase();
		if (sPrefix !== '')
		{
			if (sKey === 'main')
			{
				sNewKey = sPrefix.toLowerCase();
			}
			else
			{
				sNewKey = sPrefix.toLowerCase() + '-' + sKey;
			}
		}
		
		this.oConstructors[sNewKey] = CScreenView;
	}, this));
};

CScreens.prototype.route = function (aParams)
{
	var
		sCurrentScreen = this.currentScreen(),
		oCurrentScreen = this.oViews[sCurrentScreen],
		sNextScreen = aParams.shift()
	;
	
	if ((sNextScreen === '' || !this.oViews[sNextScreen]) && sCurrentScreen === '')
	{
		sNextScreen = this.sDefaultScreen;
	}
	
	if (this.oViews[sNextScreen] || this.oConstructors[sNextScreen])
	{
		if (sCurrentScreen !== sNextScreen)
		{
			if (oCurrentScreen)
			{
				oCurrentScreen.hideView();
			}
			
			oCurrentScreen = this.showView(sNextScreen);
		}
		
		if (oCurrentScreen)
		{
			this.currentScreen(sNextScreen);
			oCurrentScreen.onRoute(aParams);
		}
	}
};

/**
 * @param {string} sScreen
 * 
 * @return Object
 */
CScreens.prototype.showView = function (sScreen)
{
	var
		sScreenId = sScreen,
		CScreenView = this.oConstructors[sScreenId],
		oScreen = this.oViews[sScreenId]
	;
	
	if (!oScreen && CScreenView)
	{
		oScreen = this.initView(sScreenId, CScreenView);
	}
	
	if (oScreen)
	{
		oScreen.showView();
	}
	
	return oScreen;
};

/**
 * @param {string} sScreenId
 * @param {Object} CScreenView
 * 
 * @return {Object}
 */
CScreens.prototype.initView = function (sScreenId, CScreenView)
{
	var oScreen = new CScreenView();
	
	if (oScreen.ViewTemplate)
	{
		var $templatePlace = $('<!-- ko template: { name: \'' + oScreen.ViewTemplate + '\' } --><!-- /ko -->').appendTo($('#pSevenContent .screens'));
		if ($templatePlace.length > 0)
		{
			ko.applyBindings(oScreen, $templatePlace[0]);

			oScreen.$viewDom = $templatePlace.next();

			oScreen.onBind();
		}
	}
	
	this.oViews[sScreenId] = oScreen;
	delete this.oConstructors[sScreenId];
	
	return oScreen;
};

/**
 * @param {string} sMessage
 */
CScreens.prototype.showLoading = function (sMessage)
{
	if (this.informationScreen())
	{
		this.informationScreen().showLoading(sMessage);
	}
};

CScreens.prototype.hideLoading = function ()
{
	if (this.informationScreen())
	{
		this.informationScreen().hideLoading();
	}
};

/**
 * @param {string} sMessage
 * @param {number=} iDelay
 */
CScreens.prototype.showReport = function (sMessage, iDelay)
{
	if (this.informationScreen())
	{
		this.informationScreen().showReport(sMessage, iDelay);
	}
};

/**
 * @param {string} sMessage
 * @param {boolean=} bHtml = false
 * @param {boolean=} bNotHide = false
 * @param {boolean=} bGray = false
 */
CScreens.prototype.showError = function (sMessage, bHtml, bNotHide, bGray)
{
	if (this.informationScreen())
	{
		this.informationScreen().showError(sMessage, bHtml, bNotHide, bGray);
	}
};

/**
 * @param {boolean=} bGray = false
 */
CScreens.prototype.hideError = function (bGray)
{
	if (this.informationScreen())
	{
		this.informationScreen().hideError(bGray);
	}
};

CScreens.prototype.initInformation = function ()
{
	this.informationScreen(this.showView('information'));
};

//CScreens.prototype.initHelpdesk = function ()
//{
//	var
//		CScreenView = this.oConstructors[Enums.Screens.Helpdesk],
//		oScreen = this.oViews[Enums.Screens.Helpdesk]
//	;
//
//	if (AppData.User.IsHelpdeskSupported && !oScreen && CScreenView)
//	{
//		oScreen = this.initView(Enums.Screens.Helpdesk, CScreenView);
//	}
//};

var Screens = new CScreens();

module.exports = Screens;
