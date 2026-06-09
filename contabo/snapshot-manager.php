<?php
/**
 * Contabo Snapshot Manager
 * 
 * Manages daily snapshots for Contabo instances:
 * - Lists existing snapshots
 * - Deletes oldest snapshots when limit exceeded
 * - Creates new daily snapshots
 * 
 * @author newstargeted.com
 * @version 1.1
 * @since 2024-01-01
 */

define('CONTABO_SNAPSHOT_INIT', true);

// Load configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/snapshot-names.php';

// Ensure log directory exists
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// Lock file to prevent concurrent execution
define('LOCK_FILE', LOG_DIR . '/snapshot-manager.lock');

class ContaboSnapshotManager {
    private $accessToken;
    private $instances = [];
    private $lockFileHandle = null;
    private $stats = [
        'snapshots_created' => 0,
        'snapshots_deleted' => 0,
        'errors' => 0,
        'instances_processed' => 0,
        'total_snapshots' => 0,
        'snapshot_details' => []
    ];
    
    public function __construct() {
        // Acquire lock before starting
        if (!$this->acquireLock()) {
            $this->error("Another instance is already running. Exiting.");
            exit(1);
        }
        
        $this->log("Starting Contabo Snapshot Manager");
        $this->authenticate();
        $this->loadInstances();
        contabo_validate_snapshot_name_config(function ($msg) {
            $this->log($msg);
        });
    }
    
    /**
     * Acquire lock file to prevent concurrent execution
     */
    private function acquireLock() {
        // Check if lock file exists and if it's stale (older than 1 hour)
        if (file_exists(LOCK_FILE)) {
            $lockAge = time() - filemtime(LOCK_FILE);
            if ($lockAge > 3600) { // 1 hour
                $this->log("Removing stale lock file (age: {$lockAge}s)");
                @unlink(LOCK_FILE);
            } else {
                // Lock exists and is not stale
                return false;
            }
        }
        
        // Create lock file
        $this->lockFileHandle = fopen(LOCK_FILE, 'w');
        if (!$this->lockFileHandle) {
            $this->error("Failed to create lock file");
            return false;
        }
        
        // Try to acquire exclusive lock (non-blocking)
        if (!flock($this->lockFileHandle, LOCK_EX | LOCK_NB)) {
            fclose($this->lockFileHandle);
            $this->lockFileHandle = null;
            return false;
        }
        
        // Write PID to lock file
        fwrite($this->lockFileHandle, getmypid() . "\n" . date('Y-m-d H:i:s') . "\n");
        fflush($this->lockFileHandle);
        
        // Register shutdown function to release lock
        register_shutdown_function([$this, 'releaseLock']);
        
        return true;
    }
    
    /**
     * Release lock file
     */
    public function releaseLock() {
        if ($this->lockFileHandle) {
            flock($this->lockFileHandle, LOCK_UN);
            fclose($this->lockFileHandle);
            $this->lockFileHandle = null;
        }
        if (file_exists(LOCK_FILE)) {
            @unlink(LOCK_FILE);
        }
    }
    
    /**
     * Authenticate with Contabo API
     */
    private function authenticate() {
        $this->log("Authenticating with Contabo API...");
        
        $data = [
            'grant_type' => 'password',
            'client_id' => CONTABO_CLIENT_ID,
            'client_secret' => CONTABO_CLIENT_SECRET,
            'username' => CONTABO_API_USER,
            'password' => CONTABO_API_PASSWORD
        ];
        
        $response = $this->makeRequest(CONTABO_AUTH_URL, 'POST', $data, false, true);
        
        if ($response && isset($response['access_token'])) {
            $this->accessToken = $response['access_token'];
            $this->log("Authentication successful");
        } else {
            $this->error("Authentication failed: " . json_encode($response));
            exit(1);
        }
    }
    
    /**
     * Load all instances and filter by our target instances
     */
    private function loadInstances() {
        $this->log("Loading instances...");
        
        $response = $this->makeRequest(CONTABO_API_BASE_URL . '/compute/instances');
        
        if ($response && isset($response['data'])) {
            foreach ($response['data'] as $instance) {
                if (in_array($instance['displayName'], INSTANCE_NAMES)) {
                    $instanceId = $instance['instanceId'] ?? $instance['id'];
                    $this->instances[$instanceId] = [
                        'id' => $instanceId,
                        'name' => $instance['displayName'],
                        'status' => $instance['status']
                    ];
                    $this->log("Found target instance: {$instance['displayName']} (ID: $instanceId)");
                }
            }
        } else {
            $this->error("Failed to load instances: " . json_encode($response));
            exit(1);
        }
        
        if (empty($this->instances)) {
            $this->error("No target instances found");
            exit(1);
        }
    }
    
