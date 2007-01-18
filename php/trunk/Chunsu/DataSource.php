<?php
/**
 * Defines an abstact superclass for implementing a data source.
 * 
 * @package Chunsu
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import the Atsumi logging class. */
require_once "classes/Atsumi/AtsumiLogger.php";

/** Import our parent class' definition. */
require_once "classes/Atsumi/AtsumiService.php";

/** Import the Atsumi configuration class. */
require_once "classes/Atsumi/Configuration.php";

/**
 * Provides an abstract interface to a remote data source.
 * @abstract
 * @package Chunsu
 */
class DataSource extends AtsumiService
{
    /**
     * Flag that indicates if this data source is connected.
     */
    var $isConnected = FALSE;

    /**
     * Connect to the data source.
     * <p>When a fatal log event is not encountered, but FALSE is returned,
     * it means that the connectiong mechanism has not been implemented for
     * this given query at a particular level.  This behavior is only
     * expected when calling DataSource::connect() explicitly.
     * @return bool TRUE is successful, FALSE otherwise.
     */
    function connect ()
    {
        if ( $this->isConnected )
        { 
            if ( IsLogEnabled('DEBUG') )
                LogDebug( "already connected", $this );
            return TRUE;
        }
        else
        {
            LogInfo( "connecting: ".print_r($this,TRUE), $this );
            register_shutdown_function( array($this,"shutdown") );
            $this->isConnected = TRUE;
            return FALSE;
        }
    }

    /**
     * Query the data source.
     * <p>This class makes no assumption about the content of the query
     * object, but the behavior of the resulting Cursor object should be
     * consistent between data source implementations.
     * @param mixed $query Some sort of query that will be understood by
     * the implemented data source.
     * @return Cursor The result of the query, FALSE if the query failed.
     */
    function &query ($query)
    {
        if ( $this->isConnected )
        {
            if ( IsLogEnabled('DEBUG') ) LogDebug(
                    "querying for: \n".print_r($query,TRUE), $this);
            $rv = FALSE;
            return $rv;
        }
        else
        {
            if ( IsLogEnabled('ERROR') ) LogError(
                    "Cannot run query: \n".print_r($query,TRUE).
                    "\ndata source is not connected: ".print_r($this,TRUE));
            LogFatal( "Error running query." );
        }
    }

    /**
     * Shut down the data source.
     * <p>When a fatal log event is not encountered, but FALSE is returned,
     * it means that the shutdown mechanism has not been implemented for
     * the data source a particular level.  This behavior is only expected
     * when calling DataSource::shutdown() explicitly.
     * @return bool TRUE if successful, FALSE otherwise.
     */
    function shutdown ()
    {
        if ( $this->isConnected )
        {
            if ( IsLogEnabled('DEBUG') ) LogDebug(
                    "shutting down: ".print_r($this,TRUE), $this);
            $this->isConnected = FALSE;
            return FALSE;
        }
        else
        {
            if ( IsLogEnabled('DEBUG') ) LogDebug("already shut down", $this);
            return TRUE;
        }
    }

}

/**
 * Get all of the configured data sources.
 * @return Configuration All configured data sources.
 */
function &GetDataSources() {
    global $CONFIGURED_DATASOURCES;
    if ( is_null($CONFIGURED_DATASOURCES) )
    {
        $CONFIGURED_DATASOURCES = GetConfiguration();
    }
    return $CONFIGURED_DATASOURCES;
}

/**
 * Configure a data source.
 * @param string $name The name of the data source.
 * @param mixed $config The configuration of the data source.
 */
function ConfigureDataSource($name,&$config)
{
    $cf =& GetDataSources();
    $cf->set( GetService(GetConfigurationRef($config),'DataSource'), $name );
}

/**
 * Get a configured data source by name.
 * @param string $name The name of the data source.
 */
function &GetDataSource($name)
{
    $cf =& GetDataSources();
    if ( is_null($name) )
    {
        LogFatal( "Data source name not supplied" );
        $rv = FALSE;
        return $rv;
    }
    else if ( !$cf->has($name) )
    {
        LogFatal( "Non-existant data source $name" );
        $rv = FALSE;
        return $rv;
    }
    else
    {
        $rv =& $cf->get($name);
        return $rv;
    }
}

?>
