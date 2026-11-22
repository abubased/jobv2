<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Forms\FormRegistry;
use Illuminate\Console\Command;

class ClearFormsCacheCommand extends Command
{
    protected $signature = 'forms:clear';

    protected $description = 'Forget the cached universal form registry';

    public function handle(FormRegistry $forms): int
    {
        $forms->clear();
        $this->info('Form registry cache cleared.');

        return self::SUCCESS;
    }
}
