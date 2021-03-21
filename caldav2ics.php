<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Common\Scheduler\Scheduler;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\File;
use Symfony\Component\Process\PhpExecutableFinder;

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

            $CalendarsFile = DATA_DIR . 'calendars/calendars.yaml';
            $VendorJobFile = pathinfo(__FILE__, PATHINFO_DIRNAME)."/jobs/create_calendars.php";
            $RealJobFile = pathinfo(__FILE__, PATHINFO_DIRNAME)."/jobs/job.php";
            if (file_exists($RealJobFile)) {
                $JobFile = $RealJobFile;
            } else {
                $JobFile = $VendorJobFile;
            }
            $job = $scheduler->addCommand($JobFile, $CalendarsFile);
            
            $job->at($at);
            $job->output($logs);
            $job->backlink('/plugins/caldav2ics');
            //  dump($job);
        }
    }

    public function onAdminAfterSave(): void
    {
        $VendorJobFile = pathinfo(__FILE__, PATHINFO_DIRNAME)."/jobs/create_calendars.php";
        
        $phpBinaryFinder = new PhpExecutableFinder();
        $php = $php ?? $phpBinaryFinder->find();
        //  dump($php);
        $shebang = "#!".$php;
        //  dump($shebang);
        $lines = array();
        $handle = fopen($VendorJobFile, 'r');
        if ($handle) {
            $sb = fgets($handle);
            while (($buffer = fgets($handle, 4096)) !== false) {
                array_push($lines, $buffer);
            }
            fclose($handle);
        }
        if (! $this->startswith($sb,$shebang))   {
            $RealJobFile = pathinfo(__FILE__, PATHINFO_DIRNAME)."/jobs/job.php";
            $handle = fopen($RealJobFile, 'w');
            
            if ($handle) {
                fwrite($handle, $shebang . "\n");
                foreach($lines as $line) {
                        fwrite($handle, $line);
                }
                fclose($handle);
            }
		}
        //  dump($JobFile);
        chmod($VendorJobFile, 0775);  // octal; correct value of mode
        if (file_exists($RealJobFile)) chmod($RealJobFile, 0775);  // octal; correct value of mode
        $config = $this->config();
        $calendars = array ( "calendars" => $config['calendars']);
        //  dump($calendars);   // funktioniert ! (aber erst beim 2ten Save - TODO: prüfen !)
        $jsondata = json_encode($calendars);
        //  dump($jsondata);
        $CalendarsFile = DATA_DIR . 'calendars/calendars.yaml';	// json file !
        //  dump($CalendarsFile);
        file_put_contents($CalendarsFile, $jsondata);
    }

    private function startswith ($string, $stringToSearchFor) {
        if (substr(trim($string),0,strlen($stringToSearchFor)) == $stringToSearchFor) {
                // the string starts with the string you're looking for
                return true;
        } else {
                // the string does NOT start with the string you're looking for
                return false;
        }
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
