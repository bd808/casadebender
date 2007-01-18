<?php
/**
 * Defines a class for building SQL statements from meta-data.
 * @package Chunsu
 */

/** Import our parent class definition */
require_once "classes/Atsumi/Configuration.php";

/** Import a support class for configuring joined tables */
require_once "classes/Chunsu/JoinedTable.php";

/** Import a support class for configuring query fields */
require_once "classes/Chunsu/QueryField.php";

/**
 * Builds SQL statements from meta-data.
 * @author Mark Fyffe <buckyyowza@gmail.com>
 * @package Chunsu
 */
class SQLBuilder extends Configuration {

    /**
     * Constructor.
     *
     * <pre>SQLBuilder configuration options are:
     *
     *  - table: The name of the first table in the where clause.
     *
     *  - joined-tables: Defines tables that are joined to the first table
     *  or to other joined tables. At least one joined-table must be joined
     *  to the first table, and there also must be a path from each joined
     *  table to the first table.  See the JoinedTable class for more
     *  information about joined-tables entries.
     *
     *  - fields: Defines fields with usage depending on field type.  See
     *  the QueryField class for more information about query fields.
     *
     *  - filter : arbitrary SQL where clause fragment for extra filtering
     *  criteria.  Aliases of fields defined in the SQLBuilder will be
     *  found and replaced with an auto-generated table alias followed by
     *  the field name.
     *
     *  Some configuration options are derived at initialization time, and
     *  should not be set manually.  These may be referred to anytime after
     *  the build has been initialized:
     *
     *  - primary-key : QueryField object describing the primary key field.
     *  - read-fields : Associative array (by alias) of fields that
     *  correspond to data in the database (all types but 'criteria').
     *
     *  </pre>
     *
     * @param $config The core configuration object.
     */
     function SQLBuilder(&$config)
     {
         $this->Configuration($config);

         if ( $this->has( 'tables' ) )
         {
             $joined_tables =& $this->get('tables');
             $newtables = array();
             $this->setref($newtables,'joined-tables');
             $mytable = $this->get('table');
             foreach ( array_keys($joined_tables) as $key )
             {
                 $jt =& $joined_tables[$key];
                 if ( $jt->get('name') != $mytable )
                 {
                     $this->addJoinedTable($jt);
                 }
             }
         }

         // TODO: don't clobber config data.  place query field objects
         // into a new associative array and make primary-key/read-fields
         // etc lists of the appropriate field aliases.
         if ( $this->has( 'fields' ) )
         {
             $this->set(array(),'read-fields');
             $this->set(array(),'write-fields');
             $this->set(array(),'foreign-keys');
             $query_fields =& $this->get('fields');
             $this->set(array(),'query-fields');
             foreach ( array_keys($query_fields) as $key )
                     $this->addQueryField($query_fields[$key]);
         }

         // ensure that all required configuration has been supplied
         if ( !$this->has('primary-key') ) 
         {
             $this->error( "No primary key specified: ".
                     print_r($config,TRUE) );
             $this->fatal( "No primary key specified" );
         }
     }

     /**
      * Get the joined tables in this builder.
      * @return array An indexed array of joined tables from this builder,
      * NULL if none are defined.
      */
     function &getJoinedTables()
     {
         return $this->get('joined-tables');
     }

     /**
      * Add a joined table to this builder.
      * @param mixed $config The core configuration or JoinedTable object
      * for the joined table.
      */
     function addJoinedTable(&$config)
     {
         $joined_tables =& $this->get('joined-tables');
         if ( is_null($joined_tables) )
         {
             $joined_tables = array();
             $this->setref($joined_tables,'joined-tables');
         }
         $jtable =& GetJoinedTable($config);
         $jname = $jtable->get('name');
         if ( is_null($jname) || '' == $jname )
         {
             $this->fatal( "Invalid empty or null table name in ".
                     print_r($jtable,TRUE) );
         }

         $joined_tables[$jtable->get('name')] =& $jtable;
     }

