<?php

declare(strict_types=1);

namespace FP\Resv\Presentation\API\REST;

use FP\Resv\Core\Services\LoggerInterface;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Base REST Endpoint
 * 
 * Provides common functionality for all REST endpoints.
 *
 * @package FP\Resv\Presentation\API\REST
 */
abstract class BaseEndpoint
{
    protected LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * Create a success response
     * 
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return WP_REST_Response
     */
    protected function success($data, int $status = 200): WP_REST_Response
    {
        $response = new WP_REST_Response($data, $status);
        $response->set_headers([
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
        return $response;
    }
    
    /**
     * Create an error response
     * 
     * @param string $code Error code
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array<string, mixed> $data Additional error data
     * @return WP_Error
     */
    protected function error(string $code, string $message, int $status = 400, array $data = []): WP_Error
    {
        return new WP_Error($code, $message, array_merge($data, ['status' => $status]));
    }
    
    /**
     * Handle validation exception
     * 
     * @param \FP\Resv\Core\Exceptions\ValidationException $exception Validation exception
     * @return WP_Error
     */
    protected function handleValidationException(\FP\Resv\Core\Exceptions\ValidationException $exception): WP_Error
    {
        $this->logger->warning('Validation failed', [
            'errors' => $exception->getErrors(),
        ]);
        
        return $this->error(
            'validation_failed',
            $exception->getMessage(),
            400,
            ['errors' => $exception->getErrors()]
        );
    }
    
    /**
     * Handle generic exception
     * 
     * @param \Throwable $exception Exception
     * @return WP_Error
     */
    protected function handleException(\Throwable $exception): WP_Error
    {
        $this->logger->error('Endpoint error', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
        
        $message = defined('WP_DEBUG') && WP_DEBUG
            ? $exception->getMessage() . ' (in ' . $exception->getFile() . ':' . $exception->getLine() . ')'
            : 'Si è verificato un errore durante la creazione della prenotazione.';
        
        return $this->error(
            'fp_resv_reservation_error',
            $message,
            500
        );
    }
    
    /**
     * Get request parameter with default
     * 
     * @param WP_REST_Request $request Request object
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function getParam(WP_REST_Request $request, string $key, $default = null)
    {
        return $request->get_param($key) ?? $default;
    }
}










