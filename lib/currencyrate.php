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

use Bitrix\Currency\CurrencyRateTable;
use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\Date;
use Bitrix\Currency\CurrencyTable;
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
        $query = array(
            'select'    => array('CURRENCY'),
            'filter'    => array('BASE' => 'N'),
            'cache'     => array('ttl' => 3600)
        );

        $currencies = CurrencyTable::getList($query);

        while ($currency = $currencies->Fetch())
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
            CurrencyRateTable::getEntity()->cleanCache();

            return $newRateId;
        }

        return false;
    }

    /**
     * @param      $currency
     * @param Date $date
     * @return array|null
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function getOnDate($currency, Date $date)
    {
        $query = array(
            'filter' => array(
                '=CURRENCY' => $currency,
                '=DATE_RATE'=> $date
            ),
            'order' => array('DATE_RATE' => 'desc'),
            'cache' => array('ttl' => 3600)
        );

        return CurrencyRateTable::getRow($query);
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