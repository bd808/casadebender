<?php
/**
 * Defines a class for building query field SQL fragments from meta-data.
 * @package Chunsu
 */

/** Import our parent class definition */
require_once "classes/Atsumi/Configuration.php";

/**
 * Builds query field SQL fragments from meta-data.
 * @author Mark Fyffe <buckyyowza@gmail.com>
 * @package Chunsu
 */
class QueryField extends Configuration {

    /**
     * Constructor.
     *
     * <pre>QueryField configuration options are:
     *
     * - name: The name of the field, as defined in the database.  May also
     * be an SQL expression.  Aliases of other fields defined in the
     * SQLBuilder will be found and replaced with an auto-generated table
     * alias followed by the field name.
     *
     * - alias: The name of the field, as defined in the result set.
     * default is the same as name.
     *
     * - table: The name of the table where the field may be found.
     * default is the first table in the sqlbuilder.
     *
     * - literal-type: One of string, int, decimal, date, datetime, or
     * boolean.  Defines how literals are formed when populating this field
     * by value.  May be null for automatic detection, which is the default
     * behavior.
     *
     * - type: One of... default is read-write
     *
     *   - read-only: the field is only present in the select and into
     *   clauses.
     *
     *   - read-write: the field is present in all select, set, and into
     *   clauses.
     *
     *   - criteria: the field is only present as specified in the where
     *   clause.
     *      
     *   - primary-key: same as read-only with automatic where clause
     *   criteria added to select and update statements.
     *      
     *   - foreign-key: same as read-only with automatic where clause
     *   criteria added to update statements that matches against the
     *   pulled value from a matching field found in joined-tables.
     *
     * </pre>
     *
     * @param $config The core configuration object.
     */
     function QueryField(&$config)
     {
         $this->Configuration($config);
         $this->setDefault($this->get('name'),'alias');
         $this->setDefault(NULL,'table');
         $this->setDefault(NULL,'literal-type');
         $this->setDefault('read-write','type');
     }

}

/**
 * Get a QueryField object.
 * @param mixed $initialConfig The external configuration object or array
 * to use for setting up initial values.  May be an existing QueryField
 * object, in which case no other is constructed.
 */
function &GetQueryField($initialConfig=NULL)
{
    return GetQueryFieldRef($initialConfig);
}

/**
 * Get a QueryField object.
 * @param mixed $initialConfig The external configuration object or array
 * to use for setting up initial values.  May be an existing QueryField
 * object, in which case no other is constructed.
 */
function &GetQueryFieldRef(&$initialConfig)
{
    if ( !is_a($initialConfig,'QueryField') )
    {
        $rv = new QueryField($initialConfig);
        return $rv;
    }
    return $initialConfig;
}

?>
