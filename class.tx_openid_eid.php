<?php
/*
 * @deprecated since 6.0, the classname tx_openid_eID and this file is obsolete
 * and will be removed by 7.0. The class was renamed and is now located at:
 * typo3/sysext/openid/Classes/OpenidEid.php
 */
require_once t3lib_extMgm::extPath('openid') . 'Classes/OpenidEid.php';
$module = t3lib_div::makeInstance('tx_openid_eID');
/* @var tx_openid_eID $module */
$module->main();
?>