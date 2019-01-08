<?php

require_once("Http.php");
$http = new Http();
echo $http->get("http://google.com");