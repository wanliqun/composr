<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/*EXTRA FUNCTIONS: pg\_.+|get_current_user*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core_database_drivers
 */

/**
 * Database Driver.
 *
 * @package    core_database_drivers
 */
class Database_Static_postgresql
{
    public $cache_db = array();

    /**
     * Get the default user for making db connections (used by the installer as a default).
     *
     * @return string The default user for db connections
     */
    public function db_default_user()
    {
        if ((php_function_allowed('get_current_user'))) {
            //$_ret = posix_getpwuid(posix_getuid()); $ret = $_ret['name'];
            //$ret = posix_getlogin();
            $ret = get_current_user();
            if (!in_array($ret, array('apache', 'nobody', 'www', '_www'))) {
                return $ret;
            }
        }
        return 'postgres';
    }

    /**
     * Get the default password for making db connections (used by the installer as a default).
     *
     * @return string The default password for db connections
     */
    public function db_default_password()
    {
        return '';
    }

    /**
     * Create a table index.
     *
     * @param  ID_TEXT $table_name The name of the table to create the index on
     * @param  ID_TEXT $index_name The index name (not really important at all)
     * @param  string $_fields Part of the SQL query: a comma-separated list of fields to use on the index
     * @param  array $db The DB connection to make on
     */
    public function db_create_index($table_name, $index_name, $_fields, $db)
    {
        if ($index_name[0] == '#') {
            $index_name = substr($index_name, 1);

            $postgres_fulltext_language = get_value('postgres_fulltext_language');
            if ($postgres_fulltext_language === null) {
                $postgres_fulltext_language = 'english';
            }

            $aggregation = '';
            foreach (explode(',', $_fields) as $_field) {
                if ($aggregation != '') {
                    $aggregation .= ' || \' \' || ';
                }
                $aggregation .= '\'' . $this->db_escape_string($_field) . '\'';
            }

            $this->db_query('CREATE INDEX index' . $index_name . '__' . $table_name . ' ON ' . $table_name . ' USING gin(to_tsvector(\'pg_catalog.' . $postgres_fulltext_language . '\', ' . $aggregation . '))', $db);

            return;
        }

        $fields = explode(',', $_fields);
        foreach ($fields as $field) {
            if (strpos($GLOBALS['SITE_DB']->query_select_value_if_there('db_meta', 'm_type', array('m_table' => $table_name, 'm_name' => $field)), 'LONG') !== false) {
                // We can't support this in PostgreSQL, too much data will give an error when inserting into the index
                return;
            }
        }

        $_fields = preg_replace('#\(\d+\)#', '', $_fields);

        $this->db_query('CREATE INDEX index' . $index_name . '__' . $table_name . ' ON ' . $table_name . '(' . $_fields . ')', $db);
    }

    /**
     * Change the primary key of a table.
     *
     * @param  ID_TEXT $table_name The name of the table to create the index on
     * @param  array $new_key A list of fields to put in the new key
     * @param  array $db The DB connection to make on
     */
    public function db_change_primary_key($table_name, $new_key, $db)
    {
        $this->db_query('ALTER TABLE ' . $table_name . ' DROP PRIMARY KEY', $db);
        $this->db_query('ALTER TABLE ' . $table_name . ' ADD PRIMARY KEY (' . implode(',', $new_key) . ')', $db);
    }

    /**
     * Assemble part of a WHERE clause for doing full-text search
     *
     * @param  string $content Our match string (assumes "?" has been stripped already)
     * @param  boolean $boolean Whether to do a boolean full text search
     * @return string Part of a WHERE clause for doing full-text search
     */
    public function db_full_text_assemble($content, $boolean)
    {
        static $stopwords = null;
        if (is_null($stopwords)) {
            require_code('database_search');
            $stopwords = get_stopwords_list();
        }
        if (isset($stopwords[trim(strtolower($content), '"')])) {
            // This is an imperfect solution for searching for a stop-word
            // It will not cover the case where the stop-word is within the wider text. But we can't handle that case efficiently anyway
            return db_string_equal_to('?', trim($content, '"'));
        }

        $postgres_fulltext_language = get_value('postgres_fulltext_language');
        if ($postgres_fulltext_language === null) {
            $postgres_fulltext_language = 'english';
        }

        return 'to_tsvector(?) @@ plainto_tsquery(\'pg_catalog.' . $postgres_fulltext_language . '\', \'' . $this->db_escape_string($content) . '\')';
    }

