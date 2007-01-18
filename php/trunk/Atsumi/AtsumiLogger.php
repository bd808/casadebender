<?php
/**
 * Defines a general purpose logging class for supporting AtsumiObject
 * and some functions to access logging functinality without an
 * AtsumiObject reference.
 *
 * @package Atsumi
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import the Configuration class for configuring the logger. */
require_once "classes/Atsumi/Configuration.php";

/**
 * Log configuration specified outside the scope of this class definition.
 * @global mixed $ATSUMI_LOGCONFIG
 */
$ATSUMI_LOGCONFIG =& $ATSUMI_LOGCONFIG; # <-- trick phpdoc

/**
 * Log level definitions.
 * <p>Valid log levels are FATAL, ERROR, WARN, INFO, and DEBUG.
 * @global array $LOGLEVELS
 */
$LOGLEVELS = array(
        'FATAL' => array( 'detail-level' => 1, 'php-errno' => E_ERROR ),
        'ERROR' => array( 'detail-level' => 2, 'php-errno' => E_USER_ERROR ),
        'WARN' => array( 'detail-level' => 3, 'php-errno' => E_USER_WARNING ),
        'INFO' => array( 'detail-level' => 4, 'php-errno' => E_USER_NOTICE ),
        'DEBUG' => array( 'detail-level' => 5, 'php-errno' => E_USER_NOTICE )
        );

/**
 * PHP error type definition descriptions and mappings to Atsumi log
 * levels.
 * @global array $PHP_ERRORTYPES
 */
$PHP_ERRORTYPES = array (
        E_ERROR           => array( 'ERROR', 'PHP runtime error' ),
        E_WARNING         => array( 'WARN', 'PHP runtime warning' ),
        E_PARSE           => array( 'ERROR', 'PHP compile error' ),
        E_NOTICE          => array( 'DEBUG', 'PHP pedantic warning' ),
        E_CORE_ERROR      => array( 'ERROR', 'PHP initialization error' ),
        E_CORE_WARNING    => array( 'WARN', 'PHP initialization warning' ),
        E_COMPILE_ERROR   => array( 'ERROR', 'Zend compile error' ),
        E_COMPILE_WARNING => array( 'WARN', 'Zend compile warning' ),
        E_USER_ERROR      => array( 'ERROR', 'Runtime error' ),
        E_USER_WARNING    => array( 'WARN', 'Runtime warning' ),
        E_USER_NOTICE     => array( 'INFO', 'Runtime notice' )
        //E_STRICT          => array( 'DEBUG', 'Strict pedantic warning' )
        );

/**
 * Wraps logging operations for Atsumi objects and applications.
 * @package Atsumi
 */
class AtsumiLogger {

    /**
     * Logging configuration object.
     *
     * <p>These configuration options are read by all instances of the
     * AtsumiLogger class.  Options are:
     *
     * <pre>
     *   fatal-handler - callback to a function that will perform extra
     *   fatal error handling, such as sending an email to the server
     *   admin.
     *   
     *   detail - a number from 0 to 9 determining the verbosity of
     *   the error messages (default is 5):
     *     0 - Nothing is logged.  However, any messages sent at the
     *     FATAL level are still sent to the function defined by
     *     fatal-handler. 
     *     1 - FATAL messages only.
     *     2 - FATAL, and ERROR messages.
     *     3 - FATAL, ERROR, and WARN messages.
     *     4 - FATAL, ERROR, WARN, and INFO messages.
     *     5 - All messages, including DEBUG.
     *     6 - Same as 5, plus execution back trace is also logged.
     *     7 - Same as 5, plus $submitter dump is also logged.
     *     8 - Same as 5, plus back trace and $submitter dump.
     *     9 - Same as 8, plus a dump of the system environment.
     *
     *   logfile - full path to the atsumi log file. Depending on the
     *   specific logging implementation, this option may be irrelevant
     *   (i.e. for an inline logger).  If null, log output is sent to the
     *   system logging mechanism.
     * </pre>
     *
     * @var Configuration $config
     */
    var $config;

