<?php

namespace Moltox\ColumnMultiSort;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Moltox\ColumnMultiSort\Commands\ColumnMultiSortCommand;

class ColumnMultiSortServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('column-multi-sort')
            ->hasConfigFile();
    }
}
