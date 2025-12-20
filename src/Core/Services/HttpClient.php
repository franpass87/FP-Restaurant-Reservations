<?php

declare(strict_types=1);

namespace FP\Resv\Core\Services;

use function wp_remote_request;
use function wp_remote_get;
use function wp_remote_post;
use function is_wp_error;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_remote_retrieve_response_message;
use WP_Error;

/**
 * HTTP Client Service
 * 
 * Provides HTTP request functionality using WordPress HTTP API.
 *
 * @package FP\Resv\Core\Services
 */
final class HttpClient implements HttpClientInterface
{
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_RETRIES = 3;
    
    /**
     * Make a GET request
     * 
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, response: array<string, mixed>, error?: \WP_Error}
     */
    public function get(string $url, array $args = []): array
    {
        $args['method'] = 'GET';
        return $this->request($url, $args);
    }
    
    /**
     * Make a POST request
     * 
     * @param string $url Request URL
     * @param array<string, mixed> $body Request body
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, response: array<string, mixed>, error?: \WP_Error}
     */
    public function post(string $url, array $body = [], array $args = []): array
    {
        $args['method'] = 'POST';
        $args['body'] = $body;
        return $this->request($url, $args);
    }
    
    /**
     * Make a PUT request
     * 
     * @param string $url Request URL
     * @param array<string, mixed> $body Request body
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, response: array<string, mixed>, error?: \WP_Error}
     */
    public function put(string $url, array $body = [], array $args = []): array
    {
        $args['method'] = 'PUT';
        $args['body'] = $body;
        return $this->request($url, $args);
    }
    
    /**
     * Make a DELETE request
     * 
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, response: array<string, mixed>, error?: \WP_Error}
     */
    public function delete(string $url, array $args = []): array
    {
        $args['method'] = 'DELETE';
        return $this->request($url, $args);
    }
    
    /**
     * Make a generic request
     * 
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, response: array<string, mixed>, error?: \WP_Error}
     */
    public function request(string $url, array $args = []): array
    {
        // Set default timeout
        if (!isset($args['timeout'])) {
            $args['timeout'] = self::DEFAULT_TIMEOUT;
        }
        
        // Make request with retries
        $retries = $args['retries'] ?? self::DEFAULT_RETRIES;
        unset($args['retries']);
        
        $lastError = null;
        
        for ($i = 0; $i < $retries; $i++) {
            $response = wp_remote_request($url, $args);
            
            if (is_wp_error($response)) {
                $lastError = $response;
                // Wait before retry (exponential backoff)
                if ($i < $retries - 1) {
                    usleep(100000 * ($i + 1)); // 100ms, 200ms, 300ms...
                }
                continue;
            }
            
            // Success
            return [
                'body' => wp_remote_retrieve_body($response),
                'response' => [
                    'code' => wp_remote_retrieve_response_code($response),
                    'message' => wp_remote_retrieve_response_message($response),
                    'headers' => wp_remote_retrieve_headers($response)->getAll(),
                ],
            ];
        }
        
        // All retries failed
        return [
            'body' => '',
            'response' => [
                'code' => 0,
                'message' => '',
            ],
            'error' => $lastError ?? new WP_Error('http_request_failed', 'Request failed after retries'),
        ];
    }
}
