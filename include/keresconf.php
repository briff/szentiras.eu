<?php
/* General constants */
$baseurl = "http://www.kereszteny.hu/keres/";
$scrlink = $baseurl . "openlink.php";
$scrcat = $baseurl . "showcat.php";
$scrmod = $baseurl . "admin/modlink.php";
$scrqs = $baseurl . "quicksearch.php";
$scradvs = $baseurl . "advsearch.php";
/* Database */
require("phpdb.inc");
$dbconndie = "Nem sikerült kapcsolódni az adatbázishoz";
$db = new phpDB();
$db->pconnect("localhost:/var/run/mysqld/mysql.sock", "root", "") or die($dbconndie);
$db->selectDB("linkdb");
?>
