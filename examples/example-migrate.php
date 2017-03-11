<?php
namespace Some\Other\Name;
use Kennisnet\SmbSoapClient;

/**
* Mapping SMO's from one info url to another.
* The CSV contains two columns, source and target info.
* For all SMO's containing the source url in the hreview info,
* the info is replaced, and smo updated.
*
* 2015-2016 Wim Muskee
* version 1.0
*/

require_once("../phpEdurepSearch/edurepsearch.php");

date_default_timezone_set('Europe/Amsterdam');

$csv = array_map('str_getcsv', file('mapping.csv'));

foreach( $csv as $row ) {
	$sourceinfo = $row[0];
	$targetinfo = $row[1];

	$edurep = new EdurepSearch( "smomigratie" );
	$edurep->setSearchType("smo");
	$edurep->setParameter( "maximumRecords", 100 );
	$edurep->setParameter( "query", "smo.hReview.info=\"".$sourceinfo."\"" );
	$query = $edurep->getQuery();
	$edurep->search();
	$results = new EdurepResults( $edurep->response );
	$sourcesmos = $results->records;
		
	foreach ( $sourcesmos as $sourcesmo ) {
		$client = new SmbSoapClient( $sourcesmo["supplierid"] );
		$client->setSmoId( $sourcesmo["smoid"] );
		if ( !empty( $sourcesmo["userid"] ) ) {
			$client->setUserId( $sourcesmo["userid"] );
		}
		$client->setResource( $targetinfo );
		
		if ( !empty( $sourcesmo["description"] ) ) {
			$client->setComment( htmlspecialchars($sourcesmo["description"]) );
		}
		
		if ( $sourcesmo["rating"] != -1 ) {
			$client->setRating($sourcesmo["rating"], $sourcesmo["worst"], $sourcesmo["best"] );
		}
		
		if ( !empty( $sourcesmo["tags"] ) ) {
			foreach( $sourcesmo["tags"] as $tag ) {
				$client->setTag( $tag["name"], $tag["ref"], $tag["rating"]);
			}
		}
		
		$hreviewfields = array( "summary", "version", "type", "permalink" );
		
		foreach( $hreviewfields as $field ) {
			if ( !empty( $sourcesmo[$field] ) ) {
				$client->setParameter( $field, htmlspecialchars($sourcesmo[$field]) );
			}
		}

    if (!empty($sourcesmo["dtreviewed"])) {
      $client->setDate($sourcesmo["dtreviewed"]);
    }

		if ( !empty( $sourcesmo["reviewer"] ) ) {
			$client->setReviewerVcard( $sourcesmo["reviewer"] );
		}

		if ( !empty( $sourcesmo["license"]["description"] ) ) {
			$client->setLicense( $sourcesmo["license"]["description"], $sourcesmo["license"]["ref"] );
			
			# never tested license
			print_r( $sourcesmo );
			$client->debugprint( "migrate" );
			exit;
		}
		
		#	print_r( $sourcesmo );
		#	$client->debugprint( "migrate" );
		#	exit;
		
		$client->migrate();
		
		sleep(1);
	}
}
?>
