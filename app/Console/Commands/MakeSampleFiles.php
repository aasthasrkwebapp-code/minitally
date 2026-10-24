<?php

namespace App\Console\Commands;

use App\Services\SampleFiles;
use Illuminate\Console\Command;

class MakeSampleFiles extends Command
{
    protected $signature = 'billing:samples {dir=storage/app/samples} {--month= : YYYY-MM, default this month}';

    protected $description = 'Write the sample daily-entries Excel (50 rows) and a sample bank statement';

    public function handle(SampleFiles $samples): int
    {
        $dir = rtrim($this->argument('dir'), '/');
        @mkdir($dir, 0775, true);
        $month = $this->option('month');
        $samples->entriesWorkbook("$dir/daily-entries-sample.xlsx", $month);
        $samples->bankStatement("$dir/sample-bank-statement.xlsx", $month);
        $this->info("Written to $dir");

        return self::SUCCESS;
    }
}
