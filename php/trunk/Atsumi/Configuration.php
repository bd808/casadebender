<?php
/**
 * Defines an object class that may be used to configure other objects.
 *
 * @package Atsumi
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import our parent class definition. */
require_once 'classes/Atsumi/AtsumiObject.php';

/** Import the logger. */ 
require_once 'classes/Atsumi/AtsumiLogger.php';

/**
 * Atsumi configuration object class.
 *
 * <p>Configuration wraps another object or array, allowing defaults to
 * be specified inside the Atsumi application by editing the core without
 * clobbering any existing values.
 *
 * <p>One benefit of this is that a non-Atsumi application script (or top
 * level include such as config.php) may override the default values for an
 * Atsumi object's configuration before including its class' source.
 * Therefore, the same top-level config may be used for several
 * applications without forcing the non-Atsumi applications to source a
 * bunch of class files they aren't going to use.
 *
 * <p>Another benefit is that the configuration may be specified in
 * different ways without changing the interface through which the
 * configuration is used.  This allows Atsumi objects to scale to larger
 * installations by allowing configuration to be specified
 * programmatically, or by using a ConfigurationLoader (TODO: prove it) to
 * configure objects from a data source.
 *
 * @package Atsumi
 */
class Configuration extends AtsumiObject {

    /**
     * Constructor.
     * @param mixed $initialConfig The external configuration object or array
     * to use for setting up initial values.
     */
    function Configuration(&$initialConfig)
    {
        $this->AtsumiObject($initialConfig);
    }

    /**
     * Set a default.
     * <p>This works just like set, except that if a value already exists
     * for $index it is ignored.
     * @param mixed $value The value of the configuration option.
     * @param mixed $index The name of the configuration option.
     * @param bool $clobber_null TRUE if an existing value of NULL should
     * be treated as if the value does not exist.  FALSE (the default) will
     * not override a NULL value if $index is found in the core.
     */
    function setDefault($value, $index=NULL, $clobber_null=FALSE)
    {
        if ( !$this->has($index) ||
                ($clobber_null && is_null($this->get($index))) )
        {
            $this->set($value,$index);
        }
    }

}

/**
 * Mutable configuration for introspecting an initial configuration without
 * creating a bunch of configuration objects.
 * @global Configuration $GETCONFIGURATION_HELPER
 */
$helper_ic = NULL;
$GETCONFIGURATION_HELPER = new Configuration($helper_ic);

/**
 * Get a configuration object.
 * @param mixed $initialConfig The external configuration object or array to
 * use for setting up initial values.  May be an existing Configuration
 * object, in which case no other is constructed.
 */
function &GetConfiguration($initialConfig=NULL)
{
    return GetConfigurationRef($initialConfig);
}

/**
 * Get a configuration object.
 * @param mixed $initialConfig The external configuration object or array to
 * use for setting up initial values.  May be an existing Configuration
 * object, in which case no other is constructed.
 */
function &GetConfigurationRef(&$initialConfig)
{
    global $GETCONFIGURATION_HELPER;

    if ( is_a($initialConfig,"AtsumiObject") )
    {
        $chelp = $initialConfig;
    }
    else
    {
        $GETCONFIGURATION_HELPER->setref($initialConfig);
        $chelp = $GETCONFIGURATION_HELPER;
    }

    if ( $chelp->has('configuration-class') )
    {
        $configclass = $chelp->get('configuration-class');
    }
    else
    {
        $configclass = 'Configuration';
    }

    if ( !class_exists($configclass) )
    {
        if ( IsLogEnabled('ERROR') ) LogError(
                "Configuration class $configclass is not defined:\n".
               print_r($initialConfig, TRUE));
        LogFatal("Invalid configuration");
        $rv = FALSE;
        return $rv;
    }
    else
    {
        if ( is_a($initialConfig, $configclass) )
        {
            $rv =& $initialConfig;
        }
        elseif ( is_a($initialConfig, 'Configuration') )
        {
            $ic =& $initialConfig->get();
            $rv = new $configclass($ic);
        }
        else        
        {
            $rv = new $configclass($initialConfig);
        }
        if ( !is_a($rv, 'Configuration') ) {
            if ( IsLogEnabled('ERROR') ) LogError(
                    "$configclass is not a Configuration subclass:\n".
                    print_r($initialConfig, TRUE));
            LogFatal("Invalid configuration");
            $rv = FALSE;
        }
        return $rv;
    }

}

?>
