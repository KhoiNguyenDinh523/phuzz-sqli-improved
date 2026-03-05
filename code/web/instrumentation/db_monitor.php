<?php
/**
 * Database Monitor for Error-Free SQLi Detection
 * Captures database state snapshots and detects side effects
 */

// Initialize marker table on first load
function __fuzzer__db_init_marker_table($mysql) {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS FuzzMarkerTable (
        id INT PRIMARY KEY AUTO_INCREMENT,
        marker VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    try {
        if (is_object($mysql) && method_exists($mysql, 'query')) {
            // mysqli object
            @$mysql->query($create_table_sql);
        } else {
            // mysqli connection
            @mysqli_query($mysql, $create_table_sql);
        }
    } catch (Throwable $e) {
        // Silently fail if table already exists
    }
}

// Capture current database state snapshot
function __fuzzer__db_snapshot($mysql) {
    $snapshot = [
        'tables' => [],
        'row_counts' => [],
        'timestamp' => microtime(true)
    ];
    
    try {
        // Get list of tables
        if (is_object($mysql) && method_exists($mysql, 'query')) {
            // mysqli object
            $result = @$mysql->query("SHOW TABLES");
            if ($result) {
                while ($row = $result->fetch_array()) {
                    $table_name = $row[0];
                    $snapshot['tables'][] = $table_name;
                    
                    // Get row count for each table
                    $count_result = @$mysql->query("SELECT COUNT(*) as cnt FROM `{$table_name}`");
                    if ($count_result) {
                        $count_row = $count_result->fetch_assoc();
                        $snapshot['row_counts'][$table_name] = (int)$count_row['cnt'];
                    }
                }
            }
        } else {
            // mysqli connection
            $result = @mysqli_query($mysql, "SHOW TABLES");
            if ($result) {
                while ($row = mysqli_fetch_array($result)) {
                    $table_name = $row[0];
                    $snapshot['tables'][] = $table_name;
                    
                    // Get row count
                    $count_result = @mysqli_query($mysql, "SELECT COUNT(*) as cnt FROM `{$table_name}`");
                    if ($count_result) {
                        $count_row = mysqli_fetch_assoc($count_result);
                        $snapshot['row_counts'][$table_name] = (int)$count_row['cnt'];
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // Return empty snapshot on error
    }
    
    return $snapshot;
}

// Compare two snapshots and detect differences
function __fuzzer__db_compare($before, $after) {
    $changes = [
        'tables_created' => [],
        'tables_dropped' => [],
        'rows_inserted' => [],
        'rows_deleted' => [],
        'schema_changes' => false
    ];
    
    // Detect new tables
    $new_tables = array_diff($after['tables'], $before['tables']);
    foreach ($new_tables as $table) {
        // Only report Fuzz* tables as intentional side effects
        if (strpos($table, 'Fuzz') === 0 || strpos($table, 'Devil') !== false) {
            $changes['tables_created'][] = $table;
            $changes['schema_changes'] = true;
        }
    }
    
    // Detect dropped tables
    $dropped_tables = array_diff($before['tables'], $after['tables']);
    foreach ($dropped_tables as $table) {
        $changes['tables_dropped'][] = $table;
        $changes['schema_changes'] = true;
    }
    
    // Detect row count changes
    foreach ($after['row_counts'] as $table => $count) {
        if (isset($before['row_counts'][$table])) {
            $diff = $count - $before['row_counts'][$table];
            if ($diff > 0) {
                $changes['rows_inserted'][$table] = $diff;
            } elseif ($diff < 0) {
                $changes['rows_deleted'][$table] = abs($diff);
            }
        }
    }
    
    return $changes;
}

// Report side effects to OOB server
function __fuzzer__db_report_to_oob($coverage_id, $changes) {
    // Check if there are actual changes
    $has_changes = (
        !empty($changes['tables_created']) ||
        !empty($changes['tables_dropped']) ||
        !empty($changes['rows_inserted']) ||
        !empty($changes['rows_deleted']) ||
        $changes['schema_changes']
    );
    
    if (!$has_changes) {
        return; // No changes to report
    }
    
    $payload = json_encode([
        'coverage_id' => $coverage_id,
        'side_effects' => $changes,
        'timestamp' => date('c')
    ]);
    
    // Send to OOB server (non-blocking)
    $oob_url = getenv('OOB_SERVER_URL') ?: 'http://host.docker.internal:5001/report';
    
    $ch = curl_init($oob_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1); // 1 second timeout
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    
    @curl_exec($ch);
    @curl_close($ch);
}

// Reset database to clean state (remove Fuzz* tables and marker table rows)
function __fuzzer__db_reset($mysql) {
    try {
        // Get all tables
        if (is_object($mysql) && method_exists($mysql, 'query')) {
            $result = @$mysql->query("SHOW TABLES");
            if ($result) {
                while ($row = $result->fetch_array()) {
                    $table_name = $row[0];
                    // Drop Fuzz* and Devil* tables
                    if (strpos($table_name, 'Fuzz') === 0 || strpos($table_name, 'Devil') !== false) {
                        if ($table_name !== 'FuzzMarkerTable') {
                            @$mysql->query("DROP TABLE IF EXISTS `{$table_name}`");
                        }
                    }
                }
            }
            
            // Clear marker table
            @$mysql->query("TRUNCATE TABLE FuzzMarkerTable");
        } else {
            $result = @mysqli_query($mysql, "SHOW TABLES");
            if ($result) {
                while ($row = mysqli_fetch_array($result)) {
                    $table_name = $row[0];
                    if (strpos($table_name, 'Fuzz') === 0 || strpos($table_name, 'Devil') !== false) {
                        if ($table_name !== 'FuzzMarkerTable') {
                            @mysqli_query($mysql, "DROP TABLE IF EXISTS `{$table_name}`");
                        }
                    }
                }
            }
            
            @mysqli_query($mysql, "TRUNCATE TABLE FuzzMarkerTable");
        }
    } catch (Throwable $e) {
        // Silently fail
    }
}

?>
