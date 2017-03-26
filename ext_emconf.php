<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'OpenID authentication',
    'description' => 'OpenID authentication for TYPO3 CMS',
    'category' => 'services',
    'author' => 'Dmitry Dulepov, Markus Klein',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '8.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '8.5.0-8.7.99',
            'sv' => '8.5.0-8.7.99',
            'setup' => '8.5.0-8.7.99',
        ],
        'conflicts' => [
            'naw_openid' => '',
            'naw_openid_be' => ''
        ],
        'suggests' => [],
    ],
];
