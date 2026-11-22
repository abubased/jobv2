<?php

declare(strict_types=1);

namespace App\Modules\Forms\Exceptions;

use RuntimeException;

class InvalidFormConfigurationException extends RuntimeException
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public readonly string $configurationFile,
        public readonly array $errors,
    ) {
        parent::__construct($configurationFile.': '.implode(' ', $errors));
    }
}
