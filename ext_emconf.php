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
    'version'          => '2.3.0',
    'constraints'      => [
        'depends' => [
            'extbase' => '11.5.0-14.3.99',
            'fluid' => '11.5.0-14.3.99',
            'form' => '11.5.0-14.3.99',
            'typo3' => '11.5.0-14.3.99',
        ],
    ],
];
