#!/usr/bin/env php
<?php
/**
 * Contabo Snapshot Restore CLI
 * 
 * Restore the latest cursor snapshot with confirmation
 * 
 * Usage:
 *   php restore-snapshot.php [--instance-id=ID] [--snapshot-name=NAME] [--confirm]
 *   php restore-snapshot.php --help
 * 
 * @author master3395
 * @version 1.0
 */

define('CONTABO_SNAPSHOT_INIT', true);

// Load configuration
require_once __DIR__ . '/config.php';

// Ensure log directory exists
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// Parse command line arguments
$options = [
    'instance-id' => null,
    'snapshot-name' => null,
    'confirm' => false,
    'help' => false,
    'latest-cursor' => false
];

foreach ($argv as $arg) {
    if (preg_match('/^--instance-id=(.+)$/', $arg, $matches)) {
        $options['instance-id'] = $matches[1];
    } elseif (preg_match('/^--snapshot-name=(.+)$/', $arg, $matches)) {
        $options['snapshot-name'] = $matches[1];
    } elseif ($arg === '--confirm') {
        $options['confirm'] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        $options['help'] = true;
    } elseif ($arg === '--latest-cursor') {
        $options['latest-cursor'] = true;
    }
}

// Show help
if ($options['help']) {
    showHelp();
    exit(0);
}

// Validate options
if (!$options['latest-cursor'] && !$options['snapshot-name']) {
    echo "ERROR: Either --latest-cursor or --snapshot-name must be specified.\n";
    echo "Use --help for usage information.\n";
    exit(1);
}

// Get instance ID
$instanceId = $options['instance-id'];
if (!$instanceId) {
    // Try to get from INSTANCE_NAMES
    if (!empty(INSTANCE_NAMES)) {
        $instanceName = INSTANCE_NAMES[0];
        $instanceId = getInstanceIdByName($instanceName);
        if (!$instanceId) {
            echo "ERROR: Could not find instance ID. Please specify --instance-id=ID\n";
            exit(1);
        }
    } else {
        echo "ERROR: Instance ID required. Use --instance-id=ID\n";
        exit(1);
    }
}

// Initialize restore manager
$restoreManager = new SnapshotRestoreManager();

