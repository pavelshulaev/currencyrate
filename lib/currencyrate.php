<?php
/**
 * Created by PhpStorm.
 * User: lenovo
 * Date: 19.01.2018
 * Time: 12:04
 *
 * @author Pavel Shulaev (https://rover-it.me)
 */
namespace Rover\CurrencyRate;

use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\Date;
use \Bitrix\Main\Application;
use Bitrix\Currency;
/**
 * Class CurrencyRate
 *
 * @package Rover\CurrencyRate
 * @author  Pavel Shulaev (https://rover-it.me)
 */
class CurrencyRate
{
    /**
     * @param Date|null $date
     * @throws SystemException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function updateAll(Date $date = null)
    {
        $currencies     = \CCurrency::GetList();
        $baseCurrency   = Currency\CurrencyManager::getBaseCurrency();
        while ($currency = $currencies->Fetch())
            if ($currency['CURRENCY'] != $baseCurrency)
                self::update($currency['CURRENCY'], $date);
    }

    /**
     * @param           $currency
     * @param Date|null $date
     * @return bool
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectException
     * @throws \Bitrix\Main\SystemException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function update($currency, Date $date = null)
    {
        if (!Loader::includeModule('currency'))
            return false;

        $currency = trim($currency);
        if (!strlen($currency)
            || !\CCurrency::GetByID($currency))
            return false;

        if (is_null($date))
            $date = new Date();

        if (self::isExists($currency, $date))
            return true;

        $newRateData = Request::getRate($currency, $date);

        if (empty($newRateData))
            return false;

        $newRateId = \CCurrencyRates::Add($newRateData);

        if ($newRateId) {
            // clear cache
            $cacheId    = md5($currency . $date->format('d.m.Y'));
            $cache      = Application::getInstance()->getManagedCache();
            $cache->clean($cacheId);

            return $newRateId;
        }

        return false;
    }

    /**
     * @param      $currency
     * @param Date $date
     * @return mixed
     * @throws \Bitrix\Main\SystemException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function getOnDate($currency, Date $date)
    {
        $cacheId    = md5($currency . $date->format('d.m.Y'));
        $cache      = Application::getInstance()->getManagedCache();

        if ($cache->read(3600, $cacheId)) {
            $vars = $cache->get($cacheId); // достаем переменные из кеша
        } else {

            $filter = array(
                "CURRENCY" => $currency,
                "DATE_RATE"=> $date->format('d.m.Y')
            );
            $by     = "date";
            $order  = "desc";

            $db_rate = \CCurrencyRates::GetList($by, $order, $filter);
            $vars = array(
                'result' => $db_rate->Fetch()
            );

            $cache->set($cacheId, $vars);
        }

        return $vars['result'];
    }

    /**
     * @param      $currency
     * @param Date $date
     * @return mixed
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectException
     * @throws \Bitrix\Main\SystemException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function get($currency, Date $date)
    {
        if (!self::isExists($currency, $date))
            self::update($currency, $date);

        return self::getOnDate($currency, $date);
    }

    /**
     * @param $currency
     * @return mixed
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectException
     * @throws \Bitrix\Main\SystemException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function getToday($currency)
    {
        return self::get($currency, new Date());
    }

    /**
     * @param      $curCode
     * @param Date $date
     * @return bool
     * @throws \Bitrix\Main\SystemException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function isExists($curCode, Date $date)
    {
        $rate = self::getOnDate($curCode, $date);

        return (bool)$rate;
    }

    /**
     * @param      $sum
     * @param      $currency
     * @param Date $date
     * @return float
     * @throws SystemException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function convert($sum, $currency, Date $date)
    {
        $rate = self::get($currency, $date);

        if (!$rate)
            throw new SystemException('rate not found');

        return round(($sum * $rate['RATE']) / $rate['RATE_CNT'], 2);
    }

    /**
     * @param $sum
     * @param $currency
     * @return float
     * @throws SystemException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function convertToday($sum, $currency)
    {
        return self::convert($sum, $currency, new Date());
    }
}