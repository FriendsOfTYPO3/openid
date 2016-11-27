<?php
defined('TYPO3_MODE') || die();

if (TYPO3_MODE === 'BE') {

    // Add field to setup module
    $GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_openid_openid'] = [
        'type' => 'user',
        'table' => 'be_users',
        'label' => 'LLL:EXT:openid/Resources/Private/Language/locallang_db.xlf:_MOD_user_setup.tx_openid_openid',
        'csh' => 'tx_openid_openid',
        'userFunc' => \FoT3\Openid\OpenidModuleSetup::class . '->renderOpenID',
        'access' => \FoT3\Openid\OpenidModuleSetup::class
    ];
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToUserSettings('tx_openid_openid', 'after:password2');
}
