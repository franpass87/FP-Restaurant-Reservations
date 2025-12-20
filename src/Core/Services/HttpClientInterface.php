<?php

declare(strict_types=1);

namespace FP\Resv\Core\Services;

/**
 * HTTP Client Interface
 * 
 * Provides abstraction for HTTP requests.
 *
 * @package FP\Resv\Core\Services
 */
interface HttpClientInterface
{
    /**
     * Make a GET request
     * 
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, response: array<string, mixed>, error?: \WP_Error}
     */
    public function get(string $url, array $args = []): array;
    
    /**
     * Make a POST request
     * 
     * @param string $url Request URL
     * @param array<string, mixed> $body Request body
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, response: array<string, mixed>, error?: \WP_Error}
     */
    public function post(string $url, array $body = [], array $args = []): array;
    
    /**
     * Make a PUT request
     * 
     * @param string $url Request URL
     * @param array<string, mixed> $body Request body
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, response: array<string, mixed>, error?: \WP_Error}
     */
    public function put(string $url, array $body = [], array $args = []): array;
    
    /**
     * Make a DELETE request
     * 
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, response: array<string, mixed>, error?: \WP_Error}
     */
    public function delete(string $url, array $args = []): array;
    
    /**
     * Make a generic request
     * 
     * @param string $url Request URL
     * @param array<string, mixed> $args Request arguments
     * @return array{body: string, response: array<string, mixed>, error?: \WP_Error}
     */
    public function request(string $url, array $args = []): array;
}
