<?php
/*
 - Author : Navid Mirzaaghazadeh
 - Module Designed For The : vandar.io
 - Mail : inavid.ir@gmail.com
*/

use WHMCS\Database\Capsule;

if (isset($_REQUEST['invoiceId']) && is_numeric($_REQUEST['invoiceId'])) {
    require_once __DIR__ . '/../../init.php';
    require_once __DIR__ . '/../../includes/gatewayfunctions.php';
    require_once __DIR__ . '/../../includes/invoicefunctions.php';
    $gatewayParams = getGatewayVariables('vandar');
    if (isset($_REQUEST['token']) && $_REQUEST['callback'] == 1) {


        $invoice = Capsule::table('tblinvoices')->where('id', $_REQUEST['invoiceId'])->where('status', 'Unpaid')->first();
        if (!$invoice) {
            die("Invoice not found");
        }


        if ($gatewayParams['currencyType'] == "IRT") {
            $amount = $invoice->total * 10;
        } else {
            $amount = $invoice->total;

        }


        $api = $gatewayParams['api_key'];
        $token = $_GET['token'];
        $result = json_decode(verify($api, $token));


        if (isset($result->status)) {
            if ($result->status == 1) {
                checkCbTransID($result->transId);
                logTransaction($gatewayParams['name'], $_REQUEST, 'Success');
                addInvoicePayment(
                    $invoice->id,
                    $result->transId,
                    $invoice->total,
                    0,
                    'vandar'
                );
            } else {
                logTransaction($gatewayParams['name'], array(
                    'Code' => 'vandar Status Code',
                    'Message' => $result->Status,
                    'Transaction' => $token,
                    'Invoice' => $invoice->id,
                    'Amount' => $invoice->total,
                ), 'Failure');
            }
        } else {
            if ($_GET['status'] == 0) {
                logTransaction($gatewayParams['name'], array(
                    'Code' => 'vandar Status Code',
                    'Message' => $result->Status,
                    'Transaction' => $token,
                    'Invoice' => $invoice->id,
                    'Amount' => $invoice->total,
                ), 'Failure');
            }
        }

        $go = $gatewayParams['systemurl'] . 'viewinvoice.php?id=' . $invoice->id ;
        header("Location: $go");

    } else if (isset($_SESSION['uid'])) {
        $invoice = Capsule::table('tblinvoices')->where('id', $_REQUEST['invoiceId'])->where('status', 'Unpaid')->where('userid', $_SESSION['uid'])->first();
        if (!$invoice) {
            die("Invoice not found");
        }
        $client = Capsule::table('tblclients')->where('id', $_SESSION['uid'])->first();


        if ($gatewayParams['currencyType'] == "IRT") {
            $amount = $invoice->total * 10;
        } else {
            $amount = $invoice->total;

        }

        $result = send($gatewayParams['api_key'], $amount, $gatewayParams['systemurl'] . 'modules/gateways/vandar.php?invoiceId=' . $invoice->id . '&callback=1', null,
            $invoice->id, sprintf('پرداخت فاکتور #%s', $invoice->id));
        $result = json_decode($result);

        if ($result->status == 1) {

            $go = "https://vandar.io/ipg/$result->token";

            header("Location: $go");

        } else {

            print_r($result);

        }


    }
    return;
}

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}


function vandar_MetaData()
{
    return array(
        'DisplayName' => 'ماژول پرداخت آنلاین vandar.io برای WHMCS',
        'APIVersion' => '1.0',
    );
}


function verify($api, $token)
{
    return curl_post(
        'https://vandar.io/api/ipg/verify',
        [
            'api_key' => $api,
            'token' => $token,
        ]
    );
}


function send($api, $amount, $redirect,
              $mobile = null, $factorNumber = null
    , $description = null)
{
    return curl_post(
        'https://vandar.io/api/ipg/send',
        [
            'api_key' => $api,
            'amount' => $amount,
            'callback_url' => $redirect,
            'mobile_number' => $mobile,
            'factorNumber' => $factorNumber,
            'description' => $description,
        ]
    );
}

function vandar_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'درگاه وندار',
        ),
        'currencyType' => array(
            'FriendlyName' => 'نوع ارز',
            'Type' => 'dropdown',
            'Options' => array(
                'IRR' => 'ریال',
                'IRT' => 'تومان',
            ),
        ),
        'api_key' => array(
            'FriendlyName' => 'api_key',
            'Type' => 'text',
            'Size' => '255',
            'Default' => '',
            'Description' => 'وب سرویس دریافتی از سایت وندار'
        ),

    );
}

function vandar_link($params)
{
    $htmlOutput = '<form method="GET" action="modules/gateways/vandar.php">';
    $htmlOutput .= '<input type="hidden" name="invoiceId" value="' . $params['invoiceid'] . '">';
    $htmlOutput .= '<input type="submit" value="' . $params['langpaynow'] . '" />';
    $htmlOutput .= '</form>';
    return $htmlOutput;
}


function curl_post($action, $params)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $action);
    curl_setopt($ch, CURLOPT_POSTFIELDS,
        json_encode($params));
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    return $res;
}
