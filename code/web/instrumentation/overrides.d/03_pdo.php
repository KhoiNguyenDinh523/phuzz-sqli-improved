<?php
// Include database monitor for error-free SQLi detection
require_once __DIR__ . '/../db_monitor.php';

##########################################################################################
#                                     PDO overrides                                     #
##########################################################################################

// Note: PDO doesn't have direct access to mysqli connection for snapshots
// We'll use a workaround by getting the connection from global scope if available
// For now, we'll skip side-effect detection for PDO and focus on mysqli
// (Most PHP apps use mysqli, PDO support can be added later if needed)

uopz_set_return(
    'PDO',
    'query',
    function ($query, $fetchMode = null) {
        try{
            $result = $this->query($query, $fetchMode);
            $the_exception = null;
        }catch(Throwable $e) {
            $result = false;
            $the_exception = $e;
        }
        if ($result === false) {
            if($the_exception) {
                $errno = -1;
                $errstr = $the_exception->getMessage();
            } else {
                $errno = $this->errorCode();
                $errstr = $this->errorInfo();
            }
            $json = json_encode(
                [
                    'function' => 'PDO::query',
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
        }
        // Note: PDO side-effect detection would require connection tracking
        // Skipping for initial implementation, focusing on mysqli
        return $result;
    },
    true
);

uopz_set_return(
    'PDO',
    'query',
    function ($query, $fetchMode = PDO::FETCH_COLUMN, $colno = null) {
        try{
            $result = $this->query($query, $fetchMode, $colno);
            $the_exception = null;
        }catch(Throwable $e) {
            $result = false;
            $the_exception = $e;
        }
        if ($result === false) {
            if($the_exception) {
                $errno = -1;
                $errstr = $the_exception->getMessage();
            } else {
                $errno = $this->errorCode();
                $errstr = $this->errorInfo();
            }
            $json = json_encode(
                [
                    'function' => 'PDO::query',
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
        }
        return $result;
    },
    true
);
uopz_set_return(
    'PDO',
    'query',
    function ($query, $fetchMode = PDO::FETCH_CLASS, $classname = null, $constructorArgs = null) {
        try{
            $result = $this->query($query, $fetchMode, $classname, $constructorArgs);
            $the_exception = null;
        }catch(Throwable $e) {
            $result = false;
            $the_exception = $e;
        }
        if ($result === false) {
            if($the_exception) {
                $errno = -1;
                $errstr = $the_exception->getMessage();
            } else {
                $errno = $this->errorCode();
                $errstr = $this->errorInfo();
            }
            $json = json_encode(
                [
                    'function' => 'PDO::query',
                    'params' => [$query],
                    'errno' => $errno,
                    'errstr' => $errstr,
                ]
            );
            __fuzzer_file_put_contents(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", $json . "\n", FILE_APPEND);
            chmod(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", 0777);
            if($the_exception != null) {
                throw $e;
            }
        }
        return $result;
    },
    true
);


uopz_set_return(
    'PDO',
    'query',
    function ($query, $fetchMode = PDO::FETCH_INTO, $object = null) {
        try{
            $result = $this->query($query, $fetchMode, $object);
            $the_exception = null;
        }catch(Throwable $e) {
            $result = false;
            $the_exception = $e;
        }
        if ($result === false) {
            if($the_exception) {
                $errno = -1;
                $errstr = $the_exception->getMessage();
            } else {
                $errno = $this->errorCode();
                $errstr = $this->errorInfo();
            }
            $json = json_encode(
                [
                    'function' => 'PDO::query',
                    'params' => [$query],
                    'errno' => $errno,
                    'errstr' => $errstr,
                ]
            );
            __fuzzer_file_put_contents(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", $json . "\n", FILE_APPEND);
            chmod(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", 0777);
            if($the_exception != null) {
                throw $e;
            }
        }
        return $result;
    },
    true
);

uopz_set_return(
    'PDO',
    'exec',
    function ($statement) {
        try{
            $result = $this->exec($statement);
            $the_exception = null;
        }catch(Throwable $e) {
            $result = false;
            $the_exception = $e;
        }
        if ($result === false) {
            if($the_exception) {
                $errno = -1;
                $errstr = $the_exception->getMessage();
            } else {
                $errno = $this->errorCode();
                $errstr = $this->errorInfo();
            }
            $json = json_encode(
                [
                    'function' => 'PDO::exec',
                    'params' => [$statement],
                    'errno' => $errno,
                    'errstr' => $errstr,
                ]
            );
            __fuzzer_file_put_contents(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", $json . "\n", FILE_APPEND);
            chmod(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", 0777);
            if($the_exception != null) {
                throw $e;
            }
        }
        return $result;
    },
    true
);
?>