<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Common\Scheduler\Scheduler;
use RocketTheme\Toolbox\Event\Event;


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
            'onSchedulerInitialized'    => ['onSchedulerInitialized', 0]
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
        /*
        if ($this->isAdmin()) {
            $this->enable([
                'onAdminTaskExecute' => ['onAdminTaskExecute', 0],
            ]);
            return;
        }
        */       
        $this->enable([
            //    'onPagesInitialized' => ['onPagesInitialized', 1000],
        ]);
        
        /* from devtools:
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            // Put your main events here
        ]);
        */
    }
    
    /**
     * Add create_calendars job to Grav Scheduler
     * Requires Grav 1.6.0 - Scheduler
     */
    public function onSchedulerInitialized(Event $e): void
    {
        $config = $this->config();
        dump($config);
        if ($config['enabled']) {   // NICHT plugins.caldav2ics.enabled !!!
            $scheduler = $e['scheduler'];
            $at = $config['scheduled_jobs']['at'] ?? '* * * * *';
            $logs = $config['scheduled_jobs']['logs'] ?? '';
            
            //  $job = $scheduler->addFunction('Grav\Plugin\Caldav2ics::createCalendars');  //  , [], 'tntsearch-index');
            $job = $scheduler->addCommand('user/plugins/caldav2ics/hello.sh');
            
            $job->at($at);
            $job->output($logs);
            $job->backlink('/plugins/caldav2ics');
            dump($job);
        }
    }

    public function createCalendars()   {
        $calendars = $config['plugins.caldav2ics.calendars'];
        dump($calendars);

    }
}