try {
    if ($options['latest-cursor']) {
        // Restore latest cursor snapshot
        $restoreManager->restoreLatestCursor($instanceId, $options['confirm']);
    } else {
        // Restore specific snapshot
        $restoreManager->restoreSnapshot($instanceId, $options['snapshot-name'], $options['confirm']);
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Snapshot restore error: " . $e->getMessage());
    exit(1);
}

/**
 * Get instance ID by name
 */
function getInstanceIdByName($instanceName) {
    $manager = new SnapshotRestoreManager();
    return $manager->getInstanceIdByName($instanceName);
}

/**
 * Show help message
 */
function showHelp() {
    echo "Contabo Snapshot Restore CLI\n";
    echo str_repeat("=", 60) . "\n\n";
    echo "Usage:\n";
    echo "  php restore-snapshot.php [options]\n\n";
    echo "Options:\n";
    echo "  --latest-cursor              Restore the latest cursor snapshot\n";
    echo "  --snapshot-name=NAME        Restore a specific snapshot by name\n";
    echo "  --instance-id=ID            VPS instance ID (optional if configured)\n";
    echo "  --confirm                   Skip confirmation prompt\n";
    echo "  --help, -h                  Show this help message\n\n";
    echo "Examples:\n";
    echo "  php restore-snapshot.php --latest-cursor\n";
    echo "  php restore-snapshot.php --latest-cursor --instance-id=12345\n";
    echo "  php restore-snapshot.php --snapshot-name=cursor-snapshot-2024 --confirm\n";
    echo "  php restore-snapshot.php --latest-cursor --instance-id=12345 --confirm\n\n";
}

/**
 * Snapshot Restore Manager Class
 */
class SnapshotRestoreManager {
    private $accessToken;
    
    public function __construct() {
        $this->authenticate();
    }
    
    /**
     * Authenticate with Contabo API
     */
    private function authenticate() {
        $logFile = LOG_DIR . '/restore-snapshot.log';
        $this->log("Authenticating with Contabo API", $logFile);
        
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => CONTABO_CLIENT_ID,
            'client_secret' => CONTABO_CLIENT_SECRET,
            'username' => CONTABO_API_USER,
            'password' => CONTABO_API_PASSWORD
        ];
        
        $ch = curl_init(CONTABO_AUTH_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL error during authentication: $error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Authentication failed with HTTP $httpCode: $response");
        }
        
        $result = json_decode($response, true);
        if (!isset($result['access_token'])) {
            throw new Exception("Invalid authentication response");
        }
        
        $this->accessToken = $result['access_token'];
        $this->log("Successfully authenticated", $logFile);
    }
    
    /**
     * Get instance ID by name
     */
    public function getInstanceIdByName($instanceName) {
        $instances = $this->listInstances();
        foreach ($instances as $instance) {
            if (isset($instance['name']) && $instance['name'] === $instanceName) {
                return $instance['instanceId'] ?? $instance['id'] ?? null;
            }
        }
        return null;
    }
    
    /**
     * List all instances
     */
    private function listInstances() {
        $response = $this->makeRequest(CONTABO_API_BASE_URL . '/compute/instances');
        return $response['data'] ?? [];
    }
    
    /**
     * Get all snapshots for an instance
     */
    private function getSnapshots($instanceId) {
        $response = $this->makeRequest(CONTABO_API_BASE_URL . '/compute/instances/' . $instanceId . '/snapshots');
        return $response['data'] ?? [];
    }
    
    /**
     * Get latest cursor snapshot
     */
    private function getLatestCursorSnapshot($instanceId) {
        $snapshots = $this->getSnapshots($instanceId);
        
        if (empty($snapshots)) {
            return null;
        }
        
        // Filter cursor snapshots (case-insensitive)
        $cursorSnapshots = array_filter($snapshots, function($snapshot) {
            $name = strtolower($snapshot['name'] ?? '');
            return strpos($name, 'cursor') !== false;
        });
        
        if (empty($cursorSnapshots)) {
            return null;
        }
        
        // Sort by creation date (newest first)
        usort($cursorSnapshots, function($a, $b) {
            $dateA = strtotime($a['createdDate'] ?? '1970-01-01');
            $dateB = strtotime($b['createdDate'] ?? '1970-01-01');
            return $dateB - $dateA; // Descending order
        });
        
        return reset($cursorSnapshots);
    }
    
    /**
     * Restore latest cursor snapshot
     */
    public function restoreLatestCursor($instanceId, $skipConfirm = false) {
        $logFile = LOG_DIR . '/restore-snapshot.log';
        $this->log("Finding latest cursor snapshot for instance ID: $instanceId", $logFile);
        
        $snapshot = $this->getLatestCursorSnapshot($instanceId);
        
        if (!$snapshot) {
            throw new Exception("No cursor snapshots found for instance ID: $instanceId");
        }
        
        $snapshotName = $snapshot['name'] ?? 'Unknown';
        $snapshotId = $snapshot['snapshotId'] ?? $snapshot['id'] ?? null;
        $createdDate = $snapshot['createdDate'] ?? 'Unknown';
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "Latest Cursor Snapshot Found:\n";
        echo str_repeat("=", 60) . "\n";
        echo "  Name: $snapshotName\n";
        echo "  ID: $snapshotId\n";
        echo "  Created: $createdDate\n";
        if (isset($snapshot['description'])) {
            echo "  Description: " . $snapshot['description'] . "\n";
        }
        echo str_repeat("=", 60) . "\n\n";
        
        // Confirmation
        if (!$skipConfirm) {
            echo "WARNING: This will restore the VPS to this snapshot state.\n";
            echo "All current data will be replaced with the snapshot data.\n";
            echo "This action CANNOT be undone!\n\n";
            
            echo "Type 'yes' to confirm, or 'no' to cancel: ";
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($line) !== 'yes') {
                echo "Restore cancelled.\n";
                $this->log("Restore cancelled by user", $logFile);
                exit(0);
            }
        }
        
        // Restore snapshot
        $this->log("Initiating restore of snapshot: $snapshotName (ID: $snapshotId)", $logFile);
        echo "\nInitiating restore...\n";
        
        $result = $this->restoreSnapshotById($instanceId, $snapshotId);
        
        if ($result) {
            echo "\n✓ Restore initiated successfully!\n";
            echo "The VPS will be restored to snapshot: $snapshotName\n";
            echo "This may take several minutes. Check VPS status for progress.\n";
            $this->log("Restore initiated successfully for snapshot: $snapshotName", $logFile);
        } else {
            throw new Exception("Failed to initiate restore. Check logs for details.");
        }
    }
    
    /**
     * Restore specific snapshot
     */
    public function restoreSnapshot($instanceId, $snapshotName, $skipConfirm = false) {
        $logFile = LOG_DIR . '/restore-snapshot.log';
        $this->log("Finding snapshot: $snapshotName for instance ID: $instanceId", $logFile);
        
        $snapshots = $this->getSnapshots($instanceId);
        $snapshot = null;
        
        foreach ($snapshots as $snap) {
            if (($snap['name'] ?? '') === $snapshotName) {
                $snapshot = $snap;
                break;
            }
        }
        
        if (!$snapshot) {
            throw new Exception("Snapshot '$snapshotName' not found for instance ID: $instanceId");
        }
        
        $snapshotId = $snapshot['snapshotId'] ?? $snapshot['id'] ?? null;
        $createdDate = $snapshot['createdDate'] ?? 'Unknown';
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "Snapshot Details:\n";
        echo str_repeat("=", 60) . "\n";
        echo "  Name: $snapshotName\n";
        echo "  ID: $snapshotId\n";
        echo "  Created: $createdDate\n";
        if (isset($snapshot['description'])) {
            echo "  Description: " . $snapshot['description'] . "\n";
        }
        echo str_repeat("=", 60) . "\n\n";
        
        // Confirmation
        if (!$skipConfirm) {
            echo "WARNING: This will restore the VPS to this snapshot state.\n";
            echo "All current data will be replaced with the snapshot data.\n";
            echo "This action CANNOT be undone!\n\n";
            
            echo "Type 'yes' to confirm, or 'no' to cancel: ";
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($line) !== 'yes') {
                echo "Restore cancelled.\n";
                $this->log("Restore cancelled by user", $logFile);
                exit(0);
            }
        }
        
        // Restore snapshot
        $this->log("Initiating restore of snapshot: $snapshotName (ID: $snapshotId)", $logFile);
        echo "\nInitiating restore...\n";
        
        $result = $this->restoreSnapshotById($instanceId, $snapshotId);
        
        if ($result) {
            echo "\n✓ Restore initiated successfully!\n";
            echo "The VPS will be restored to snapshot: $snapshotName\n";
            echo "This may take several minutes. Check VPS status for progress.\n";
            $this->log("Restore initiated successfully for snapshot: $snapshotName", $logFile);
        } else {
            throw new Exception("Failed to initiate restore. Check logs for details.");
        }
    }
    
    /**
     * Restore snapshot by ID
     */
    private function restoreSnapshotById($instanceId, $snapshotId) {
        $url = CONTABO_API_BASE_URL . '/compute/instances/' . $instanceId . '/snapshots/' . $snapshotId . '/restore';
        
        $response = $this->makeRequest($url, 'POST');
        
        // Check if restore was initiated successfully
        if ($response && (isset($response['data']) || isset($response['snapshotId']))) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Make API request
     */
    private function makeRequest($url, $method = 'GET', $data = null) {
        $ch = curl_init();
        
        $headers = [
            'x-request-id: ' . $this->generateRequestId(),
            'Content-Type: application/json'
        ];
        
        if ($this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL error: $error");
        }
        
        if ($httpCode >= 400) {
            $errorMsg = "API request failed with HTTP $httpCode";
            if ($response) {
                $errorData = json_decode($response, true);
                if (isset($errorData['message'])) {
                    $errorMsg .= ": " . $errorData['message'];
                } else {
                    $errorMsg .= ": $response";
                }
            }
            throw new Exception($errorMsg);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Generate request ID
     */
    private function generateRequestId() {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Log message
     */
    private function log($message, $logFile = null) {
        if ($logFile === null) {
            $logFile = LOG_FILE;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}
