<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 * CApiTenantsManager class summary
 *
 * @package Tenants
 */
class CApiTenantsManager extends AApiManagerWithStorage
{
	/**
	 * @var array
	 */
	static $aTenantNameCache = array();
	
	/**
	 * @var CApiEavManager
	 */
	public $oEavManager = null;
	
	/**
	 * @var CTenant
	 */
	static $oDefaultTenant = null;

	/**
	 * Creates a new instance of the object.
	 *
	 * @param CApiGlobalManager &$oManager
	 */
	public function __construct(CApiGlobalManager &$oManager, $sForcedStorage = '')
	{
		parent::__construct('tenants', $oManager, $sForcedStorage);
		
		$this->oEavManager = \CApi::GetCoreManager('eav', 'db');
		
		$this->inc('classes.tenant');
		$this->inc('classes.socials');
	}

	/**
	 * @param int $iPage
	 * @param int $iTenantsPerPage
	 * @param string $sOrderBy Default value is **'Login'**.
	 * @param bool $bOrderType Default value is **true**.
	 * @param string $sSearchDesc Default value is empty string.
	 *
	 * @return array|false [Id => [Login, Description]]
	 */
	public function getTenantList($iPage, $iTenantsPerPage, $sOrderBy = 'Login', $iOrderType = \ESortOrder::ASC, $sSearchDesc = '')
	{
		$aResult = false;
		try
		{
			$aResultTenants = $this->oEavManager->getObjects('CTenant', 
				array(
					'Login', 
					'Description'
				), $iPage, $iTenantsPerPage,
				array('Description' => '%'.$sSearchDesc.'%'),
				$sOrderBy, $iOrderType
			);

			foreach($aResultTenants as $oTenat)
			{
				$aResult[$oTenat->iObjectId] = array($oTenat->Login, $oTenat->Description);
			}
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $aResult;
	}

	/**
	 * @param string $sSearchDesc Default value is empty string.
	 *
	 * @return int|false
	 */
	public function getTenantCount($sSearchDesc = '')
	{
		$iResult = false;
		try
		{
			$aResultTenants = $this->oEavManager->getObjectsCount('CTenant', 
				array(
					'Description' => $sSearchDesc
				)
			);
			
			$iResult = count($aResultTenants);
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $iResult;
	}

	/**
	 * @param int $iTenantId
	 *
	 * @return int
	 */
	public function getTenantAllocatedSize($iTenantId)
	{
		//TODO use new logic then Account class will be rewrited
		$iResult = 0;
		if (0 < $iTenantId)
		{
			try
			{
				$iResult = $this->oStorage->getTenantAllocatedSize($iTenantId);
			}
			catch (CApiBaseException $oException)
			{
				$this->setLastException($oException);
			}
		}

		return $iResult;
	}

	/**
	 * @return CTenant
	 */
	public function getDefaultGlobalTenant()
	{
		if (self::$oDefaultTenant === null)
		{
			try
			{
				$oResult = $this->oEavManager->getObjects('CTenant', 
					array(
						'IsDefault'
					),
					0,
					0,
					array('IsDefault' => true)
				);

				if ($oResult instanceOf \CTenant)
				{
					self::$oDefaultTenant = $oResult;
				}
			}
			catch (CApiBaseException $oException)
			{
				$this->setLastException($oException);
			}
		}
		
		return self::$oDefaultTenant;
	}

	/**
	 * @param mixed $mTenantId
	 * @param bool $bIdIsHash Default value is **false**.
	 *
	 * @return CTenant|null
	 */
	public function getTenantById($mTenantId, $bIdIsHash = false)
	{
		$oTenant = null;
		try
		{
			//TODO verify logic
//			$oTenant = $this->oStorage->getTenantById($mTenantId, $bIdIsHash);
			if (!$bIdIsHash)
			{
				$oResult = $this->oEavManager->getObjectById($mTenantId);
				
				if ($oResult instanceOf \CTenant)
				{
					$oTenant = $oResult;
				}
			}
			else
			{
				$aResultTenants = $this->oEavManager->getObjects('CTenant', 
					array(
						'Hash'
					),
					0,
					1,
					array('Hash' => $mTenantId)
				);
				
				if (isset($aResultTenants[0]) && $aResultTenants[0] instanceOf \CTenant)
				{
					$oTenant = $aResultTenants[0];
				}
			}
			
			if ($oTenant)
			{
				/* @var $oTenant CTenant */
				
				$mTenantId = $oTenant->iObjectId;

				$iFilesUsageInMB = 0;
				if (0 < strlen($oTenant->FilesUsageInBytes))
				{
					$iFilesUsageInMB = (int) ($oTenant->FilesUsageInBytes / (1024 * 1024));
				}

				$oTenant->AllocatedSpaceInMB = $this->getTenantAllocatedSize($mTenantId) + $iFilesUsageInMB;
//				$oTenant->FlushObsolete('AllocatedSpaceInMB');

				$oTenant->FilesUsageInMB = $iFilesUsageInMB;
//				$oTenant->FlushObsolete('FilesUsageInMB');

				if (0 < $oTenant->QuotaInMB)
				{
					$oTenant->FilesUsageDynamicQuotaInMB = $oTenant->QuotaInMB - $oTenant->AllocatedSpaceInMB + $oTenant->FilesUsageInMB;
					$oTenant->FilesUsageDynamicQuotaInMB = 0 < $oTenant->FilesUsageDynamicQuotaInMB ?
						$oTenant->FilesUsageDynamicQuotaInMB : 0;
//					$oTenant->FlushObsolete('FilesUsageDynamicQuotaInMB');
				}
			}
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $oTenant;
	}

	/**
	 * @param string $sTenantHash
	 *
	 * @return CTenant
	 */
	public function getTenantByHash($sTenantHash)
	{
		return $this->getTenantById($sTenantHash, true);
	}

	/**
	 * @param string $sTenantLogin
	 * @param string $sTenantPassword Default value is **null**.
	 *
	 * @return int
	 */
	public function getTenantIdByLogin($sTenantLogin, $sTenantPassword = null)
	{
		$iTenantId = 0;
		try
		{
			if (!empty($sTenantLogin))
			{
				$oFilterBy = array('Login' => $sTenantLogin);
				if (null !== $sTenantPassword)
				{
					$oFilterBy['PasswordHash'] = CTenant::hashPassword($sTenantPassword);
					
					//TODO why we shoud filter by these fields?
					$oFilterBy['IsDisabled'] = false;
					$oFilterBy['IsEnableAdminPanelLogin'] = true;
				}
				
				$aResultTenants = $this->oEavManager->getObjects(
					'CTenant', 
					array(
						'Login'
					),
					0,
					1,
					$oFilterBy
				);

				if (($aResultTenants[0]) && $aResultTenants[0] instanceOf \CTenant)
				{
					$iTenantId = $aResultTenants[0]->iObjectId;
				}
			}
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $iTenantId;
	}
	
	/**
	 * @param string $sTenantHash
	 *
	 * @return int|bool
	 */
	public function getTenantIdByHash($sTenantHash)
	{
		//TODO
		$iResult = 0;

		if (0 === strlen($sTenantHash))
		{
			return 0;
		}
		else if (0 < strlen($sTenantHash))
		{
			$oTenant = $this->getTenantByHash($sTenantHash);
			if ($oTenant)
			{
				$iResult = $oTenant->iObjectId;
			}
		}

		return 0 < $iResult ? $iResult : false;
	}
	
	
	/**
	 * @param int $iDomainId
	 *
	 * @return int
	 */
	public function getTenantIdByDomainId($iDomainId)
	{
		//TODO use DOMAIN Manager for that
		$iTenantId = 0;
		try
		{
			if (0 < $iDomainId)
			{
				$iTenantId = $this->oStorage->getTenantIdByDomainId($iDomainId);
			}
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		
		return $iTenantId;
	}

	/**
	 * @TODO
	 * @param int $iIdTenant
	 * @param bool $bUseCache Default value is **false**.
	 *
	 * @return string
	 */
	public function getTenantLoginById($iIdTenant, $bUseCache = false)
	{
		$sResult = '';
		try
		{
			if (0 < $iIdTenant)
			{
				if ($bUseCache && !empty(self::$aTenantNameCache[$iIdTenant]))
				{
					return self::$aTenantNameCache[$iIdTenant];
				}

				$sResult = $this->oStorage->getTenantLoginById($iIdTenant);
				if ($bUseCache && !empty($sResult))
				{
					self::$aTenantNameCache[$iIdTenant] = $sResult;
				}
			}
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $sResult;
	}

	/**
	 * @param CTenant $oTenant
	 *
	 * @return bool
	 */
	public function isTenantExists(CTenant $oTenant)
	{
		//TODO
//		$bResult = $oTenant->IsDefault;
		
		$bResult = false;

		try
		{
			$aResultTenants = $this->oEavManager->getObjects('CTenant',
				array('Login'),
				0,
				0,
				array('Login' => $oTenant->Login)
			);

			if ($aResultTenants)
			{
				foreach($aResultTenants as $oObject)
				{
					if ($oObject->iObjectId !== $oTenant->iObjectId)
					{
						$bResult = true;
						break;
					}
				}
			}
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @TODO use domains manager
	 * @param int $iTenantId
	 *
	 * @return array|bool
	 */
	public function getTenantDomains($iTenantId)
	{
		
		$mResult = false;
		if (0 < $iTenantId)
		{
			try
			{
				$mResult = $this->oStorage->getTenantDomains($iTenantId);
			}
			catch (CApiBaseException $oException)
			{
				$this->setLastException($oException);
			}
		}
		return $mResult;
	}

	/**
	 * @param CTenant $oTenant
	 *
	 * @return bool
	 */
	public function createTenant(CTenant &$oTenant)
	{
		$bResult = false;
		try
		{
			if ($oTenant->validate() && !$oTenant->IsDefault)
			{
				if (!$this->isTenantExists($oTenant))
				{
					if (0 < $oTenant->IdChannel && CApi::GetConf('tenant', false))
					{
						/* @var $oChannelsApi CApiChannelsManager */
						$oChannelsApi = CApi::GetCoreManager('channels');
						if ($oChannelsApi)
						{
							/* @var $oChannel CChannel */
							$oChannel = $oChannelsApi->getChannelById($oTenant->IdChannel);
							if (!$oChannel)
							{
								throw new CApiManagerException(Errs::ChannelsManager_ChannelDoesNotExist);
							}
						}
						else
						{
							$oTenant->IdChannel = 0;
						}
					}
					else
					{
						$oTenant->IdChannel = 0;
					}
					
					if (!$this->oEavManager->saveObject($oTenant))
					{
						throw new CApiManagerException(Errs::TenantsManager_TenantCreateFailed);
					}
				}
				else
				{
					throw new CApiManagerException(Errs::TenantsManager_TenantAlreadyExists);
				}
			}

			$bResult = true;
		}
		catch (CApiBaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
	}

	/**
	 * @param CTenant $oTenant
	 *
	 * @throws CApiManagerException(Errs::TenantsManager_QuotaLimitExided) 1707
	 * @throws CApiManagerException(Errs::TenantsManager_TenantUpdateFailed) 1703
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function updateTenant(CTenant $oTenant)
	{
		$bResult = false;
		try
		{
			if ($oTenant->validate())
			{
				if ($oTenant->IsDefault && 0 === $oTenant->iObjectId)
				{
					//TODO remove update settings
					$this->oSettings->SetConf('Helpdesk/AdminEmailAccount', $oTenant->HelpdeskAdminEmailAccount);
					$this->oSettings->SetConf('Helpdesk/ClientIframeUrl', $oTenant->HelpdeskClientIframeUrl);
					$this->oSettings->SetConf('Helpdesk/AgentIframeUrl', $oTenant->HelpdeskAgentIframeUrl);
					$this->oSettings->SetConf('Helpdesk/SiteName', $oTenant->HelpdeskSiteName);
					$this->oSettings->SetConf('Helpdesk/StyleAllow', $oTenant->HelpdeskStyleAllow);
					$this->oSettings->SetConf('Helpdesk/StyleImage', $oTenant->HelpdeskStyleImage);
					$this->oSettings->SetConf('Helpdesk/StyleText', $oTenant->HelpdeskStyleText);

					$this->oSettings->SetConf('Helpdesk/FetcherType', $oTenant->HelpdeskFetcherType);

					$this->oSettings->SetConf('Common/LoginStyleImage', $oTenant->LoginStyleImage);
					$this->oSettings->SetConf('Common/AppStyleImage', $oTenant->AppStyleImage);

					$this->oSettings->SetConf('Helpdesk/FacebookAllow', $oTenant->HelpdeskFacebookAllow);
					$this->oSettings->SetConf('Helpdesk/FacebookId', $oTenant->HelpdeskFacebookId);
					$this->oSettings->SetConf('Helpdesk/FacebookSecret', $oTenant->HelpdeskFacebookSecret);
					$this->oSettings->SetConf('Helpdesk/GoogleAllow', $oTenant->HelpdeskGoogleAllow);
					$this->oSettings->SetConf('Helpdesk/GoogleId', $oTenant->HelpdeskGoogleId);
					$this->oSettings->SetConf('Helpdesk/GoogleSecret', $oTenant->HelpdeskGoogleSecret);
					$this->oSettings->SetConf('Helpdesk/TwitterAllow', $oTenant->HelpdeskTwitterAllow);
					$this->oSettings->SetConf('Helpdesk/TwitterId', $oTenant->HelpdeskTwitterId);
					$this->oSettings->SetConf('Helpdesk/TwitterSecret', $oTenant->HelpdeskTwitterSecret);

					$this->oSettings->SetConf('Sip/AllowSip', $oTenant->SipAllow);
					$this->oSettings->SetConf('Sip/Realm', $oTenant->SipRealm);
					$this->oSettings->SetConf('Sip/WebsocketProxyUrl', $oTenant->SipWebsocketProxyUrl);
					$this->oSettings->SetConf('Sip/OutboundProxyUrl', $oTenant->SipOutboundProxyUrl);
					$this->oSettings->SetConf('Sip/CallerID', $oTenant->SipCallerID);

					$this->oSettings->SetConf('Twilio/AllowTwilio', $oTenant->TwilioAllow);
					$this->oSettings->SetConf('Twilio/PhoneNumber', $oTenant->TwilioPhoneNumber);
					$this->oSettings->SetConf('Twilio/AccountSID', $oTenant->TwilioAccountSID);
					$this->oSettings->SetConf('Twilio/AuthToken', $oTenant->TwilioAuthToken);
					$this->oSettings->SetConf('Twilio/AppSID', $oTenant->TwilioAppSID);
					$this->oSettings->SetConf('Common/InvitationEmail', $oTenant->InviteNotificationEmailAccount);
					$this->oSettings->SetConf('Socials', $oTenant->getSocialsForSettings());

					$bResult = $this->oSettings->SaveToXml();
				}
				else
				{
					if (null !== $oTenant->QuotaInMB)
					{
						$iQuota = $oTenant->QuotaInMB;
						if (0 < $iQuota)
						{
							$iSize = $this->getTenantAllocatedSize($oTenant->iObjectId);
							if ($iSize > $iQuota)
							{
								throw new CApiManagerException(Errs::TenantsManager_QuotaLimitExided);
							}
						}
					}

					
					if (!$this->oEavManager->saveObject($oTenant))
					{
						throw new CApiManagerException(Errs::TenantsManager_TenantUpdateFailed);
					}

					if (null !== $oTenant->IsDisabled)
					{
						/* @var $oDomainsApi CApiDomainsManager */
						$oDomainsApi = CApi::GetCoreManager('domains');
						if (!$oDomainsApi->enableOrDisableDomainsByTenantId($oTenant->iObjectId, !$oTenant->IsDisabled))
						{
							$oException = $oDomainsApi->GetLastException();
							if ($oException)
							{
								throw $oException;
							}
						}
					}
				}
			}

			$bResult = true;
		}
		catch (CApiBaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @todo
	 * @param int $iTenantID
	 *
	 * @return false
	 */
	public function updateTenantMainCapa($iTenantID)
	{
		return false;
	}

	/**
	 * @param CTenant $oTenant
	 * @param int $iNewAllocatedSizeInBytes
	 *
	 * @return bool
	 */
	public function allocateFileUsage($oTenant, $iNewAllocatedSizeInBytes)
	{
		try
		{
			if ($oTenant && 0 < $oTenant->iObjectId)
			{
				$iNewUsedInMB = (int) round($iNewAllocatedSizeInBytes / (1024 * 1024));

				if (0 < $oTenant->QuotaInMB && $oTenant->FilesUsageDynamicQuotaInMB < $iNewUsedInMB)
				{
					return false;
				}
				else
				{
					$oProperty = new CProperty('FilesUsageInBytes', $iNewAllocatedSizeInBytes, $oTenant->getPropertyType('FilesUsageInBytes'));
					$oProperty->ObjectId = $oTenant->iObjectId;
					$this->oEavManager->setProperty($oProperty);
				}
			}
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}

		return false;
	}

	/**
	 * @param int $iTenantID
	 * @param int|null $iExceptUserId Default value is **null**.
	 *
	 * @return array|bool
	 */
	public function getSubscriptionUserUsage($iTenantID, $iExceptUserId = null)
	{
		$mResult = false;
		if (0 < $iTenantID)
		{
			$mResult = $this->oStorage->getSubscriptionUserUsage($iTenantID, $iExceptUserId);
		}

		return $mResult;
	}

	/**
	 * @param int $iChannelId
	 *
	 * @return array
	 */
	public function getTenantsByChannelId($iChannelId)
	{
		$aResult = false;
		try
		{
			$aResult = $this->oEavManager->getObjectsByType('CTenant',
				array('IsDefault', 'IdChannel'),
				0,
				0,
				array('IdChannel' => $iChannelId)
			);
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $aResult;
	}

	/**
	 * @param int $iChannelId
	 *
	 * @return bool
	 */
	public function deleteTenantsByChannelId($iChannelId)
	{
		$iResult = 1;
		$aTenants = $this->getTenantsByChannelId($iChannelId);

		if (is_array($aTenants))
		{
			foreach ($aTenants as $oTenant)
			{
				if (!$oTenant->IsDefault && 0 < $oTenant->iObjectId)
				{
					$iResult &= $this->deleteTenant($oTenant);
				}
			}
		}

		return (bool) $iResult;
	}

	/**
	 * @TODO rewrite other menagers usage
	 * 
	 * @param CTenant $oTenant
	 *
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function deleteTenant(CTenant $oTenant)
	{
		$bResult = false;
		try
		{
			if ($oTenant && !$oTenant->IsDefault)
			{
				/* @var $oDomainsApi CApiDomainsManager */
				$oDomainsApi = CApi::GetCoreManager('domains');
				if (!$oDomainsApi->deleteDomainsByTenantId($oTenant->iObjectId, true))
				{
					$oException = $oDomainsApi->GetLastException();
					if ($oException)
					{
						throw $oException;
					}
				}

				$bResult = $this->oEavManager->deleteObject($oTenant->iObjectId);
				
				// TODO subscriptions
				//if ($bResult)
				//{
				//	$this->oStorage->deleteTenantSubscriptions($oTenant->iObjectId);
				//}
			}
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $bResult;
	}
	
	/**
	 * @param int $iIdTenant
	 *
	 * @return array|false
	 */
	public function getSocials($iIdTenant)
	{
		$aResult = false;
		try
		{
			$aResult = $this->oStorage->getSocials($iIdTenant);
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $aResult;
	}	
	
	/**
	 * @param int $iIdSocial
	 *
	 * @return CTenantSocials|null
	 */
	public function getSocialById($iIdSocial)
	{
		$oSocial = null;
		try
		{
			$oSocial = $this->oStorage->getSocialById($iIdSocial);
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $oSocial;
	}		
	
	/**
	 * @param int $iIdTenant
	 * @param string $sSocialName
	 *
	 * @return CTenantSocials|null
	 */
	public function getSocialByName($iIdTenant, $sSocialName)
	{
		$oSocial = null;
		try
		{
			$oSocial = $this->oStorage->getSocialByName($iIdTenant, $sSocialName);
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $oSocial;
	}
	
	/**
	 * @param CTenantSocials $oSocial
	 *
	 * @return bool
	 */
	public function isSocialExists(CTenantSocials $oSocial)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->isSocialExists($oSocial);
		}
		catch (CApiBaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $bResult;
	}	
	
	/**
	 * @param CTenantSocials $oSocial
	 *
	 * @return bool
	 */
	public function createSocial(CTenantSocials $oSocial)
	{
		$bResult = false;
		try
		{
			if (!$this->isSocialExists($oSocial))
			{
				if (!$this->oStorage->createTenant($oSocial))
				{
					throw new CApiManagerException(Errs::TenantsManager_TenantCreateFailed);
				}
			}
			else
			{
				throw new CApiManagerException(Errs::TenantsManager_TenantAlreadyExists);
			}

			$bResult = true;
		}
		catch (CApiBaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
	}
	
	/**
	 * @param CTenantSocials $oSocial
	 *
	 * @return bool
	 */
	public function deleteSocial(CTenantSocials $oSocial)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->deleteSocial($oSocial->Id);
		}
		catch (CApiBaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
	}
	
	/**
	 * @param int $iTenanatId
	 *
	 * @return bool
	 */
	public function deleteSocialsByTenantId($iTenanatId)
	{
		$bResult = false;
		try
		{
			$bResult = $this->oStorage->deleteSocialsByTenantId($iTenanatId);
		}
		catch (CApiBaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
	}

	/**
	 * @param CTenantSocials $oSocial
	 *
	 * @return bool
	 */
	public function updateSocial(CTenantSocials $oSocial)
	{
		$bResult = false;
		try
		{
			if (!$this->oStorage->updateSocial($oSocial))
			{
				throw new CApiManagerException(Errs::TenantsManager_TenantUpdateFailed);
			}
			$bResult = true;
		}
		catch (CApiBaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
	}
}
