<?php
/**
 * GitHub Connection Test Script
 * 
 * Run this script directly to test GitHub connectivity
 * Usage: php test-github-connection.php
 */

// Load configuration
function load_github_config() {
    $config_file = __DIR__ . '/github-config.php';
    if (file_exists($config_file)) {
        $config = include $config_file;
        
        // Parse repository URL if provided
        if (!empty($config['repository_url'])) {
            $url = rtrim($config['repository_url'], '/');
            $url = preg_replace('/\.git$/', '', $url);
            
            if (preg_match('/github\.com[\/:]([^\/]+)\/([^\/]+)/', $url, $matches)) {
                $config['username'] = $matches[1];
                $config['repository'] = $matches[2];
            }
        }
        
        return $config;
    }
    
    // Default fallback
    return [
        'username' => 'wildrain',
        'repository' => 'tbpublic',
        'repository_url' => 'https://github.com/wildrain/tbpublic'
    ];
}

// Get configuration
$github_config = load_github_config();

// Test functions
function test_github_connectivity() {
    global $github_config;
    
    echo "Testing GitHub Connectivity...\n";
    echo "Repository: " . $github_config['repository_url'] . "\n";
    echo str_repeat("-", 50) . "\n";
    
    // Test 1: Basic connectivity
    echo "1. Testing basic GitHub connectivity...\n";
    $start = microtime(true);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://github.com');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    if ($error) {
        echo "   ❌ FAILED: $error (${duration}ms)\n";
    } else {
        echo "   ✅ SUCCESS: HTTP $http_code (${duration}ms)\n";
    }
    
    // Test 2: GitHub API
    echo "\n2. Testing GitHub API access...\n";
    $start = microtime(true);
    
    $api_url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', 
        $github_config['username'], 
        $github_config['repository']
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.github.v3+json',
        'User-Agent: Truebeep-Test/1.0'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    if ($error) {
        echo "   ❌ FAILED: $error (${duration}ms)\n";
    } else {
        $data = json_decode($response, true);
        if ($http_code == 200 && isset($data['tag_name'])) {
            echo "   ✅ SUCCESS: Found version {$data['tag_name']} (${duration}ms)\n";
        } elseif ($http_code == 403) {
            echo "   ⚠️  WARNING: Rate limited (HTTP 403) - this is normal (${duration}ms)\n";
        } else {
            echo "   ❌ FAILED: HTTP $http_code (${duration}ms)\n";
        }
    }
    
    // Test 3: Download capability
    echo "\n3. Testing download capability...\n";
    $start = microtime(true);
    
    $download_url = sprintf('https://github.com/%s/%s/archive/refs/heads/master.zip',
        $github_config['username'],
        $github_config['repository']
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $download_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RANGE, '0-1023'); // Only download first 1KB
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $download_size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
    curl_close($ch);
    
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    if ($error) {
        echo "   ❌ FAILED: $error (${duration}ms)\n";
    } else {
        if (in_array($http_code, [200, 206])) {
            echo "   ✅ SUCCESS: Downloaded {$download_size} bytes (${duration}ms)\n";
        } else {
            echo "   ❌ FAILED: HTTP $http_code (${duration}ms)\n";
        }
    }
    
    // Test 4: DNS resolution
    echo "\n4. Testing DNS resolution...\n";
    $domains = ['github.com', 'api.github.com'];
    foreach ($domains as $domain) {
        $ip = gethostbyname($domain);
        if ($ip !== $domain) {
            echo "   ✅ $domain → $ip\n";
        } else {
            echo "   ❌ $domain → DNS resolution failed\n";
        }
    }
    
    // Test 5: System info
    echo "\n5. System Information:\n";
    echo "   PHP Version: " . PHP_VERSION . "\n";
    echo "   cURL Version: " . (function_exists('curl_version') ? curl_version()['version'] : 'Not available') . "\n";
    echo "   OpenSSL: " . (defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'Not available') . "\n";
    
    echo "\n" . str_repeat("-", 50) . "\n";
    echo "Test completed!\n";
}

// Run the test
if (php_sapi_name() === 'cli') {
    test_github_connectivity();
} else {
    echo "<pre>";
    test_github_connectivity();
    echo "</pre>";
}
?>