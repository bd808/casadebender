<?php
/**
 * Defines an object to act as a bridge between a persistent object and an
 * SQL based source.
 * 
 * @package Chunsu
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import our parent class' definition. */
require_once "classes/Chunsu/StorageMethod.php";

/** Import the Atsumi logging class. */
require_once "classes/Atsumi/AtsumiLogger.php";

/** Import the SQL generator class. */
require_once "classes/Chunsu/SQLGenerator.php";

/**
 * Acts as a bridge between a persistent object and an SQL based source.
 * @package Chunsu
 */
class SQLStorageMethod extends StorageMethod
{

    /**
     * Get the primary keys expected by this storage method.
     */
    function getPrimaryKeys()
    {
        $mypk =& $this->config->get('primary-key');
        $pkname = $mypk->get('alias');
        if ( is_null($pkname) || "" == $pkname ) return array();
        return array($pkname);
    }

    /**
     * Save an object.
     * @param ChunsuObject $saveme The object to save.
     * @param DataSource $source The data source to save the object to.
     * @return bool TRUE is successful, FALSE otherwise.
     */
    function save(&$saveme,&$source)
    {
        parent::save($saveme,$source);
        if ( $saveme->isChanged() )
        {
            if ( $saveme->config->get('create-on-save') &&
                    $saveme->is_new )
            {
                $saveme->commit(); // flatten changes into core
                $gen = new SQLGenerator($saveme->getCore());
                $savequeries = $gen->replace($this->config);
            }
            else
            {
                $params = $saveme->changeset;
                $pk = $this->config->getPrimaryKey();
                $pkalias = $pk->get('alias');
                $params[$pkalias] = $saveme->get($pkalias);
                foreach ( $this->config->getForeignKeys() as $fk )
                {
                    $fkalias = $fk->get('alias');
                    $params[$fkalias] = $saveme->get($fkalias);
                }
                $gen = new SQLGenerator($params);
                $savequeries = $gen->update($this->config);
            }

            foreach ($savequeries as $sq)
            {
                $cursor =& $source->query($sq);
                if ( !$cursor->getNext() ) 
                {
                    LogError( "save query failed! saving ".
                            print_r($saveme,TRUE) );
                    return FALSE;
                }
            }
            $saveme->is_new = FALSE;

            if ( $pkval = $cursor->get('generated-id') )
            {
                $pkfield =& $this->config->get('primary-key');
                $pka =& $pkfield->get('alias');

                // for "replace into", treat this save as a load
                $saveme->is_loaded = TRUE;
                $opkval = $saveme->get($pka);
                if ( $opkval == 'new' ) $opkval = NULL;
                if ( !is_null($opkval) && $pkval != $opkval )
                {
                    LogError( "Primary key mismatch: $pkval != $opkval saving ".
                            $saveme->trace() );
                    LogFatal( "Primary key mismatch: $pkval != $opkval" );
                }

                // reset the primary key with the value assigned by the
                // data source
                $saveme->set($pkval, $pka);
                $saveme->commit();
                if ( IsLogEnabled('DEBUG') ) LogDebug(
                        "set $pka to $pkval: ".$saveme->trace() );
            }

            if ( $rows = $cursor->get('affected-rows') > 1 )
            {
                LogWarning( $rows." records written saving ".
                        print_r($saveme,TRUE) );
            }
        }
        return TRUE;
    }

    /**
     * Load an object.
     * @param ChunsuObject $loadme The object to load.
     * @param DataSource $source The data source to load the object from.
     * @return bool TRUE is successful, FALSE otherwise.
     */
    function load(&$loadme,$source)
    {
        parent::load($loadme,$source);
        return $this->loadOrImport(TRUE,$loadme,$source);
    }

    /**
     * Import an object.
     * @param ChunsuObject $importme The object to import.
     * @param DataSource $source The data source to import the object from.
     * @return bool TRUE is successful, FALSE otherwise.
     */
    function import(&$importme,$source)
    {
        parent::import($importme,$source);
        return $this->loadOrImport(FALSE,$importme,$source);
    }

    /**
     * Implementation of load and import varies only slight, so both
     * methods refer to this helper method.
     * @param bool $do_load TRUE for load, FALSE for import.
     * @param ChunsuObject $loadme The object to load.
     * @param DataSource $source The data source to load the object from.
     * @return bool TRUE is successful, FALSE otherwise.
     */
    function loadOrImport($do_load,&$loadme,$source)
    {
        $pkfield =& $this->config->get('primary-key');
        $pka =& $pkfield->get('alias');
        $core =& $loadme->getCore();
        if ( is_array($core) || is_object($core) )
        {
            $dummy =& $loadme->getDummy();
            $pkval = $dummy->get($pka);
        }
        else
        {
            $pkval = $core;
        }
        if ( !is_null($pkval) )
        {
            $gen = new SQLGenerator($loadme->getCore());
            $cursor = $source->query($gen->select($this->config));
            if ( $cursor->getNext() )
            {
                $rec =& $cursor->get();
                if ( $do_load )
                {
                    $loadme->setref( $rec );
                    $loadme->is_new = FALSE;
                }
                else
                { 
                    $loadme->bulkSet( $rec );
                }
                return TRUE;
                // TODO: keep reading and handle conflicts if >1 record
            }    
        }
        
        if ( !$loadme->config->get('create-on-save') )
        {
            LogError( "Cannot load object: no records found.\n".
                    $loadme->trace() );
            LogFatal( "Cannot load object: no records found. ");
        }
        return TRUE;
    }

    /**
     * Remove an object.
     * @param ChunsuObject $removeme The object to remove.
     * @param DataSource $source The data source to remove the object from.
     * @return bool TRUE is successful, FALSE otherwise.
     */
    function remove(&$removeme,$source)
    {
        parent::remove($removeme,$source);
        $gen = new SQLGenerator($removeme->getCore());
        $removequeries = $gen->delete($this->config);
        foreach ($removequeries as $rq)
        {
            $cursor =& $source->query($rq);
            $rv = $cursor->getNext();
            if ( !$rv ) 
            {
                LogError( "remove query failed! removing ".
                        print_r($removeme,TRUE) );
                return FALSE;
            }
        }

        $rv = $cursor->getNext();
        if ( !$rv )
        {
            LogError( "Remove failed! Removing ".print_r($removeme,TRUE) );
            return FALSE;
        }

        if ( $rows = $cursor->get('affected-rows') > 1 )
        {
            LogWarning( "$rows records deleted removing ".
                    print_r($removeme,TRUE) );
        }
        $removeme->is_new = $removeme->config->get('create-on-save');
        return TRUE;
    }

}
