<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Rover\CurrencyRate\Agent;

Loc::LoadMessages(__FILE__);

/**
 * Class rover_currencyrate
 *
 * @author Pavel Shulaev (https://rover-it.me)
 */
class rover_currencyrate extends CModule
{
    var $MODULE_ID	= "rover.currencyrate";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_CSS;

    /**
     * rover_params constructor.
     */
    function __construct()
    {
        global $curError;

		$arModuleVersion    = array();
        $curError       = array();

        require dirname(__FILE__) . "/version.php";

		if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion)) {
			$this->MODULE_VERSION		= $arModuleVersion["VERSION"];
			$this->MODULE_VERSION_DATE	= $arModuleVersion["VERSION_DATE"];
        } else
            $curError[] = Loc::getMessage('rover-cr__version_info_error');

        $this->MODULE_NAME			= Loc::getMessage('rover-cr__name');
        $this->MODULE_DESCRIPTION	= Loc::getMessage('rover-cr__descr');
        $this->PARTNER_NAME         = GetMessage('rover-cr__partner_name');
        $this->PARTNER_URI          = GetMessage('rover-cr__partner_uri');
	}

    /**
     * @author Pavel Shulaev (https://rover-it.me)
     */
    function DoInstall()
    {
        global $APPLICATION;
        $rights = $APPLICATION->GetGroupRight($this->MODULE_ID);

        if ($rights == "W")
            $this->ProcessInstall();
	}

    /**
     * @author Pavel Shulaev (https://rover-it.me)
     */
    function DoUninstall()
    {
        global $APPLICATION;
        $rights = $APPLICATION->GetGroupRight($this->MODULE_ID);

        if ($rights == "W")
            $this->ProcessUninstall();
    }

    /**
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    function GetModuleRightsList()
    {
        return array(
            "reference_id" => array("D", "R", "W"),
            "reference" => array(
                Loc::getMessage('rover-cr__reference_deny'),
                Loc::getMessage('rover-cr__reference_read'),
                Loc::getMessage('rover-cr__reference_write')
            )
        );
    }

	/**
	 * @author Pavel Shulaev (https://rover-it.me)
	 */
	private function ProcessInstall()
    {
        global $APPLICATION, $curError;

        if (PHP_VERSION_ID < 50306)
            $curError[] = Loc::getMessage('rover-cr__php_version_error');

        if (empty($curError))
            try{
                ModuleManager::registerModule($this->MODULE_ID);
            } catch(\Exception $e) {
                $curError[] = $e->getMessage();
            }

        $this->installAgent();

	    $APPLICATION->IncludeAdminFile(Loc::getMessage("rover-cr__install_title"),
            dirname(__FILE__) . '/message.php');
    }

	/**
	 * @author Pavel Shulaev (https://rover-it.me)
	 */
	private function ProcessUninstall()
	{
	    self::installAgent();

	    ModuleManager::unRegisterModule($this->MODULE_ID);

        global $APPLICATION;
        $APPLICATION->IncludeAdminFile(Loc::getMessage("rover-cr__uninstall_title"),
            dirname(__FILE__) . '/unMessage.php');
	}

    /**
     * @author Pavel Shulaev (https://rover-it.me)
     */
	protected function installAgent()
    {
        global $curError;

        require_once dirname(__FILE__) . '/../lib/agent.php';
        
        try{
            Agent::install();
        } catch (\Exception $e) {
            $curError[] = $e->getMessage();
        }
    }

    /**
     * @author Pavel Shulaev (https://rover-it.me)
     */
	protected function unInstallAgent()
    {
        global $curError;

        require_once dirname(__FILE__) . '/../lib/agent.php';

        try{
            Agent::uninstall();
        } catch (\Exception $e) {
            $curError[] = $e->getMessage();
        }
    }
}
