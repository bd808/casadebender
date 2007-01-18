<?php
/**
 * Defines a controller class for finding persistent object based on search
 * criteria.
 *
 * @package Chunsu
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import our superclass definition. */
require_once( "classes/Atsumi/AtsumiObject.php" );

/** Import our the SQL Code Generation class definition. */
require_once( "classes/Chunsu/SQLGenerator.php" );

/**
 * Controller class for finding persistent objects based on search
 * criteria.
 * @author Mark Fyffe <buckyyowza@gmail.com>
 * @package Chunsu
 */
class ChunsuSearch extends AtsumiObject
{
    /**
     * The persistent object to use as an example for searching.
     * @var ChunsuObject $example
     */
    var $example;

    /**
     * Array of joined examples, for adding additional joined tables to the
     * example query.
     * @var array $joinedExamples
     */
    var $joinedExamples;

    /**
     * Constructor.
     * @param ChunsuObject $example
     */
    function ChunsuSearch ( $example )
    {
        $tc = array();
        $this->AtsumiObject( $tc );
        $this->example = $example;
    }

    /**
     * Add a joined example.
     * @param ChunsuObject $example.
     */
    function joinTo ( $example )
    {
        $myds = $this->example->datasource;
        $theirds = $example->datasource;
        if ( $myds != $theirds )
        {
            $this->fatal( "cannot join example from different datasource" );
            return;
        }

        if ( is_null($this->joinedExamples) ) $this->joinedExamples = array();
        $this->joinedExamples[] =& $example;
    }

    /**
     * Find matching objects.
     */
    function query ( )
    {
        $sql = new SQLGenerator( $this->example->getCore() );
        $ds = $this->example->datasource;
        $sb = $this->example->storage_method->config->copyObject();
        if ( !is_null($this->joinedExamples) )
        {
            foreach ( $this->joinedExamples as $je )
            {
                $sb->joinTo( $je->storage_method->config,TRUE );
            }
        }
        $ds->connect();
        $res = $ds->query( $sql->select( $sb, $this->getCore() ) );
        $cs =& $res->getNext();
        $rv = array();
        while ( $cs )
        {
            $score =& $cs->getCore();
            assert( is_array($score) );
            $clone = $this->example; // copy
            $clone->is_loaded = TRUE;
            $clone->set($score);
            $rv[] = $clone;
            $cs =& $res->getNext();
        }
        return $rv;
    }

}
?>
