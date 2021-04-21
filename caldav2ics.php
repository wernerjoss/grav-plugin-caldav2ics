<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Common\Scheduler\Scheduler;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\File;
use Symfony\Component\DomCrawler\Crawler;
use Grav\Common\Yaml;
use Grav\Framework\File\Formatter\YamlFormatter;
use Symfony\Component\Process\PhpExecutableFinder;  // for php executable detection

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
    {   //  see https://github.com/trilbymedia/grav-plugin-tntsearch/blob/develop/tntsearch.php
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000],
                ['onPluginsInitialized', 0]
            ],
            'onSchedulerInitialized'    => ['onSchedulerInitialized', 0],
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
        if ($this->isAdmin()) {
            /** @var Uri */
            $uri = $this->grav['uri'];
            if ('caldav2ics' !== $uri->basename()) {
                return; // do not enable onAdminAfterSave if not in plugin Admin page
            }
            $this->enable([
                //  see https://github.com/trilbymedia/grav-plugin-tntsearch/blob/develop/tntsearch.php
                'onAdminAfterSave'    => ['onAdminAfterSave', 0],
            ]);
        }
    }
    
    /**
     * Add create_calendars job to Grav Scheduler
     * Requires Grav 1.6.0 - Scheduler
     */
    public function onSchedulerInitialized(Event $e): void
    {
        $config = $this->config();
        //  dump($config);
        if ($config['scheduled_jobs']['enabled']) {    // this is also necessary
            if (!empty($config['calendars']) && ($config['scheduled_jobs']['enabled'])) {
                //  dump($config['calendars']); // just to check in backend when this is called - always :-/
                $scheduler = $e['scheduler'];
                $at = $config['scheduled_jobs']['at'] ?? '* * * * *';
                $logs = $config['scheduled_jobs']['logs'] ?? '';

                $VendorJobFile = pathinfo(__FILE__, PATHINFO_DIRNAME)."/jobs/create_calendars.php";
                $RealJobFile = pathinfo(__FILE__, PATHINFO_DIRNAME)."/jobs/job.php";
                if (file_exists($RealJobFile)) {
                    $JobFile = $RealJobFile;
                } else {
                    $JobFile = $VendorJobFile;
                }
                //  see php.net:
                //  When trying to make a callable from a function name located in a namespace, you MUST give the fully qualified function name (regardless of the current namespace or use statements).
                //  $job = $scheduler->addCommand('Grav\Plugin\Caldav2icsPlugin::createCalendars', $CalendarsFile); // this does not (yet) work !
                //  $job = $scheduler->addFunction('Grav\Plugin\Caldav2icsPlugin::createCalendars', $CalendarsFile);    // same as addCommand()...
                
                $job = $scheduler->addCommand($JobFile, USER_DIR);    // new approach (08.04.21): only pass USER_DIR to create_calendars.php
                
                $job->at($at);
                $job->output($logs);
                $job->backlink('/plugins/caldav2ics');
                //  dump($job); // just to check in backend when this is called - always :-/
            }
        }
    }

    public function onAdminAfterSave(Event $e): void
    {
        /** @var config **/
        $config = $e['object'];   //  <-- Contains the new data submitted by Admin, do NOT use '$config = $this->config();' here !
        dump($config);
        $IsEnabled = $config['enabled'];
        //  dump($IsEnabled);
        $HasJobsEnabled = $config['scheduled_jobs']['enabled'];
        //  dump($HasJobsEnabled);
        if ($IsEnabled && $HasJobsEnabled) {
            $VendorJobFile = pathinfo(__FILE__, PATHINFO_DIRNAME)."/jobs/create_calendars.php";
            $Perms = substr(sprintf('%o', fileperms($VendorJobFile)), -4);  // actual Permissions, octal
            //  dump($Perms);
            if (! $this::startswith($Perms, '0775'))    {
                chmod($VendorJobFile, 0775);  // octal; correct value of mode only if not executable
            }
            //  dump($config);  // just to check in backend when this is called
            if (!empty($config['calendars'])) {
                $shebang = $config["shebang"];  // new approach, as PhpExecutableFinder(); does not always work !
                //  dump($shebang);
                if ( $shebang == null ) {   // this is the default, see above: try to find php executable if config is empty, this should work on most servers, if not, override with config value !
                    $PhpBinaryFinder = new PhpExecutableFinder();
                    $php = $php ?? $PhpBinaryFinder->find();
                    //  dump($php);
                    $shebang = "#!".$php;
                }
                //  dump($shebang); 
                $lines = array();
                $handle = fopen($VendorJobFile, 'r');
                if ($handle) {
                    $sb = fgets($handle);   // $sb is shebang from $VendorJobFile
                    while (($buffer = fgets($handle, 4096)) !== false) {
                        array_push($lines, $buffer);
                    }
                    fclose($handle);
                }
                $FileAge = time()-filemtime($VendorJobFile);    // this is always the reference
                if (! $this::startswith($sb,$shebang))   {
                    $RealJobFile = pathinfo(__FILE__, PATHINFO_DIRNAME)."/jobs/job.php";
                    if (file_exists($RealJobFile)) {    // if this exists, check if it is younger than reference file,
                        $JobfileAge = time()-filemtime($RealJobFile);    
                        if ($FileAge < $JobfileAge) {                // Vendor Job file is older than existing Job File (e.g. updated), so recreate it
                            $handle = fopen($RealJobFile, 'w');
                            if ($handle) {
                                fwrite($handle, $shebang . "\n");
                                foreach($lines as $line) {
                                        fwrite($handle, $line);
                                }
                                fclose($handle);
                                $JobfileAge = time()-filemtime($RealJobFile);    
                            }
                            if (file_exists($RealJobFile)) chmod($RealJobFile, 0775);  // octal; correct value of mode
                        }
                    }   else    {   //  Vendor shebang does not fit, create $RealJobFile with shebang from config
                        $handle = fopen($RealJobFile, 'w');
                        if ($handle) {
                            fwrite($handle, $shebang . "\n");
                            foreach($lines as $line) {
                                    fwrite($handle, $line);
                            }
                            fclose($handle);
                            $JobfileAge = time()-filemtime($RealJobFile);    
                        }
                        if (file_exists($RealJobFile)) chmod($RealJobFile, 0775);  // octal; correct value of mode
                    }
                }   else    {
                    $JobfileAge = time()-filemtime($VendorJobFile);    // $VendorJobFile is used to do the Job, so this is also the time refernce
                }
            }
        }
    }

    public static function startswith ($string, $stringToSearchFor) {  // was: private, not static
        if (substr(trim($string),0,strlen($stringToSearchFor)) == $stringToSearchFor) {
                // the string starts with the string you're looking for
                return true;
        } else {
                // the string does NOT start with the string you're looking for
                return false;
        }
    }

    public static function createCalendars($CalendarsFile)   {  
        // TODO: make this work when called from the scheduler (currently only works with direct call from inside admin), Code omitted for the time beeing
        return true;
    }
}
