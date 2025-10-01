<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function add_filter;
use function apply_filters;
use function current_user_can;
use function is_string;
use function strpos;

final class Security
{
    public static function boot(): void
    {
        add_filter('rest_post_dispatch', [self::class, 'enforceNoStoreHeaders'], 20, 3);
    }

    public static function currentUserCanManage(): bool
    {
        return current_user_can(self::managementCapability());
    }

    public static function managementCapability(): string
    {
        $capability = apply_filters('fp_resv_management_capability', 'manage_options');
        if (!is_string($capability) || $capability === '') {
            return 'manage_options';
        }

        return $capability;
    }

    public static function enforceNoStoreHeaders(
        WP_REST_Response $response,
        WP_REST_Server $server,
        WP_REST_Request $request
    ): WP_REST_Response {
        $route = $request->get_route();
        if (!is_string($route)) {
            return $response;
        }

        if (strpos($route, '/fp-resv/v1/') !== 0) {
            return $response;
        }

        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');

        return $response;
    }
}
