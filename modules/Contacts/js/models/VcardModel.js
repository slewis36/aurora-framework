'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	Utils = require('core/js/utils/Common.js'),
	App = require('core/js/App.js'),
	Ajax = require('modules/Contacts/js/Ajax.js'),
	
	ContactsCache = require('modules/Contacts/js/Cache.js'),
	HeaderItemView = !App.isNewTab() ? require('modules/Contacts/js/views/HeaderItemView.js') : null,
	BaseTab = (App.isNewTab() && window.opener) ? window.opener.BaseTabContactsMethods : null
;

/**
 * @constructor
 */
function CVcardModel()
{
	this.uid = ko.observable('');
	this.file = ko.observable('');
	this.name = ko.observable('');
	this.email = ko.observable('');
	this.exists = ko.observable(false);
	this.isJustSaved = ko.observable(false);
}

/**
 * @param {AjaxVCardResponse} oData
 */
CVcardModel.prototype.parse = function (oData)
{
	if (oData && oData['@Object'] === 'Object/CApiMailVcard')
	{
		this.uid(Utils.pString(oData.Uid));
		this.file(Utils.pString(oData.File));
		this.name(Utils.pString(oData.Name));
		this.email(Utils.pString(oData.Email));
		this.exists(!!oData.Exists);
		
		ContactsCache.addVcard(this);
	}
};

/**
 * @param {Object} oData
 * @param {Object} oParameters
 */
CVcardModel.prototype.onContactsSaveVcfResponse = function (oData, oParameters)
{
	if (oData && oData.Result && oData.Result.Uid)
	{
		this.uid(oData.Result.Uid);
	}
};

CVcardModel.prototype.addContact = function ()
{
	Ajax.send('AddContactsFromFile', {'File': this.file()}, this.onContactsSaveVcfResponse, this);
	
	this.isJustSaved(true);
	this.exists(true);
	
	setTimeout(_.bind(function () {
		this.isJustSaved(false);
	}, this), 20000);
	
	console.log('HeaderItemView', HeaderItemView);
	console.log('BaseTab', BaseTab);
	if (HeaderItemView)
	{
		HeaderItemView.recivedAnim(true);
	}
	else if (BaseTab)
	{
		BaseTab.markVcardsExistentByFile(this.file());
	}
};

module.exports = CVcardModel;