<?php
/**
 * Defines an dataset wrapper for builing SQL statements.
 * @package Chunsu
 */

/** Import our superclass definition. */
require_once( "classes/Atsumi/AtsumiObject.php" );

/**
 * Dataset wrapper for generating SQL code based on an SQLBuilder.
 * @author Mark Fyffe <buckyyowza@gmail.com>
 * @package Chunsu
 */
class SQLGenerator extends AtsumiObject
{
    /**
     * Converts a null literal to SQL code.
     */
    function getNullLiteral() 
    {
        return "null";
    }

    /**
     * Converts a string literal to SQL code.
     */
    function getStringLiteral($s) 
    {
        return "'".str_replace("'","''",$s)."'";
    }

    /**
     * Converts a integer literal to SQL code.
     */
    function getIntLiteral($i)
    {
        return (int) $i;
    }

    /**
     * Converts a decimal literal to SQL code.
     */
    function getDecimalLiteral($d)
    {
        return (float) $d;
    }

    /**
     * Converts a date literal to SQL code.
     */
    function getDateLiteral($dv)
    {
        // Short circuit for MySQL empty date
        if ( $dv == '0000-00-00' ) return "'$dv'";
        if ( is_string($dv) ) $dv = strtotime($dv);
        return "'".date('Y-m-d',$dv)."'";
    }
    
    /**
     * Converts a date and time literal to SQL code.
     */
    function getDateTimeLiteral($dv)
    {
        if ( is_string($dv) ) $dv = strtotime($dv);
        return "'".date('Y-m-d H:i:s',$dv)."'";
    }

    /**
     * Converts a boolean literal to SQL code.
     */
    function getBooleanLiteral($bv)
    {
        if ( $bv ) return '1';
        else return '0';
    }

    /**
     * Convert this literal to SQL code.
     * @param mixed $literal The data to express as a literal.
     * @param string $type string, int, decimal, boolean, date,
     * datetime.
     * @param string $ftype field type, for special behavior dependent on
     * the SQL context.
     */
    function getLiteral($literal,$type=NULL,$ftype=NULL)
    {
        if ( is_null($literal) ||
                ($ftype == 'primary-key' &&
                 ($literal == 0 || $literal == 'new'))
                ) return $this->getNullLiteral();

        if ( $type == 'string' || (is_null($type) && is_string($literal)) )
            return $this->getStringLiteral($literal);

        if ( $type == 'boolean' ||
                (is_null($type) && (TRUE === $literal || FALSE === $literal )))
            return $this->getBooleanLiteral($literal);

        if ( $type == 'int' || (is_null($type) && is_int($literal)) )
            return $this->getIntLiteral($literal);

        if ( $type == 'decimal' || (is_null($type) && is_float($literal)) )
            return $this->getDecimalLiteral($literal);

        if ( $type == 'date' )
            return $this->getDateLiteral($literal);

        if ( $type == 'datetime' )
            return $this->getDateTimeLiteral($literal);
    }

    /**
     * Get an SQL reference to a query field.
     */
    function getQueryFieldReference ($queryfield,$withtable=TRUE)
    {
        $table = $queryfield->get('table');
        $rv = $queryfield->get('name');
        if ( $withtable && !is_null($table) ) $rv = "$table.$rv";
        return $rv;
    }

    /**
     * Add criteria to a where clause (logical 'and').
     * @param string $where The where clause to add to.  May be an empty
     * string to start a new where clause.
     * @param string $crit The criteria to and into $where.
     */
    function addToWhereClause (&$where,$crit)
    {
        if ( $where == '' ) $where = "\nwhere $crit";
        else $where .= "\n  and $crit";
    }

    /**
     * Generate in criteria.
     * @param QueryField $field The field to match against a value list.
     * @param array $vals The value list to match against.
     */
    function getInCrit ($field,$vals,$withtable=TRUE)
    {
        $inlist = $this->getQueryFieldReference($field,$withtable)." in (";
        $isfirst = true;
        foreach ( $vals as $initem )
        {
            if ($isfirst) $isfirst=false;
            else
            {
                $inlist .= ',';
            }
            $inlist .= $this->getLiteral($initem,
                    $field->get('literal-type'),
                    $field->get('type'));
        }
        $inlist .= ")";
        return $inlist;
    }

    /**
     * Generate equals criteria.
     * @param QueryField $field The field to match against a value.
     * @param mixed $val The value to match against.
     */
    function getEqualsCrit ($field,$val,$withtable=TRUE)
    {
        return $this->getQueryFieldReference($field,$withtable).
                ' = '.$this->getLiteral($val,
                        $field->get('literal-type'),
                        $field->get('type'));
    }

