<?php
defined('TYPO3_MODE') or die();

// Prepare new columns for be_users table
$tempColumns = [
    'tx_openid_openid' => [
        'exclude' => 0,
        'label' => 'LLL:EXT:openid/Resources/Private/Language/locallang_db.xlf:be_users.tx_openid_openid',
        'config' => [
            'type' => 'input',
            'size' => '30',
            // Requirement: unique (BE users are unique in the whole system)
            'eval' => 'trim,nospace,unique',
            'wizards' => [
                '0' => [
                    'type' => 'popup',
                    'title' => 'Add OpenID',
                    'module' => [
                        'name' => 'wizard_openid'
                    ],
                    'icon' => 'EXT:openid/Resources/Public/Icons/ext_icon_small.png',
                    'JSopenParams' => ',width=800,height=600,status=0,menubar=0,scrollbars=0',
                ]
            ],
        ]
    ]
];
// Add new columns to be_users table
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('be_users', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('be_users', 'tx_openid_openid', '', 'after:username');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('be_users', 'EXT:openid/Resources/Private/Language/locallang_csh.xlf');
