<?php

namespace Botble\RealEstate\Services\Treeb;

use RuntimeException;

/**
 * Raised when the PROPTX/AMPRE API answers with a non-2xx status.
 *
 * The message is intentionally payload-free (status + resource only) so it is
 * safe to store on the sync log and in application logs under the IDX agreement.
 */
class TreebApiException extends RuntimeException
{
    public function __construct(string $message, protected int $status = 0)
    {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function isAuthFailure(): bool
    {
        return in_array($this->status, [401, 403], true);
    }
}
