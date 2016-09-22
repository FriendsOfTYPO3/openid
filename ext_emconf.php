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
    'version' => '7.6.2',
    'constraints' => array(
        'depends' => array(
            'typo3' => '7.6.0-7.6.99',
            'sv' => '7.6.0-7.6.99',
            'setup' => '7.6.0-7.6.99',
        ),
        'conflicts' => array(
            'naw_openid' => '',
            'naw_openid_be' => ''
        ),
        'suggests' => array(),
    ),
);
