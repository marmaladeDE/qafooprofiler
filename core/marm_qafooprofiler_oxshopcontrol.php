<?php
/**
 * This file is part of a marmalade Module for OXID eShop CE/PE/EE.
 *
 * It is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License.
 *
 * @link http://marmalade.de
 * @copyright (C) marmalade.de 2014
 * @author Joscha Krug <support@marmalade.de>
 */

if( false )
{
    class marm_qafooprofiler_oxshopcontrol_parent extends oxShopControl{}
}
if(!class_exists('QafooLabs\Profiler\Backend'))
{
    include(oxRegistry::getConfig()->getModulesDir() . 'marm/qafooprofiler/libs/QafooProfiler.php');
}
        
class marm_qafooprofiler_oxshopcontrol extends marm_qafooprofiler_oxshopcontrol_parent
{
    public function start( $sClass = null, $sFunction = null, $aParams = null, $aViewsChain = null )
    {
        $this->startProfiler();
        
        parent::start( $sClass, $sFunction, $aParams, $aViewsChain );
        
        $sClass = $this->_getControllerToLoad( $sClass );
        
        $this->setProfilerTransaction( $sClass );
    }
    
    public function startProfiler()
    {
        $oConfig = oxRegistry::getConfig();
        
        $sApiKey = $oConfig->getShopConfVar( 'sApiKey', null, 'module:marm/qafooprofiler' );
        
        $isDevMode = (bool)$oConfig->getShopConfVar( 'isDevMode', null, 'module:marm/qafooprofiler' );

        if( !$oConfig->isProductiveMode() && $isDevMode )
        {
            \QafooLabs\Profiler::startDevelopment( $sApiKey );
        }
        else
        {
            \QafooLabs\Profiler::start( $sApiKey );
        }
    }
    
    public function setProfilerTransaction( $sController )
    {
        \QafooLabs\Profiler::setTransactionName( $sController );
    }
}
