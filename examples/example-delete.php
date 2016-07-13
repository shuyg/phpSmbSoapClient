<?php
namespace Some\Other\Name;
use Kennisnet\SmbSoapClient;

date_default_timezone_set('Europe/Amsterdam');

$client = new SmbSoapClient( "kennisnet" );

$client->setSmoId( "smbphp.1" );
$client->delete();

print_r( $client );

?>
