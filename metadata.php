<?php
/**
 * Version:    1.0
 * Author:     Joscha Krug <krug@marmalade.de>
 * Author URI: http://www.marmalade.de
 */
/**
 * Metadata version
 */
$sMetadataVersion = '1.1';

/**
 * Module information
 */
$aModule = array(
    'id'            => 'marm/qafooprofiler',
    'title'         => 'marmalade :: Qafoo Profiler activation',
    'description'   => 'Activate the Qafoo Profiler for your shop',
    'thumbnail'     => 'marmalade.jpg',
    'version'       => '1.0',
    'author'        => 'marmalade GmbH',
    'url'           => 'http://www.marmalade.de',
    'email'         => 'support@marmalade.de',
    'extend' => array(
        'oxshopcontrol'     => 'marm/qafooprofiler/core/marm_qafooprofiler_oxshopcontrol',
    ),
    'settings' => array(
        array(
                'group' => 'main',
                'name'  => 'sApiKey',
                'type'  => 'str', 
                'value' => 'XXX'
        ),
        array(
                'group' => 'main',
                'name'  => 'isDevMode',
                'type'  => 'bool', 
                'value' => 'false'
        ), 
    )
);