    /**
     * Main execution method
     */
    public function run() {
        $this->log("Starting snapshot management for " . count($this->instances) . " instances");
        
        foreach ($this->instances as $instanceId => $instance) {
            $this->log("Processing instance: {$instance['name']} (ID: $instanceId)");
            
            try {
                // Get existing snapshots for this instance
                $snapshots = $this->getSnapshots($instanceId);
                $this->log("Found " . count($snapshots) . " existing snapshots for {$instance['name']}");
                
                // Delete oldest snapshots if we exceed the limit
                $this->cleanupOldSnapshots($instanceId, $snapshots);
                
                // Create new snapshot
                $this->createSnapshot($instanceId, $instance['name']);
                
                // Increment instances processed
                $this->stats['instances_processed']++;
                
            } catch (Exception $e) {
                $this->error("Error processing instance {$instance['name']}: " . $e->getMessage());
                $this->stats['errors']++;
            }
        }
        
        // Calculate total snapshot count across all instances and collect snapshot details
        $totalSnapshots = 0;
        $allSnapshots = [];
        foreach ($this->instances as $instanceId => $instance) {
            try {
                $snapshots = $this->getSnapshots($instanceId);
                $totalSnapshots += count($snapshots);
                $this->log("Final snapshot count for {$instance['name']}: " . count($snapshots));
                
                // Collect snapshot details for Discord embed
                foreach ($snapshots as $snapshot) {
                    $allSnapshots[] = [
                        'name' => $snapshot['name'] ?? 'Unknown',
                        'createdDate' => $snapshot['createdDate'] ?? null,
                        'autoDeleteDate' => $snapshot['autoDeleteDate'] ?? null,
                        'instanceName' => $instance['name']
                    ];
                }
            } catch (Exception $e) {
                $this->error("Error getting final snapshot count for {$instance['name']}: " . $e->getMessage());
            }
        }
        $this->stats['total_snapshots'] = $totalSnapshots;
        $this->stats['snapshot_details'] = $allSnapshots;
        $this->log("Total snapshots across all instances: $totalSnapshots");
        
        $this->log("Snapshot management completed");
        
        // Send Discord notification
        $success = $this->stats['errors'] === 0;
        $this->sendDiscordNotification($success);
    }
    
    /**
     * Get all snapshots for a specific instance
     */
    private function getSnapshots($instanceId) {
        $response = $this->makeRequest(CONTABO_API_BASE_URL . '/compute/instances/' . $instanceId . '/snapshots');
        
        if ($response && isset($response['data'])) {
            $instanceSnapshots = $response['data'];
            
            // Sort by creation date (oldest first)
            usort($instanceSnapshots, function($a, $b) {
                return strtotime($a['createdDate']) - strtotime($b['createdDate']);
            });
            
            return $instanceSnapshots;
        }
        
        return [];
    }
    
    /**
     * Delete oldest snapshots if we exceed the limit
     */
    private function cleanupOldSnapshots($instanceId, $snapshots) {
        $snapshotsToDelete = count($snapshots) - MAX_SNAPSHOTS_PER_INSTANCE + 1;
        
        if ($snapshotsToDelete > 0) {
            $this->log("Need to delete $snapshotsToDelete old snapshots for instance $instanceId");
            
            for ($i = 0; $i < $snapshotsToDelete; $i++) {
                $snapshot = $snapshots[$i];
                $this->deleteSnapshot($snapshot['snapshotId'], $snapshot['name']);
            }
            
            // Wait a moment for API to process deletions
            sleep(2);
            
            // Verify deletions by re-fetching snapshots
            $remainingSnapshots = $this->getSnapshots($instanceId);
            $this->log("After cleanup: " . count($remainingSnapshots) . " snapshots remaining");
            
            if (count($remainingSnapshots) >= MAX_SNAPSHOTS_PER_INSTANCE) {
                $this->error("Warning: Still have " . count($remainingSnapshots) . " snapshots after cleanup. Limit is " . MAX_SNAPSHOTS_PER_INSTANCE);
            }
        }
    }
    