    /**
     * Generate empty (is null) criteria.
     * @param QueryField $field The field to match against a value.
     */
    function getEmptyCrit ($field,$withtable=TRUE)
    {
        return $this->getQueryFieldReference($field,$withtable).' is null';
    }

    /**
     * Generate criteria based on a field and filter value.
     * @param QueryField $field The field to match.
     * @param mixed $crit Criteia to match against the field: an atom for
     * equals criteria, array for in criteria, and TODO.
     */
    function getFilterCrit ($field,$crit,$withtable=TRUE)
    {
        // generate equal criteria for atoms
        if ( is_scalar($crit) )
        {
            return $this->getEqualsCrit($field,$crit,$withtable);
        }

        // generate in criteria
        elseif ( is_array($crit) )
        {
            // in (empty list) cannot match
            if ( count($crit) == 0 ) return FALSE;
            else return $this->getInCrit($field,$crit,$withtable);
        }

        // generate equals criteria
        elseif ( is_null($crit) )
        {
            return $this->getEmptyCrit($field,$withtable);
        }

        // TODO: implement other criteria (less, greater, etc.)
        else
        {
            $this->fatal( "filter criteria not implemented for: ".
                    print_r($crit,TRUE) );
            return FALSE;
        }
    }

    /**
     * Get the where clause for a builder including primary key matching
     * and theta join criteria.
     * @param array $criteria Associative array of search criteria, for
     * filtering the result set. $criteria is keyed on alias name in
     * $sqlbuilder, and the behavior of the matched value depends on the
     * literal type of the field with the same alias in $sqlbuilder.
     * Multiple values to match may be anded together by placing them in an
     * indexed array.
     * @param string $table Only provide a where clause for this table if
     * not null.  If null, then get the where clause for all tables.
     */
    function getWhereClause ($sqlbuilder,$criteria=NULL,$table=NULL)
    {
        $andcrit = array();
        foreach ( $sqlbuilder->getKeyFields($table) as $keyfield )
        {
            $kfa = $keyfield->get('alias');
            $tcore = $this->getCore();
            if ( is_scalar($tcore) )
            {
                $andcrit[$kfa] = array($keyfield,$tcore);
            }
            elseif ( $this->has($kfa) )
            {
                $andcrit[$kfa] = array($keyfield,$this->get($kfa));
            }
            elseif ( is_null($criteria) )
            {
                $this->debug( $sqlbuilder );
                $this->debug( $this );
                $this->debug( $criteria );
                $this->fatal( "key field $kfa is not defined" );
            }
        }

        if ( !is_null($criteria) )
        {
            foreach ( $sqlbuilder->getQueryFieldsByTable($table) as $field )
            {
                $fa = $field->get('alias');
                if ( array_key_exists($fa,$criteria) )
                {
                    $andcrit[$fa] = array($field,$criteria[$fa]);
                }
            }
        }

        $where = '';
        foreach ( $andcrit as $ac )
        {
            $filtercriteria =
                    $this->getFilterCrit($ac[0],$ac[1],is_null($table));
            if ( $filtercriteria )
            {
                $this->addToWhereClause($where,$filtercriteria);
            }
            else
            {
                // the filter criteria resolved to a state which
                // cannot produce a match, so the query should not
                // be run and the results should be empty.
                return FALSE;
            }
        }

        return $where;
    }

    /**
     * Get the select statement for a builder.
     * @return string The select clause defined by a builder.
     */
    function select ($sqlbuilder,$criteria=NULL)
    {
        $select = "select distinct";
        $hasfields = FALSE;
        $fields =& $sqlbuilder->getReadFields();
        foreach ( array_keys($fields) as $i )
        {
            $ftype = $fields[$i]->get('type');
            $falias = $fields[$i]->get('alias');
            if ( $ftype != 'criteria' )
            {
                if ( $hasfields ) $select .= ',';
                $select .= "\n    ".
                        $this->getQueryFieldReference($fields[$i]).
                        ' as '.$fields[$i]->get('alias');
                $hasfields= TRUE;
            }
        }
        $select .= "\nfrom ".$sqlbuilder->get('table');

        $joins =& $sqlbuilder->getJoinedTables();
        if ( !is_null($joins) )
        {
            foreach ( array_keys($joins) as $i )
            {
                $jname = $joins[$i]->get('name');

                $fks =& $sqlbuilder->get('foreign-keys');
                foreach ( array_keys( $fks ) as $key )
                {
                    $fk =& $fks[$key];
                    $related =& $fk->get('related-field');
                    if ( $related && $related->get('table') == $jname )
                    {
                        // TODO: implement right,outer,theta etc.
                        $select .= "\n  join ".$jname.' on '.
                                $this->getQueryFieldReference($fk).
                                ' = '.$this->getQueryFieldReference($related);
                    }
                }
            }
        }

        $select .= $this->getWhereClause($sqlbuilder,$criteria);
        return $select;
    }

