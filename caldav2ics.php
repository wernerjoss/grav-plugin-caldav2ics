<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Common\Scheduler\Scheduler;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\File;

/**
 * Class Caldav2icsPlugin
 * @package Grav\Plugin
 */
class Caldav2icsPlugin extends Plugin
{
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000],
                ['onPluginsInitialized', 0]
            ],
            'onSchedulerInitialized'    => ['onSchedulerInitialized', 0],
            'onAdminAfterSave'    => ['onAdminAfterSave', 0],
        ];
    }

    /**
    * Composer autoload.
    *is
    * @return ClassLoader
    */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        $this->enable([
            //    'onPagesInitialized' => ['onPagesInitialized', 1000],
        ]);
    }
    
    /**
     * Add create_calendars job to Grav Scheduler
     * Requires Grav 1.6.0 - Scheduler
     */
    public function onSchedulerInitialized(Event $e): void
    {
        $config = $this->config();
        //  dump($config);
        if ($config['enabled']) {   // NICHT plugins.caldav2ics.enabled !!!
            $scheduler = $e['scheduler'];
            $at = $config['scheduled_jobs']['at'] ?? '* * * * *';
            $logs = $config['scheduled_jobs']['logs'] ?? '';
            
            //  $job = $scheduler->addFunction('Grav\Plugin\caldav2ics::createCalendars', [], '');
            $CalendarsFile = DATA_DIR . 'calendars/calendars.yaml';
		    $job = $scheduler->addCommand('user/plugins/caldav2ics/jobs/create_calendars.php', $CalendarsFile);
            
            $job->at($at);
            $job->output($logs);
            $job->backlink('/plugins/caldav2ics');
            //  dump($job);
        }
    }

    public function onAdminAfterSave(): void
    {
        $config = $this->config();
        $calendars = array ( "calendars" => $config['calendars']);
        //  dump($calendars);   // funktioniert ! (aber erst beim 2ten Save - TODO: prüfen !)
        $jsondata = json_encode($calendars);
        dump($jsondata);
        $CalendarsFile = DATA_DIR . 'calendars/calendars.yaml';	// json file !
		//  dump($CalendarsFile);
        file_put_contents($CalendarsFile, $jsondata);
    }

    public function createCalendars()   {
        $config = $this->config();
        $calendars = $config['calendars'];
        //  dump($calendars);
    }
}
