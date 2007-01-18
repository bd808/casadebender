<?php
/**
 * Defines a data source implementation for querying the user input sent
 * via HTTP request.
 *
 * @package Chunsu
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import the Atsumi logging mechanism. */
require_once "classes/Atsumi/AtsumiLogger.php";

/** Import our parent class definition. */
require_once "classes/Chunsu/DataSource.php";

/** Import the array cursor, to use as a result type. */
require_once "classes/Chunsu/ArrayCursor.php";

/**
 * Extract a field alias from an SQL chunk.
 */
function ExtractFieldAlias ($table,$field)
{
    $al=array();
    $field=trim($field);
    if (eregi(".*[ \t\n]+as[ \t\n]+(.*)",$field,$al) ||
            eregi(".*[ \t\n]+(.*)",$field,$al) ||
            eregi("$table\.(.*)",$field,$al)
       ) 
    {
        $alias=$al[1];
    }
    else
    {
        $alias=$field;
    }
    #LogDebug( "table: $table  field: $field  alias: $alias" );
    return trim($alias);
}

/**
 * Match an id to a provided value.  This is a straight equals (==)
 * comparison, except NULL, FALSE, and 0 all mean the same thing.
 */
function MatchID ($id1,$id2)
{
    if (($id1==0 || !$id1 || is_null($id1)) &&
            ($id2==0 || !$id2 || is_null($id2))) return TRUE;
    else return $id1==$id2;
}

/**
 * Extract a record from an array (from the request query).
 */
function ExtractRecordFromRequest ($table,$idfield,$idmatch,$fields,$req)
{
    #LogDebug( "idfield: $idfield   idmatch: $idmatch" );
    #LogDebug( $fields );
    #LogDebug( $req );
    if ( array_key_exists($idfield,$req) && MatchID($idmatch,$req[$idfield]) )
    {
        $rec=array();
        foreach ( $fields as $field )
        {
            // TODO: un-escape etc to make the data re-writable.
            if ( array_key_exists($field,$req) )
            {
                $rec[$field]=$req[$field];
            }
        }
        if ( $idmatch == 'new' || $idmatch == 0 ) $rec[$idfield]=NULL;
        if ( sizeof($rec)>0 ) return $rec;
        else return FALSE;
    }
    else return FALSE;
}

/**
 * Data source implementation for querying the user input sent via HTTP
 * request. Should understand as much SQL as SQLGenerator does so that the
 * same storage method can be used for this data source that is used for
 * 99% of the rest of the data source implementations.
 *
 * <pre>
 * Select queries seek user input in the following order:
 *  1. Matches entries in the indexed array of associative arrays sent in
 *  with the same name as the "table" in the from clause.  Only the entries
 *  in the array that match the ID criteria are considered.
 *  
 *  2. Matches an associative array with the same name as the "table" in
 *  the from clause as long as the ID criteria matches.
 *
 *  3. Uses $_REQUEST itself as long as the ID criteria matches.
 * </pre>
 *  
 * @package Chunsu
 */
class UserInputDataSource extends DataSource
{

    /**
     * "Connect" to the user input.
     * @return TRUE is successful, FALSE otherwise.
     */
    function connect ()
    {
        if ( !parent::connect() )
        {
            return !is_null($_REQUEST);
        }
        return TRUE;
    }

    /**
     * Query the user input.  Recognizes this minimal SQL statement form:
     *
     * select key1,key2,key3,etc
     * from assoc_array
     * where id_key = value
     *
     * TODO: Currently does not recognize update, insert, or delete queries
     * though a short leap of logic could imagine that update and insert
     * queries might thunk HTML output for displaying/editing a persistent
     * object.  I'm not sure that logic can leap so far as to delete a an
     * object from the user.
     *
     * @param string $query An SQL select statment of the format noted
     * above.
     * @return Cursor The result of the query, FALSE otherwise (though this
     * is unlikely to occur because of the fatal error event when a query
     * fails).
     */
    function &query ($query)
    {
        if ( !$rv =& parent::query($query) )
        {
            $parts=array();
            if (eregi("select(.*)from(.*)where(.*)",$query,$parts)) 
            {
                $fields=array();
                $table=trim($parts[2]);
                $where=trim($parts[3]);

                $wparts=array();
                if ( eregi("(.*)=(.*)",$where,$wparts) )
                {
                    #LogDebug( $wparts );
                    $idfield=ExtractFieldAlias($table,$wparts[1]);
                    $idmatch=trim($wparts[2]);
                    $tmp=array();
                    if (ereg("^\'(.*)\'$",$idmatch,$tmp))
                    {
                        $idmatch=$tmp[1];
                    }
                }
                elseif ( eregi("(.*) is null",$where,$wparts) )
                {
                    $idfield=ExtractFieldAlias($table,$wparts[1]);
                    $idmatch=NULL;
                }
                else
                {
                    LogError( "Invalid user input where clause: $query" );
                    LogFatal( "Invalid user input where clause" );
                }

                $gotid=FALSE;

                foreach (split(',',$parts[1]) as $field)
                {
                    $alias=ExtractFieldAlias($table,$field);
                    if ($idfield == $alias) $gotid=TRUE;
                    $fields[]=$alias;
                }
            }
            else 
            {
                LogError( "Invalid user input query: $query" );
                LogFatal( "Invalid user input query" );
            }

            $rv = array();
            if ( array_key_exists($table,$_REQUEST) &&
                    is_array($tin=$_REQUEST[$table]) )
            {
                #LogDebug( "--$idfield--" );
                #LogDebug( $tin );
                if ( array_key_exists($idfield,$tin) )
                {
                    if ( $rec=ExtractRecordFromRequest(
                                $table,$idfield,$idmatch,$fields,$tin) )
                        $rv[]=$rec;
                }
                else
                {
                    foreach ( $tin as $trow )
                    {
                        if ( $rec=ExtractRecordFromRequest(
                                    $table,$idfield,$idmatch,$fields,$trow) )
                            $rv[]=$rec;
                    }
                }
            }
            else
                if ( $rec=ExtractRecordFromRequest(
                            $table,$idfield,$idmatch,$fields,$_REQUEST) )
                    $rv[]=$rec;

        }
        $r=new ArrayCursor(&$rv,&$this);
        #LogDebug($r);
        return $r;
    }

    /**
     * Pretend to close the connection, though there is not connection to
     * close.
     * @return TRUE if successful, FALSE otherwise.
     */
    function shutdown ()
    {
        if ( !parent::shutdown() )
        {
            return !is_null($_REQUEST);
        }
        return TRUE;
    }

}
?>