    /**
     * Get a 2D array of the write fields to use for update, insert, and
     * replace indexed by table name and field name.
     */
    function getSetFields ($sqlbuilder,$addKeys) {
        $fields =& $sqlbuilder->getWriteFields();
        $sfs = array();
        foreach ( array_keys($fields) as $i )
        {
            $falias = $fields[$i]->get('alias');
            $ftable = $fields[$i]->get('table');
            if ( $this->has($falias) )
            {
                if ( !array_key_exists($ftable,$sfs) )
                {
                    $sfs[$ftable] = array();
                }
                $sfs[$ftable][$falias] = $fields[$i];
            }
        }
        if ( $addKeys )
        {
            foreach ( array_keys($sfs) as $table )
            {
                foreach ( $sqlbuilder->getKeyFields($table) as $kf )
                {
                    $sfst = $sfs[$table];
                    $sfs[$table] = 
                            array_merge(array($kf),$sfst);
                }
            }
        }
        return $sfs;
    }

    /**
     * Get the update statement for a builder.
     * @return string The update statement defined by a builder
     */
    function update ($sqlbuilder)
    {
        $sfs = $this->getSetFields($sqlbuilder,FALSE);
        if ( count($sfs) == 0 )
        {
            $this->debug( $this );
            $this->fatal( "no defined read-write fields are populated" );
        }

        $rv = array();
        foreach ( array_keys($sfs) as $table )
        {
            $hasset = FALSE;
            $update = 'update '.$table." set\n";
            foreach ( $sfs[$table] as $field )
            {
                $falias = $field->get('alias');
                $ftype = $field->get('type');
                if ( $hasset ) $update .= ",\n";
                $update .= '    '.$field->get('name').' = '.
                        $this->getLiteral(
                                $this->get($falias),
                                $field->get('literal-type'),$ftype);
                $hasset = TRUE;
            }
            $update .= $this->getWhereClause($sqlbuilder,NULL,$table);
            $rv[] = $update;
        }
        return $rv;
    }

    /**
     * Get the values clause for a builder.
     * @return string The values clause defined by a builder.
     */
    function values ($fields,$includenames=TRUE)
    {
        if ( $includenames ) $names = '(';
        $values = "values (";
        $hasfield = FALSE;
        foreach ( array_keys($fields) as $i )
        {
            $ftype = $fields[$i]->get('type');
            $falias = $fields[$i]->get('alias');
            if ( $ftype != 'criteria' && $this->has($falias) )
            {
                if ( $hasfield )
                {
                    if ( $includenames ) $names .= ',';
                    $values .= ',';
                }
                if ( $includenames ) $names .= $fields[$i]->get('name');
                $values .= $this->getLiteral(
                        $this->get($falias),
                        $fields[$i]->get('literal-type'),
                        $ftype);
                $hasfield = TRUE;
            }
        }
        if ( $includenames ) $values = "$names)\n$values";
        if ( $hasfield ) return "$values)";
        else
        {
            $this->debug( $this );
            $this->fatal( "no defined write fields or primary ".
                    "keys are populated" );
        }
    }

    /**
     * Get the insert statment for a builder.
     * @return string The insert statement defined by a builder.
     */
    function insert ($sqlbuilder)
    {
        $sfs = $this->getSetFields($sqlbuilder,TRUE);
        $rv = array();
        foreach ( array_keys($sfs) as $table )
        {
            $insert = 'insert into '.$table.' ';
            $insert .= $this->values($sfs[$table]);
            $rv[] = $insert;
        }
        return $rv;
    }

    /**
     * Get the replace statment for a builder.
     * @return string The replace statement defined by a builder.
     */
    function replace ($sqlbuilder)
    {
        $sfs = $this->getSetFields($sqlbuilder,TRUE);
        $rv = array();
        foreach ( array_keys($sfs) as $table )
        {
            $replace = 'replace into '.$table.' ';
            $replace .= $this->values($sfs[$table]);
            $rv[] = $replace;
        }
        return $rv;
    }

    /**
     * Get the delete statement for a builder.
     * @return string The delete statement defined by a builder.
     */
    function delete ($sqlbuilder)
    {
        $sfs = $this->getSetFields($sqlbuilder,FALSE);
        if ( count($sfs) == 0 )
        {
            $this->debug( $this );
            $this->fatal( "no defined read-write fields are populated" );
        }

        $rv = array();
        foreach ( array_keys($sfs) as $table )
        {
            $hasset = FALSE;
            $delete = 'delete from '.$table;
            $delete .= $this->getWhereClause($sqlbuilder,NULL,$table);
            $rv[] = $delete;
        }
        return $rv;

    }

}

?>
