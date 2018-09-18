<?php
/**
 * Created by PhpStorm.
 * User: thiagozampieri
 * Date: 17/09/18
 * Time: 21:16
 */


ini_set('allow_url_fopen', 'on');
ini_set('allow_url_include', 'on');

// CONNECT & PROCEDURE
$zipcode  = str_replace(array(".", "-"), "", $_REQUEST["zc"]);
$username = $_REQUEST["un"];
$password = $_REQUEST["pw"];
$format = $_REQUEST["ft"];
//print_r($_REQUEST);exit();
if ($zipcode != '' & $username != '' & $password != '')
{
    include_once ("class/DNE.class.php");

    header('Access-Control-Allow-Origin: *');

    $dne = new DNE();
    $xml_string = $dne->getAddress($zipcode, $username, $password);

    switch($format) {
        case "json":
            header ("content-type: application/json");
            $xml = simplexml_load_string($xml_string);
            $result = json_encode($xml);
            //$array = json_decode($json,TRUE);
            break;
        default:
            header ("content-type: text/xml");
            $result = $xml_string;
            break;
    }
    echo $result;
}
exit();