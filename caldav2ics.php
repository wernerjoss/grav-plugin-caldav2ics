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
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000],
                ['onPluginsInitialized', 0]
            ],
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
            'onSchedulerInitialized'    => ['onSchedulerInitialized', 0],
            'onAdminAfterSave'    => ['onAdminAfterSave', 0],
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
            if (!empty($config['scheduled_jobs']['enabled'])) {    // this is also necessary
                if (!empty($config['calendars']) && ($config['scheduled_jobs']['enabled'])) {
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
                    $CalendarsFile = DATA_DIR . 'calendars/calendars.yaml';
                    if (! is_dir(DATA_DIR . 'calendars'))   {
                        mkdir(DATA_DIR . 'calendars', 0775);  // create data dir, if not exists
                    }
                    $CalfileAge = 0; // default, is always older than any existing file
                    if (\file_exists($CalendarsFile))   {
                        $CalfileAge = time()-filemtime($CalendarsFile);
                    }
                    $content = Yaml::dump($config["calendars"]);
                    $JobfileAge = time()-filemtime($JobFile);    // $VendorJobFile is used to do the Job, so this is also the time refernce
                    if ($JobfileAge < $CalfileAge)  {
                        \file_put_contents($CalendarsFile, $content);   // write new $CalendarsFile only if existing Version is older than $JobFile
                    }
                    //  see php.net:
                    //  When trying to make a callable from a function name located in a namespace, you MUST give the fully qualified function name (regardless of the current namespace or use statements).
                    //  $job = $scheduler->addFunction('Grav\Plugin\Caldav2icsPlugin::createCalendars', $CalendarsFile);    // same as addCommand()...
                    //  $job = $scheduler->addCommand('Grav\Plugin\Caldav2icsPlugin::createCalendars', $CalendarsFile); // this does not (yet) work !

                    $job = $scheduler->addCommand($JobFile, $CalendarsFile);    // old approach via external PHP script, ugly, but works :-)
                    
                    $job->at($at);
                    $job->output($logs);
                    $job->backlink('/plugins/caldav2ics');
                    //  dump($job);
                }
            }
        }
    }

    public function onAdminAfterSave(): void
    {
        $VendorJobFile = pathinfo(__FILE__, PATHINFO_DIRNAME)."/jobs/create_calendars.php";
        $Perms = substr(sprintf('%o', fileperms($VendorJobFile)), -4);  // actual Permissions, octal
        //  dump($Perms);
        if (! $this::startswith($Perms, '0775'))    {
            chmod($VendorJobFile, 0775);  // octal; correct value of mode only if not executable
        }
        $config = $this->config();
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
            
            $calendars = array ( "calendars" => $config['calendars']);
            $CalendarsFile = DATA_DIR . 'calendars/calendars.yaml';
            //  dump($CalendarsFile);
            if (! is_dir(DATA_DIR . 'calendars'))   {
                mkdir(DATA_DIR . 'calendars', 0775);  // create data dir, if not exists
            }
            $CalfileAge = 0; // default, is always older than any existing file
            if (\file_exists($CalendarsFile))   {
                $CalfileAge = time()-filemtime($CalendarsFile);
            }
            $formatter = new YamlFormatter;
            $content = $formatter->encode($config["calendars"]);
            //  dump($content);
            if (($JobfileAge < $CalfileAge) or (! file_exists($CalendarsFile))) {
                \file_put_contents($CalendarsFile, $content);   // write new $CalendarsFile only if existing Version is older than $JobFile
            }
        }
        //  $this::createCalendars($CalendarsFile);
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

    public static function createCalendars($CalendarsFile)   {  // TODO: make this work when called from the scheduler (currently only works with direct call from inside admin)
        $verbose = false;
        $LogEnabled = true;
        
        //  internal data, may not be available when called from scheduler ?
        /*
        $config = $this->config();
        $calendars = $config['calendars'];
        dump($calendars);
        */
        
        //  dump($CalendarsFile);
        $RawCfg = file_get_contents($CalendarsFile);
        if ($verbose)   dump($RawCfg);
        $calendars = Yaml::parse($RawCfg); // read physical config file, ok
        //  dump($calendars);
        $path_parts = pathinfo($CalendarsFile);
        $IcsPath = $path_parts['dirname'];
        $LogFile = $IcsPath . '/caldav2ics.log';
        if ($LogEnabled) {
            $handle = fopen($LogFile, 'w');
            fwrite($handle, print_r($CalendarsFile, true)."\n");
            fwrite($handle, print_r($IcsPath, true)."\n");
            fwrite($handle, print_r($calendars, true)."\n");
            fclose($handle);
        }
        //  return;
        
        foreach ($calendars as $calendar) {
            $cal = (array) $calendar;
            //  dump($cal);
            //  break;
            $name = $cal["Name"];
            $calendar_url = $cal['Url'];
            $calendar_user = $cal['User'];
            $calendar_password = $cal['Pass'];
            
            $IcalFile = $IcsPath."/".$name.".ics";	// ical file name
            if ($verbose) {
                echo "\n";
                echo "Calendar: $name\n";
                echo "URL: $calendar_url\n";
                $user = md5($calendar_user, true);
                echo "User: $user\n";
                $pw = md5($calendar_password, true);
                echo "PW: $pw\n";
                echo "$IcalFile\n";
            }
            //	break;
            $fmdelay = 60;	// seconds
            
            if ($LogEnabled)	{
                $loghandle = fopen($LogFile, 'w') or die('Cannot open file:  '.$LogFile);
            }
            if (empty($calendar_url) || empty($calendar_user) || empty($calendar_password))	{
                if ($LogEnabled) {
                    $loghandle = fopen($LogFile, 'w') or die('Cannot open file:  '.$LogFile);
                }
                fwrite($loghandle, "Invalid Settings !\n");
                fwrite($loghandle, "Calendar URL: ".$calendar_url." must be specified\n");
                fwrite($loghandle, "Username: ".$calendar_user." must be specified\n");
                fwrite($loghandle, "Password: ".$calendar_password." must be specified\n");
                fclose($loghandle);
                return;
            }
            
            if (filter_var($calendar_url, FILTER_VALIDATE_URL) === false) {
                print_r("Invalid Calendar URL: ", $calendar_url);
                if (!$LogEnabled) {
                    $loghandle = fopen($LogFile, 'w') or die('Cannot open file:  '.$LogFile);
                    fwrite($loghandle, "Invalid Calendar URL: ", $calendar_url);
                    fclose($loghandle);
                }
                return;
            }

            if ($LogEnabled)	{
                print_r($calendar_url);
                fwrite($loghandle, $calendar_url."\n");
                fwrite($loghandle, $calendar_user."\n");
                fwrite($loghandle, $calendar_password."\n");
                fwrite($loghandle, "Delay:".$fmdelay."\n");
                fwrite($loghandle, "EnableLog:".$LogEnabled."\n");
            }	
            // Simple caching system, feel free to change the delay
            if (file_exists($IcalFile)) {
                $last_update = filemtime($IcalFile);
            } else {
                $last_update = 0;
            }
            if ($last_update + $fmdelay < time()) {	

                // Get events
                $headers = array(
                    'Content-Type: application/xml; charset=utf-8',
                    'Depth: 1',
                    'Prefer: return-minimal'
                );
                
                // see https://uname.pingveno.net/blog/index.php/post/2016/07/30/Sample-public-calendar-for-ownCloud-using-ICS-parser
                // Prepare request body, MANDATORY !
                $doc  = new \DOMDocument('1.0', 'utf-8');
                $doc->formatOutput = true;

                $query = $doc->createElement('c:calendar-query');
                $query->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:c', 'urn:ietf:params:xml:ns:caldav');
                $query->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:d', 'DAV:');

                $prop = $doc->createElement('d:prop');
                $prop->appendChild($doc->createElement('d:getetag'));
                $prop->appendChild($doc->createElement('c:calendar-data'));
                $query->appendChild($prop);

                $prop = $doc->createElement('c:filter');
                $filter = $doc->createElement('c:comp-filter');
                $filter->setAttribute('name', 'VCALENDAR');
                $prop->appendChild($filter);
                $query->appendChild($prop);

                $doc->appendChild($query);
                $body = $doc->saveXML();
                
                // Debugging purpose
                if ($LogEnabled) { 
                    echo htmlspecialchars($body);
                    fwrite($loghandle, htmlspecialchars($body));
                }

                // Prepare cURL request
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $calendar_url);
                curl_setopt($ch, CURLOPT_USERPWD, $calendar_user . ':' . $calendar_password);
                curl_setopt($ch, CURLOPT_VERBOSE, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'REPORT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

                $response = curl_exec($ch);
                if (curl_error($ch)) {
                    if ($LogEnabled) { 
                        echo curl_error($ch); 
                        fwrite($loghandle, curl_error($ch));
                        fclose($loghandle);
                    }
                    return;
                }
                curl_close($ch);

                // Debugging purpose
                if ($LogEnabled) { 
                    echo htmlspecialchars($response);
                    fwrite($loghandle, htmlspecialchars($response));
                }

                // Get the useful part of the response
                /*	skip any xml conversion/parsing
                */

                // Parse events
                $calendar_events = array();
                $handle = fopen($IcalFile, 'w') or die('Cannot open file:  '.$IcalFile);
                
                // create valid ICS File with only ONE Vcalendar !
                // write VCALENDAR header
                fwrite($handle, 'BEGIN:VCALENDAR'."\r\n");
                fwrite($handle, 'VERSION:2.0'."\r\n");
                fwrite($handle, 'PRODID:-//hoernerfranzracing/caldav2ics.php'."\r\n");
                // find and write TIMEZONE data, new feature, 27.12.19
                $skip = true;
                $wroteTZ = false;
                $lines = explode("\n", $response);
                //  dump($lines);
                foreach ($lines as $line)   {
                    $line = trim($line);
                    if ( !$wroteTZ )	{
                        if (self::startswith($line,'BEGIN:VTIMEZONE'))	{
                            $skip = false;
                        }
                        if ( !$skip )	{
                            fwrite($handle, $line."\r\n"); // write everything between 'BEGIN:VTIMEZONE' and 'END:VTIMEZONE'
                            // echo $line."\n";
                        }
                        if (self::startswith($line,'END:VTIMEZONE'))	{
                            $skip = true;
                            $wroteTZ = true;    // only write VTIMEZONE entry once
                        }
                    }
                }
                // parse $response, do NOT write VCALENDAR header for each one, just the event data
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strstr($line,'BEGIN:VCALENDAR'))	{	// first occurrence might not be at line start
                        $skip = true;
                    }
                    if (self::startswith($line,'PRODID:'))	{
                        $skip = true;
                    }
                    if (strstr($line,'VERSION:'))	{
                        $skip = true;	// VERSION can appear in different places
                    }
                    if (self::startswith($line,'CALSCALE:'))	{
                        $skip = true;
                    }
                    if (self::startswith($line,'BEGIN:VEVENT'))	{
                        $skip = false;
                        //fwrite($handle, "\r\n");	// improves readability, but triggers warning in validator :)
                    }
                    if (self::startswith($line,'END:VCALENDAR'))	{
                        $skip = true;
                    }
                    if ( !$skip )	{
                        //  dump($line);
                        fwrite($handle, $line."\r\n");
                    }
                }
                fwrite($handle, 'END:VCALENDAR'."\r\n");
                fclose($handle);
                    if ($LogEnabled) { 
                    fclose($loghandle);
                }
            }
        }
        return true;
    }
}
