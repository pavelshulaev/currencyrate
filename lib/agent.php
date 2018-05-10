<?php
/**
 * Created by PhpStorm.
 * User: lenovo
 * Date: 19.01.2018
 * Time: 12:05
 *
 * @author Pavel Shulaev (https://rover-it.me)
 */

namespace Rover\CurrencyRate;

use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;

/**
 * Class Agent
 *
 * @package Rover\CurrencyRate
 * @author  Pavel Shulaev (https://rover-it.me)
 */
class Agent
{
    /**
     * @throws SystemException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function install()
    {
        $nextExec = DateTime::createFromTimestamp(strtotime('tomorrow 1:00'));

        if (!\CAgent::AddAgent(self::getName(), 'rover.currencyrate', 'Y', 86400, '', 'Y', $nextExec->format('d.m.Y H:i:s')))
        {
            global $APPLICATION;
            throw new SystemException($APPLICATION->GetException()->GetString());
        }
    }

    /**
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function uninstall()
    {
        \CAgent::RemoveModuleAgents('rover.currencyrate');
    }

    /**
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function run()
    {
        try{
            CurrencyRate::updateAll();
        } catch (\Exception $e) {}

        return self::getName();
    }

    /**
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function getName()
    {
        return __CLASS__ . '::run()';
    }
}