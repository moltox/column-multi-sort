<?php

namespace Moltox\ColumnMultiSort\Commands;

use Illuminate\Console\Command;

class ColumnMultiSortCommand extends Command
{
    public $signature = 'column-multi-sort';

    public $description = 'My command';

    public function handle()
    {
        $this->comment('All done');
    }
}
