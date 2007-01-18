<?php
/**
 * Defines an abstact superclass for implementing a data cursor.
 * 
 * @package Chunsu
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import our parent class definition. */
require_once "classes/Atsumi/AtsumiObject.php";

/**
 * Abstract database cursor class.  A cursor object acts as an iterator
 * over a result set in a data source.
 * @abstract
 * @package Chunsu
 */
class Cursor extends AtsumiObject
{
    /**
     * Get the next record in the result set.
     * @return mixed The next item in the result set, or FALSE if there are
     * no more items.
     */
    function &getNext ()
    {
        return FALSE;
    }
}
?>
