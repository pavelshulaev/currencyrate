<?php
/**
 * Created by PhpStorm.
 * User: lenovo
 * Date: 19.01.2018
 * Time: 12:12
 *
 * @author Pavel Shulaev (https://rover-it.me)
 */

namespace Rover\CurrencyRate;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main,
    Bitrix\Currency;

Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/currency/tools/get_rate.php');

/**
 * Class Request
 *
 * @package Rover\CurrencyRate
 * @author  Pavel Shulaev (https://rover-it.me)
 */
class Request
{
    /**
     * @param           $currency
     * @param Date|null $date
     * @param string    $baseCurrency
     * @return array
     * @throws ArgumentNullException
     * @throws Main\LoaderException
     * @throws Main\ObjectException
     * @throws Main\SystemException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    public static function getRate($currency, Date $date = null, $baseCurrency = '')
    {
        if (!Loader::includeModule('currency'))
            throw new Main\SystemException(Loc::getMessage('BX_CURRENCY_GET_RATE_ERR_MODULE_ABSENT'));

        $currency = trim($currency);
        if (!$currency)
            throw new ArgumentNullException('currency');

        if (is_null($date))
            $date = new Date();

        $baseCurrency = trim($baseCurrency);
        if (!strlen($baseCurrency))
            $baseCurrency = Currency\CurrencyManager::getBaseCurrency();

        $url    = self::getUrlByBaseCurrency($baseCurrency, $date);
        $data   = self::get($url);
        $data   = self::getXml($data);
        $result = self::getResult($currency, $baseCurrency, $data);

        if ($result['STATUS'] != 'OK')
            throw new Main\SystemException(Loc::getMessage('BX_CURRENCY_GET_RATE_ERR_RESULT_ABSENT'));

        unset($result['STATUS']);
        unset($result['MESSAGE']);

        $result['CURRENCY']     = $currency;
        $result['DATE_RATE']    = $date->format('d.m.Y');

        return $result;
    }

    /**
     * @param array $data
     * @param       $currency
     * @param       $baseCurrency
     * @return array
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function getResult($currency, $baseCurrency, array $data = null)
    {
        $result = array(
            'STATUS' => '',
            'MESSAGE' => '',
            'RATE_CNT' => '',
            'RATE' => '',
        );

        if (empty($data))
            return $result;

        switch ($baseCurrency)
        {
            case 'UAH':
                if (!empty($data["exchange"]["#"]['currency']) && is_array($data["exchange"]["#"]['currency']))
                {
                    $currencyList = $data['exchange']['#']['currency'];
                    foreach ($currencyList as $currencyRate)
                    {
                        if ($currencyRate['#']['cc'][0]['#'] == $currency)
                        {

                            $result['STATUS'] = 'OK';
                            $result['RATE_CNT'] = 1;
                            $result['RATE'] = (float)str_replace(",", ".", $currencyRate['#']['rate'][0]['#']);
                            break;
                        }
                    }
                    unset($currencyRate, $currencyList);
                }
                break;
            case 'BYR':
            case 'BYN':
                if (!empty($data["DailyExRates"]["#"]["Currency"]) && is_array($data["DailyExRates"]["#"]["Currency"]))
                {
                    $currencyList = $data['DailyExRates']['#']['Currency'];
                    foreach ($currencyList as $currencyRate)
                    {
                        if ($currencyRate["#"]["CharCode"][0]["#"] == $currency)
                        {
                            $result['STATUS'] = 'OK';
                            $result['RATE_CNT'] = (int)$currencyRate["#"]["Scale"][0]["#"];
                            $result['RATE'] = (float)str_replace(",", ".", $currencyRate["#"]["Rate"][0]["#"]);
                            break;
                        }
                    }
                    unset($currencyRate, $currencyList);
                }
                break;
            case 'RUB':
            case 'RUR':
                if (!empty($data["ValCurs"]["#"]["Valute"]) && is_array($data["ValCurs"]["#"]["Valute"]))
                {
                    $currencyList = $data["ValCurs"]["#"]["Valute"];
                    foreach ($currencyList as $currencyRate)
                    {
                        if ($currencyRate["#"]["CharCode"][0]["#"] == $currency)
                        {
                            $result['STATUS'] = 'OK';
                            $result['RATE_CNT'] = (int)$currencyRate["#"]["Nominal"][0]["#"];
                            $result['RATE'] = (float)str_replace(",", ".", $currencyRate["#"]["Value"][0]["#"]);
                            break;
                        }
                    }
                    unset($currencyRate, $currencyList);
                }
                break;
        }

        return $result;
    }

    /**
     * @param $data
     * @return array|bool
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function getXml($data)
    {
        require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/xml.php");

        $objXML = new \CDataXML();

        return ($objXML->LoadString($data) === false)
            ? null
            : $objXML->GetArray();
    }

    /**
     * @param $url
     * @return bool|string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function get($url)
    {
        global $APPLICATION;

        $http       = new Main\Web\HttpClient();
        $data       = $http->get($url);
        $charset    = 'windows-1251';
        $matches    = array();

        if (preg_match("/<"."\?XML[^>]{1,}encoding=[\"']([^>\"']{1,})[\"'][^>]{0,}\?".">/i", $data, $matches))
            $charset = trim($matches[1]);

        $data = preg_replace("#<!DOCTYPE[^>]+?>#i", '', $data);
        $data = preg_replace("#<"."\\?XML[^>]+?\\?".">#i", '', $data);

        return $APPLICATION->ConvertCharset($data, $charset, SITE_CHARSET);
    }

    /**
     * @param      $baseCurrency
     * @param Date $date
     * @return string
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function getUrlByBaseCurrency($baseCurrency, Date $date)
    {
        global $DB;

        switch ($baseCurrency)
        {
            case 'UAH':
                return 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange/?date='.$DB->FormatDate($date->format('d.m.Y'), \CLang::GetDateFormat('SHORT', LANGUAGE_ID), 'YMD');
            case 'BYR':
            case 'BYN':
                return 'https://www.nbrb.by/Services/XmlExRates.aspx?ondate='.$DB->FormatDate($date->format('d.m.Y'), \CLang::GetDateFormat('SHORT', LANGUAGE_ID), 'Y-M-D');
            case 'RUB':
            case 'RUR':
                return 'https://www.cbr.ru/scripts/XML_daily.asp?date_req='.$DB->FormatDate($date->format('d.m.Y'), \CLang::GetDateFormat('SHORT', LANGUAGE_ID), 'D.M.Y');
                break;
            default:
                return '';
        }
    }

    /**
     * @return string
     * @throws \Bitrix\Main\SystemException
     * @author Pavel Shulaev (https://rover-it.me)
     */
    protected static function getFullHostName()
    {
        $https      = \CMain::IsHTTPS();
        $httpHost   = Application::getInstance()->getContext()->getServer()->getHttpHost();

        $fullName = $https
            ? 'https://' . $httpHost
            : 'http://' . $httpHost;

        if (substr($fullName, strlen($fullName)-1) == "/")
            $fullName = substr($fullName, 0, strlen($fullName) - 1);

        return $fullName;
    }
}