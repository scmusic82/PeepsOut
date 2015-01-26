<?php

return array(

    'po.ios.dev'      => array(
        'environment' => 'development',
        'certificate' => app_path() . '/PODevelopmentCertificate.pem',
        'passPhrase'  => 'atm.peepsout.ios',
        'service'     => 'apns'
    ),
    'po.ios.prd'      => array(
        'environment' => 'production',
        'certificate' => app_path() . '/PODistributionCertificate.pem',
        'passPhrase'  => 'atm.peepsout.ios',
        'service'     => 'apns'
    ),

);