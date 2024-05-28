<?php

$EM_CONF[$_EXTKEY] = [
    'title'            => 'hCaptcha for EXT:form',
    'description'      => 'TYPO3 Extension to add hCaptcha to EXT:form - The privacy friendly captcha alternative.',
    'category'         => 'frontend',
    'author'           => 'dreistrom.land AG',
    'author_email'     => 'hello@dreistrom.land',
    'author_company'   => 'dreistrom.land AG',
    'state'            => 'stable',
    'uploadfolder'     => '0',
    'clearCacheOnLoad' => 1,
    'version'          => '2.2.0',
    'constraints'      => [
        'depends' => [
            'extbase' => '10.4.0-13.0.99',
            'fluid' => '10.4.0-13.0.99',
            'form' => '10.4.0-13.0.99',
            'typo3' => '10.4.0-13.0.99',
        ],
    ],
];
