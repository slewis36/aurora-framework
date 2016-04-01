<?php

/* -AFTERLOGIC LICENSE HEADER- */

/**
 *
 * @package Users
 * @subpackage Classes
 */
class CAccount extends api_APropertyBag
{
	/**
	 * Creates a new instance of the object.
	 * 
	 * @return void
	 */
	public function __construct($sModule, $oParams)
	{
		parent::__construct(get_class($this), $sModule);
		
		$this->__USE_TRIM_IN_STRINGS__ = true;
		
		$this->SetDefaults();

		CApi::Plugin()->RunHook('api-account-construct', array(&$this));
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
	
	public static function createInstance($sModule = 'Auth', $oParams = array())
	{
		return new CAccount($sModule, $oParams);
	}
	/**
	 * Obtains static map of user fields. Function with the same name is used for other objects in a unified container **api_AContainer**.
	 * 
	 * @return array
	 */
	public function getMap()
	{
		return self::getStaticMap();
	}

	/**
	 * Obtains static map of user fields.
	 * 
	 * @return array
	 */
	public static function getStaticMap()
	{
		return array(
			'IsDisabled'			=> array('bool', false),
			'IdUser'				=> array('int', 0),
			'Login'					=> array('string', ''),
			'Password'				=> array('string', '')
		);
	}
}
