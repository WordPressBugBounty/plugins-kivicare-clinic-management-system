<?php
namespace App\baseClasses;

use App\database\classes\KCMigrator;


defined( 'ABSPATH' ) or die( 'Something went wrong' );
class KCMigration
{
    public static function migrate()
    {
        // Run the migration
        $migrator = KCMigrator::instance();
        $migrator->setup();
        $migrator->run();
    }
}