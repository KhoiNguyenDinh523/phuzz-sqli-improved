# Error-Free SQLi Detection - Testing Guide

## Overview

This guide walks you through testing the new error-free SQL injection detection feature in PHUZZ. The system detects SQLi attacks that execute successfully without causing database errors by monitoring database side effects.

---

## Prerequisites

- Docker and Docker Compose installed
- Python 3.x with Flask (`pip install flask`)
- PHUZZ codebase with error-free SQLi modifications
- Terminal access (3 terminals recommended)

---

## Step-by-Step Testing Instructions

### **Step 1: Start the OOB Monitoring Server**

Open **Terminal 1** and start the Out-of-Band monitoring server:

```bash
cd /home/ehsan/phuzz/code/fuzzer
python3 oob_server.py
```

**Expected Output:**
```
============================================================
🚀 Starting OOB Monitoring Server for Error-Free SQLi Detection
============================================================
Listening on: http://0.0.0.0:5001
Endpoints:
  POST /report          - Receive side-effect reports
  GET  /check/<id>      - Check if coverage_id has side effects
  POST /reset           - Clear all detections
  GET  /stats           - View detection statistics
  GET  /health          - Health check
============================================================
 * Running on http://0.0.0.0:5001
```

**What to watch for:**
- 🔴 Red messages indicating side effects detected
- Coverage IDs with detected changes
- Tables created, rows inserted, etc.

**Leave this terminal running** - it will display real-time detection logs.

---

### **Step 2: Start Docker Containers**

Open **Terminal 2** and start the database and web containers:

```bash
cd /home/ehsan/phuzz/code

# Start database
sudo docker-compose up -d db --build --force-recreate
sleep 15  # Wait for DB to initialize

# Start web application
sudo docker-compose up -d web --build --force-recreate
sleep 10  # Wait for web server to start
```

**Verify containers are running:**
```bash
sudo docker-compose ps
```

You should see `db` and `web` containers in "Up" status.

---

### **Step 3: Run the Fuzzer**

In **Terminal 2**, start the fuzzer targeting DVWA SQLi endpoint:

```bash
sudo docker-compose up fuzzer-dvwa-sqli-low-1 --build --force-recreate
```

**What happens:**
1. Fuzzer sends mutated requests to DVWA
2. PHP instrumentation monitors database state
3. Side effects are reported to OOB server (Terminal 1)
4. Fuzzer queries OOB server for detections
5. Vulnerabilities are saved to output files

**Let it run for 2-5 minutes** to generate sufficient test cases.

---

### **Step 4: Monitor OOB Server Logs (Terminal 1)**

Watch **Terminal 1** for detection messages:

```
[INFO] 🔴 SIDE EFFECT DETECTED for 1738140123-a1b2c3d4-...
[INFO]    Tables Created: ['FuzzDevilTable_4521']
[INFO]    Rows Inserted: {'FuzzMarkerTable': 1}
[INFO]    Tables Dropped: []
[INFO]    Schema Changes: True
```

**Key indicators:**
- **Tables Created**: New tables with `Fuzz*` or `Devil*` prefix
- **Rows Inserted**: New rows in `FuzzMarkerTable` or other tables
- **Schema Changes**: Database structure modified

---

### **Step 5: Stop the Fuzzer**

After 2-5 minutes, press **Ctrl+C** in Terminal 2 to stop the fuzzer.

---

### **Step 6: Check Results**

View the vulnerability report:

```bash
cd /home/ehsan/phuzz/code
cat fuzzer/output/fuzzer-1/vulnerable-candidates.json | grep -A 20 "ErrorFreeSQLi"
```

**Expected Output:**
```json
{
  "ErrorFreeSQLi": [
    {
      "coverage_id": "1738140123-a1b2c3d4-...",
      "http_target": "http://web/vulnerabilities/sqli/",
      "http_method": "GET",
      "fuzz_params": {
        "query_params": {
          "id": "1'; CREATE TABLE FuzzDevilTable_4521 (id INT); --",
          "Submit": "Submit"
        }
      },
      "side_effects": {
        "tables_created": ["FuzzDevilTable_4521"],
        "rows_inserted": {},
        "schema_changes": true
      }
    }
  ]
}
```

---

## Understanding the Output

### **Vulnerability Report Structure**

```json
{
  "ErrorFreeSQLi": [        // Vulnerability type
    {
      "coverage_id": "...",  // Unique request identifier
      "http_target": "...",  // Target URL
      "http_method": "GET",  // HTTP method
      "fuzz_params": {       // Parameters that triggered the vulnerability
        "query_params": {
          "id": "1'; CREATE TABLE FuzzDevilTable_4521 (id INT); --"
        }
      },
      "side_effects": {      // Detected database changes
        "tables_created": ["FuzzDevilTable_4521"],
        "rows_inserted": {"FuzzMarkerTable": 2},
        "tables_dropped": [],
        "schema_changes": true
      }
    }
  ]
}
```

### **Key Fields Explained**

| Field | Description |
|-------|-------------|
| `ErrorFreeSQLi` | Vulnerability type - SQL injection without errors |
| `coverage_id` | Unique ID linking request to side effects |
| `fuzz_params` | The exact payload that caused the injection |
| `side_effects.tables_created` | New tables created by the injection |
| `side_effects.rows_inserted` | Rows added to existing tables |
| `side_effects.schema_changes` | Whether database schema was modified |

