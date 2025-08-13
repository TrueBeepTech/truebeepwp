<?php

namespace Truebeep\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple rate limiter using WordPress transients
 */
class RateLimiter
{
    /**
     * Check if action is rate limited
     *
     * @param string $action Action identifier
     * @param string $identifier User identifier (IP or user ID)
     * @param int $max_attempts Maximum attempts allowed
     * @param int $window Time window in seconds
     * @return bool True if rate limited, false if allowed
     */
    public static function is_rate_limited($action, $identifier, $max_attempts = 5, $window = 60)
    {
        $key = 'truebeep_rate_' . md5($action . '_' . $identifier);
        $attempts = get_transient($key);
        
        if ($attempts === false) {
            set_transient($key, 1, $window);
            return false;
        }
        
        if ($attempts >= $max_attempts) {
            return true;
        }
        
        set_transient($key, $attempts + 1, $window);
        return false;
    }
    
    /**
     * Reset rate limit for an action
     *
     * @param string $action Action identifier
     * @param string $identifier User identifier
     */
    public static function reset($action, $identifier)
    {
        $key = 'truebeep_rate_' . md5($action . '_' . $identifier);
        delete_transient($key);
    }
    
    /**
     * Get user identifier for rate limiting
     *
     * @return string
     */
    public static function get_identifier()
    {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        
        return 'ip_' . self::get_client_ip();
    }
    
    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip()
    {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = filter_var($_SERVER[$key], FILTER_VALIDATE_IP);
                if ($ip !== false) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
}