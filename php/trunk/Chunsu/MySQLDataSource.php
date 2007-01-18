<?php
/**
 * Defines a data source implementation for MySQL.
 *
 * @package Chunsu
 * @author Mark Fyffe <buckyyowza@gmail.com>
 */

/** Import the Atsumi logging mechanism. */
require_once "classes/Atsumi/AtsumiLogger.php";

/** Import our parent class definition. */
require_once "classes/Chunsu/DataSource.php";

/** Import the concrete Cursor type for MySQL. */
require_once "classes/Chunsu/MySQLCursor.php";

/**
 * Provides an abstract interface to a remote data source.
 * @package Chunsu
 */
class MySQLDataSource extends DataSource
{

    /**
     * The MySQL database connection.
     */
    var $myLink;

    /**
     * Connect to the MySQL data source, looks for the following
     * configuration options.
     * <ul>
     *   <li>server - The hostname or IP address of the MySQL DB
     *   server.</li>
     *   <li>username - The username on the MySQL database.</li>
     *   <li>password - The password on the MySQL database.</li>
     * </ul>
     * @return TRUE is successful, FALSE otherwise (though this is
     * unlikely to occur because of the fatal error event when the
     * connection fails).
     */
    function connect ()
    {
        if ( !parent::connect() )
        {
            $username = $this->config->get('username');
            if ( !is_string($username) )
            {
                $username = get_current_user();
                $this->config->warn(
                        "MySQL username is not specified, using the user ".
                        "of the PHP process ($username).  This may be ".
                        "ambiguous, consider setting the 'username' ".
                        "configuration option." );
            }

            if ( !$this->myLink = mysql_connect( 
                    $this->config->get('server'),
                    $username,
                    $this->config->get('password')) )
            {
                LogFatal( "Unable to establish MySQL connetion: ".
                        mysql_error() );
                return FALSE;
            }

            $database = $this->config->get('database');
            if ( !is_string($database) )
            {
                $this->config->warn(
                        "MySQL database name is not specified, using the ".
                        "username ($username) as the database name.  This ".
                        "may be ambiguous, consider setting the 'database' ".
                        "configuration option." );
                $database = $username;
            }

            if ( !mysql_select_db($database,$this->myLink) )
            {
                LogFatal( "Unable to select MySQL database: ".
                        mysql_error() );
                return FALSE;
            }
        }
        return TRUE;
    }

    /**
     * Query the data source.
     * <p>This class makes no assumption about the content of the query
     * object, but the behavior of the resulting Cursor object should be
     * consistent between data source implementations.
     * @param mixed $query Some sort of query that will be understood by
     * the implemented data source.
     * @return Cursor The result of the query, FALSE otherwise (though this
     * is unlikely to occur because of the fatal error event when a query
     * fails).
     */
    function &query ($query)
    {
        if ( !$rv =& parent::query($query) )
        {
            $result = mysql_query($query,$this->myLink);
            if ( $result )
            {
                $rv = new MySQLCursor($result,$this);
            }
            else
            {
                LogError( "MySQL query failed: \n".$query."\n".mysql_error() );
                LogFatal( "MySQL query failed" );
                $rv = FALSE;
            }
        }
        return $rv;
    }

    /**
     * Close the connection to the MySQL data source.
     * <p>Not only does this happen automatically on shutdown, but MySQL
     * also closes open connetions on shutdown.  Only one will matter since
     * the other will notice that the connection is closed and not attempt
     * to close it again.  However, it is entirely unnecessary to call
     * shutodwn() from an application that uses a MySQL data source unless
     * you want to explicitly shut down the data source.
     * @return TRUE if successful, FALSE otherwise.
     */
    function shutdown ()
    {
        if ( !parent::shutdown() )
        {
            if ( !mysql_close($this->myLink) )
            {
                LogFatal( "Unable to shut down MySQL connection: ".
                        mysql_error() );
                return FALSE;
            }
        }
        return TRUE;
    }

}
?>
