<?php

namespace MemberproApi;

use Exception;
//use SimpleXMLElement;

class Order
{
    protected $ended = false;
    /**
     * @var int
     */
    protected $overallPrice = 0;

    /**
     * @var int
     */
    protected $orderId;

    /**
     * @var Memberpro_Api
     */
    protected $api;

    /**
     * @var array
     */
    protected $requiredItemParams = [
        'id_item',
        'name',
        'code_vat',     //vat code 0-standard vat, 1-lowered vat, 2-without vat || 0 - 21%, 1 - 15%, 0 - 0%
        'count',
        'price_with_vat_per_each',
    ];

    /**
     * @var array
     */
    protected $vatRates = [
        0 => 21,
        1 => 15,
        2 => 0,
    ];

    /**
     * order constructor.
     * @param Memberpro_Api $api
     * @param string $email
     * @throws Exception
     */
    public function __construct(Memberpro_Api $api, string $email)
    {
        $this->api = $api;
        $this->orderId = $this->getNewOrderId($email);
    }

    /**
     * @param string $email
     * @return int
     * @throws Exception
     */
    protected function getNewOrderId(string $email): int
    {
        $customerParams = [
            'ID_ESHOP' => 'ID_ESHOP_CONFIG',
            'ZPUSOB_UHRADY' => '1',             //payment method code: 1-payment gate, 2-on site, 3-cod, 4-ostatní
            'ZPUSOB_DORUCENI' => '1',           //delivery code: 1-email, 2-personal, 3-Mail, 4-other
            'EMAIL' => $email,                  //Email customer
            'FIRMA_OSOBA' => 1,
            'OSLOVENI' => '',
            'JMENO' => '',
            'PRIJMENI' => '',
            'TELEFON' => '',
            'ADRESA_1' => '',
            'ADRESA_2' => '',
            'ADRESA_3' => '',
            'ADRESA_4' => '',
            'ICO' => '',
            'DIC' => '',
            'NAZEV_FIRMY' => '',
            'DOR_ADRESA_1' => '',
            'DOR_ADRESA_2' => '',
            'DOR_ADRESA_3' => '',
            'DOR_ADRESA_4' => '',
        ];

        $newOrderId = $this->api->API_AG_PRODEJ_NEW($customerParams);

        if ((int)$newOrderId->ID < 1) {
            throw new Exception($newOrderId->TEXT_CHYBA);
        }

        return (int)$newOrderId->ID;
    }

    /**
     * @param array $item
     * @return mixed
     * @throws Exception
     */
    public function addItem(array $item)
    {
        foreach ($this->requiredItemParams as $param) {
            if (!isset($item[$param])) {
                throw new Exception("missing required parameter: " . $param);
            }
        }

        $totalPriceWithVat = $item['count'] * $item['price_with_vat_per_each'];
        $totalPriceWithoutVat = 100 * ($totalPriceWithVat / (100 + $this->vatRates[$item['code_vat']]));
        $totalVat = $totalPriceWithVat - $totalPriceWithoutVat;

        $add['ID'] = $this->orderId;
        $add['ID_MAT'] = $item['id_item'];
        $add['MNOZSTVI'] = $item['count'];
        $add['CENA_J'] = $item['price_with_vat_per_each'];
        $add['CELKEM'] = round($totalPriceWithVat, 2);
        $add['BEZ_DPH'] = round($totalPriceWithoutVat, 2);
        $add['DPH'] = round($totalVat, 2);
        $add['KOD_DPH'] = $item['code_vat'];                    //Kod sazby DPH 0-základní sazba,1-snížená sazba,2-bez DPH || 0 - 21%, 1 - 15%, 0 - 0%
        $add['NAZEV'] = $item['name'];

        $insert = $this->api->API_AG_PRODEJ_ITEM_INSERT($add);

        $this->overallPrice = $this->overallPrice + $add['CELKEM'];

        return $insert->ID_RADEK;
    }

    /**
     * @throws Exception
     */
    public function orderFinish()
    {
        $params = [
            'ID' => $this->orderId,
            'STAV' => 10,
            'ZPUSOB_UHRADY' => 1,
            'ZPUSOB_DORUCENI' => 1,
            'ID_POKLADNY' => '',
            'ID_PROVOZU' => 0,
            'FIK' => '',
            'BKP' => '',
            'PKP' => '',
            'CDD' => 0,
            'ID_PP_1' => '',
            'ID_PP_2' => '',
            'DPH_ZS' => 0,
            'DPH_SS' => 0,
            'ZD_ZS' => 0,
            'ZD_SS' => 0,
            'ZD_0' => 0,
            'CELKEM' => $this->overallPrice,
        ];

        $result = $this->api->API_AG_PRODEJ_FINISH($params);

        if ($result->OK != 1) {
            throw new Exception($result->TEXT_CHYBA);
        }

        $this->ended = true;

        return $this->getVouchers();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getVouchers()
    {
        if(!$this->ended) {
            throw new Exception("Order isn't finished");
        }

        $voucherParams = ['ID' => $this->orderId];
        $voucher = $this->api->API_AG_PRODEJ_GET_VOUCHERS($voucherParams);
        return $voucher;
    }

    /**
     * @return int
    */
    public function getOverallPrice(): int
    {
        return $this->overallPrice;
    }

}