    /**
     * Get instance ID for a snapshot (we know it's our target instance)
     */
    private function getInstanceIdForSnapshot($snapshotId) {
        // Since we only have one instance, return its ID
        return array_keys($this->instances)[0];
    }
    
    /**
     * Resolve next snapshot name from config.php (SNAPSHOT_NAMES).
     */
    private function getNextSnapshotName($instanceId, $instanceName) {
        $snapshots = $this->getSnapshots($instanceId);
        $existingNames = [];
        foreach ($snapshots as $snapshot) {
            if (!empty($snapshot['name'])) {
                $existingNames[] = $snapshot['name'];
            }
        }

        $name = contabo_pick_next_snapshot_name($instanceName, $existingNames);
        if ($name === null || $name === '') {
            $this->error('No snapshot names configured for instance: ' . $instanceName);
            return null;
        }

        return $name;
    }

    /**
     * Delete a specific snapshot
     */
    private function deleteSnapshot($snapshotId, $snapshotName) {
        $this->log("Deleting snapshot: $snapshotName (ID: $snapshotId)");
        
        $instanceId = $this->getInstanceIdForSnapshot($snapshotId);
        $response = $this->makeRequest(
            CONTABO_API_BASE_URL . "/compute/instances/$instanceId/snapshots/$snapshotId",
            'DELETE',
            ['confirm' => true] // Non-empty JSON body
        );
        
        if ($response !== false) {
            $this->log("Successfully deleted snapshot: $snapshotName");
            $this->stats['snapshots_deleted']++;
        } else {
            $this->error("Failed to delete snapshot: $snapshotName");
            $this->stats['errors']++;
        }
    }
    
    /**
     * Create a new snapshot for an instance
     */
    private function createSnapshot($instanceId, $instanceName) {
        // First, verify current snapshot count
        $currentSnapshots = $this->getSnapshots($instanceId);
        $currentCount = count($currentSnapshots);
        
        $this->log("Current snapshot count: $currentCount (limit: " . MAX_SNAPSHOTS_PER_INSTANCE . ")");
        
        if ($currentCount >= MAX_SNAPSHOTS_PER_INSTANCE) {
            $this->error("Cannot create snapshot: Already at limit ($currentCount/" . MAX_SNAPSHOTS_PER_INSTANCE . ")");
            $this->log("Attempting to delete oldest snapshot and retry...");
            
            // Try to delete the oldest snapshot
            if (!empty($currentSnapshots)) {
                $oldest = $currentSnapshots[0];
                $this->deleteSnapshot($oldest['snapshotId'], $oldest['name']);
                sleep(2); // Wait for API to process
                
                // Re-check snapshot count
                $currentSnapshots = $this->getSnapshots($instanceId);
                $currentCount = count($currentSnapshots);
                $this->log("After emergency cleanup: $currentCount snapshots remaining");
            }
            
            if ($currentCount >= MAX_SNAPSHOTS_PER_INSTANCE) {
                $this->error("Still at snapshot limit after cleanup. Skipping snapshot creation.");
                $this->stats['errors']++;
                return false;
            }
        }
        
        $snapshotName = $this->getNextSnapshotName($instanceId, $instanceName);
        if ($snapshotName === null) {
            $this->stats['errors']++;
            return false;
        }
        
        $this->log("Creating snapshot: $snapshotName for instance: $instanceName");
        
        $data = [
            'name' => $snapshotName,
            'description' => contabo_format_snapshot_description()
        ];
        
        $response = $this->makeRequest(
            CONTABO_API_BASE_URL . "/compute/instances/" . $instanceId . "/snapshots",
            'POST',
            $data
        );
        
        if ($response && isset($response['data'][0]['snapshotId'])) {
            $snapshotId = $response['data'][0]['snapshotId'];
            $this->log("Successfully created snapshot: $snapshotName (ID: $snapshotId)");
            $this->stats['snapshots_created']++;
            return true;
        } else {
            // Check if it's a 402 error (limit exceeded)
            if (is_array($response) && isset($response['message']) && strpos($response['message'], 'exceed') !== false) {
                $this->error("Snapshot limit exceeded. Current count: $currentCount. Response: " . json_encode($response));
                $this->log("Attempting to clean up and retry...");
                
                // Try one more cleanup
                $currentSnapshots = $this->getSnapshots($instanceId);
                if (count($currentSnapshots) > 0) {
                    $oldest = $currentSnapshots[0];
                    $this->deleteSnapshot($oldest['snapshotId'], $oldest['name']);
                    sleep(3);
                    
                    // Retry creation
                    $retryResponse = $this->makeRequest(
                        CONTABO_API_BASE_URL . "/compute/instances/" . $instanceId . "/snapshots",
                        'POST',
                        $data
                    );
                    
                    if ($retryResponse && isset($retryResponse['data'][0]['snapshotId'])) {
                        $snapshotId = $retryResponse['data'][0]['snapshotId'];
                        $this->log("Successfully created snapshot after retry: $snapshotName (ID: $snapshotId)");
                        $this->stats['snapshots_created']++;
                        return true;
                    }
                }
            }
            
            $this->error("Failed to create snapshot: $snapshotName - " . json_encode($response));
            $this->stats['errors']++;
            return false;
        }
    }
    
