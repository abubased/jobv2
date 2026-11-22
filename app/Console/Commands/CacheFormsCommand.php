<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Forms\FormRegistry;
use Illuminate\Console\Command;

class CacheFormsCommand extends Command
{
    protected $signature = 'forms:cache';

    protected $description = 'Discover form configurations and store them in the registry cache';

    public function handle(FormRegistry $forms): int
    {
        $forms->refresh();

        $this->info('Cached '.count($forms->all()).' enabled form(s).');

        foreach ($forms->all() as $form) {
            $this->line($form->slug());
        }

        return self::SUCCESS;
    }
}