### **Interpreting Side Effects**

**1. Table Creation**
```json
"tables_created": ["FuzzDevilTable_4521", "FuzzTemp_1234"]
```
- Attacker successfully created new tables
- Indicates **high severity** - full SQL execution control

**2. Row Insertion**
```json
"rows_inserted": {"FuzzMarkerTable": 3, "users": 1}
```
- Attacker inserted data into tables
- Could lead to privilege escalation or data manipulation

**3. Schema Changes**
```json
"schema_changes": true
```
- Database structure was modified
- Indicates successful multi-statement injection

---

## Comparing with Traditional SQLi Detection

### **Traditional Error-Based Detection**

```json
{
  "SQLi": [
    {
      "fuzz_params": {
        "query_params": {
          "id": "1' OR '1'='1"  // Causes syntax error
        }
      }
    }
  ]
}
```

**Limitation**: Only detects injections that cause errors.

### **Error-Free Detection (NEW)**

```json
{
  "ErrorFreeSQLi": [
    {
      "fuzz_params": {
        "query_params": {
          "id": "1'; CREATE TABLE FuzzEvil (x INT); --"  // Executes successfully
        }
      },
      "side_effects": {
        "tables_created": ["FuzzEvil"]
      }
    }
  ]
}
```

**Advantage**: Detects successful injections that bypass error-based detection.

---

## Troubleshooting

### **Issue 1: OOB Server Not Receiving Reports**

**Symptoms:**
- Terminal 1 shows no detection messages
- `vulnerable-candidates.json` has no `ErrorFreeSQLi` entries

**Solutions:**
1. Check OOB server is running on port 5001:
   ```bash
   curl http://localhost:5001/health
   ```

2. Verify Docker can reach host:
   ```bash
   sudo docker exec -it code_web_1 curl http://host.docker.internal:5001/health
   ```

3. Check firewall settings (allow port 5001)

---

### **Issue 2: No Side Effects Detected**

**Symptoms:**
- OOB server running but no detections
- Fuzzer completes but no `ErrorFreeSQLi` found

**Possible Causes:**
1. **Database permissions**: MySQL user may not have CREATE TABLE privileges
2. **Payload not reaching DB**: Check if DVWA security level is too high
3. **Insufficient fuzzing time**: Run longer (5-10 minutes)

**Solutions:**
```bash
# Check DVWA security level (should be "low")
# Access: http://localhost/vulnerabilities/sqli/
# Set security to "Low" in DVWA settings

# Verify database permissions
sudo docker exec -it code_db_1 mysql -u root -p
# Password: dvwa
mysql> SHOW GRANTS FOR 'dvwa'@'%';
```

---

### **Issue 3: False Positives**

**Symptoms:**
- Side effects detected on non-vulnerable endpoints
- Tables created during normal operation

**Solutions:**
1. Check if tables are truly malicious (Fuzz* prefix)
2. Review baseline database state
3. Adjust detection thresholds in `db_monitor.php`

---

## Advanced Testing

### **Manual Payload Testing**

Test specific payloads directly:

```bash
# Terminal 3
curl "http://localhost/vulnerabilities/sqli/?id=1';%20CREATE%20TABLE%20TestEvil%20(x%20INT);%20--&Submit=Submit" \
  -H "Cookie: security=low; PHPSESSID=..."
```

Check OOB server logs for detection.

---

### **Viewing OOB Server Statistics**

```bash
curl http://localhost:5001/stats | python3 -m json.tool
```

**Output:**
```json
{
  "total_detections": 15,
  "recent_detections": [
    {
      "coverage_id": "...",
      "side_effects": {...},
      "timestamp": "2026-01-29T14:30:00"
    }
  ]
}
```

---

### **Resetting Detection State**

Clear all detections:

```bash
curl -X POST http://localhost:5001/reset
```

---

## Expected Results Summary

After successful testing, you should see:

✅ **OOB Server Logs**
- Multiple "SIDE EFFECT DETECTED" messages
- Tables created with `Fuzz*` or `Devil*` prefixes
- Rows inserted into `FuzzMarkerTable`

✅ **Fuzzer Output**
- `vulnerable-candidates.json` contains `ErrorFreeSQLi` section
- Multiple vulnerability entries with side effect details
- Coverage IDs matching OOB server logs

✅ **Database State**
- New tables visible in database (can verify with `SHOW TABLES`)
- Marker table has new rows

---

## Cleanup

After testing:

```bash
# Stop all containers
sudo docker-compose down

# Stop OOB server (Ctrl+C in Terminal 1)

# Clean up output
rm -rf fuzzer/output/fuzzer-1/*
```

---

## Next Steps

1. **Analyze Results**: Review detected vulnerabilities
2. **Tune Payloads**: Adjust mutation rates in `mutator.py`
3. **Test Other Apps**: Try against different vulnerable applications
4. **Compare Results**: Run with/without error-free detection enabled

---

## Key Takeaways

- **Error-free SQLi detection** finds vulnerabilities that traditional methods miss
- **OOB server** provides real-time monitoring of database side effects
- **Side effect analysis** reveals the true impact of SQL injections
- **Comprehensive coverage** combines error-based + error-free detection

**Your thesis contribution**: Demonstrating that PHUZZ can detect successful SQL injections that execute without errors, closing a significant research gap.
