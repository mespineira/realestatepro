<?php
require_once("wp-load.php");
require_once ABSPATH.'wp-admin/includes/file.php';

$url = "https://media.mobiliagestion.es/Portals/inmogandara/Images/1058/16443707-original.jpg";
$tmp = wp_tempnam($url);
echo "TMP FILE: $tmp\n";
echo "Exists: " . (file_exists($tmp) ? "yes" : "no") . "\n";
echo "Writable: " . (is_writable($tmp) ? "yes" : "no") . "\n";

$body = "test";
$written = file_put_contents($tmp, $body);
echo "Written test: " . var_export($written, true) . "\n";

// Let's test with wp_remote_get
$response = wp_safe_remote_get($url, array(
    'timeout' => 30,
    'headers' => array('User-Agent' => 'RealEstatePro/1.0')
));
$body2 = wp_remote_retrieve_body($response);
echo "Body size: " . strlen($body2) . " bytes\n";
$written2 = file_put_contents($tmp, $body2);
echo "Written image: " . var_export($written2, true) . "\n";

unlink($tmp);
