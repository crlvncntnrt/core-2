<?php
ob_start();
ini_set('date.timezone', 'Asia/Manila');
date_default_timezone_set('Asia/Manila');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once('initialize.php');
require_once('classes/DBConnection.php');
require_once('classes/systemSettings.php');

$db = new DBConnection;
$conn = $db->conn;

function redirect($url = '')
{
    if (!empty($url))
        echo '<script>location.href="' . base_url . $url . '"</script>';
}

function format_num($number = '', $decimal = '')
{
    if (is_numeric($number)) {
        $ex = explode(".", $number);
        $decLen = isset($ex[1]) ? strlen($ex[1]) : 0;
        if (is_numeric($decimal)) {
            return number_format($number, $decimal);
        } else {
            return number_format($number, $decLen);
        }
    } else {
        return "Invalid Input";
    }
}

function isMobileDevice()
{
    $aMobileUA = array(
        '/iphone/i' => 'iPhone',
        '/ipod/i' => 'iPod',
        '/ipad/i' => 'iPad',
        '/android/i' => 'Android',
        '/blackberry/i' => 'BlackBerry',
        '/webos/i' => 'Mobile'
    );

    foreach ($aMobileUA as $sMobileKey => $sMobileOS) {
        if (preg_match($sMobileKey, $_SERVER['HTTP_USER_AGENT'])) {
            return true;
        }
    }
    return false;
}

ob_end_flush();
?>
