<?php
/*
 * @copyright Copyright (c) 2016, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 * 
 */

/**
 * @property int $IdUser
 * @property int $IdSubscription
 * @property int $IdHelpdeskUser
 * @property int $ContactsPerPage
 * @property int $AutoRefreshInterval
 * @property int $CreatedTime
 * @property int $LastLogin
 * @property int $LastLoginNow
 * @property int $LoginsCount
 * @property string $DefaultSkin
 * @property string $DefaultLanguage
 * @property int $DefaultTimeZone
 * @property int $DefaultTimeFormat
 * @property string $DefaultDateFormat
 * @property string $Question1
 * @property string $Question2
 * @property string $Answer1
 * @property string $Answer2
 * @property string $Capa
 * @property string $ClientTimeZone
 * @property bool $DesktopNotifications
 * @property bool $EnableOpenPgp
 * @property bool $AutosignOutgoingEmails
 * @property bool $AllowHelpdeskNotifications
 * @property mixed $CustomFields
 * @property bool $SipEnable
 * @property string $SipImpi
 * @property string $SipPassword
 * 
 * @property string $TwilioNumber
 * @property bool $TwilioEnable
 * @property bool $TwilioDefaultNumber
 * 
 * @property string $HelpdeskSignature
 * @property bool $HelpdeskSignatureEnable
 * @property bool $FilesEnable
 * @property string $EmailNotification
 * @property string $PasswordResetHash
 *
 * @package Users
 * @subpackage Classes
 */
class CUser extends AEntity
{
	/**
	 * @var CSubscription
	 */
	private $oSubCache;

	/**
	 * Creates a new instance of the object.
	 * 
	 * @return void
	 */
	public function __construct($sModule, $oParams)
	{
		//parent::__construct(get_class($this), 'IdUser');
		parent::__construct(get_class($this), $sModule);
		
		$this->__USE_TRIM_IN_STRINGS__ = true;
		
		$this->SetDefaults();

		$this->oSubCache = null;
		
//		$this->SetUpper(array('Capa'));

		$this->setInheritedSettings($oParams);
	}
	
	/**
	 * temp method
	 */
	public function setInheritedSettings($oParams = array())
	{
		$oSettings =& CApi::GetSettings();
		
		if (isset($oParams['domain']))
		{
//				array(
	//			'IdUser'							=> 0,
	//			'IdSubscription'					=> 0,
	//			'IdHelpdeskUser'					=> 0,

				$this->ContactsPerPage = $oParams['domain']->ContactsPerPage;
				$this->AutoRefreshInterval = $oParams['domain']->AutoRefreshInterval;

	//			'CreatedTime'						=> 0,
	//			'LastLogin'							=> 0,
	//			'LastLoginNow'						=> 0,
	//			'LoginsCount'						=> 0,

				$this->DefaultSkin = $oParams['domain']->DefaultSkin;
				$this->DefaultLanguage = $oParams['domain']->DefaultLanguage;

				$this->DefaultTimeZone = 0; // $oDomain->DefaultTimeZone, // TODO
				$this->DefaultTimeFormat = $oParams['domain']->DefaultTimeFormat;
				$this->DefaultDateFormat = $oParams['domain']->DefaultDateFormat;

	//			'Question1'							=> '',
	//			'Question2'							=> '',
	//			'Answer1'							=> '',
	//			'Answer2'							=> '',

	//			'TwilioNumber'						=> '',
	//			'TwilioEnable'						=> true,
	//			'TwilioDefaultNumber'				=> false,
	//			'SipEnable'							=> true,
	//			'SipImpi'							=> '',
	//			'SipPassword'						=> '',

	//			'Capa'								=> '',
	//			'ClientTimeZone'					=> '',
	//			'DesktopNotifications'				=> false,
	//			'EnableOpenPgp'						=> false,
	//			'AutosignOutgoingEmails'			=> false,
	//			'AllowHelpdeskNotifications'		=> false,
	//			'CustomFields'						=> '',
	//
	//			'HelpdeskSignature'					=> '',
	//			'HelpdeskSignatureEnable'			=> false,
	//
	//			'FilesEnable'						=> true,
	//			
	//			'EmailNotification'					=> '',
	//			
	//			'PasswordResetHash'					=> ''
//			);
		}

//		CDomain $oDomain
	}

	/**
	 * @ignore
	 * @todo not used
	 * 
	 * @param string $sCapaName
	 *
	 * @return bool
	 */
	public function getCapa($sCapaName)
	{
		return true;
		// TODO

		if (!CApi::GetConf('capa', false) || '' === $this->Capa ||
			0 === $this->IdSubscription)
		{
			return true;
		}

		$sCapaName = preg_replace('/[^A-Z0-9_=]/', '', strtoupper($sCapaName));

		$aCapa = explode(' ', $this->Capa);

		return in_array($sCapaName, $aCapa);
	}

