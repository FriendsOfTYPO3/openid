<?php
$EM_CONF[$_EXTKEY] = array(
    'title' => 'OpenID authentication',
    'description' => 'OpenID authentication for TYPO3 CMS',
    'category' => 'services',
    'author' => 'Dmitry Dulepov',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '7.6.4',
    'constraints' => array(
        'depends' => array(
            'typo3' => '8.5.0-8.99.99',
            'sv' => '8.5.0-8.99.99',
            'setup' => '8.5.0-8.99.99',
        ),
        'conflicts' => array(
            'naw_openid' => '',
            'naw_openid_be' => ''
        ),
        'suggests' => array(),
    ),
);
