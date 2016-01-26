'use strict';

var
	_ = require('underscore'),
	$ = require('jquery'),
	ko = require('knockout'),
	
	TextUtils = require('core/js/utils/Text.js'),
	Utils = require('core/js/utils/Common.js'),
	
	Api = require('core/js/Api.js'),
	Screens = require('core/js/Screens.js'),
	ModulesManager = require('core/js/ModulesManager.js'),
	CAbstractSettingsFormView = ModulesManager.run('Settings', 'getAbstractSettingsFormViewClass'),
	
	Accounts = require('modules/Mail/js/AccountList.js'),
	Ajax = require('modules/Mail/js/Ajax.js')
;

/**
 * @constructor
 * 
 * @param {Object} oParent
 * @param {boolean} bCreate
 */
function CIdentityPropertiesPaneView(oParent, bCreate)
{
	CAbstractSettingsFormView.call(this, 'Mail');
	
	this.identity = ko.observable(null);
	
	this.defaultAccountId = Accounts.defaultId;
	this.oParent = oParent;
	this.bCreate = bCreate;

	this.disableCheckbox = ko.observable(false);

	this.enabled = ko.observable(true);
	this.isDefault = ko.observable(false);
	this.email = ko.observable('');
	this.loyal = ko.observable(false);
	this.friendlyName = ko.observable('');
	this.friendlyNameHasFocus = ko.observable(false);
}

_.extendOwn(CIdentityPropertiesPaneView.prototype, CAbstractSettingsFormView.prototype);

CIdentityPropertiesPaneView.prototype.ViewTemplate = 'Mail_Settings_IdentityPropertiesPaneView';

CIdentityPropertiesPaneView.prototype.__name = 'CIdentityPropertiesPaneView';

/**
 * @param {Object} oIdentity
 */
CIdentityPropertiesPaneView.prototype.show = function (oIdentity)
{
	this.identity(oIdentity && !oIdentity.FETCHER ? oIdentity : null);
	this.populate();
};

CIdentityPropertiesPaneView.prototype.getCurrentValues = function ()
{
	return [
		this.friendlyName(),
		this.email()
	];
};

CIdentityPropertiesPaneView.prototype.getParametersForSave = function ()
{
	if (this.identity())
	{
		var
			iAccountId = this.identity().accountId(),
			oParameters = {
				'AccountID': iAccountId,
				'Default': this.isDefault() ? 1 : 0,
				'FriendlyName': this.friendlyName(),
				'Loyal': this.identity().loyal() ? 1 : 0
			}
		;

		if (!this.identity().loyal())
		{
			_.extendOwn(oParameters, {
				'Email': this.email(),
				'Enabled': this.enabled() ? 1 : 0
			});

			if (!this.bCreate)
			{
				oParameters.IdIdentity = this.identity().id();
			}
		}

		return oParameters;
	}
	
	return {};
};

CIdentityPropertiesPaneView.prototype.save = function ()
{
	if (this.email() === '')
	{
		Screens.showError(Utils.i18n('WARNING/IDENTITY_CREATE_ERROR'));
	}
	else
	{
		this.isSaving(true);

		this.updateSavedState();

		Ajax.send(this.bCreate ? 'CreateIdentity' : 'UpdateIdentity', this.getParametersForSave(), this.onResponse, this);
	}
};

/**
 * @param {Object} oResponse
 * @param {Object} oRequest
 */
CIdentityPropertiesPaneView.prototype.onResponse = function (oResponse, oRequest)
{
	this.isSaving(false);

	if (!oResponse.Result)
	{
		Api.showErrorByCode(oResponse, TextUtils.i18n('SETTINGS/ACCOUNTS_IDENTITY_ADDING_ERROR'));
	}
	else
	{
		var
			oParameters = JSON.parse(oRequest.Parameters),
			iAccountId = Utils.pInt(oResponse.AccountID),
			oAccount = 0 < iAccountId ? Accounts.getAccount(iAccountId) : null
		;
		
		Accounts.populateIdentities();
		
		if (this.bCreate && $.isFunction(this.oParent.closePopup))
		{
			this.oParent.closePopup();
		}

		if (oParameters.Loyal === 1 && oAccount)
		{
			oAccount.updateExtended(oParameters);
			oAccount.isExtended(false);
		}

		this.disableCheckbox(this.isDefault());
		
		Screens.showReport(TextUtils.i18n('SETTINGS/COMMON_REPORT_UPDATED_SUCCESSFULLY'));
	}
};

CIdentityPropertiesPaneView.prototype.populate = function ()
{
	var oIdentity = this.identity();
	
	if (oIdentity)
	{
		this.enabled(oIdentity.enabled());
		this.isDefault(oIdentity.isDefault());
		this.email(oIdentity.email());
		this.loyal(oIdentity.loyal());
		this.friendlyName(oIdentity.friendlyName());

		this.disableCheckbox(oIdentity.isDefault());

		setTimeout(function () {
			this.updateSavedState();
		}.bind(this), 1);
	}
};

CIdentityPropertiesPaneView.prototype.remove = function ()
{
	if (this.identity() && !this.identity().loyal())
	{
		var oParameters = {
			'AccountID': this.identity().accountId(),
			'IdIdentity': this.identity().id()
		};

		Ajax.send('DeleteIdentity', oParameters, this.onAccountIdentityDeleteResponse, this);

		if (!this.bCreate && $.isFunction(this.oParent.onRemoveIdentity))
		{
			this.oParent.onRemoveIdentity();
		}
	}
};

/**
 * @param {Object} oResponse
 * @param {Object} oRequest
 */
CIdentityPropertiesPaneView.prototype.onAccountIdentityDeleteResponse = function (oResponse, oRequest)
{
	if (!oResponse.Result)
	{
		Api.showErrorByCode(oResponse, Utils.i18n('SETTINGS/ACCOUNTS_IDENTITY_DELETING_ERROR'));
	}
	Accounts.populateIdentities();
};

CIdentityPropertiesPaneView.prototype.cancel = function ()
{
	if ($.isFunction(this.oParent.cancelPopup))
	{
		this.oParent.cancelPopup();
	}
};

module.exports = CIdentityPropertiesPaneView;