	/**
	 * @ignore
	 * @todo not used
	 * 
	 * @return void
	 */
	public function allowAllCapas()
	{
		$this->Capa = '';
	}

	/**
	 * @ignore
	 * @todo not used
	 * 
	 * @return void
	 */
	public function removeAllCapas()
	{
		$this->Capa = ECapa::NO;
	}

	/**
	 * @ignore
	 * @todo not used
	 * 
	 * @param CTenant $oTenant
	 * @param string $sCapaName
	 * @param bool $bValue
	 *
	 * @return bool
	 */
	public function setCapa($oTenant, $sCapaName, $bValue)
	{
		if (!CApi::GetConf('capa', false) || !$oTenant)
		{
			return true;
		}

		return true;
	}

	/**
	 * Checks if the user has only valid data.
	 * 
	 * @return bool
	 */
	public function isValid()
	{
		switch (true)
		{
			case false:
				throw new CApiValidationException(Errs::Validation_FieldIsEmpty, null, array(
					'{{ClassName}}' => 'CUser', '{{ClassField}}' => 'Error'));
		}

		return true;
	}
	
	public static function createInstance($sModule = 'Core', $oParams = array())
	{
		return new CUser($sModule, $oParams);
	}

	/**
	 * Obtains static map of user fields.
	 * 
	 * @return array
	 */
	public static function getStaticMap()
	{
		return array(

			'IdUser'							=> array('int', 0), //'id_user'),
			'IdSubscription'					=> array('int', 0), //'id_subscription'),
			'IdHelpdeskUser'					=> array('int', 0), //'id_helpdesk_user'),

			'ContactsPerPage'					=> array('int', 0), //'contacts_per_page'),
			'AutoRefreshInterval'				=> array('int', 0), //'auto_checkmail_interval'),

			'CreatedTime'						=> array('string', ''), //'created_time'), //must be datetime
			'LastLogin'							=> array('string', ''), //'last_login', true, false), //must be datetime
			'LastLoginNow'						=> array('string', ''), //'last_login_now', true, false), //must be datetime
			'LoginsCount'						=> array('int', 0), //'logins_count', true, false),

			'DefaultSkin'						=> array('string', ''), //'def_skin'),
			'DefaultLanguage'					=> array('string', ''), //'def_lang'),

			'DefaultTimeZone'					=> array('int', 0), //'def_timezone'),
			'DefaultTimeFormat'					=> array('int', 0), //'def_time_fmt'),
			'DefaultDateFormat'					=> array('string', ''), //'def_date_fmt'),
			'ClientTimeZone'					=> array('string', ''), //'client_timezone'),

			'Question1'							=> array('string', ''), //'question_1'),
			'Question2'							=> array('string', ''), //'question_2'),
			'Answer1'							=> array('string', ''), //'answer_1'),
			'Answer2'							=> array('string', ''), //'answer_2'),

			'SipEnable'							=> array('bool', true), //'sip_enable'),
			'SipImpi'							=> array('string', ''), //'sip_impi'),
			'SipPassword'						=> array('string', ''), //'sip_password'), //must be password
			
			'TwilioEnable'						=> array('bool', true), //'twilio_enable'),
			'TwilioNumber'						=> array('string', ''), //'twilio_number'),
			'TwilioDefaultNumber'				=> array('bool', false), //'twilio_default_number'),

			'DesktopNotifications'				=> array('bool', false), //'desktop_notifications'),
			'AllowHelpdeskNotifications'		=> array('bool', false), //'allow_helpdesk_notifications'),

			'EnableOpenPgp'						=> array('bool', true), //'enable_open_pgp'),
			'AutosignOutgoingEmails'			=> array('bool', true), //'autosign_outgoing_emails'),

			'Capa'								=> array('string', ''), //'capa'),
			'CustomFields'						=> array('string', ''), //'custom_fields'), //must be serialize type

			'HelpdeskSignature'					=> array('string', ''), //'helpdesk_signature'),
			'HelpdeskSignatureEnable'			=> array('bool', true), //'helpdesk_signature_enable'),

			'FilesEnable'						=> array('bool', true), //'files_enable'),
			
			'EmailNotification'					=> array('string', ''), //'email_notification'),
			
			'PasswordResetHash'					=> array('string', ''), //'password_reset_hash')
		);
	}
}
