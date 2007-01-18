<?php
/**
 * Defines a superclass for persistent objects.
 * @author Mark Fyffe <buckyyowza@gmail.com>
 * @package Chunsu
 */

/** Import our parent class' definition */
require_once "classes/Atsumi/AtsumiObject.php";

/** Import the configuration class definition */
require_once "classes/Atsumi/Configuration.php";

/** Import the serivce class definition */
require_once "classes/Atsumi/AtsumiService.php";

/** Import the data source class definition */
require_once "classes/Chunsu/DataSource.php";

/** Import the storage method class definition */
require_once "classes/Chunsu/StorageMethod.php";

/**
 * Superclass for persistent objects.
 * @abstract
 * @pacakge Chunsu
 */
class ChunsuObject extends AtsumiObject {

    /**
     * This object's data source.
     * @var DataSource $datasource
     */
    var $datasource;

    /**
     * This object's storage method.
     * @var StorageMethod $storagemethod
     */
    var $storage_method;

    /**
     * Indicates if the persistent object has been loaded.
     * @var bool $is_loaded
     */
    var $is_loaded;

    /**
     * Indicates if the persistent object is a new object.
     * @var bool $is_new
     */
    var $is_new;

    /**
     * The change set.
     * @var array $changeset
     */
    var $changeset;

    /**
     * Constructor.
     * <pre>Configuration options are:
     *  - datasource : Data source configuration, passed to GetDataSource.
     *  - storage-method : Storage method configuration, passed to
     *  GetStorageMethod.
     *  - create-on-save : indicates that the object should be created in the
     *  data source if it doesn't already exist.
     * </pre>
     * @param mixed $config Configuration data for this persistent object.
     * @param mixed $initialData Core object or array with initial data.
     * If $initialData is atomic, and a single identifier (i.e. primary
     * key) is defined by the storage method for the default data souce,
     * then this data is routed to be associated with the field
     * corresponding to that identifier rather than becoming the core
     * object itself.
     */
    function ChunsuObject(&$config,&$initialData)
    {
        // there is too much stuff inside of a chunsu object that might get
        // clobbered, so ensure that the core is not null.
        // TODO: ??? if ( $initialData == NULL ) $initialData = array();
        $this->AtsumiObject($initialData);
        $this->config =& GetConfiguration($config);

        $this->datasource =&
                GetDataSource($this->config->get('datasource'));

        $this->storage_method =&
                GetStorageMethod($this,$this->config->get('storage-method'));

        $this->is_loaded = FALSE;
        $this->is_new = $this->config->get('create-on-save');
        $this->changeset = NULL;
    }

    /**
     * Connect this persistent object's data source.
     */
    function connectDataSource()
    {
        if ( !$this->datasource->connect() ) 
        {
            $this->fatal( "Failed to connect data source" );
        }
    }

    /**
     * Load the data for this object from the data source.
     */
    function load()
    {
        $this->connectDataSource();
        $this->storage_method->load($this, $this->datasource);
        $this->is_loaded = TRUE;
    }

    /**
     * Import data from a data source into this object's change set.
     * @param DataSource $ds alternate data source to use.
     */
    function import(&$ds)
    {
        $ds->connect();
        $this->storage_method->import($this, $ds);
    }

    /**
     * Save this object's data in the data source.
     */
    function save()
    {
        $this->connectDataSource();
        $this->storage_method->save($this, $this->datasource);
        $this->commit();
    }

    /**
     * Remove this object's data from the data source.
     */
    function remove()
    {
        $this->connectDataSource();
        $this->storage_method->remove($this, $this->datasource);
    }

    /**
     * Automatically loads data before a get if the data is not present in
     * a change set and the object has not yet been loaded.
     */
    function &get($index=NULL)
    {
        if ( $index != null && $this->changeset != NULL &&
                array_key_exists($index,$this->changeset)
                ) return $this->changeset[$index];

        if ( !$this->is_loaded ) $this->load();
        return parent::get($index);
    }

    /**
     * Change data in the change set.
     */
    function setref(&$value,$index=NULL)
    {
        if ( !is_null($index) && parent::get($index) == $value )
        {
            if ( !is_null($this->changeset) &&
                    array_key_exists($index,$this->changeset)
                    ) unset($this->changeset[$index]);
        }
        else
        {

            if ( is_null($index) ) parent::setref($value);
            else
            {
                if ( is_null($this->changeset) )
                {
                    // initialize change set
                    $this->changeset = array();
                    $core =& $this->getCore();
                    if ( !is_array($core) && !is_object($core) )
                    {
                        $nucore = array();
                        foreach ( $this->storage_method->getPrimaryKeys()
                                as $pk )
                        {
                            $nucore[$pk] =& $core;
                        }
                        parent::setref($nucore);
                    }
                }
                $this->changeset[$index] =& $value;
            }
        }
    }

    /**
     * Set all of the data from an associative array or object in the
     * change set.
     * @param array $fromMe associative array or object with data to set
     * the change set with.
     */
    function bulkSet($fromMe)
    {
        if ( !is_array($fromMe) )
        {
            $fromMe = get_object_vars($fromMe);
        }
        $this->debug($fromMe);
        foreach ( array_keys($fromMe) as $key )
        {
            $this->set($fromMe[$key],$key);
        }
    }

    /**
     * Commit changes to the main data set.  This is performed by save()
     * and should not be done manually in most cases.
     */
    function commit()
    {
        if ( !is_null($this->changeset) )
        {
            $core =& $this->getCore();
            if ( !is_array($core) && !is_object($core) )
            {
                $pks = $this->storage_method->getPrimaryKeys();
                $this->set( array($pks[0]=>$core) );
            }
            foreach ( array_keys($this->changeset) as $key )
            {
                parent::setref( $this->changeset[$key],$key );
            }
        }
        $this->changeset = NULL;
    }

    /**
     * Rollback all uncommitted changes.
     */
    function rollback()
    {
        $this->changeset = NULL;
    }

    /**
     * Has the data identified by index changed since the last save? 
     */
    function isChanged($index=NULL) {
        if ( is_null($index) ) return !is_null($this->changeset);
        return !is_null($this->changeset) &&
                array_key_exists($index,$this->changeset);
    }

    /**
     * Trace the change set under the core.
     */
    function trace()
    {
        $rv = parent::trace();
        $rv.= 'Chunsu('.get_class($this).") {";
        if ( !is_null($this->changeset) )
        {
            foreach ( array_keys($this->changeset) as $index )
            {
                $rv .= "\n  $index -> ".print_r($this->changeset[$index],true);
            }
        }
        $rv.= "\n}\n";
        return $rv;
    }

}

?>
