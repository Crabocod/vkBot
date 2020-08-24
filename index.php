<?php
require_once 'Api.php';

$api = new Api('565d81312ed66b91b76d5c909ea25a602544824ddd7d02870aab47549bb90c1a2eb04d80f254e070956f3');
$res = $api->server();
$info = $api->info($res);

$api->start($info, $res);
