<?php
/**
 * Defines the a superclass for service-type controller objects.
 *
 * @package Atsumi
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import the logging support object. */
require_once "classes/Atsumi/AtsumiLogger.php";

/** Import the configuration definition class */
require_once "classes/Atsumi/Configuration.php";

/**
 * Superclass for service-type controller objects.
 * <p>An AtsumiService is an object that performs a cohesive set of
 * parameterized services that may be configured on the fly via
 * user-defined configuration parameters.
 * @abstract
 * @package Atsumi
 */
class AtsumiService {

    /**
     * This configuration object for this service.
     * @var mixed $atsumi_core
     */
    var $config;

    /**
     * Initialize this service's configuration.
     * @param mixed $config The Configuration object or core representation
     * for configuring this servce.
     */
    function init(&$config)
    {
        $this->config =& GetConfigurationRef($config);
    }

    /**
     * Constructor.
     * @param mixed $core The core object.
     */
    function AtsumiService(&$config)
    {
        $this->init($config);
    }

}

/**
 * Create an AtsumiService object from a configuration.
 * @param mixed $config Either a Configuration object or something else
 * that may be used as the core representation of a Configurtion.
 * @param string $targetClass The class of service to be created.  Target
 * class should be a subclass of AtsumiService, but a superclass of the
 * object returned by GetService.  The class of the returned object is
 * specified in the configuration as 'service-class'.
 * @return AtsumiServie A service implementation.  Returns FALSE if the
 * service could not be created and the fatal error handler doesn't kill
 * the script.
 */
function GetService(&$config,$targetClass)
{
    if ( !class_exists($targetClass) )
    {
        if ( IsLogEnabled('ERROR') ) LogError(
                "Target class $targetClass does not exist\n".
                print_r($config,TRUE) );
        LogFatal("Invalid service target class");
        return FALSE;
    }

    $cf =& GetConfigurationRef($config);
    $serviceclass = $cf->get( 'service-class' );

    if ( !$cf->has('service-class') )
    {
        if ( IsLogEnabled('ERROR') ) LogError(
                "Configuration does not specify a service class:\n".
                "Please set the 'service-class' configuration option.\n".
                "Target: $targetClass\n".
                print_r($cf,TRUE) );
        LogFatal("Invalid service configuration");
        return FALSE;
    }
    elseif ( !class_exists($serviceclass) )
    {
        if ( IsLogEnabled('ERROR') ) LogError(
                "Serivce class $serviceclass is not defined:\n".
                "Target: $targetClass\n".
                print_r($cf, TRUE));
        LogFatal("Invalid service configuration");
        return FALSE;
    }
    else
    {
        $rv = new $serviceclass($cf);
        if ( !is_a($rv, $targetClass) )
        {
            if ( IsLogEnabled('ERROR') ) LogError(
                    "Configuration does not specify a service class:\n".
                    "Class $serviceclass is not a subclass of $targetClass.\n".
                    print_r($rv,TRUE).print_r($cf,TRUE) );
            LogFatal("Invalid service configuration");
            return FALSE;
        }
        elseif ( !is_a($rv, 'AtsumiService') )
        {
            if ( IsLogEnabled('ERROR') ) LogError(
                    "Configuration does not specify a service class:\n".
                    "Class $targetClass is not a subclass of AtsumiService.\n".
                    print_r($rv,TRUE).print_r($cf,TRUE) );
            LogFatal("Invalid service configuration");
            return FALSE;
        }
        else
        {
            return $rv;
        }
    }
}
