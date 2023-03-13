<?php

// Prepare new columns for fe_users table
$tempColumns = [
    'tx_openid_openid' => [
        'exclude' => 0,
        'label' => 'LLL:EXT:openid/Resources/Private/Language/locallang_db.xlf:be_users.tx_openid_openid',
        'config' => [
            'type' => 'input',
            'size' => '30',
            // Requirement: unique (BE users are unique in the whole system)
            'eval' => 'trim,nospace,uniqueInPid',
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
// Add new columns to fe_users table
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToAllPalettesOfField('fe_users', 'username', 'tx_openid_openid');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('fe_users', 'EXT:openid/Resources/Private/Language/locallang_csh.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_user_setup', 'EXT:openid/Resources/Private/Language/locallang_csh_mod.xlf');
