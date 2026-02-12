<?php
/**
 * Web-Based Migration Tool: Backfill Loan Codes
 * 
 * Access this file via: http://your-domain/admin/Loan-Portfolio-Risk-Management/migrate_loan_codes_web.php
 * 
 * SECURITY: This script should only be accessible to administrators
 */

require_once(__DIR__ . '/../../initialize_coreT2.php');
require_once(__DIR__ . '/../inc/sess_auth.php');
require_once(__DIR__ . '/../inc/check_auth.php');

if (session_status() === PHP_SESSION_NONE) session_start();

// Only allow administrators to run this
// Add your admin check here if needed
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
//     die('Access denied: Administrator privileges required');
// }

$step = isset($_GET['step']) ? $_GET['step'] : 'preview';
$confirmed = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Code Migration Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 1000px;
            margin: 50px auto;
        }
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
        }
        .log-output {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 500px;
            overflow-y: auto;
        }
        .log-success { color: #4ec9b0; }
        .log-error { color: #f48771; }
        .log-warning { color: #dcdcaa; }
        .log-info { color: #9cdcfe; }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-bottom: 15px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        .btn-migrate {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: bold;
        }
        .btn-migrate:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center mb-4">
            <h1><i class="bi bi-database-gear"></i> Loan Code Migration Tool</h1>
            <p class="text-muted">Backfill loan_code for existing loans and update payment schedules</p>
        </div>

        <?php if ($step === 'preview'): ?>
            <!-- PREVIEW STEP -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-eye"></i> Migration Preview
                </div>
                <div class="card-body">
                    <?php
                    // Get statistics
                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM loan_portfolio");
                    $stmt->execute();
                    $total_loans = (int)$stmt->get_result()->fetch_assoc()['total'];
                    $stmt->close();

                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM loan_portfolio WHERE loan_code IS NULL OR loan_code = ''");
                    $stmt->execute();
                    $loans_without_code = (int)$stmt->get_result()->fetch_assoc()['total'];
                    $stmt->close();

                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM loan_schedule");
                    $stmt->execute();
                    $total_schedules = (int)$stmt->get_result()->fetch_assoc()['total'];
                    $stmt->close();

                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM loan_schedule WHERE loan_code IS NULL OR loan_code = ''");
                    $stmt->execute();
                    $schedules_without_code = (int)$stmt->get_result()->fetch_assoc()['total'];
                    $stmt->close();
                    ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="stat-box">
                                <div class="stat-number"><?php echo $total_loans; ?></div>
                                <div class="text-muted">Total Loans</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-box">
                                <div class="stat-number text-warning"><?php echo $loans_without_code; ?></div>
                                <div class="text-muted">Loans Without loan_code</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-box">
                                <div class="stat-number"><?php echo $total_schedules; ?></div>
                                <div class="text-muted">Total Payment Schedules</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-box">
                                <div class="stat-number text-warning"><?php echo $schedules_without_code; ?></div>
                                <div class="text-muted">Schedules Without loan_code</div>
                            </div>
                        </div>
                    </div>

                    <?php if ($loans_without_code > 0): ?>
                        <div class="warning-box">
                            <h5><i class="bi bi-exclamation-triangle"></i> Important Information</h5>
                            <ul class="mb-0">
                                <li>This migration will generate <strong>loan_code</strong> for <?php echo $loans_without_code; ?> existing loans</li>
                                <li>Format: <code>LN-YYYY-XXXX</code> (e.g., LN-2025-0027)</li>
                                <li>All loan_schedule records will be updated with corresponding loan_code</li>
                                <li>The operation is wrapped in a transaction (safe rollback on errors)</li>
                                <li><strong>Recommendation:</strong> Backup your database before proceeding</li>
                            </ul>
                        </div>

                        <h5 class="mt-4">Preview of loans to be updated:</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Loan ID</th>
                                        <th>Member</th>
                                        <th>Type</th>
                                        <th>Current Code</th>
                                        <th>New Code</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("
                                        SELECT l.loan_id, l.loan_code, l.start_date, l.loan_type, l.created_at,
                                               COALESCE(m.full_name, 'Unknown') as member_name
                                        FROM loan_portfolio l
                                        LEFT JOIN members m ON m.member_id = l.member_id
                                        WHERE l.loan_code IS NULL OR l.loan_code = ''
                                        ORDER BY l.loan_id ASC
                                        LIMIT 20
                                    ");
                                    $stmt->execute();
                                    $preview_loans = $stmt->get_result();
                                    $stmt->close();

                                    while ($loan = $preview_loans->fetch_assoc()) {
                                        $year = date('Y');
                                        if (!empty($loan['start_date'])) {
                                            $year = date('Y', strtotime($loan['start_date']));
                                        } elseif (!empty($loan['created_at'])) {
                                            $year = date('Y', strtotime($loan['created_at']));
                                        }
                                        $new_code = sprintf('LN-%s-%04d', $year, $loan['loan_id']);
                                        
                                        echo "<tr>";
                                        echo "<td>{$loan['loan_id']}</td>";
                                        echo "<td>" . htmlspecialchars($loan['member_name']) . "</td>";
                                        echo "<td>" . htmlspecialchars($loan['loan_type']) . "</td>";
                                        echo "<td><span class='text-muted'>None</span></td>";
                                        echo "<td><code>{$new_code}</code></td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                            <?php if ($loans_without_code > 20): ?>
                                <p class="text-muted text-center">... and <?php echo $loans_without_code - 20; ?> more loans</p>
                            <?php endif; ?>
                        </div>

                        <form method="POST" action="?step=execute" class="text-center mt-4">
                            <div class="form-check d-inline-block mb-3">
                                <input class="form-check-input" type="checkbox" id="confirmCheck" name="confirm" value="yes" required>
                                <label class="form-check-label" for="confirmCheck">
                                    I have backed up my database and understand this will modify <?php echo $loans_without_code; ?> loan records
                                </label>
                            </div>
                            <br>
                            <button type="submit" class="btn btn-primary btn-migrate btn-lg">
                                <i class="bi bi-play-circle"></i> Run Migration
                            </button>
                            <a href="../Dashboard/" class="btn btn-secondary btn-lg ms-2">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> <strong>All loans already have loan_code!</strong>
                            <p class="mb-0 mt-2">No migration needed. All your loans are properly configured.</p>
                        </div>
                        <div class="text-center">
                            <a href="../Dashboard/" class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($step === 'execute' && $confirmed): ?>
            <!-- EXECUTION STEP -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-gear-fill"></i> Migration in Progress...
                </div>
                <div class="card-body">
                    <div class="log-output" id="logOutput">
                        <?php
                        // Execute migration
                        $logs = [];
                        $errors = [];
                        
                        function addLog($message, $type = 'info') {
                            global $logs;
                            $timestamp = date('H:i:s');
                            $logs[] = ['time' => $timestamp, 'type' => $type, 'msg' => $message];
                            echo "<div class='log-$type'>[$timestamp] [$type] $message</div>";
                            flush();
                            ob_flush();
                        }

                        try {
                            $conn->begin_transaction();
                            
                            addLog("Migration started", 'success');
                            
                            // Step 1: Generate loan codes
                            addLog("Fetching loans without loan_code...", 'info');
                            $stmt = $conn->prepare("
                                SELECT loan_id, start_date, created_at 
                                FROM loan_portfolio 
                                WHERE loan_code IS NULL OR loan_code = ''
                                ORDER BY loan_id ASC
                            ");
                            $stmt->execute();
                            $loans = $stmt->get_result();
                            $stmt->close();
                            
                            $updated = 0;
                            $skipped = 0;
                            
                            while ($loan = $loans->fetch_assoc()) {
                                $loan_id = $loan['loan_id'];
                                $year = date('Y');
                                
                                if (!empty($loan['start_date'])) {
                                    $year = date('Y', strtotime($loan['start_date']));
                                } elseif (!empty($loan['created_at'])) {
                                    $year = date('Y', strtotime($loan['created_at']));
                                }
                                
                                $loan_code = sprintf('LN-%s-%04d', $year, $loan_id);
                                
                                // Check for duplicates
                                $check = $conn->prepare("SELECT COUNT(*) as c FROM loan_portfolio WHERE loan_code = ?");
                                $check->bind_param('s', $loan_code);
                                $check->execute();
                                $exists = (int)$check->get_result()->fetch_assoc()['c'];
                                $check->close();
                                
                                if ($exists > 0) {
                                    addLog("Skipped loan_id $loan_id - code already exists", 'warning');
                                    $skipped++;
                                    continue;
                                }
                                
                                // Update loan
                                $update = $conn->prepare("UPDATE loan_portfolio SET loan_code = ? WHERE loan_id = ?");
                                $update->bind_param('si', $loan_code, $loan_id);
                                
                                if ($update->execute()) {
                                    addLog("✓ Generated $loan_code for loan_id $loan_id", 'success');
                                    $updated++;
                                } else {
                                    addLog("✗ Failed to update loan_id $loan_id", 'error');
                                    $errors[] = "Update failed for loan_id $loan_id";
                                }
                                $update->close();
                            }
                            
                            addLog("Updated $updated loans, skipped $skipped", 'info');
                            
                            // Step 2: Check/add loan_code column to loan_schedule
                            addLog("Checking loan_schedule table structure...", 'info');
                            $check_col = $conn->query("SHOW COLUMNS FROM loan_schedule LIKE 'loan_code'");
                            if ($check_col->num_rows === 0) {
                                addLog("Adding loan_code column to loan_schedule...", 'warning');
                                $conn->query("ALTER TABLE loan_schedule ADD COLUMN loan_code VARCHAR(50) NULL AFTER schedule_id");
                                addLog("✓ Column added successfully", 'success');
                            } else {
                                addLog("✓ Column already exists", 'info');
                            }
                            
                            // Step 3: Update schedules
                            addLog("Updating loan_schedule records...", 'info');
                            $stmt = $conn->prepare("
                                UPDATE loan_schedule ls
                                INNER JOIN loan_portfolio lp ON ls.loan_id = lp.loan_id
                                SET ls.loan_code = lp.loan_code
                                WHERE (ls.loan_code IS NULL OR ls.loan_code = '')
                                AND lp.loan_code IS NOT NULL
                            ");
                            $stmt->execute();
                            $schedules_updated = $stmt->affected_rows;
                            $stmt->close();
                            
                            addLog("✓ Updated $schedules_updated schedule records", 'success');
                            
                            // Verify
                            addLog("Verifying data integrity...", 'info');
                            $verify = $conn->prepare("SELECT COUNT(*) as c FROM loan_portfolio WHERE loan_code IS NULL OR loan_code = ''");
                            $verify->execute();
                            $remaining = (int)$verify->get_result()->fetch_assoc()['c'];
                            $verify->close();
                            
                            if ($remaining > 0) {
                                addLog("⚠ Warning: $remaining loans still without loan_code", 'warning');
                            } else {
                                addLog("✓ All loans now have loan_code", 'success');
                            }
                            
                            if (count($errors) > 0) {
                                addLog("Migration failed - rolling back changes", 'error');
                                $conn->rollback();
                                foreach ($errors as $err) {
                                    addLog("Error: $err", 'error');
                                }
                            } else {
                                addLog("Committing changes to database...", 'info');
                                $conn->commit();
                                addLog("=== MIGRATION COMPLETED SUCCESSFULLY ===", 'success');
                            }
                            
                        } catch (Exception $e) {
                            addLog("Fatal error: " . $e->getMessage(), 'error');
                            $conn->rollback();
                            addLog("All changes rolled back", 'error');
                        }
                        ?>
                    </div>

                    <div class="text-center mt-4">
                        <?php if (count($errors) === 0): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill"></i> Migration completed successfully!
                            </div>
                            <a href="index.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-arrow-left"></i> Back to Loan Portfolio
                            </a>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-x-circle-fill"></i> Migration failed. All changes were rolled back.
                            </div>
                            <a href="?step=preview" class="btn btn-warning btn-lg">
                                <i class="bi bi-arrow-clockwise"></i> Try Again
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Invalid access -->
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> Invalid request. Please start from the beginning.
            </div>
            <a href="?step=preview" class="btn btn-primary">Start Migration</a>
        <?php endif; ?>

        <div class="text-center mt-4 text-muted">
            <small>Microfinance EIS - Loan Code Migration Tool v1.0</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>