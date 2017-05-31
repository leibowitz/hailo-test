<?php

// CSV parsing options 
define('CSV_DELIMITER',',');
define('CSV_ENCLOSURE', '"');
define('CSV_ESCAPE', '\\');

// fields names, only used internaly
// for mapping array keys to fields names
define('LATITUDE', 'latitude');
define('LONGITUDE', 'longitude');
define('TIMESTAMP', 'timestamp');

// maximum distance (in meters) the vehicule 
// can move per seconds
// 20 m/s =~ 45 mph (72 km/h) 
define('SPEED_THRESHOLD', 20); 

// Store a point data
class Point
{
    protected $latitude;
    protected $longitude;
    protected $timestamp;

    public function __construct($latitude, $longitude, $timestamp)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->timestamp = $timestamp;
    }

    public static function fromArray($data)
    {
        return new Point($data[LATITUDE], $data[LONGITUDE], $data[TIMESTAMP]);
    }

    public function getLatitude()
    {
        return $this->latitude;
    }
    
    public function getLongitude()
    {
        return $this->longitude;
    }
    
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    public function timeAfter($point)
    {
        return $this->getTimestamp() - $point->getTimestamp();
    }
    
    public function distanceFrom($point)
    {
        $R = 6371; // 
        // Use haversine formula to compute distance between two points
        $dlat = deg2rad($point->getLatitude() - $this->getLatitude());
        $dlong = deg2rad($point->getLongitude() - $this->getLongitude());
        
        $lat1 = deg2rad($this->getLatitude());
        $lat2 = deg2rad($point->getLatitude());

        $a = pow(sin($dlat/2), 2) + pow(sin($dlong/2), 2)
            * cos($lat1/2) * cos($lat2/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }
}

// Store the list of points into a Path
class Path implements IteratorAggregate
{
    protected $points = array();

    public function __construct()
    {
    }

    public function addPoint(Point $point)
    {
        $this->points[] = $point;
    }

    public function getIterator()
    {
        return new ArrayIterator( $this->points );
    }

    private function sortPoints($point1, $point2)
    {
        if( $point1->getTimestamp() == $point2->getTimestamp())
        {
            return 0;
        }
        return $point1->getTimestamp() < $point2->getTimestamp() ? -1 : 1;
    }

    public function sort()
    {
        usort($this->points, array($this, 'sortPoints'));
    }

    public function getPoints()
    {
        return $this->points;
    }

    public function getStart()
    {
        return reset($this->points);
    }

    public function getLast()
    {
        return end($this->points);
    }

}

// Main Program
class ReadDataPoint
{
    private $path;

    // fields order
    public static $fields = array(LATITUDE, LONGITUDE, TIMESTAMP);


    public function __construct()
    {
        $this->path = new Path();
    }

    public function isValid($point)
    {
        $total = count($this->path->getPoints());
        // If this is the first point we take the assumption
        // that it is a valid one, as we have nothing to compare it to
        if($total == 0)
        {
            return true;
        }

        $last_point = $this->path->getLast();
        
        // distance in KM
        $dt = $point->distanceFrom($last_point);
        // time in sec
        $st = $point->timeAfter($last_point);
        return ($dt/$st)*1000 < SPEED_THRESHOLD;
    }

    public function parse($handler)
    {
        // Read data line by line
        while($line = fgets($handler))
        {
            // Parse each line as CSV
            $data = str_getcsv($line, CSV_DELIMITER, CSV_ENCLOSURE, CSV_ESCAPE);
            
            $point = Point::fromArray(array_combine(self::$fields, $data));

            // check if current point is within threshold
            if($this->isValid($point))
            {
                // Add new point to the path object
                $this->path->addPoint( $point );
            }
        }
    }

    public function getPath()
    {
        return $this->path;
    }

    public function readData($sort = true)
    {
        // Sort points by timestamp first
        if($sort)
        {
            $this->path->sort();
        }

        // Iterate over all points
        foreach($this->path as $point)
        {
            echo sprintf("%s,%s,%d\n", 
                $point->getLatitude(),
                $point->getLongitude(),
                $point->getTimestamp()
                );
        }
    }
}

// main function 
function main()
{
    $program = new ReadDataPoint();
    // Read from STDIN
    $program->parse(STDIN);
    $program->readData(false); // remove falst argument to force data sort

}

    
// Execute the code only if executed from the command line
if(php_sapi_name() === 'cli')
{
    main();
}
