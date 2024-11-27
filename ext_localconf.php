<?php

if (version_compare(PHP_VERSION, '8.2.26', '>=') && version_compare(PHP_VERSION, '8.3.0', '<') || version_compare(PHP_VERSION, '8.3.14', '>=') && version_compare(PHP_VERSION, '8.4.0', '<')) {
    // See https://github.com/php/php-src/issues/16870
    define('Auth_OpenID_BUGGY_GMP', 1);
}

// Register OpenID authentication service with TYPO3
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
    'openid',
    'auth',
    'tx_openid_service',
    [
        'title' => 'OpenID Authentication',
        'description' => 'OpenID authentication service for Frontend and Backend',
        'subtype' => 'getUserBE,authUserBE,processLoginDataBE',
        'available' => true,
        'priority' => 75,
        // Must be higher than for \TYPO3\CMS\Sv\AuthenticationService (50) or \TYPO3\CMS\Sv\AuthenticationService will log failed login attempts
        'quality' => 60,
        'os' => '',
        'exec' => '',
        'className' => \FoT3\Openid\OpenidService::class
    ]
);

// Register eID script that performs final FE user authentication. It will be called by the OpenID provider
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['setup']['accessLevelCheck'][\FoT3\Openid\OpenidModuleSetup::class] = '';

// Use popup window to refresh login instead of the AJAX relogin:
$GLOBALS['TYPO3_CONF_VARS']['BE']['showRefreshLoginPopup'] = 1;

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][1433416748] = [
    'provider' => \FoT3\Openid\LoginProvider\OpenIdLoginProvider::class,
    'sorting' => 25,
    'iconIdentifier' => 'ext-openid',
    'label' => 'LLL:EXT:openid/Resources/Private/Language/locallang.xlf:login.link'
];

\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class)->registerIcon(
    'ext-openid',
    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
    [
        'source' => 'EXT:openid/Resources/Public/Icons/login-icon.svg',
    ]
);