    /**
     * Get the ID of the first row in an auto-increment table (used whenever we need to reference the first).
     *
     * @return integer First ID used
     */
    public function db_get_first_id()
    {
        return 1;
    }

    /**
     * Get a map of Composr field types, to actual database types.
     *
     * @return array The map
     */
    public function db_get_type_remap()
    {
        $type_remap = array(
            'AUTO' => 'serial',
            'AUTO_LINK' => 'integer',
            'INTEGER' => 'integer',
            'UINTEGER' => 'bigint',
            'SHORT_INTEGER' => 'smallint',
            'REAL' => 'real',
            'BINARY' => 'smallint',
            'MEMBER' => 'integer',
            'GROUP' => 'integer',
            'TIME' => 'bigint',
            'LONG_TRANS' => 'bigint',
            'SHORT_TRANS' => 'bigint',
            'LONG_TRANS__COMCODE' => 'integer',
            'SHORT_TRANS__COMCODE' => 'integer',
            'SHORT_TEXT' => 'text',
            'LONG_TEXT' => 'text',
            'ID_TEXT' => 'varchar(80)',
            'MINIID_TEXT' => 'varchar(40)',
            'IP' => 'varchar(40)',
            'LANGUAGE_NAME' => 'varchar(5)',
            'URLPATH' => 'varchar(255)',
        );
        return $type_remap;
    }

    /**
     * Close the database connections. We don't really need to close them (will close at exit), just disassociate so we can refresh them.
     */
    public function db_close_connections()
    {
        $this->cache_db = array();
    }

