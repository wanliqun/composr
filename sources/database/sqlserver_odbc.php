<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core_database_drivers
 */

/*EXTRA FUNCTIONS: odbc\_.+*/

/*
ODBC is an unusual technology when viewed from a current reference point. It does not itself use TCP/IP, it is only an API standard, not a networking standard.
PHP > Some PHP ODBC extension > Some ODBC implementation > Some ODBC driver > Some database server
e.g:
PHP Windows > (Inbuilt support on Windows) > Windows ODBC > SQL Server Native Client 10.0 > SQL Server Express
PHP Linux > PHP ODBC extension > unixODBC > FreeTDS > SQL Server Express

Therefore host name is ignored.

It would be possible for us to use a connection string to tell FreeTDS (for example) how to connect through to the SQL Server Express machine. However, this would require telling PHP explicitly to use FreeTDS along with all the specific configuration FreeTDS understands, and we don't provide any configuration for that in _config.php. Hence we rely on locally configured ODBC connections, which is cleaner.

Instructions:
1) Make sure you have the SQL Server network service started
2) In SQL Server Management Studio, create your new database.
3) If using SQL Server Express, enable Network connections https://technet.microsoft.com/en-us/library/ms165718(v=sql.105).aspx. Also make sure TCP/IP is enabled on any network interface you're connecting on, in the same area of configuration, "IP Addresses tab". Also set the "IPAll" port to "1433" in here.
4) This driver works by ODBC. It needs setting up on the webserver, to connect to database server via ODBC.
 a) For Windows, create a mapping in the ODBC part of control panel:
  i) It will be a System DSN
  ii) Use 'SQL Server Native Client 10.0'
  iii) Set the Name as the same name as your database, to keep things simple
  iv) Set the Server as '127.0.0.1' if the database is running on the webserver, else the correct IP address or hostname
  v) Finish
 b) For Linux or MacOS, we suggest using unixODBC with FreeTDS (avoid iODBC and Microsoft's own drivers). PHP will need to be compiled with unixODBC support. These are some rough instructions http://www.johnro.net/2016/02/13/php-connect-to-sql-server-database-on-linux-and-mac-os-x/.
5) When installing use the DSN for the database name

Be aware of this bug https://bugs.php.net/bug.php?id=73448
And this bug https://bugs.php.net/bug.php?id=75534 (not yet fixed in PHP at time of writing)
And this bug https://bugs.php.net/bug.php?id=44278 (not yet fixed in PHP at time of writing)

Full-text search is supported, however searching in SQL Server is per-table, not per-field, so precision is a bit more limited than other database engines. You may disable full-text search via the hidden 'skip_fulltext_sqlserver' option. Full-text search on SQL Server can be a bit spotty, the index builds in the background and has been seen to lag or not build at all during some testing (at least on SQL Server Express).

Sample unixODBC config (/usr/local/etc/odbc.ini)...

[cms]
Driver=FreeTDS
Trace=No
Server=192.168.0.22
Port=1433
Database=cms
TDS_Version=7.2
ClientCharset=UTF-8

Version 7.2 is the minimum version supported (consistent with Microsoft SQL Server 2005).
*/

require_code('database/shared/sqlserver');

/**
 * Standard code module initialisation function.
 *
 * @ignore
 */
function init__database__sqlserver_odbc()
{
    safe_ini_set('odbc.defaultlrl', '20M');
}

/**
 * Database Driver.
 *
 * @package    core_database_drivers
 */
class Database_Static_sqlserver_odbc extends Database_super_sqlserver
{
    public $cache_db = array();