    /**
     * The log file, specified by the 'logfile' configuration option,
     * opened for append.
     * @var resource $logfile
     */
    var $logfile;

    /**
     * Constructor.
     * @param mixed $config An object, array, or Configuration to sets up
     * initial log configuration.  If not supplied, the global
     * $ATSUMI_LOGCONFIG is used.
     */
    function AtsumiLogger(&$config)
    {
        global $ATSUMI_LOGCONFIG;
        $this->config =& GetConfigurationRef($config);
        $this->config->setDefault(5,'detail');
    }

    /**
     * Ensure that the log file is open.
     */
    function openLogFile()
    {
        if ( !is_resource($this->logfile) )
        {
            $logfile =& $this->config->get('logfile');
            if ( !$this->logfile = fopen($logfile,'a') )
            {
                if ( !$this->handleFatal("Cannot open log file $logfile!") )
                {
                    echo "FATAL! Cannot open log file $logfile for append\n";
                }
                die;
            }
            register_shutdown_function(array($this,'closeLogFile'));
        }
    }

    /**
     * Flush and close the log file.
     */
    function closeLogFile()
    {
        if ( is_resource($this->logfile) )
        {
            fflush($this->logfile);
            fclose($this->logfile);
            $this->logfile = NULL;
        }
    }

    /**
     * Generate a log message.
     * @param string $level The log level string.
     * @param mixed $message The log message.  If not a string, will
     * print_r $message.
     * @param object $submitter The object requesting log submission.
     * @return string A log message.
     */
    function format($level,$message,$submitter=null)
    {
        if ( is_null($submitter) ) $submitter =& $this;
        $class = get_class($submitter);
        $time = date('Y-m-d H:i:s');
        $msg = "$time $class $level ";

        if ( is_string($message) ) $msg .= $message;
        else $msg .= print_r($message,TRUE);
        $msg .= "\n";

        $detail = $this->config->get('detail');
        if ( $detail == 6 || $detail >= 8 )
        {
            $msg .= "backtrace:\n";
            foreach ( debug_backtrace() as $bte )
            {
                if ( array_key_exists('class',$bte) )
                {
                    $msg .= $bte['class'].$bte['type'];
                }
                $msg .= $bte['function'].'()';
                if ( array_key_exists('file',$bte) )
                {
                    $msg .= ' ['.$bte['file'];
                    if ( array_key_exists('line',$bte) )
                        $msg .= ':'.$bte['line'];
                    $msg .= "]\n";
                }
            }
            $msg .= "\n";
        }
        if ( $detail >= 7 )
        {
            $msg .= "submitter:\n".print_r($submitter,TRUE);
        }
        if ( $detail == 9 )
        {
            $msg .= "\n_REQUEST: ".print_r($_REQUEST,TRUE);
            $msg .= "\n_SERVER: ".print_r($_SERVER,TRUE);
            $msg .= "\n_ENV: ".print_r($_ENV,TRUE)."\n";
        }
        return $msg;
    }

    /**
     * Handle a formatted log message.
     * <p>If logfile is defined, then that file is appended.  Otherwise,
     * the PHP system logging mechanism is used.
     * @param string $message Formatted log message to handle.
     */
    function handle($message)
    {
        // notice that error_log() is not preferred because apache munges
        // the log output and makes any formatted message with a newline
        // practically unreadable.
        if ( $this->config->has('message-handler') )
        {
            $mh = $this->config->get('message-handler');
            if ( function_exists($mh) )
            {
                $mh($message);
            }
            else
            {
                error_log("Invalid message handler $mh\n".
                        "unhandled message: $message" );
            }
        }
        elseif ( $this->config->has('logfile') )
        {
            $this->openLogFile();
            fwrite($this->logfile,$message);
        }
        else
        {
            error_log($message);
        }
    }

    /**
     * Handle a fatal log message.
     * @param string $message Formatted log message to handle.
     * @return bool FALSE if the fatal message was not handled and further
     * reporting should be done.
     */
    function handleFatal($message)
    {
        $fh =& $this->config->get('fatal-handler');
        if ( function_exists($fh) )
        {
            return $fh($message);
        }
        else return FALSE;
    }