    /**
     * Create a new table.
     *
     * @param  ID_TEXT $table_name The table name
     * @param  array $fields A map of field names to Composr field types (with *#? encodings)
     * @param  array $db The DB connection to make on
     */
    public function db_create_table($table_name, $fields, $db)
    {
        $type_remap = $this->db_get_type_remap();

        $_fields = '';
        $keys = '';
        foreach ($fields as $name => $type) {
            if ($type[0] == '*') { // Is a key
                $type = substr($type, 1);
                if ($keys != '') {
                    $keys .= ', ';
                }
                $keys .= $name;
            }

            if ($type[0] == '?') { // Is perhaps null
                $type = substr($type, 1);
                $perhaps_null = 'NULL';
            } else {
                $perhaps_null = 'NOT NULL';
            }

            $type = isset($type_remap[$type]) ? $type_remap[$type] : $type;

            $_fields .= '    ' . $name . ' ' . $type;
            if (substr($name, -13) == '__text_parsed') {
                $_fields .= ' DEFAULT \'\'';
            } elseif (substr($name, -13) == '__source_user') {
                $_fields .= ' DEFAULT ' . strval(db_get_first_id());
            }
            $_fields .= ' ' . $perhaps_null . ',' . "\n";
        }

        $query = 'CREATE TABLE ' . $table_name . ' (
          ' . $_fields . '
          PRIMARY KEY (' . $keys . ')
        )';
        $this->db_query($query, $db, null, null);
    }

    /**
     * Encode an SQL statement fragment for a conditional to see if two strings are equal.
     *
     * @param  ID_TEXT $attribute The attribute
     * @param  string $compare The comparison
     * @return string The SQL
     */
    public function db_string_equal_to($attribute, $compare)
    {
        return $attribute . "='" . $this->db_escape_string($compare) . "'";
    }

    /**
     * Encode an SQL statement fragment for a conditional to see if two strings are not equal.
     *
     * @param  ID_TEXT $attribute The attribute
     * @param  string $compare The comparison
     * @return string The SQL
     */
    public function db_string_not_equal_to($attribute, $compare)
    {
        return $attribute . "<>'" . $this->db_escape_string($compare) . "'";
    }

    /**
     * This function is internal to the database system, allowing SQL statements to be build up appropriately. Some databases require IS NULL to be used to check for blank strings.
     *
     * @return boolean Whether a blank string IS NULL
     */
    public function db_empty_is_null()
    {
        return false;
    }

    /**
     * Whether 'OFFSET' syntax is used on limit clauses.
     *
     * @return boolean Whether it is
     */
    public function db_uses_offset_syntax()
    {
        return true;
    }

    /**
     * Find whether table truncation support is present
     *
     * @return boolean Whether it is
     */
    public function db_supports_truncate_table()
    {
        return true;
    }

    /**
     * Delete a table.
     *
     * @param  ID_TEXT $table The table name
     * @param  array $db The DB connection to delete on
     */
    public function db_drop_table_if_exists($table, $db)
    {
        $this->db_query('DROP TABLE ' . $table, $db, null, null, true);
    }

    /**
     * Determine whether the database is a flat file database, and thus not have a meaningful connect username and password.
     *
     * @return boolean Whether the database is a flat file database
     */
    public function db_is_flat_file_simple()
    {
        return false;
    }

    /**
     * Encode a LIKE string comparision fragement for the database system. The pattern is a mixture of characters and ? and % wildcard symbols.
     *
     * @param  string $pattern The pattern
     * @return string The encoded pattern
     */
    public function db_encode_like($pattern)
    {
        return $this->db_escape_string($pattern);
    }

    /**
     * Get a database connection. This function shouldn't be used by you, as a connection to the database is established automatically.
     *
     * @param  boolean $persistent Whether to create a persistent connection
     * @param  string $db_name The database name
     * @param  string $db_host The database host (the server)
     * @param  string $db_user The database connection username
     * @param  string $db_password The database connection password
     * @param  boolean $fail_ok Whether to on error echo an error and return with a null, rather than giving a critical error
     * @return ?array A database connection (null: failed)
     */
    public function db_get_connection($persistent, $db_name, $db_host, $db_user, $db_password, $fail_ok = false)
    {
        // Potential caching
        if (isset($this->cache_db[$db_name][$db_host])) {
            return $this->cache_db[$db_name][$db_host];
        }

        if (!function_exists('pg_pconnect')) {
            $error = 'The postgreSQL PHP extension not installed (anymore?). You need to contact the system administrator of this server.';
            if ($fail_ok) {
                echo ((running_script('install')) && (get_param_string('type', '') == 'ajax_db_details')) ? strip_html($error) : $error;
                return null;
            }
            critical_error('PASSON', $error);
        }

        $db = $persistent ? @pg_pconnect('host=' . $db_host . ' dbname=' . $db_name . ' user=' . $db_user . ' password=' . $db_password) : @pg_connect('host=' . $db_host . ' dbname=' . $db_name . ' user=' . $db_user . ' password=' . $db_password);
        if ($db === false) {
            $error = 'Could not connect to database-server (' . $php_errormsg . ')';
            if ($fail_ok) {
                echo ((running_script('install')) && (get_param_string('type', '') == 'ajax_db_details')) ? strip_html($error) : $error;
                return null;
            }
            critical_error('PASSON', $error); //warn_exit(do_lang_tempcode('CONNECT_DB_ERROR'));
        }

        if (!$db) {
            fatal_exit(do_lang('CONNECT_DB_ERROR'));
        }
        $this->cache_db[$db_name][$db_host] = $db;
        return $db;
    }

    /**
     * Get the number of rows in a table, with approximation support for performance (if necessary on the particular database backend).
     *
     * @param string $table The table name
     * @param array $where WHERE clauses if it will help get a more reliable number when we're not approximating in map form
     * @param string $where_clause WHERE clauses if it will help get a more reliable number when we're not approximating in SQL form
     * @param object $db The DB connection to check against
     * @return ?integer The count (null: do it normally)
     */
    public function get_table_count_approx($table, $where, $where_clause, $db)
    {
        $sql = 'SELECT n_live_tup FROM pg_stat_all_tables WHERE relname=\'' . $db->get_table_prefix() . $table . '\'';
        return $db->query_value_if_there($sql, false, true);
    }

    /**
     * Find whether full-text-search is present
     *
     * @param  array $db A DB connection
     * @return boolean Whether it is
     */
    public function db_has_full_text($db)
    {
        return true;
    }

    /**
     * Escape a string so it may be inserted into a query. If SQL statements are being built up and passed using db_query then it is essential that this is used for security reasons. Otherwise, the abstraction layer deals with the situation.
     *
     * @param  string $string The string
     * @return string The escaped string
     */
    public function db_escape_string($string)
    {
        $string = fix_bad_unicode($string);

        return pg_escape_string($string);
    }

    /**
     * Find whether full-text-boolean-search is present
     *
     * @return boolean Whether it is
     */
    public function db_has_full_text_boolean()
    {
        return true; // Actually it is always boolean for PostgreSQL
    }

    /**
     * This function is a very basic query executor. It shouldn't usually be used by you, as there are abstracted versions available.
     *
     * @param  string $query The complete SQL query
     * @param  array $db A DB connection
     * @param  ?integer $max The maximum number of rows to affect (null: no limit)
     * @param  ?integer $start The start row to affect (null: no specification)
     * @param  boolean $fail_ok Whether to output an error on failure
     * @param  boolean $get_insert_id Whether to get the autoincrement ID created for an insert query
     * @return ?mixed The results (null: no results), or the insert ID
     */
    public function db_query($query, $db, $max = null, $start = null, $fail_ok = false, $get_insert_id = false)
    {
        if ((strtoupper(substr(ltrim($query), 0, 7)) == 'SELECT ') || (strtoupper(substr(ltrim($query), 0, 8)) == '(SELECT ')) {
            if ((!is_null($max)) && (!is_null($start))) {
                $query .= ' LIMIT ' . strval(intval($max)) . ' OFFSET ' . strval(intval($start));
            } elseif (!is_null($max)) {
                $query .= ' LIMIT ' . strval(intval($max));
            } elseif (!is_null($start)) {
                $query .= ' OFFSET ' . strval(intval($start));
            }
        }

        $results = @pg_query($db, $query);
        if ((($results === false) || (((strtoupper(substr(ltrim($query), 0, 7)) == 'SELECT ') || (strtoupper(substr(ltrim($query), 0, 8)) == '(SELECT ')) && ($results === true))) && (!$fail_ok)) {
            $err = pg_last_error($db);
            if (function_exists('ocp_mark_as_escaped')) {
                ocp_mark_as_escaped($err);
            }
            if ((!running_script('upgrader')) && ((!get_mass_import_mode()) || (get_param_integer('keep_fatalistic', 0) == 1))) {
                if (!function_exists('do_lang') || is_null(do_lang('QUERY_FAILED', null, null, null, null, false))) {
                    fatal_exit(htmlentities('Query failed: ' . $query . ' : ' . $err));
                }

                fatal_exit(do_lang_tempcode('QUERY_FAILED', escape_html($query), ($err)));
            } else {
                echo htmlentities('Database query failed: ' . $query . ' [') . ($err) . htmlentities(']') . "<br />\n";
                return null;
            }
        }

        $sub = substr(ltrim($query), 0, 4);
        if (($results !== true) && (($sub === '(SEL') || ($sub === 'SELE') || ($sub === 'sele') || ($sub === 'CHEC') || ($sub === 'EXPL') || ($sub === 'REPA') || ($sub === 'DESC') || ($sub === 'SHOW')) && ($results !== false)) {
            return $this->db_get_query_rows($results);
        }

        if ($get_insert_id) {
            if (strtoupper(substr($query, 0, 7)) == 'UPDATE ') {
                return null;
            }

            // Inefficient :(
            $pos = strpos($query, '(');
            $table_name = substr($query, 12, $pos - 13);

            $r3 = @pg_query($db, 'SELECT last_value FROM ' . $table_name . '_id_seq');
            if ($r3) {
                $seq_array = pg_fetch_row($r3, 0);
                return intval($seq_array[0]);
            }
        }

        return null;
    }

    /**
     * Get the rows returned from a SELECT query.
     *
     * @param  resource $results The query result pointer
     * @param  ?integer $start Whether to start reading from (null: irrelevant for this forum driver)
     * @return array A list of row maps
     */
    public function db_get_query_rows($results, $start = null)
    {
        $num_fields = pg_num_fields($results);
        $types = array();
        $names = array();
        for ($x = 1; $x <= $num_fields; $x++) {
            $types[$x - 1] = pg_field_type($results, $x - 1);
            $names[$x - 1] = strtolower(pg_field_name($results, $x - 1));
        }

        $out = array();
        $i = 0;
        while (($row = pg_fetch_row($results)) !== false) {
            $j = 0;
            $newrow = array();
            foreach ($row as $v) {
                $name = $names[$j];
                $type = strtoupper($types[$j]);

                if ((substr($type, 0, 3) == 'INT') || ($type == 'SMALLINT') || ($type == 'SERIAL') || ($type == 'UINTEGER')) {
                    if (!is_null($v)) {
                        $newrow[$name] = intval($v);
                    } else {
                        $newrow[$name] = null;
                    }
                } elseif (substr($type, 0, 5) == 'FLOAT') {
                    $newrow[$name] = floatval($v);
                } else {
                    $newrow[$name] = $v;
                }

                $j++;
            }

            $out[] = $newrow;

            $i++;
        }
        pg_free_result($results);
        return $out;
    }
}