    /**
     * Make HTTP request to Contabo API
     */
    private function makeRequest($url, $method = 'GET', $data = null, $useAuth = true, $isFormData = false) {
        $ch = curl_init();
        
        $headers = [
            'x-request-id: ' . $this->generateRequestId()
        ];
        
        if ($isFormData) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } else {
            $headers[] = 'Content-Type: application/json';
        }
        
        if ($useAuth && $this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                if ($isFormData) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->error("cURL error: $error");
            return false;
        }
        
        if ($httpCode >= 400) {
            $this->error("HTTP error $httpCode: $response");
            return false;
        }
        
        $decodedResponse = json_decode($response, true);
        return $decodedResponse !== null ? $decodedResponse : $response;
    }
    
    /**
     * Generate unique request ID
     */
    private function generateRequestId() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Send Discord webhook notification
     */
    private function sendDiscordNotification($success = true) {
        if (!DISCORD_WEBHOOK_ENABLED || empty(DISCORD_WEBHOOK_URL)) {
            return;
        }
        require_once dirname(__DIR__) . '/lib/discord_notify_contabo.php';
        $useCv2 = defined('DISCORD_USE_CV2') ? DISCORD_USE_CV2 : true;
        $result = discord_notify_contabo_send([
            'webhook_url' => DISCORD_WEBHOOK_URL,
            'use_cv2' => $useCv2,
            'success' => $success,
            'stats' => $this->stats,
            'instances' => array_values($this->instances),
            'timezone' => defined('TIMEZONE') ? TIMEZONE : 'Europe/Oslo',
            'hero_image_url' => defined('DISCORD_HERO_IMAGE_URL') ? DISCORD_HERO_IMAGE_URL : 'https://newstargeted.com/assets/status-cv2/contabo.png',
            'username' => defined('DISCORD_WEBHOOK_USERNAME') ? DISCORD_WEBHOOK_USERNAME : 'Contabo Snapshot Manager',
            'panel_url' => defined('CONTABO_PANEL_URL') ? CONTABO_PANEL_URL : '',
            'error_log_path' => defined('ERROR_LOG_FILE') ? ERROR_LOG_FILE : '',
        ]);
        if (!empty($result['ok'])) {
            $this->log('Discord notification sent successfully');
            return;
        }
        $message = $result['error'] ?? ('HTTP ' . ($result['http_code'] ?? 'unknown'));
        $this->log('Discord webhook failed: ' . $message);
    }
    
    /**
     * Log message
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
        echo $logMessage;
    }
    
    /**
     * Log error message
     */
    private function error($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] ERROR: $message" . PHP_EOL;
        file_put_contents(ERROR_LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
        file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
        echo $logMessage;
    }
}

// Main execution
try {
    $manager = new ContaboSnapshotManager();
    $manager->run();
    $manager->releaseLock();
} catch (Exception $e) {
    error_log("Fatal error in snapshot manager: " . $e->getMessage());
    // Release lock on error
    if (isset($manager)) {
        $manager->releaseLock();
    }
    exit(1);
}
?>
