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
        /*
        $config = $this->config();
        $calendars = $config['calendars'];
        dump($calendars);
        */
        $CalendarsFile = DATA_DIR . 'calendars/calendars.yaml';
		    
        $verbose = true;
        $LogEnabled = true;
        $LogFile = pathinfo($CalendarsFile, PATHINFO_DIRNAME)."/create_calendars.log";
	
        if ( file_exists($CalendarsFile) ) {
            $jsondata = file_get_contents($CalendarsFile);
            $Config = (array) json_decode($jsondata);
        }	else {
            echo $CalendarsFile." does not exist !";
            exit();
        }
        foreach ($Config as $entry) {
            if ($verbose)	var_dump('Entry:', $entry);
            foreach ($entry as $calendars) {
                $cal = (array) $calendars;
                $name = $cal["Name"];
                $calendar_url = $cal['Url'];
                $calendar_user = $cal['User'];
                $calendar_password = $cal['Pass'];
                if ($verbose)	{
                    var_dump($name);
                    var_dump($calendar_url);
                    var_dump($calendar_user);
                    var_dump($calendar_password);
                }
                $ICalFile = pathinfo($CalendarsFile, PATHINFO_DIRNAME)."/".$name.".ics";
                if ($verbose)	var_dump($ICalFile);
                //	break;
                if ($verbose) {
                    echo "\n";
                    echo "$name\n";
                    echo "$calendar_url\n";
                    echo "$calendar_user\n";
                    echo "$calendar_password\n";
                    echo "$ICalFile\n";
                }
                //	break;
                $fmdelay = 60;	// seconds
                
                if ($LogEnabled)	{
                    $loghandle = fopen($LogFile, 'w') or die('Cannot open file:  '.$LogFile);
                }
                if (empty($calendar_url) || empty($calendar_user) || empty($calendar_password))	{
                    if (!$LogEnabled) {
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
                    /*	no :-)
                    fwrite($loghandle, $calendar_user."\n");
                    fwrite($loghandle, $calendar_password."\n");
                    */
                    fwrite($loghandle, "Delay:".$fmdelay."\n");
                    fwrite($loghandle, "EnableLog:".$LogEnabled."\n");
                }
                break;	
                // Simple caching system, feel free to change the delay
                if (file_exists($ICalFile)) {
                    $last_update = filemtime($ICalFile);
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
                    $doc  = new DOMDocument('1.0', 'utf-8');    // FIXME: this does not work inside Grav !!!
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
                    $handle = fopen($ICalFile, 'w') or die('Cannot open file:  '.$ICalFile);
                    
                    // create valid ICS File with only ONE Vcalendar !
                    // write VCALENDAR header
                    fwrite($handle, 'BEGIN:VCALENDAR'."\r\n");
                    fwrite($handle, 'VERSION:2.0'."\r\n");
                    fwrite($handle, 'PRODID:-//hoernerfranzracing/caldav2ics.php'."\r\n");
                    // find and write TIMEZONE data, new feature, 27.12.19
                    $skip = true;
                    $wroteTZ = false;
                    $lines = explode("\n", $response);
                    foreach ($lines as $line)   {
                        $line = trim($line);
                        if ( !$wroteTZ )	{
                            if (startswith($line,'BEGIN:VTIMEZONE'))	{
                                $skip = false;
                            }
                            if ( !$skip )	{
                                fwrite($handle, $line."\r\n"); // write everything between 'BEGIN:VTIMEZONE' and 'END:VTIMEZONE'
                                // echo $line."\n";
                            }
                            if (startswith($line,'END:VTIMEZONE'))	{
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
                        if (startswith($line,'PRODID:'))	{
                            $skip = true;
                        }
                        if (strstr($line,'VERSION:'))	{
                            $skip = true;	// VERSION can appear in different places
                        }
                        if (startswith($line,'CALSCALE:'))	{
                            $skip = true;
                        }
                        if (startswith($line,'BEGIN:VEVENT'))	{
                            $skip = false;
                            //fwrite($handle, "\r\n");	// improves readability, but triggers warning in validator :)
                        }
                        if (startswith($line,'END:VCALENDAR'))	{
                            $skip = true;
                        }
                        if ( !$skip )	{
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
        }
            
    
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

}
