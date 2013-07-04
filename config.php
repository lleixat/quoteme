<?php
/**
 * System options don't modify them
 */
$system['dateFormat']  = 'Y-m-d';
$system['version']     = 'devel';
$system['lastUpdate']  = '0000-00-00';
$system['lastVersion'] = '1.2b';

/**
 * config options, put your informations here
 */
$config['dbHost'] = '';
$config['dbName'] = '';
$config['dbUser'] = '';
$config['dbPass'] = '';
$config['dbTable'] = '';
$config['lang'] = '';
$config['themeDir'] = 'themes/simple/';
$config['langDir'] = 'lang/';
$config['siteDoc'] = 'http://q.uote.me/api.php';
$config['email'] = '';
$config['user'] = '';
$config['password'] = '';
$config['sessionExpire'] = 1800;
$config['appVers'] = $system['version']; // your app version
//$config['appVers'] = 'a5e'; // (anonyme) Anonyme stat of client when check update
//$config['appVers'] = ''; // does not generate stats of client when check update
?>
