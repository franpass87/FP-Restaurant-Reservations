<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Customers;

final class Model
{
    public int $id;
    public string $email;
    public string $firstName = '';
    public string $lastName = '';
    public string $name = '';
    public string $phone = '';
    public string $phoneE164 = '';
    public string $phoneCountry = '';
    public string $phoneNational = '';
    public string $lang = '';
    public bool $marketingConsent = false;
    public bool $profilingConsent = false;
    public ?string $consentTimestamp = null;
    public ?string $consentVersion = null;
}
