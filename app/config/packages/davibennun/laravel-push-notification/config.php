<?php

return array(

    'peepsout.ios.dev' => array(
        'environment'  => 'development',
        'certificate'  => app_path() . '/DeveloperCertificates.p12',
        'passPhrase'   => 'atm.peepsout.ios',
        'service'      => 'apns'
    ),
    'peepsout.ios.prd' => array(
        'environment'  => 'production',
        'certificate'  => app_path() . '/DistributionCertificates.p12',
        'passPhrase'   => 'atm.peepsout.ios',
        'service'      => 'apns'
    )

);