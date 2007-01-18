<?php
/**
 * Defines the top-level superclass AtsumiObject.
 *
 * @package Atsumi
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import the logging support object. */
require_once "classes/Atsumi/AtsumiLogger.php";

/**
 * Superclass for Atsumi-based objects.
 * <p>An AtsumiObject is a fancy any-other-datatype refence that provides a
 * first-class object interface to either an atom, an array, or an object -
 * these things are so similar in PHP that we don't even care which!
 * @abstract
 * @package Atsumi
 */
class AtsumiObject {

    /**
     * This is the data inside a wrapper object.
     * @var mixed $atsumi_core
     */
    var $atsumi_core;

    /**
     * A dummy object for performing safe operations on the core.
     * @var AtsumiObject $dummy
     */
    var $dummy;

    /**
     * Constructor.
     * @param mixed $core The core object.
     */
    function AtsumiObject(&$core)
    {
        $this->atsumi_core =& $core;
    }

    /**
     * Get a copy of this object (with the core copied as well).
     */
    function copyObject()
    {
        $rv = serialize($this);
        return unserialize($rv);
    }

    /**
     * Is this a wrapper object?
     * @return bool TRUE if this is a wrapper object, FALSE if get/set/has
     * etc refer to this object itself.
     */
    function isWrapper()
    {
        return !is_null($this->atsumi_core);
    }

    /**
     * Get the core of this object.
     * @return mixed The core of this object, or this itself if it is not a
     * wrapper.
     */
    function &getCore()
    {
        if ( $this->isWrapper() )
	    return $this->atsumi_core;
	else
	    return $this;
    }

    /**
     * Get data from the core.
     * @param mixed $index Index in the array, or field in the object to
     * refer to the data.  If NULL, refers to the core data itself.
     * @return mixed Data from the core that $index refers to.
     */
    function &get($index=NULL)
    {
        $core =& $this->getCore();

        // No index, get the core itself
        if ( is_null($index) ) return $core;

        // We're wrapping an array, so refer to a key in the array
        if ( is_array($core) && array_key_exists($index,$core) )
        {
            return $core[$index];
        }

        // We're (wrapping) an object
        if ( is_object($core) )
        {
            $getmethod = "get_$index";
            if ( method_exists($core,$getmethod) )
            {
                // call the method to get the value
                $rv = $core->$getmethod();
                return $rv;
            }
            else if ( array_key_exists($index,$core) )
            {
                // otherwise, return the value of the field
                return $core->$index;
            }
        }

        // Nothing else can be referenced by index, so return NULL
        $rv = NULL;
        return $rv;
    }

    /**
     * Set data in the core.
     * @param mixed $value Data to associated in the core by $index.
     * @param mixed $index Index in the array, or field in the object to
     * refer to the data.  If NULL, the core itself with be clobbered with
     * $value.
     */
    function set($value, $index=NULL)
    {
        $this->setref($value,$index);
    }

    /**
     * Set data in the core.
     * @param mixed $value Data to associated in the core by $index.
     * @param mixed $index Index in the array, or field in the object to
     * refer to the data.  If NULL, the core itself with be clobbered with
     * $value.
     */
    function setref(&$value, $index=NULL)
    {
//        if ( is_a($value,"AtsumiObject") )
//        {
//            $setval =& $value->getCore();
//        }
//        else
//        {
            $setval =& $value;
//        }

        // No index, set the core itself
        if ( is_null($index) ) 
        {
            $this->atsumi_core =& $setval;
        }
        else
        {
            // Get a handle to the core
            $core =& $this->getCore();

            // We're wrapping an array, so refer to a key in the array.
            if ( is_array($core) ) $core[$index] =& $setval;

            // We're (wrapping) an object, so refer to a field in the
            // object.
            if ( is_object($core) ) 
            {
                $setmethod = "set_$index";
                if ( method_exists($core, $setmethod) )
                {
                    // for a method, call it with the value
                    $core->$setmethod($setval);
                }
                else
                {
                    // otherwise, set a field
                    $core->$index =& $setval;
                }
            }

            // Nothing else can be referenced by index, so do nothing else.
        }
    }

    /**
     * Check the core for a data item.
     * @param mixed $index Index in the array, or field in the object to
     * refer to the data.
     * @return bool TRUE if the core has the specified index or field, or if
     * $index is NULL.  Otherwise, FALSE.
     */
    function has($index=NULL)
    {
        if ( is_null($index) ) return TRUE;

        $core =& $this->getCore();
        if ( is_array($core) )
        {
            return array_key_exists($index,$core);
        }

        if ( is_object($core) )
        {
            return array_key_exists($index,$core) ||
                    method_exists($core,"get_$index");
        }

        return FALSE;
    }

    /**
     * Log a debug message.
     * @param string $message The message.
     */
    function debug($message)
    {
        LogDebug($message,$this);
    }

    /**
     * Log an info message.
     * @param string $message The message.
     */
    function info($message)
    {
        LogInfo($message,$this);
    }

    /**
     * Log a warning message.
     * @param string $message The message.
     */
    function warn($message)
    {
        LogWarning($message,$this);
    }

    /**
     * Log an error message.
     * @param string $message The message.
     */
    function error($message)
    {
        LogError($message,$this);
    }

    /**
     * Log a fatal message.
     * @param string $message The message.
     */
    function fatal($message)
    {
        LogFatal($message,$this);
    }

    /**
     * Get a dummy object for performing safe operations on the core.
     */
    function &getDummy()
    {
        if ( is_null($this->dummy) )
        {
            $this->dummy = GetAtsumiObject( );
        }
        $core =& $this->getCore();
        $this->dummy->atsumi_core =& $core;
        return $this->dummy;
    }

    /**
     * Print the object for debugging purposes.
     */
    function trace()
    {
        $rv = 'Atsumi('.get_class($this).") {";
        $ik = $this->getIndexKeys();
        if ( !is_null($ik) )
        {
            $ao = new AtsumiObject($this->getCore());
            foreach ( $ik as $index )
            {
                $rv .= "\n  $index -> ".$ao->get($index);
            }
        }
        $rv.= "\n}\n";
        return $rv;
    }

    /**
     * Get the valid index keys for this object.
     * @return array List of valid index keys for this object.  NULL if the
     * core is atomic, meaning that NULL is the only valid key.
     */
    function getIndexKeys()
    {
        $core =& $this->getCore();
        if ( is_array($core) ) return array_keys($core);
        // TODO: also include get_... methods for objects
        if ( is_object($core) ) return array_keys(get_object_vars($core));
        return NULL;
    }

}

/**
 * Get an AtsumiObject without a reference.
 * @param mixed $core The core object.
 */
function GetAtsumiObject($core=null)
{
    return new AtsumiObject($core);
}
 
