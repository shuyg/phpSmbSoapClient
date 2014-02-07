<?php
/**
* PHP package for submitting SMO records via SOAP to Edurep.
*
* @version 0.0.1
* @link http://developers.wiki.kennisnet.nl/index.php/Edurep:Hoofdpagina
*
* Copyright 2014 Wim Muskee <wimmuskee@gmail.com>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, version 3 of the License.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class SmbSoapClient extends SoapClient {
	private $wsdl = "smd.wsdl";
	private $soapOptions = array( "trace" => 1 );

	private $content = FALSE;
	private $supplierId = "";
	private $userId = "";
	private $smoValues = array(
		"simple" => array(
			"info" => "",
			"summary" => "",
			"version" => "",
			"reviewer" => "",
			"description" => "" ),
		"complex" => array(
			"rating" => array(
				"rating" => NULL,
				"worst" => 1,
				"best" => 5 ) ) );
	private $smo = "";


	public function __construct( $supplierId ) {
		if ( !empty( $supplierId ) ) {
			$this->supplierId = $supplierId;
		}
		else {
			throw new InvalidArgumentException( "Use a valid SMB supplierId." );
		}

		parent::__construct( $this->wsdl, $this->soapOptions );
	}

	#------------------
	# Set SMO variables
	#------------------

	/*
	* Check if input is URI by seeing if it is either a URN or URL.
	*/
	public function setResource( $uri ) {
		$urnRE = '/^urn:[a-z0-9][a-z0-9-]{1,31}:([a-z0-9()+,-.:=@;$_!*\']|%(0[1-9a-f]|[1-9a-f][0-9a-f]))+$/i';
		$urlRE = "/^([a-z][a-z0-9\*\-\.]*):\/\/(?:(?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*(?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@)?(?:(?:[a-z0-9\-\.]|%[0-9a-f]{2})+|(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\]))(?::[0-9]+)?(?:[\/|\?](?:[\w#!:\.\?\+=&@!$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})*)?$/";

		if ( preg_match( $urnRE, $uri ) || preg_match( $urlRE, $uri ) ) {
			$this->setParameter( "info", $uri );
		}
	}
	public function setComment( $comment ) {
		if ( !empty( $comment ) ) {
			$this->content = TRUE;
			$this->setParameter( "description", $comment );
		}
	}

	public function setRating( $rating, $worst, $best ) {
		if ( is_numeric( $rating ) && is_numeric( $worst ) && is_numeric( $best ) && $rating >= $worst && $rating <= $best ) {
			$this->content = TRUE;
			$this->smoValues["complex"]["rating"]["rating"] = $rating;
			$this->smoValues["complex"]["rating"]["worst"] = $worst;
			$this->smoValues["complex"]["rating"]["best"] = $best;
		}
	}

	public function setReviewer( $name, $firstname = "", $organisation = "", $id = "" ) {
		if ( !empty( $id ) ) {
			$this->userId = $id;
		}

		if ( !empty( $name ) ) {
			$vcard = "BEGIN:VCARD&#xA;VERSION:3.0&#xA;";
			if ( empty( $firstname ) ) {
				$vcard .= "FN:".$name."&#xA;";
			}
			else {
				$vcard .= "FN:".$firstname." ".$name."&#xA;";
				$vcard .= "N:".$name.";".$firstname."&#xA;";
			}
			if ( !empty( $organisation ) ) {
				$vcard .= "ORG:".$organisation."&#xA;";
			}
			$vcard .= "END:VCARD";
			$this->setParameter( "reviewer", $vcard );
		}
	}

	public function setParameter( $key, $value ) {
		if ( array_key_exists( $key, $this->smoValues["simple"] ) && !empty( $value ) ) {
			$this->smoValues["simple"][$key] = $value;
		}
	}

	#------------------
	# SMB Soap actions
	#------------------

	public function insertSmo( $id = "" ) {
		$this->createSmoRequest( $id );
		
		#$this->insertSMO();
		#$this->__getLastResponse()
	}

	public function updateSmo( $id ) {
		if ( empty( $id ) ) {
			throw new InvalidArgumentException( "Use an existing SMO id." );
		}
		
		#$this->updateSMO();
	}

	public function deleteSmo( $id ) {
		if ( empty( $id ) ) {
			throw new InvalidArgumentException( "Use an existing SMO id." );
		}
		
		#$this->deleteSMO();
	}

	#------------------
	# Other functions
	#------------------

	private function createSmoRequest( $id ) {
		if ( !$this->content ) {
			throw new UnexpectedValueException( "Provide at least a comment, rating or tag." );
		}

		$xmlstring = "<smd:smo xmlns:smd=\"http://xsd.kennisnet.nl/smd/1.0/\" xmlns:hreview=\"http://xsd.kennisnet.nl/smd/hreview/1.0/\">";
		$xmlstring .= "<smd:supplierId>".$this->supplierId."</smd:supplierId>";
		
		if ( !empty( $this->userId ) ) {
			$xmlstring .= "<smd:userId>".$this->userId."</smd:userId>";
		}

		foreach( $this->smoValues["simple"] as $key => $value ) {
			if ( !empty( $value ) ) {
				$xmlstring .= "<hreview:".$key.">".$value."</hreview:".$key.">";
			}
		}
		
		if ( !is_null( $this->smoValues["complex"]["rating"]["rating"] ) ) {
			foreach( $this->smoValues["complex"]["rating"] as $key => $value ) {
				$xmlstring .= "<hreview:".$key.">".$value."</hreview:".$key.">";
			}
		}
		$this->smo = $xmlstring;
	}
}




$client = new SmbSoapClient( "kn" );

$client->setResource( "urn:isbn:0-486-27557-4" );
$client->setParameter( "version", "1.0" );
$client->setComment( "this is ok" );
$client->setRating( 0, -1, 5 );
$client->setReviewer( "butts", "seymour", "", 1 );
$client->insertSmo();

print_r( $client );
?>
