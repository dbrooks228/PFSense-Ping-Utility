<?php
// ping.php
$ip = $_GET['ip'] ?? '8.8.8.8'; // Default IP address for testing

// Security check: Validate IP address format
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode(['error' => 'Invalid IP address format.']);
    exit;
}

$logFilePath = "/var/log/dhcpd.log"; // DHCP log file path

// Function to check for a recent DHCPACK for the specific IP address
function isDeviceOnline($logFilePath, $ipAddress) {
    $eightHoursAgo = new DateTime();
    $eightHoursAgo->sub(new DateInterval('PT8H')); // Subtracts 8 hours to see if a lease has been given in that time frame

    if (file_exists($logFilePath) && is_readable($logFilePath)) {
        $lines = file($logFilePath);

        foreach ($lines as $line) {
            // Use regular expression for an exact IP address match
            if (preg_match("/DHCPACK on $ipAddress /", $line)) {
                // Extract the date and time from the log entry
                $dateString = substr($line, 0, 15); // Example: "Mar 22 11:59:47"
                // Convert log date string to DateTime object
                $year = date('Y');
                $dateTime = DateTime::createFromFormat('M d H:i:s Y', $dateString . ' ' . $year);

                // Ensure DateTime creation was successful and check if the entry is within the last 8 hours
                if ($dateTime !== false && $dateTime >= $eightHoursAgo) {
                    return true; // Device is considered online
                }
            }
        }

        return false; // No recent DHCPACK found for the IP
    } else {
        echo "Error: Unable to read DHCP log file.";
        return false;
    }
}

// Function to ping the IP address and check if the device is alive
function pingDevice($ipAddress) {
    $result = shell_exec("ping -c 2 $ipAddress"); // Ping the IP address
    
    // Determine if the ping was successful based on packet loss
    $alive = strpos($result, '100.0% packet loss') === false;
    
    // Return both the alive status and the ping result for detailed response
    return [
        'alive' => $alive,
        'result' => $alive ? 'Ping successful' : 'Ping failed with 100.0% packet loss',
        'detail' => $result // Return the actual ping output for debugging or information
    ];
}

// First, try checking for a recent DHCPACK for the IP address
if (isDeviceOnline($logFilePath, $ip)) {
    echo json_encode(['alive' => true, 'ip' => $ip, 'method' => 'DHCPACK found', 'detail' => '']);
} else {
    // If no recent DHCPACK, fall back to pinging the device
    $pingResult = pingDevice($ip);
    
    if ($pingResult['alive']) {
        echo json_encode(['alive' => true, 'ip' => $ip, 'method' => 'Ping successful', 'detail' => $pingResult['detail']]);
    } else {
        // Device is considered dead if there's no DHCPACK and ping fails
        echo json_encode(['dead' => true, 'ip' => $ip, 'method' => 'Ping failed', 'detail' => $pingResult['detail']]);
    }
}
?>