     /**
      * Get a joined table object from this builder by table name.
      * @param string $name The name of the joined table.
      * @return JoinedTable The joined table configuration if defined, NULL
      * if $name refers to the primary table, and FALSE if $name does not
      * refer to a table defined by this builder.
      */
     function &getJoinedTable($name)
     {
         if ( $name == $this->get('table') ) return NULL;
         if ( !$this->has('joined-tables') ) return FALSE;
         $joined_tables =& $this->get('joined-tables');
         if ( !array_key_exists($joined_tables,$name) ) return FALSE;
         return $joined_tables[$name];
     }

     /**
      * Add a query field to this builder.
      * @param mixed $config The core configuration or QueryField object
      * for the query field.
      */
     function addQueryField(&$config)
     {
         $query_fields =& $this->get('query-fields');
         $readfields =& $this->get('read-fields');
         $writefields =& $this->get('write-fields');
         $foreignkeys =& $this->get('foreign-keys');

         $qf = GetQueryField($config);
         $query_fields[] =& $qf;
         $qtype = $qf->get('type');
         $falias = $qf->get('alias');

         if ( is_null($qf->get('table')) )
         {
             $qf->set($this->get('table'),'table');
         }
         else
         {
             $tb = $qf->get('table');
             if ( !$this->hasTable($tb) )
             {
                 $jtconfig = array('name'=>$tb);
                 $this->addJoinedTable($jtconfig);
             }
         }

         if ( $qf->get('type') == 'foreign-key' )
         {
             $foreignkeys[$falias] =& $qf;
             if ( !$qf->has('related_field') &&
                     array_key_exists($falias,$readfields) )
             {
                 $qf->setref($readfields[$falias],'related-field');
             }
         }
         else
         {
             if ( array_key_exists($falias,$foreignkeys) )
             {
                 $fk =& $foreignkeys[$falias];
                 if ( $fk->has('related-field') )
                 {
                     $this->error(
                             'foreign key already has a related field: '.
                             print_r($fk->get('related-field'),TRUE).
                             ' error occured adding '.
                             print_r($qf,TRUE) );
                     $this->fatal( 'foreign key already has a related field' );
                 }
                 $fk->setref($qf,'related-field');
             }
         }

         // locate the primary key field
         if ( $qf->get('type') == 'primary-key' )
         {
             if ( $this->has('primary-key') )
             {
                 $rf =& $readfields[$falias];
                 if ( !$rf || $rf->get('type') != 'foreign-key' )
                 {
                     $this->error('Found multiple primary key '.
                             "field definitions.\nold: ".
                             print_r($this->get('primary-key'),TRUE).
                             "\nnew: ".print_r($qf,TRUE) );
                     $this->fatal('Found multiple primary keys.' );
                 }
                 else
                 {
                     $qf->set('criteria','type');
                     $qtype = 'criteria';
                 }
             }
             else $this->setref($qf,'primary-key');
         }

         // index read fields by alias
         if ( $qtype != 'criteria' )
         {
             if ($qtype != 'foreign-key' || !$qf->has('related-field'))
             {
                 $readfields[$falias] =& $qf;
             }
             if ($qtype != 'read-only' && $qtype != 'primary-key')
             {
                 if ( !array_key_exists($falias,$writefields) ||
                         !$writefields[$falias]->has('related-field') ||
                         $qf != $writefields[$falias]->get('related-field') )
                 {
                     $writefields[$falias] =& $qf;
                 }
             }
         }
     }

     /**
      * Get the list of query fields defined in this builder.
      * @return array An indexed array of all the query fields defined in
      * this builder.
      */
     function &getQueryFields()
     {
         return $this->get('query-fields');
     }

     /**
      * Get an associative array of read fields defined in this builder.
      * @return array An associative array of all the read fields defined in
      * this builder.
      */
     function &getReadFields()
     {
         return $this->get('read-fields');
     }

     /**
      * Get an associative array of write fields defined in this builder.
      * @return array An associative array of all the write fields defined in
      * this builder.
      */
     function &getWriteFields()
     {
         return $this->get('write-fields');
     }

