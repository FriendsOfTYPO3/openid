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
    'version' => '9.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '9.0.0-9.1.99',
            'setup' => '9.0.0-9.1.99',
        ],
        'conflicts' => [
            'naw_openid' => '',
            'naw_openid_be' => ''
        ],
        'suggests' => [],
    ],
];
