<?php

    $MerchantID = '4ecb173c-2737-11e7-92b7-005056a205be';
    $Amount = 100; //Amount will be based on Toman
    echo $_GET['license_id'].'<-------';
    $Authority = $_GET['Authority'];

    if ($_GET['Status'] == 'OK') {
        // URL also can be ir.zarinpal.com or de.zarinpal.com
        $client = new \SoapClient('https://www.zarinpal.com/pg/services/WebGate/wsdl', ['encoding' => 'UTF-8']);

        $result = $client->PaymentVerification([
            'MerchantID'     => $MerchantID,
            'Authority'      => $Authority,
            'Amount'         => $Amount,
        ]);

        if ($result->Status == 100) {
            echo 'Transation success. RefID:'.$result->RefID;
        } else {
            echo 'Transation failed. Status:'.$result->Status;
        }
    } else {
        echo 'Transaction canceled by user';
    }