     /**
      * Get the query fields defined by table name.
      * @param string $table The name of the table, NULL for all fields in
      * all tables.
      * @return array An indexed array of query fields defined in $table,
      * or all fields if $table is null.  FALSE is returned if
      * !$this->hasTable($table)
      */
     function getQueryFieldsByTable($table)
     {
         $qfs =& $this->getQueryFields();

         if ( is_null($table) ||
                 // also don't bother with the secondary index if there are
                 // no joined tables
                 (!$this->has('joined-tables') &&
                  $table == $this->get('table'))
                 ) return $qfs;

         if ( !$this->hasTable($table) ) return FALSE;
         $this->setDefault(array(),'query-fields-by-table');
         $qfbt =& $this->get('query-fields-by-table');
         if ( !array_key_exists($table,$qfbt) )
         {
             $qfbt[$table] = array();
             foreach ( array_keys($qfs) as $fk )
             {
                 if ( $table == $qfs[$fk]->get('table') )
                 {
                     $qfbt[$table][] =& $qfs[$fk];
                 }
             }
         }
         return $qfbt[$table];
     }

     /**
      * Get an indexed array of the keys fields needed to uniquely identify
      * a record in the given table.
      * @param string $table The name of the table, NULL for the list of
      * keys over the entire space defined by this builder.
      * @return array An indexed array of the field aliases naming the keys
      * needed to uniquely identify a record in $table, or FALSE if $table
      * is not defined by this builder.
      */
     function getKeyFields($table=NULL)
     {
         $pk =& $this->getPrimaryKey();
         if ( is_null($table) ||
                 ($table == $pk->get('table')) ) return array($pk);
         if ( !$this->hasTable($table) ) return FALSE;
         $this->setDefault(array(),'key-fields');
         $kfa =& $this->get('key-fields');
         if ( !array_key_exists($table,$kfa) )
         {
             $kfs = $this->get('foreign-keys');
             $kfa[$table] = array();
             foreach ( array_keys($kfs) as $fk )
             {
                 if ( $kfs[$fk]->has('related-field') )
                 {
                     $fkrf = $kfs[$fk]->get('related-field');
                     if ( $fkrf->get('table') == $table )
                     {
                         $kfa[$table][] =& $fkrf;
                     }
                 }
             }
         }
         return $kfa[$table];
     }

     /**
      * Get the primary key field defined in this builder.
      * @return QueryField The primary key field defined in
      * this builder.
      */
     function &getPrimaryKey()
     {
         return $this->get('primary-key');
     }

     /**
      * Get the foreign key fields defined in this builder.
      * @return array The foreign key fields defined in
      * this builder.
      */
     function &getForeignKeys()
     {
         return $this->get('foreign-keys');
     }

     /**
      * Check to see if this builder defines a table, either as the primary
      * table or a joined table.
      * @param string $table The name of the table to checkfor.
      * @return TRUE if $table is defined by this builder, FALSE if not.
      */
     function hasTable($table)
     {
         if ( $table == $this->get('table') ) return TRUE;
         if ( !$this->has('joined-tables') ) return FALSE;
         foreach ( $this->get('joined-tables') as $jt )
         {
             if ( $table == $jt->get('name') ) return TRUE;
         }
         return FALSE;
     }

     /**
      * Join an SQLBuilder configuration into this one.  Adds the builder's
      * primary table as one of my joined tables, and adds all of the read
      * fields as mine.  There must be at least one foreign key field in
      * my primary table that matches the alias of a primary-key field in
      * the builder's primary table.
      */
     function joinTo($builder,$critonly)
     {
         $pk =& $builder->getPrimaryKey();
         if ( !$pk ) 
         {
             $this->debug( 'No primary key in joined SQL builder: '.
                     $builder->trace() );
             $this->fatal( 'No primary key in joined SQL builder' );
         }

         $tfields =& $this->getReadFields();
         $falias = $pk->get('alias');
         $fk =& $tfields[$falias];
         if ( !$fk )
         {
             $this->debug( "No foreign key for $falias: ".
                     $this->trace() );
             $this->fatal( "No foreign key for $falias: " );
         }

         $jtname = $builder->get('table');
         $jt = array( "name" => $jtname );
         $this->addJoinedTable( $jt );

         $jf =& $builder->get('fields');
         foreach ( array_keys($jf) as $key )
         {
             $jfield = $jf[$key];
             $jfield['table']=$jtname;
             if ( $critonly && (!array_key_exists('type',$jfield) ||
                     $jfield['type'] != 'primary-key') )
             {
                 $jfield['type'] = 'criteria';
             }
             $this->addQueryField($jfield);
         }
     }

}
