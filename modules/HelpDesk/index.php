<?php

class HelpDeskModule extends AApiModule
{
	public $oApiHelpDeskManager = null;
	
	public function init() 
	{
		$this->oApiHelpDeskManager = $this->GetManager('main', 'db');
		$this->AddEntry('helpdesk', 'EntryHelpDesk');
	}
	
	/**
	 * @param bool $bThrowAuthExceptionOnFalse Default value is **true**.
	 *
	 * @return \CHelpdeskUser|null
	 */
	protected function getHelpdeskAccountFromParam($bThrowAuthExceptionOnFalse = true)
	{
		$oResult = null;
		$oAccount = null;

		if ('0' === (string) $this->getParamValue('IsExt', '1'))
		{
			$oAccount = $this->getDefaultAccountFromParam($bThrowAuthExceptionOnFalse);
			if ($oAccount && $this->oApiCapabilityManager->isHelpdeskSupported($oAccount))
			{
				$oResult = $this->getHelpdeskAccountFromMainAccount($oAccount);
			}
		}
		else
		{
			$oApiTenants = \CApi::GetCoreManager('tenants');
			$mTenantID = $oApiTenants->getTenantIdByHash($this->getParamValue('TenantHash', ''));
			if (is_int($mTenantID))
			{
				$oResult = \api_Utils::GetHelpdeskAccount($mTenantID);
			}
		}

		if (!$oResult && $bThrowAuthExceptionOnFalse)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::UnknownError);
		}

		return $oResult;
	}
	
	/**
	 * @param \CAccount $oAccount
	 * 
	 * @return \CHelpdeskUser|null
	 */
	protected function getHelpdeskAccountFromMainAccount(&$oAccount)
	{
		$oResult = null;
		$oApiUsers = CApi::GetCoreManager('users');
		if ($oAccount && $oAccount->IsDefaultAccount && $this->oApiCapabilityManager->isHelpdeskSupported($oAccount))
		{
			if (0 < $oAccount->User->IdHelpdeskUser)
			{
				$oHelpdeskUser = $this->oApiHelpDeskManager->getUserById($oAccount->IdTenant, $oAccount->User->IdHelpdeskUser);
				$oResult = $oHelpdeskUser instanceof \CHelpdeskUser ? $oHelpdeskUser : null;
			}

			if (!($oResult instanceof \CHelpdeskUser))
			{
				$oHelpdeskUser = $this->oApiHelpDeskManager->getUserByEmail($oAccount->IdTenant, $oAccount->Email);
				$oResult = $oHelpdeskUser instanceof \CHelpdeskUser ? $oHelpdeskUser : null;
				
				if ($oResult instanceof \CHelpdeskUser)
				{
					$oAccount->User->IdHelpdeskUser = $oHelpdeskUser->IdHelpdeskUser;
					$oApiUsers->updateAccount($oAccount);
				}
			}

			if (!($oResult instanceof \CHelpdeskUser))
			{
				$oHelpdeskUser = new \CHelpdeskUser();
				$oHelpdeskUser->Email = $oAccount->Email;
				$oHelpdeskUser->Name = $oAccount->FriendlyName;
				$oHelpdeskUser->IdSystemUser = $oAccount->IdUser;
				$oHelpdeskUser->IdTenant = $oAccount->IdTenant;
				$oHelpdeskUser->Activated = true;
				$oHelpdeskUser->IsAgent = true;
				$oHelpdeskUser->Language = $oAccount->User->DefaultLanguage;
				$oHelpdeskUser->DateFormat = $oAccount->User->DefaultDateFormat;
				$oHelpdeskUser->TimeFormat = $oAccount->User->DefaultTimeFormat;

				$oHelpdeskUser->setPassword($oAccount->IncomingMailPassword);

				if ($this->oApiHelpDeskManager->createUser($oHelpdeskUser))
				{
					$oAccount->User->IdHelpdeskUser = $oHelpdeskUser->IdHelpdeskUser;
					$oApiUsers->updateAccount($oAccount);

					$oResult = $oHelpdeskUser;
				}
			}
		}

		return $oResult;
	}	
	
	public function Login()
	{
		setcookie('aft-cache-ctrl', '', time() - 3600);
		$sTenantHash = trim($this->getParamValue('TenantHash', ''));
		if ($this->oApiCapabilityManager->isHelpdeskSupported())
		{
			$sEmail = trim($this->getParamValue('Email', ''));
			$sPassword = trim($this->getParamValue('Password', ''));
			$bSignMe = '1' === (string) $this->getParamValue('SignMe', '0');

			if (0 === strlen($sEmail) || 0 === strlen($sPassword))
			{
				throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
			}
			
			$oApiTenants = \CApi::GetCoreManager('tenants');
			$mIdTenant = $oApiTenants->getTenantIdByHash($sTenantHash);
			
			if (!is_int($mIdTenant))
			{
				throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
			}

			try
			{
				$oApiIntegrator = \CApi::GetCoreManager('integrator');
				$oHelpdeskUser = $oApiIntegrator->loginToHelpdeskAccount($mIdTenant, $sEmail, $sPassword);
				if ($oHelpdeskUser && !$oHelpdeskUser->Blocked)
				{
					$oApiIntegrator->setHelpdeskUserAsLoggedIn($oHelpdeskUser, $bSignMe);
					return $this->TrueResponse(null, __FUNCTION__);
				}
			}
			catch (\Exception $oException)
			{
				$iErrorCode = \Core\Notifications::UnknownError;
				if ($oException instanceof \CApiManagerException)
				{
					switch ($oException->getCode())
					{
						case \Errs::HelpdeskManager_AccountSystemAuthentication:
							$iErrorCode = \Core\Notifications::HelpdeskSystemUserExists;
							break;
						case \Errs::HelpdeskManager_AccountAuthentication:
							$iErrorCode = \Core\Notifications::AuthError;
							break;
						case \Errs::HelpdeskManager_UnactivatedUser:
							$iErrorCode = \Core\Notifications::HelpdeskUnactivatedUser;
							break;
						case \Errs::Db_ExceptionError:
							$iErrorCode = \Core\Notifications::DataBaseError;
							break;
					}
				}

				throw new \Core\Exceptions\ClientException($iErrorCode);
			}
		}

		return $this->FalseResponse(null, __FUNCTION__);
	}

	public function Logout()
	{
		setcookie('aft-cache-ctrl', '', time() - 3600);
		if ($this->oApiCapabilityManager->isHelpdeskSupported())
		{
			$oApiIntegrator = \CApi::GetCoreManager('integrator');
			$oApiIntegrator->logoutHelpdeskUser();
		}

		return $this->TrueResponse(null, __FUNCTION__);
	}	
	
	public function Register()
	{
		$sTenantHash = trim($this->getParamValue('TenantHash', ''));
		if ($this->oApiCapabilityManager->isHelpdeskSupported())
		{
			$sEmail = trim($this->getParamValue('Email', ''));
			$sName = trim($this->getParamValue('Name', ''));
			$sPassword = trim($this->getParamValue('Password', ''));

			if (0 === strlen($sEmail) || 0 === strlen($sPassword))
			{
				throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
			}

			$oApiTenants = \CApi::GetCoreManager('tenants');
			$mIdTenant = $oApiTenants->getTenantIdByHash($sTenantHash);
			if (!is_int($mIdTenant))
			{
				throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
			}

			$bResult = false;
			try
			{
				$oApiIntegrator = \CApi::GetCoreManager('integrator');
				$bResult = !!$oApiIntegrator->registerHelpdeskAccount($mIdTenant, $sEmail, $sName, $sPassword);
			}
			catch (\Exception $oException)
			{
				$iErrorCode = \Core\Notifications::UnknownError;
				if ($oException instanceof \CApiManagerException)
				{
					switch ($oException->getCode())
					{
						case \Errs::HelpdeskManager_UserAlreadyExists:
							$iErrorCode = \Core\Notifications::HelpdeskUserAlreadyExists;
							break;
						case \Errs::HelpdeskManager_UserCreateFailed:
							$iErrorCode = \Core\Notifications::CanNotCreateHelpdeskUser;
							break;
						case \Errs::Db_ExceptionError:
							$iErrorCode = \Core\Notifications::DataBaseError;
							break;
					}
				}

				throw new \Core\Exceptions\ClientException($iErrorCode);
			}

			return $this->DefaultResponse(null, __FUNCTION__, $bResult);
		}

		return $this->FalseResponse(null, __FUNCTION__);
	}	
	
	/**
	 * @return array
	 */
	public function IsAgent()
	{
		$oAccount = null;
		$oUser = $this->getHelpdeskAccountFromParam();

		return $this->DefaultResponse($oAccount, __FUNCTION__, $oUser && $oUser->IsAgent);
	}	
	
	public function Forgot()
	{
		$sTenantHash = trim($this->getParamValue('TenantHash', ''));
		if ($this->oApiCapabilityManager->isHelpdeskSupported())
		{
			$sEmail = trim($this->getParamValue('Email', ''));

			if (0 === strlen($sEmail))
			{
				throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
			}

			$oApiTenants = \CApi::GetCoreManager('tenants');
			$mIdTenant = $oApiTenants->getTenantIdByHash($sTenantHash);
			if (!is_int($mIdTenant))
			{
				throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
			}

			$oUser = $this->oApiHelpDeskManager->getUserByEmail($mIdTenant, $sEmail);
			if (!($oUser instanceof \CHelpdeskUser))
			{
				throw new \Core\Exceptions\ClientException(\Core\Notifications::HelpdeskUnknownUser);
			}

			return $this->DefaultResponse(null, __FUNCTION__, $oHelpdesk->forgotUser($oUser));
		}
		
		return $this->FalseResponse(null, __FUNCTION__);
	}	
	
	public function ForgotChangePassword()
	{
		$sTenantHash = trim($this->getParamValue('TenantHash', ''));
		if ($this->oApiCapabilityManager->isHelpdeskSupported())
		{
			$sActivateHash = \trim($this->getParamValue('ActivateHash', ''));
			$sNewPassword = \trim($this->getParamValue('NewPassword', ''));

			if (0 === strlen($sNewPassword) || 0 === strlen($sActivateHash))
			{
				throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
			}

			$oApiTenants = \CApi::GetCoreManager('tenants');
			$mIdTenant = $oApiTenants->getTenantIdByHash($sTenantHash);
			if (!is_int($mIdTenant))
			{
				throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
			}

			$oUser = $this->oApiHelpDeskManager->getUserByActivateHash($mIdTenant, $sActivateHash);
			if (!($oUser instanceof \CHelpdeskUser))
			{
				throw new \Core\Exceptions\ClientException(\Core\Notifications::HelpdeskUnknownUser);
			}

			$oUser->Activated = true;
			$oUser->setPassword($sNewPassword);
			$oUser->regenerateActivateHash();

			return $this->DefaultResponse(null, __FUNCTION__, $oHelpdesk->updateUser($oUser));
		}

		return $this->FalseResponse(null, __FUNCTION__);
	}	
	
	public function CreatePost()
	{
		$oAccount = null;
		$oUser = $this->getHelpdeskAccountFromParam();
		/* @var $oAccount CAccount */

		$iThreadId = (int) $this->getParamValue('ThreadId', 0);
		$sSubject = trim((string) $this->getParamValue('Subject', ''));
		$sText = trim((string) $this->getParamValue('Text', ''));
		$sCc = trim((string) $this->getParamValue('Cc', ''));
		$sBcc = trim((string) $this->getParamValue('Bcc', ''));
		$bIsInternal = '1' === (string) $this->getParamValue('IsInternal', '0');
		$mAttachments = $this->getParamValue('Attachments', null);
		
		if (0 === strlen($sText) || (0 === $iThreadId && 0 === strlen($sSubject)))
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
		}

		$mResult = false;
		$bIsNew = false;

		$oThread = null;
		if (0 === $iThreadId)
		{
			$bIsNew = true;
			
			$oThread = new \CHelpdeskThread();
			$oThread->IdTenant = $oUser->IdTenant;
			$oThread->IdOwner = $oUser->IdHelpdeskUser;
			$oThread->Type = \EHelpdeskThreadType::Pending;
			$oThread->Subject = $sSubject;

			if (!$this->oApiHelpDeskManager->createThread($oUser, $oThread))
			{
				$oThread = null;
			}
		}
		else
		{
			$oThread = $this->$this->oApiHelpDeskManager->getThreadById($oUser, $iThreadId);
		}

		if ($oThread && 0 < $oThread->IdHelpdeskThread)
		{
			$oPost = new \CHelpdeskPost();
			$oPost->IdTenant = $oUser->IdTenant;
			$oPost->IdOwner = $oUser->IdHelpdeskUser;
			$oPost->IdHelpdeskThread = $oThread->IdHelpdeskThread;
			$oPost->Type = $bIsInternal ? \EHelpdeskPostType::Internal : \EHelpdeskPostType::Normal;
			$oPost->SystemType = \EHelpdeskPostSystemType::None;
			$oPost->Text = $sText;

			$aResultAttachment = array();
			if (is_array($mAttachments) && 0 < count($mAttachments))
			{
				foreach ($mAttachments as $sTempName => $sHash)
				{
					$aDecodeData = \CApi::DecodeKeyValues($sHash);
					if (!isset($aDecodeData['HelpdeskUserID']))
					{
						continue;
					}

					$rData = $this->ApiFileCache()->getFile($oUser, $sTempName);
					if ($rData)
					{
						$iFileSize = $this->ApiFileCache()->fileSize($oUser, $sTempName);

						$sThreadID = (string) $oThread->IdHelpdeskThread;
						$sThreadID = str_pad($sThreadID, 2, '0', STR_PAD_LEFT);
						$sThreadIDSubFolder = substr($sThreadID, 0, 2);

						$sThreadFolderName = API_HELPDESK_PUBLIC_NAME.'/'.$sThreadIDSubFolder.'/'.$sThreadID;

						$this->oApiFilestorage->createFolder($oUser, \EFileStorageTypeStr::Corporate, '',
							$sThreadFolderName);

						$sUploadName = isset($aDecodeData['Name']) ? $aDecodeData['Name'] : $sTempName;

						$this->oApiFilestorage->createFile($oUser,
							\EFileStorageTypeStr::Corporate, $sThreadFolderName, $sUploadName, $rData, false);

						$oAttachment = new \CHelpdeskAttachment();
						$oAttachment->IdHelpdeskThread = $oThread->IdHelpdeskThread;
						$oAttachment->IdHelpdeskPost = $oPost->IdHelpdeskPost;
						$oAttachment->IdOwner = $oUser->IdHelpdeskUser;
						$oAttachment->IdTenant = $oUser->IdTenant;

						$oAttachment->FileName = $sUploadName;
						$oAttachment->SizeInBytes = $iFileSize;
						$oAttachment->encodeHash($oUser, $sThreadFolderName);
						
						$aResultAttachment[] = $oAttachment;
					}
				}

				if (is_array($aResultAttachment) && 0 < count($aResultAttachment))
				{
					$oPost->Attachments = $aResultAttachment;
				}
			}

			$mResult = $this->oApiHelpDeskManager->createPost($oUser, $oThread, $oPost, $bIsNew, true, $sCc, $sBcc);

			if ($mResult)
			{
				$mResult = array(
					'ThreadId' => $oThread->IdHelpdeskThread,
					'ThreadIsNew' => $bIsNew
				);
			}
		}

		return $this->DefaultResponse($oAccount, __FUNCTION__, $mResult);
	}	
	
	/**
	 * @return array
	 */
	public function DeletePost()
	{
		$oAccount = null;
		$oUser = $this->getHelpdeskAccountFromParam($oAccount);

		if (!$oUser)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::AccessDenied);
		}

		$iThreadId = (int) $this->getParamValue('ThreadId', 0);
		$iPostId = (int) $this->getParamValue('PostId', 0);
		
		if (0 >= $iThreadId || 0 >= $iPostId)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
		}

		$oThread = $this->oApiHelpDeskManager->getThreadById($oUser, $iThreadId);
		if (!$oThread)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
		}

		if (!$this->oApiHelpDeskManager->verifyPostIdsBelongToUser($oUser, array($iPostId)))
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::AccessDenied);
		}

		return $this->DefaultResponse($oAccount, __FUNCTION__,
			$this->oApiHelpDeskManager->deletePosts($oUser, $oThread, array($iPostId)));
	}	
	
	/**
	 * @return array
	 */
	public function GetThreadByIdOrHash()
	{
		$oAccount = null;
		$oThread = false;
		$oUser = $this->getHelpdeskAccountFromParam($oAccount);

		$bIsAgent = $oUser->IsAgent;

		$sThreadId = (int) $this->getParamValue('ThreadId', 0);
		$sThreadHash = (string) $this->getParamValue('ThreadHash', '');
		if (empty($sThreadHash) && $sThreadId === 0)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
		}

		$mHelpdeskThreadId = $sThreadId ? $sThreadId : $this->oApiHelpDeskManager->getThreadIdByHash($oUser->IdTenant, $sThreadHash);
		if (!is_int($mHelpdeskThreadId) || 1 > $mHelpdeskThreadId)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
		}

		$oThread = $this->oApiHelpDeskManager->getThreadById($oUser, $mHelpdeskThreadId);
		if (!$oThread)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
		}

		$aUserInfo = $this->oApiHelpDeskManager->userInformation($oUser, array($oThread->IdOwner));
		if (is_array($aUserInfo) && 0 < count($aUserInfo))
		{
			if (isset($aUserInfo[$oThread->IdOwner]) && is_array($aUserInfo[$oThread->IdOwner]))
			{
				$sEmail = isset($aUserInfo[$oThread->IdOwner][0]) ? $aUserInfo[$oThread->IdOwner][0] : '';
				$sName = isset($aUserInfo[$oThread->IdOwner][1]) ? $aUserInfo[$oThread->IdOwner][1] : '';

				if (empty($sEmail) && !empty($aUserInfo[$oThread->IdOwner][3]))
				{
					$sEmail = $aUserInfo[$oThread->IdOwner][3];
				}

				if (!$bIsAgent && 0 < strlen($sName))
				{
					$sEmail = '';
				}

				$oThread->Owner = array($sEmail, $sName);
			}
		}

		return $this->DefaultResponse($oAccount, __FUNCTION__, $oThread);
	}	
	
	/**
	 * @return array
	 */
	public function GetPosts()
	{
		$oAccount = null;
		$oUser = $this->getHelpdeskAccountFromParam($oAccount);

		$bIsAgent = $oUser->IsAgent;

		$iThreadId = (int) $this->getParamValue('ThreadId', 0);
		$iStartFromId = (int) $this->getParamValue('StartFromId', 0);
		$iLimit = (int) $this->getParamValue('Limit', 10);
		$iIsExt = (int) $this->getParamValue('IsExt', 1);

		if (1 > $iThreadId || 0 > $iStartFromId || 1 > $iLimit)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
		}

		$oThread = $this->oApiHelpDeskManager->getThreadById($oUser, $iThreadId);
		if (!$oThread)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
		}

		$aList = $this->oApiHelpDeskManager->getPosts($oUser, $oThread, $iStartFromId, $iLimit);
		$iExtPostsCount = $iIsExt ? $this->oApiHelpDeskManager->getExtPostsCount($oUser, $oThread) : 0;

		$aIdList = array();
		if (is_array($aList) && 0 < count($aList))
		{
			foreach ($aList as &$oItem)
			{
				if ($oItem)
				{
					$aIdList[$oItem->IdOwner] = (int) $oItem->IdOwner;
				}
			}
		}

		$aIdList[$oThread->IdOwner] = (int) $oThread->IdOwner;

		if (0 < count($aIdList))
		{
			$aIdList = array_values($aIdList);
			$aUserInfo = $this->oApiHelpDeskManager->userInformation($oUser, $aIdList);

			if (is_array($aUserInfo) && 0 < count($aUserInfo))
			{
				foreach ($aList as &$oItem)
				{
					if ($oItem && isset($aUserInfo[$oItem->IdOwner]) && is_array($aUserInfo[$oItem->IdOwner]))
					{
						$oItem->Owner = array(
							isset($aUserInfo[$oItem->IdOwner][0]) ? $aUserInfo[$oItem->IdOwner][0] : '',
							isset($aUserInfo[$oItem->IdOwner][1]) ? $aUserInfo[$oItem->IdOwner][1] : ''
						);

						if (empty($oItem->Owner[0]))
						{
							$oItem->Owner[0] = isset($aUserInfo[$oItem->IdOwner][3]) ? $aUserInfo[$oItem->IdOwner][3] : '';
						}

						if (!$bIsAgent && 0 < strlen($oItem->Owner[1]))
						{
							$oItem->Owner[0] = '';
						}

						$oItem->IsThreadOwner = $oThread->IdOwner === $oItem->IdOwner;
					}

					if ($oItem)
					{
						$oItem->ItsMe = $oUser->IdHelpdeskUser === $oItem->IdOwner;
					}
				}

				if (isset($aUserInfo[$oThread->IdOwner]) && is_array($aUserInfo[$oThread->IdOwner]))
				{
					$sEmail = isset($aUserInfo[$oThread->IdOwner][0]) ? $aUserInfo[$oThread->IdOwner][0] : '';
					$sName = isset($aUserInfo[$oThread->IdOwner][1]) ? $aUserInfo[$oThread->IdOwner][1] : '';

					if (!$bIsAgent && 0 < strlen($sName))
					{
						$sEmail = '';
					}

					$oThread->Owner = array($sEmail, $sName);
				}
			}
		}

		if ($oThread->HasAttachments)
		{
			$aAttachments = $this->oApiHelpDeskManager->getAttachments($oUser, $oThread);
			if (is_array($aAttachments))
			{
				foreach ($aList as &$oItem)
				{
					if (isset($aAttachments[$oItem->IdHelpdeskPost]) && is_array($aAttachments[$oItem->IdHelpdeskPost]) &&
						0 < count($aAttachments[$oItem->IdHelpdeskPost]))
					{
						$oItem->Attachments = $aAttachments[$oItem->IdHelpdeskPost];

						foreach ($oItem->Attachments as $oAttachment)
						{
							if ($oAttachment && '.asc' === \strtolower(\substr(\trim($oAttachment->FileName), -4)))
							{
								$oAttachment->populateContent($oUser, $this->oApiHelpdesk, $this->oApiFilestorage);
							}
						}
					}
				}
			}
		}

		return $this->DefaultResponse($oAccount, __FUNCTION__, array(
			'ThreadId' => $oThread->IdHelpdeskThread,
			'StartFromId' => $iStartFromId,
			'Limit' => $iLimit,
			'ItemsCount' => $iExtPostsCount ? $iExtPostsCount : ($oThread->PostCount > count($aList) ? $oThread->PostCount : count($aList)),
			'List' => $aList
		));
	}
	
	/**
	 * @return array
	 */
	public function DeleteThread()
	{
		$oAccount = null;
		$oUser = $this->getHelpdeskAccountFromParam($oAccount);

		if (!$oUser)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::AccessDenied);
		}

		$iThreadId = (int) $this->getParamValue('ThreadId', '');

		if (0 < $iThreadId && !$oUser->IsAgent && !$this->oApiHelpDeskManager->verifyThreadIdsBelongToUser($oUser, array($iThreadId)))
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::AccessDenied);
		}

		$bResult = false;
		if (0 < $iThreadId)
		{
			$bResult = $this->oApiHelpDeskManager->archiveThreads($oUser, array($iThreadId));
		}

		return $this->DefaultResponse($oAccount, __FUNCTION__, $bResult);
	}	
	
	/**
	 * @return array
	 */
	public function ChangeThreadState()
	{
		$oAccount = null;
		$oUser = $this->getHelpdeskAccountFromParam($oAccount);

		$iThreadId = (int) $this->getParamValue('ThreadId', 0);
		$iThreadType = (int) $this->getParamValue('Type', \EHelpdeskThreadType::None);

		if (1 > $iThreadId || !in_array($iThreadType, array(
			\EHelpdeskThreadType::Pending,
			\EHelpdeskThreadType::Waiting,
			\EHelpdeskThreadType::Answered,
			\EHelpdeskThreadType::Resolved,
			\EHelpdeskThreadType::Deferred
		)))
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
		}

		if (!$oUser || ($iThreadType !== \EHelpdeskThreadType::Resolved && !$oUser->IsAgent))
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::AccessDenied);
		}

		$bResult = false;
		$oThread = $this->oApiHelpDeskManager->getThreadById($oUser, $iThreadId);
		if ($oThread)
		{
			$oThread->Type = $iThreadType;
			$bResult = $this->oApiHelpDeskManager->updateThread($oUser, $oThread);
		}
		
		return $this->DefaultResponse($oAccount, __FUNCTION__, $bResult);
	}	
	

	public function PingThread()
	{
		$oAccount = null;
		$oUser = $this->getHelpdeskAccountFromParam($oAccount);

		$iThreadId = (int) $this->getParamValue('ThreadId', 0);

		if (0 === $iThreadId)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
		}

		$this->oApiHelpDeskManager->setOnline($oUser, $iThreadId);

		return $this->DefaultResponse($oAccount, __FUNCTION__, $this->oApiHelpDeskManager->getOnline($oUser, $iThreadId));
	}
	
	public function SetThreadSeen()
	{
		$oAccount = null;
		$oUser = $this->getHelpdeskAccountFromParam($oAccount);

		$iThreadId = (int) $this->getParamValue('ThreadId', 0);

		if (0 === $iThreadId)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
		}

		$oThread = $this->oApiHelpDeskManager->getThreadById($oUser, $iThreadId);
		if (!$oThread)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::AccessDenied);
		}

		return $this->DefaultResponse($oAccount, __FUNCTION__, $this->oApiHelpDeskManager->setThreadSeen($oUser, $oThread));
	}	
	
	/**
	 * @return array
	 */
	public function GetThreads()
	{
		$oAccount = null;
		$oUser = $this->getHelpdeskAccountFromParam($oAccount);
		
		$iFilter = (int) $this->getParamValue('Filter', \EHelpdeskThreadFilterType::All);
		$sSearch = (string) $this->getParamValue('Search', '');
		$iOffset = (int) $this->getParamValue('Offset', 0);
		$iLimit = (int) $this->getParamValue('Limit', 10);

		$bIsAgent = $oUser->IsAgent;

		if (0 > $iOffset || 1 > $iLimit)
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::InvalidInputParameter);
		}

		$aList = array();
		$iCount = $this->oApiHelpDeskManager->getThreadsCount($oUser, $iFilter, $sSearch);
		if ($iCount)
		{
			$aList = $this->oApiHelpDeskManager->getThreads($oUser, $iOffset, $iLimit, $iFilter, $sSearch);
		}

		$aOwnerIdList = array();
		if (is_array($aList) && 0 < count($aList))
		{
			foreach ($aList as &$oItem)
			{
				$aOwnerIdList[$oItem->IdOwner] = (int) $oItem->IdOwner;
			}
		}

		if (0 < count($aOwnerIdList))
		{
			$aOwnerIdList = array_values($aOwnerIdList);
			$aUserInfo = $this->oApiHelpDeskManager->userInformation($oUser, $aOwnerIdList);

			if (is_array($aUserInfo) && 0 < count($aUserInfo))
			{
				foreach ($aList as &$oItem)
				{
					if ($oItem && isset($aUserInfo[$oItem->IdOwner]) && is_array($aUserInfo[$oItem->IdOwner]))
					{
						$sEmail = isset($aUserInfo[$oItem->IdOwner][0]) ? $aUserInfo[$oItem->IdOwner][0] : '';
						$sName = isset($aUserInfo[$oItem->IdOwner][1]) ? $aUserInfo[$oItem->IdOwner][1] : '';

						if (empty($sEmail) && !empty($aUserInfo[$oItem->IdOwner][3]))
						{
							$sEmail = $aUserInfo[$oItem->IdOwner][3];
						}

						if (!$bIsAgent && 0 < strlen($sName))
						{
							$sEmail = '';
						}
						
						$oItem->Owner = array($sEmail, $sName);
					}
				}
			}
		}

		return $this->DefaultResponse($oAccount, __FUNCTION__, array(
			'Search' => $sSearch,
			'Filter' => $iFilter,
			'List' => $aList,
			'Offset' => $iOffset,
			'Limit' => $iLimit,
			'ItemsCount' =>  $iCount
		));
	}	
	
	
	public function GetThreadsPendingCount()
	{
		$oAccount = null;
		$oUser = $this->getHelpdeskAccountFromParam($oAccount);

		if (!($oUser instanceof \CHelpdeskUser))
		{
			throw new \Core\Exceptions\ClientException(\Core\Notifications::HelpdeskUnknownUser);
		}


		return $this->DefaultResponse($oAccount, __FUNCTION__, $this->oApiHelpDeskManager->getThreadsPendingCount($oUser->IdTenant));
	}	
	
	public function EntryHelpDesk()
	{
		$oLogginedAccount = $this->GetDefaultAccount();

		$oApiIntegrator = \CApi::GetCoreManager('integrator');

		$oApiTenants = \CApi::GetCoreManager('tenants');
		$mHelpdeskIdTenant = $oApiTenants->getTenantIdByHash($this->oHttp->GetQuery('helpdesk'));
		if (!is_int($mHelpdeskIdTenant))
		{
			\CApi::Location('./');
			return '';
		}

		$bDoId = false;
		$sThread = $this->oHttp->GetQuery('thread');
		$sThreadAction = $this->oHttp->GetQuery('action');
		if (0 < strlen($sThread))
		{
			$iThreadID = $this->oApiHelpDeskManager->getThreadIdByHash($mHelpdeskIdTenant, $sThread);
			if (0 < $iThreadID)
			{
				$oApiIntegrator->setThreadIdFromRequest($iThreadID, $sThreadAction);
				$bDoId = true;
			}
		}

		$sActivateHash = $this->oHttp->GetQuery('activate');
		if (0 < strlen($sActivateHash) && !$this->oHttp->HasQuery('forgot'))
		{
			$bRemove = true;
			$oUser = $this->oApiHelpDeskManager->getUserByActivateHash($mHelpdeskIdTenant, $sActivateHash);
			/* @var $oUser \CHelpdeskUser */
			if ($oUser)
			{
				if (!$oUser->Activated)
				{
					$oUser->Activated = true;
					$oUser->regenerateActivateHash();

					if ($this->oApiHelpDeskManager->updateUser($oUser))
					{
						$bRemove = false;
						$oApiIntegrator->setUserAsActivated($oUser);
					}
				}
			}

			if ($bRemove)
			{
				$oApiIntegrator->removeUserAsActivated();
			}
		}

		if ($oLogginedAccount && $this->oApiCapabilityManager->isHelpdeskSupported($oLogginedAccount) &&
			$oLogginedAccount->IdTenant === $mHelpdeskIdTenant)
		{
			if (!$bDoId)
			{
				$oApiIntegrator->setThreadIdFromRequest(0);
			}

			$oApiIntegrator->skipMobileCheck();
			\CApi::Location('./');
			return '';
		}	
				
	}
}

return new HelpDeskModule('1.0');
