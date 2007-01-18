<? // config.php

/**
 * <p>Global logging definition.  This must be defined before attempting to
 * load any Atsumi-based objects, or the logger will set itself up with a
 * default configuration (debug level 5, log output via error_log()).
 * @global array $ATSUMI_LOGCONFIG
 */
$ATSUMI_LOGCONFIG = array(
                'logfile' => "$GTLHOME/debuglog" ,
                'fatal-handler' => 'PrintFatalError' ,
                'detail' => 5
        );

/** Setup the generic data source object  */
require_once 'classes/Chunsu/DataSource.php';

/** Setup the MySQL data source object */
require_once 'classes/Chunsu/MySQLDataSource.php';

/**
 * Set up MySQL connection for persistent objects.
 */
$CLIENT_DSCONFIG = array (
        'service-class' => 'MySQLDataSource',
        'database' => 'mydb',
        'username' => 'mydbuser'
        );
ConfigureDataSource('client',$CLIENT_DSCONFIG);

?>
