<?php
// Include database monitor for error-free SQLi detection
require_once __DIR__ . '/../db_monitor.php';

##########################################################################################
#                                    mysqli overrides                                    #
##########################################################################################

// Global variable to track if marker table is initialized
if (!defined('__FUZZER__DB_MARKER_INITIALIZED')) {
    define('__FUZZER__DB_MARKER_INITIALIZED', true);
}

uopz_set_return(
    'mysqli_query',
    function ($mysql, $query, $result_mode = MYSQLI_STORE_RESULT) {
        //__fuzzer__debug("mysqli_query is: " . $query . "\n\n");
        mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT); // to prevent throwing exception, became default in PHP 8
        
        // Initialize marker table once
        static $marker_initialized = false;
        if (!$marker_initialized) {
            __fuzzer__db_init_marker_table($mysql);
            $marker_initialized = true;
        }
        
        // Take snapshot before query execution
        $snapshot_before = __fuzzer__db_snapshot($mysql);
        
        try {
            $result = mysqli_query($mysql, $query, $result_mode);
            $the_exception = null;
        }catch(Throwable $e) {
            $result = false;
            $the_exception = $e;
        }
        
        if ($result === false) {
            // Original error handling
            if($the_exception) {
                $errno = -1;
                $errstr = $the_exception->getMessage();
            }else {
                $errno = mysqli_errno($mysql);
                $errstr = mysqli_error($mysql);
            }
            $json = json_encode(
                [
                    'function' => 'mysqli_query',
                    'params' => [$query],
                    'errno' => $errno,
                    'errstr' => $errstr,
                ]
            );
            __fuzzer_file_put_contents(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", $json . "\n", FILE_APPEND);
            chmod(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", 0777);
            if($the_exception != null) {
                throw $the_exception;
            }
        } else {
            // Query succeeded - check for side effects (error-free SQLi)
            $snapshot_after = __fuzzer__db_snapshot($mysql);
            $changes = __fuzzer__db_compare($snapshot_before, $snapshot_after);
            
            // Report to OOB server if side effects detected
            __fuzzer__db_report_to_oob(__FUZZER__COVID, $changes);
        }
        
        return $result;
    },
    true
);


uopz_set_return(
    'mysqli',
    'query',
    function ($query, $result_mode = MYSQLI_STORE_RESULT) {
        //__fuzzer__debug("mysqli::query is: " . $query . "\n\n");
        mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT);
        
        // Initialize marker table once
        static $marker_initialized = false;
        if (!$marker_initialized) {
            __fuzzer__db_init_marker_table($this);
            $marker_initialized = true;
        }
        
        // Take snapshot before query execution
        $snapshot_before = __fuzzer__db_snapshot($this);
        
        try{
            $result = $this->query($query, $result_mode);
            $the_exception = null;
        }catch(Throwable $e) {
            $result = false;
            $the_exception = $e;
        }
        
        if ($result === false) {
            // Original error handling
            if($the_exception) {
                $errno = -1;
                $errstr = $the_exception->getMessage();
            } else {
                $errno = $this->errno;
                $errstr = $this->error;
            }
            $json = json_encode(
                [
                    'function' => 'mysqli::query',
                    'params' => [$query],
                    'errno' => $errno,
                    'errstr' => $errstr,
                ]
            );
            __fuzzer_file_put_contents(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", $json . "\n", FILE_APPEND);
            chmod(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", 0777);
            if($the_exception != null) {
                throw $the_exception;
            }
        } else {
            // Query succeeded - check for side effects (error-free SQLi)
            $snapshot_after = __fuzzer__db_snapshot($this);
            $changes = __fuzzer__db_compare($snapshot_before, $snapshot_after);
            
            // Report to OOB server if side effects detected
            __fuzzer__db_report_to_oob(__FUZZER__COVID, $changes);
        }
        
        return $result;
    },
    true
);
?>

