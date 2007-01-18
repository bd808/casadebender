<?php
/**
 * Defines a class for building joined tables for SQL statements from
 * meta-data.
 * @package Chunsu
 */

/** Import our parent class definition */
require_once "classes/Atsumi/Configuration.php";

/**
 * Builds SQL joined table fragments from meta-data.
 * @author Mark Fyffe <buckyyowza@gmail.com>
 * @package Chunsu
 */
class JoinedTable extends Configuration {

    /**
     * Constructor.
     *
     * <pre>JoinedTable configuration options are:
     *
     * - name: The name of the joined table.
     *
     * - join-direction: One of left or right.  Only applies if
     * join-type is not theta.  Default is left.
     *
     * - join-type: One of inner, outer, or, theta. Default is inner.
     *
     * - join-style: One of (default is auto)
     *
     * - inline : Implement the join by generating join expressions
     * inside a single sql statement.  an inline joined table will
     * cause a fatal error during processing in cases where the joined
     * table comes from a different data source.
     *      
     * - manual : Implement the join by generating an SQL statement
     * for each side of the join and a callback for merging the
     * results of running each SQL statement into a single result set.
     *
     * - auto : Selects inline if the tables are in the same database,
     * and manual if they are in different data sources.
     * </pre>
     *
     * @param $config The core configuration object.
     */
     function JoinedTable(&$config)
     {
         $this->Configuration($config);
     }

}

/**
 * Get a JoinedTable object.
 * @param mixed $initialConfig The external configuration object or array
 * to use for setting up initial values.  May be an existing JoinedTable
 * object, in which case no other is constructed.
 */
function &GetJoinedTable($initialConfig=NULL)
{
    return GetJoinedTableRef($initialConfig);
}

/**
 * Get a JoinedTable object.
 * @param mixed $initialConfig The external configuration object or array
 * to use for setting up initial values.  May be an existing JoinedTable
 * object, in which case no other is constructed.
 */
function &GetJoinedTableRef(&$initialConfig)
{
    if ( is_a($initialConfig,'JoinedTable') )
    {
        return $initialConfig;
    }
    else
    {
        $rv = new JoinedTable($initialConfig);
        return $rv;
    }
}
?>
