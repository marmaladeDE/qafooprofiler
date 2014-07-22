<?php

if( false )
{
    class marm_qafooprofiler_oxshopcontrol_parent extends oxShopControl{}
}

class marm_qafooprofiler_oxshopcontrol extends marm_qafooprofiler_oxshopcontrol_parent
{
    public function start( $sClass = null, $sFunction = null, $aParams = null, $aViewsChain = null )
    {
        $this->startProfiler();
        
        parent::start( $sClass, $sFunction, $aParams, $aViewsChain );
        
        $this->setProfilerTransaction( $sClass );
    }
    
    public function startProfiler()
    {
        if( class_exists( '\QafooLabs\Profiler' ) )
        {
            
            $sApiKey = oxRegistry::getConfig()->getShopConfVar( 'sApiKey', null, 'marm/qafooprofiler' );
            
            \QafooLabs\Profiler::startDevelopment( $sApiKey );
            
            //\QafooLabs\Profiler::start( $sApiKey );
        }
    }
    
    public function setProfilerTransaction( $sController )
    {
        if( class_exists( '\QafooLabs\Profiler' ) )
        {
            \QafooLabs\Profiler::setTransactionName( $sController );
        }
    }
}
