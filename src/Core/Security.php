<?php

declare(strict_types=1);

namespace FP\Resv\Core;

final class Security
{
    public static function currentUserCanManage(): bool
    {
        return current_user_can('manage_options');
    }
}
