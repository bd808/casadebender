<?php
/**
 * Defines a data cursor class for use with a plain indexed array.
 *
 * @package Chunsu
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import our parent class definition */
require_once "classes/Chunsu/Cursor.php";

/**
 * Cursor class for use with a plain indexed array.
 * @package Chunsu
 */
class ArrayCursor extends Cursor {

    /**
     * Constructor.
     * @param array $result An indexed array with the data to step
     * through.
     * @param DataSource $dataSource The data source that ran the
     * query, if applicible.
     */
    function ArrayCursor(&$result,&$dataSource)
    {
        $this->Cursor($icore=NULL);
        $this->result =& $result;
        $this->dataSource =& $dataSource;
    }

    /**
     * The array to step through.
     * var array $result
     */
    var $result;

    /**
     * The dataSource that handled the query for this Cursor.
     * var DataSource $dataSouce
     */
    var $dataSource;

    /**
     * Wrap the next object in the array and answer $this.
     * @return ArrayCursor $this, after wrapping the next object in the
     * array.  If there are no more objects in the array, FALSE
     * is returned.
     */
    function &getNext()
    {
        $nx = each($this->result);
        if ( !$nx ) 
        {
            $this->set(NULL);
            $nx = FALSE;
            return $nx;
        }
        else
        {
            $this->setref($nx['value']);
            return $this;
        }
    }

}
?>