    /**
     * Close the database connections. We don't really need to close them (will close at exit), just disassociate so we can refresh them.
     */
    public function db_close_connections()
    {
        $this->cache_db = array();
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

        $dsn = $db_name;

        if (!function_exists('odbc_connect')) {
            $error = 'The ODBC PHP extension not installed (anymore?). You need to contact the system administrator of this server.';
            if ($fail_ok) {
                echo ((running_script('install')) && (get_param_string('type', '') == 'ajax_db_details')) ? strip_html($error) : $error;
                return null;
            }
            critical_error('PASSON', $error);
        }

        $db = $persistent ? @odbc_pconnect($dsn, $db_user, $db_password) : @odbc_connect($dsn, $db_user, $db_password);
        if ($db === false) {
            $error = 'Could not connect to database-server (' . preg_replace('#[[:^print:]].*$#'/*error messages don't come through cleanly https://bugs.php.net/bug.php?id=73448*/, '', odbc_errormsg()) . ')';
            if ($fail_ok) {
                echo ((running_script('install')) && (get_param_string('type', '') == 'ajax_db_details')) ? strip_html($error) : $error;
                return null;
            }
            critical_error('PASSON', $error); //warn_exit(do_lang_tempcode('CONNECT_DB_ERROR'));
        }

        if ($db === false) {
            fatal_exit(do_lang('CONNECT_DB_ERROR'));
        }

        odbc_exec($db, 'SET TEXTSIZE 300000');

        $this->cache_db[$db_name][$db_host] = $db;
        return $db;
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
        $this->apply_sql_limit_clause($query, $max, $start);

        $this->rewrite_to_unicode_syntax($query);

        $results = @odbc_exec($db, $query);
        if (($results === false) && (strtoupper(substr($query, 0, 12)) == 'INSERT INTO ') && (strpos($query, '(id, ') !== false)) {
            $pos = strpos($query, '(');
            $table_name = substr($query, 12, $pos - 13);
            if ((!multi_lang_content()) || (substr($table_name, -strlen('translate')) != 'translate')) {
                $results = @odbc_exec($db, 'SET IDENTITY_INSERT ' . $table_name . ' ON; ' . $query);
            }
        }
        if ((($results === false) || (((strtoupper(substr(ltrim($query), 0, 7)) == 'SELECT ') || (strtoupper(substr(ltrim($query), 0, 8)) == '(SELECT ')) && ($results === true))) && (!$fail_ok)) {
            $err = preg_replace('#[[:^print:]].*$#'/*error messages don't come through cleanly https://bugs.php.net/bug.php?id=73448*/, '', odbc_errormsg($db));
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
            return $this->db_get_query_rows($results, $query, $start);
        }

        if ($get_insert_id) {
            if (strtoupper(substr($query, 0, 7)) == 'UPDATE ') {
                return null;
            }

            $pos = strpos($query, '(');
            $table_name = substr($query, 12, $pos - 13);

            $res2 = odbc_exec($db, 'SELECT MAX(IDENTITYCOL) FROM ' . $table_name);
            odbc_fetch_row($res2);
            return odbc_result($res2, 1);
        }

        return null;
    }

    /**
     * Get the rows returned from a SELECT query.
     *
     * @param  resource $results The query result pointer
     * @param  string $query The complete SQL query (useful for debugging)
     * @param  ?integer $start Whether to start reading from (null: irrelevant)
     * @return array A list of row maps
     */
    public function db_get_query_rows($results, $query, $start = null)
    {
        $out = array();
        if ($start === null) {
            $start = 0;
        }
        $i = 0;

        $num_fields = odbc_num_fields($results);
        $types = array();
        $names = array();
        for ($x = 1; $x <= $num_fields; $x++) {
            $types[$x] = strtoupper(odbc_field_type($results, $x));
            $names[$x] = strtolower(odbc_field_name($results, $x));
        }

        while (odbc_fetch_row($results, $start + $i + 1)) {
            $newrow = array();

            for ($j = 1; $j <= $num_fields; $j++) {
                $v = odbc_result($results, $j);

                $type = $types[$j];
                $name = $names[$j];

                if (($type == 'SMALLINT') || ($type == 'BIGINT') || ($type == 'INT') || ($type == 'INTEGER') || ($type == 'UINTEGER') || ($type == 'BYTE') || ($type == 'COUNTER')) {
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
            }

            $out[] = $newrow;

            $i++;
        }

        odbc_free_result($results);
        return $out;
    }

    /**
     * Start a transaction
     *
     * @param  array $db A DB connection
     */
    public function db_start_transaction($db)
    {
        odbc_autocommit($db, false);
    }

    /**
     * End a transaction
     *
     * @param  array $db A DB connection
     */
    public function db_end_transaction($db)
    {
        odbc_commit($db);
        odbc_autocommit($db, true);
    }
}