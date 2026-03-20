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
    'version' => '14.0.3',
    'constraints' => [
        'depends' =>[
            'typo3' => '14.0.0-14.4.999',
            'setup' => '14.0.0-14.4.999',
        ],
        'conflicts' => [
            'naw_openid' => '',
            'naw_openid_be' => '',
        ],
        'suggests' => [],
    ],
];
