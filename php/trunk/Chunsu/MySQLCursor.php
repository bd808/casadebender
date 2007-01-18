<?php
/**
 * Defines a data cursor class for use with a MySQL database.
 *
 * @package Chunsu
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import our parent class definition */
require_once "classes/Chunsu/Cursor.php";

/**
 * Implements the Cursor class for MySQLCursor object.  
 * @package Chunsu
 */
class MySQLCursor extends Cursor {

    /**
     * Constructor.
     * @param resource $result The MySQL result resource this cursor is
     * stepping through.
     * @param MySQLDataSource $dataSource The MySQL data source that ran
     * the query.
     */
    function MySQLCursor($result,$dataSource)
    {
        $blankrecord = array();
        $this->Cursor($blankrecord);
        $this->result =& $result;
        $this->dataSource =& $dataSource;
    }

    /**
     * The MySQL result resource.
     * var resource $result
     */
    var $result;

    /**
     * The MySQL dataSource that handling the query for this Cursor.
     * var MySQLDataSource $dataSouce
     */
    var $dataSource;

    /**
     * Wrap the next object in the result set and answer $this.
     * @return MySQLCursor $this, after wrapping the next object in the
     * result set.  If there are no more objects in the result set, FALSE
     * is returned.  For an update, insert, delete, or
     * replace query, the fields in the returned array are: affected-rows,
     * and generated-id.  If the row count is 0, then FALSE is returned.
     */
    function &getNext()
    {
        // if this is a cursor from a select query
        if ( is_resource($this->result) )
        {
            $this->set(mysql_fetch_array($this->result, MYSQL_ASSOC));
            if ( !$this->atsumi_core )
            {
                $rv = FALSE;
                return $rv;
            }
            else return $this;
        }

        // else if this is a cursor from an update, delete, insert, or
        // replace query.
        else if ( $this->result )
        {
            $affected = mysql_affected_rows($this->dataSource->myLink);
            if ( $affected >= 0 )
            {
                $this->set($affected, "affected-rows");
                if ( $affected > 0 )
                {
                    $this->set(mysql_insert_id($this->dataSource->myLink),
                            "generated-id");
                }
                return $this;
            }
            else return FALSE;
        }
        else
        {
            $this->error( "Results indicate query failed: ".
                    print_r($this, TRUE));
            $this->fatal( "Results indicate query" );
            return FALSE;
        }
    }

}
?>
