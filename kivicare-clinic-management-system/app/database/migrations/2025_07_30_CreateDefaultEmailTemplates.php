<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;
use App\emails\KCEmailTemplateManager;

defined('ABSPATH') or die('Something went wrong');

class CreateDefaultEmailTemplates extends KCAbstractMigration
{
    public function run()
    {
        KCEmailTemplateManager::getInstance()->createDefaultTemplates('mail');
    }
}
