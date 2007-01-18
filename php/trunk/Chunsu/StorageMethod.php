<?php
/**
 * Defines an abstact superclass for objects that act as a bridge between a
 * persistent object and a data source.
 * 
 * @package Chunsu
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import the Atsumi logging class. */
require_once "classes/Atsumi/AtsumiLogger.php";

/**
 * Provides an abstract interface to a remote data source.
 * @abstract
 * @package Chunsu
 */
class StorageMethod extends AtsumiService
{
    /**
     * Save an object.
     * @param ChunsuObject $saveme The object to save.
     * @param DataSource $source The data source to save the object to.
     * @return bool TRUE is successful, FALSE otherwise.
     */
    function save(&$saveme,&$source)
    {
        if ( IsLogEnabled('DEBUG') ) LogDebug(
                "saving ".get_class($saveme), $this);
        return FALSE;
    }

    /**
     * Load an object.
     * @param ChunsuObject $loadme The object to load.
     * @param DataSource $source The data source to load the object from.
     * @return bool TRUE is successful, FALSE otherwise.
     */
    function load(&$loadme,&$source)
    {
        if ( IsLogEnabled('DEBUG') ) LogDebug(
                "loading ".get_class($loadme), $this);
        return FALSE;
    }

    /**
     * Import data from a data source into an object.  Importing is similar
     * to loading, but data is loaded into the change set and the object is
     * not flagged as loaded after an import.
     * @param ChunsuObject $importme The object to receive imported data.
     * @param DataSource $source The data source to load the object from.
     * @return bool TRUE is successful, FALSE otherwise.
     */
    function import(&$importme,&$source)
    {
        if ( IsLogEnabled('DEBUG') ) LogDebug(
                "importing ".get_class($importme), $this);
        return FALSE;
    }

    /**
     * Remove an object.
     * @param ChunsuObject $removeme The object to remove.
     * @param DataSource $source The data source to remove the object from.
     * @return bool TRUE is successful, FALSE otherwise.
     */
    function remove(&$removeme,&$source)
    {
        if ( IsLogEnabled('DEBUG') ) LogDebug(
                "removing ".get_class($removeme), $this);
        return FALSE;
    }

    /**
     * Get the expected primary keys names for this storage method.
     * @abstract
     */
    function getPrimaryKeys()
    {
        return FALSE;
    }

}

/**
 * Get all of the configured storage methods .
 * @param string $pclass Persistent object class.
 * @return Configuration All configured data sources.
 */
function &GetStorageMethods($pclass) {
    global $CONFIGURED_STORAGEMETHODS;
    $pclass = strtolower($pclass);
    if ( !class_exists($pclass) )
    {
        if (IsLogEnabled('WARN')) LogWarning(
                "Persistent object class $pclass does not exist" );
    }
    if ( is_null($CONFIGURED_STORAGEMETHODS) )
    {
        $CONFIGURED_STORAGEMETHODS = GetConfiguration();
    }
    if ( !$CONFIGURED_STORAGEMETHODS->has($pclass) )
    {
        $CONFIGURED_STORAGEMETHODS->set(GetConfiguration(), $pclass);
    }
    return $CONFIGURED_STORAGEMETHODS->get($pclass);
}

/**
 * Configure a storage method.
 * @param string $name The name of the storage method.
 * @param mixed $config The configuration of the storage method.
 */
function ConfigureStorageMethod($pclass,$name,&$config)
{
    $cf =& GetStorageMethods($pclass);
    $cf->set( GetService($config,'StorageMethod'), $name );
}

/**
 * Get a configured storage method by name.
 * @param string $name The name of the storage method.
 */
function &GetStorageMethod($pobj,$name)
{
    $pclass = get_class($pobj);
    $rv = FALSE;
    while ( $pclass && strtolower($pclass) != 'atsumiobject' && !$rv )
    {
        $cf =& GetStorageMethods($pclass);
        if ( $cf->has($name) )
        {
            $rv =& $cf->get($name);
        }
        $pclass = get_parent_class($pclass);
    }

    if ( !$rv )
    {
        if ( IsLogEnabled('ERROR') ) LogError(
                "Non-existant storage method $name for\n".print_r($pobj,TRUE) );
        LogFatal( "Non-existant storage method\n" );
        return FALSE;
    }
    else
    {
        return $cf->get($name);
    }
}

?>
