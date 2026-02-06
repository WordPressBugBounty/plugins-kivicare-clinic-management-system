<?php

namespace KiviCare\Migrations;

use App\database\classes\KCAbstractMigration;
use App\emails\KCEmailTemplateManager;

defined('ABSPATH') or die('Something went wrong');

/**
 * Migration to generate all default templates (SMS, Google Meet, Google Calendar, Email)
 */
class GenerateAllDefaultTemplates extends KCAbstractMigration
{
    /**
     * Run the migration
     */
    public function run()
    {
        /*SMS Templates Generate*/
        KCEmailTemplateManager::getInstance()->createDefaultTemplates('sms');
        /*Google Meet Templates Generate*/
        KCEmailTemplateManager::getInstance()->createDefaultTemplates('gmeet');
        /*Google Calendar Templates Generate*/
        KCEmailTemplateManager::getInstance()->createDefaultTemplates('gcal');
        /*Email Templates Generate*/
        KCEmailTemplateManager::getInstance()->createDefaultTemplates('mail');
    }

    /**
     * Rollback the migration
     */
    public function rollback()
    {
        // No rollback needed for template generation as they are content
    }
}
