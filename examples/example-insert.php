<?php
namespace Some\Other\Name;
use Kennisnet\SmbSoapClient;

date_default_timezone_set('Europe/Amsterdam');

$client = new SmbSoapClient( "kennisnet" );

$client->setSmoId( "smbphp.1" );
$client->setResource( "urn:isbn:0-486-27557-4" );
$client->setParameter( "version", "1.0" );
$client->setDate( "2014-02-11T11:59:42+01:00" );
$client->setComment( "this is ok" );
$client->setRating( 0, -1, 5 );
$client->setReviewer( "butts", "seymour", "", 1 );
$client->setTag( "keurmerk" );
$client->setTag( "future", "http://technoratie.com/tags/future" );
$client->setTag( "leesbaarheid", "", 5, 1, 5 );
$client->setLicense( "CC-BY-30", "http://creativecommons.org/licenses/by/3.0/" );
$client->insert();

print_r( $client );

?>