    /**
     * Submit a log message.
     * @param int $level The log level, and index in log-levels.
     * @param mixed $message The message to submit.
     */
    function submit($level,$message,$submitter=null)
    {
        if ( ($level != 'FATAL' || !$this->handleFatal($message)) &&
                $this->isEnabled($level) )
        {
            $this->handle($this->format($level,$message,$submitter));
        }
    }

    /**
     * Is logging enabled at the specified level in this logger?
     * @param string $level The logging level.
     * @return bool TRUE if logging is enabled, FALSE otherwise.
     */
    function isEnabled($level)
    {
        global $LOGLEVELS;
        if ( !array_key_exists($level,$LOGLEVELS) ) $level = 'DEBUG';
        $lv =& $LOGLEVELS[$level];
        return ( $lv['detail-level'] <= $this->config->get('detail') );
    }

    /**
     * Submit a PHP log message through this logger.
     * @param int $errno The PHP error number.
     * @param string $errmsg The error message.
     * @param string $filename The file the message originated in.
     * @param string $linenum The line number in the file the message
     * originated in.
     */
    function submitFromPHP ($errno, $errmsg, $filename, $linenum) {
        global $PHP_ERRORTYPES;

        if ( array_key_exists($errno, $PHP_ERRORTYPES) )
        {
            $errorType = $PHP_ERRORTYPES[$errno];
        }
        else
        {
            LogWarning( "Unknown PHP error type $errno, assuming E_ERROR" );
            $errorType = $PHP_ERRORTYPES[E_ERROR];
        }

        $this->submit( $errorType[0], "$errno ".
                $errorType[1]." ($filename:$linenum): $errmsg" );
    }

}

/**
 * The default logger object that is used by LogDebug, LogInfo, etc. and is
 * also used to log error messages from PHP.
 * @global AtsumiLogger $DEFAULT_LOGGER
 */
$DEFAULT_LOGGER = new AtsumiLogger($ATSUMI_LOGCONFIG);
set_error_handler(array(&$DEFAULT_LOGGER,"submitFromPHP"));

/**
 * Get the default logging object used by FatalLog, ErrorLog, etc to send
 * log messages without an AtusmiObject.  In fact, AtsumiObject wraps uses
 * these functions.
 * @return The default logging object.
 */
function &GetDefaultLogger()
{
    global $DEFAULT_LOGGER;
    return $DEFAULT_LOGGER;
}

/**
 * Check to see if logging is enabled at a specific level.
 * @param string $level The log level.
 */
function IsLogEnabled($level)
{
    $log =& GetDefaultLogger();
    return $log->isEnabled($level);
}

/**
 * Log a fatal error.
 * @param mixed $message The fatal error message.
 * @param object $submitter The object submitting the log message.
 */
function LogFatal($message,$submitter=null)
{
    $log =& GetDefaultLogger();
    $log->submit('FATAL',$message,$submitter);
}

/**
 * Log an error.
 * @param mixed $message The error message.
 * @param object $submitter The object submitting the log message.
 */
function LogError($message,$submitter=null)
{
    $log =& GetDefaultLogger();
    $log->submit('ERROR',$message,$submitter);
}

/**
 * Log a warning.
 * @param mixed $message The warning message.
 * @param object $submitter The object submitting the log message.
 */
function LogWarning($message,$submitter=null)
{
    $log =& GetDefaultLogger();
    $log->submit('WARN',$message,$submitter);
}

/**
 * Log an informational message.
 * @param mixed $message The info message.
 * @param object $submitter The object submitting the log message.
 */
function LogInfo($message,$submitter=null)
{
    $log =& GetDefaultLogger();
    $log->submit('INFO',$message,$submitter);
}

/**
 * Log a debug message.
 * @param mixed $message The debug message.
 * @param object $submitter The object submitting the log message.
 */
function LogDebug($message,$submitter=null)
{
    $log =& GetDefaultLogger();
    $log->submit('DEBUG',$message,$submitter);
}

?>
