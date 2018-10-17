<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'OpenID authentication',
    'description' => 'OpenID authentication for TYPO3 CMS',
    'category' => 'services',
    'author' => 'Dmitry Dulepov, Markus Klein',
    'state' => 'stable',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearCacheOnLoad' => false,
    'version' => '8.1.1',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.19-9.5.999',
            'setup' => '8.7.19-9.5.999',
        ],
        'conflicts' => [
            'naw_openid' => '',
            'naw_openid_be' => ''
        ],
        'suggests' => [],
    ],
];
