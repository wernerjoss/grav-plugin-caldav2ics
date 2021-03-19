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
            $JobFile = pathinfo(__FILE__, PATHINFO_DIRNAME)."/jobs/create_calendars.sh";
            $CalendarsFile = DATA_DIR . 'calendars/calendars.yaml';
            $Args = array();
            array_push($Args, $JobFile, $CalendarsFile);
            //  dump($Args);
            $job = $scheduler->addCommand('user/plugins/caldav2ics/jobs/create_calendars.php', $CalendarsFile);
            
            //  $job = $scheduler->addCommand('user/plugins/caldav2ics/jobs/create_calendars.sh', $JobFile, $CalendarsFile);    //  does not work, see create_calendars.sh comments
            /*  TODO: make internal function work with scheduler
            $job = $scheduler->addFunction('Grav\Plugin\Caldav2icsPlugin::createCalendars', $CalendarsFile);
            $func = $this->createCalendars(); //($CalendarsFile);
            dump($func);
            $job = $scheduler->addFunction($func, $CalendarsFile);
            */

            $job->at($at);
            $job->output($logs);
            $job->backlink('/plugins/caldav2ics');
            //  dump($job);
        }
    }

    public function onAdminAfterSave(): void
    {
        $JobFile = pathinfo(__FILE__, PATHINFO_DIRNAME)."/jobs/create_calendars.sh";
        //  dump($JobFile);
        chmod($JobFile, 0775);  // octal; correct value of mode
        $config = $this->config();
        $calendars = array ( "calendars" => $config['calendars']);
        //  dump($calendars);   // funktioniert ! (aber erst beim 2ten Save - TODO: prüfen !)
        $jsondata = json_encode($calendars);
        //  dump($jsondata);
        $CalendarsFile = DATA_DIR . 'calendars/calendars.yaml';	// json file !
        //  dump($CalendarsFile);
        file_put_contents($CalendarsFile, $jsondata);
        //  $this::createCalendars($CalendarsFile);    // call this directly upon save, no button needed :-)
    }

    public function createCalendars()   {
        //	this is needed for $job = $scheduler->addFunction() - evtl. future task
        /*
        $config = $this->config();
        $calendars = $config['calendars'];
        dump($calendars);
        */
    }
}
