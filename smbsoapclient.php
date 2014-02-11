<?php
/**
* PHP package for submitting SMO records via SOAP to Edurep.
*
* @version 0.0.3
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
	# soap client options
	private $wsdl = "smd.wsdl";
	private $soapOptions = array( "trace" => 1 );

	# checks if either a rating, review or tag has been inserted
	private $content = FALSE;

	# contains some smo values
	private $supplierId = "";
	private $userId = "";
	private $smoId = "";

	# contains proprocessed smo values
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

	# holds generated smo xml string
	private $smo = "";

	# namespaces used in smb results
	private $namespaces = array(
		"http://schemas.xmlsoap.org/soap/envelope/" => "soapenv",
		"http://xsd.kennisnet.nl/smd/1.0/" => "smd",
		"http://xsd.kennisnet.nl/smd/hreview/1.0/" => "hreview" );


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

	/**
	* Check if input is URI by seeing if it is either a URN or URL.
	*
	* @param string $uri URI to set.
	* @see https://github.com/fkooman/php-urn-validator
	* @see http://archive.mattfarina.com/2009/01/08/rfc-3986-url-validation/
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

	public function insert( $id = "" ) {
		$this->createSmoRequest( $id );
		$xmlVar = new SoapVar( "<ns1:insertSMO>".$this->smo."</ns1:insertSMO>", XSD_ANYXML );
		$this->insertSMO( $xmlVar );
		$this->processResponse( $this->__getLastResponse() );
	}

	public function update( $id ) {
		if ( empty( $id ) ) {
			throw new InvalidArgumentException( "Use an existing SMO id." );
		}
		
		#$this->updateSMO();
	}

	public function delete( $id ) {
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

		$xmlstring .= "<hreview:hReview>";

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

		$xmlstring .= "</hreview:hReview>";
		$xmlstring .= "</smd:smo>";
		$this->smo = $xmlstring;
	}

	private function processResponse( $xmlstring ) {
		$xml = simplexml_load_string( $xmlstring );

		if ( !is_object( $xml ) ) {
			throw new UnexpectedValueException( "SMB response is not XML." );
		}

		$response = $this->load( $xml );
		
		if ( array_key_exists( "errorResponse", $response["Body"][0] ) ) {
			$code = $response["Body"][0]["errorResponse"][0]["error"][0]["code"][0][0];
			$msg = $response["Body"][0]["errorResponse"][0]["error"][0]["description"][0][0];
			throw new DomainException( $msg." (".$code.")" );
		}
		
		#print_r( $response );
	}

	/**
	 * Loads raw xml (with different namespaces) into array. 
	 * Keeps attributes without namespace or xml prefix.
	 * 
	 * @param object $xml SimpleXML object.
	 * @return array $array XML array.
	 * @see http://www.php.net/manual/en/ref.simplexml.php#52512
	 */
	private function load( $xml ) {
		$fils = 0;
		$array = array();

		foreach( $this->namespaces as $uri => $prefix ) {   
			foreach( $xml->children($uri) as $key => $value ) {   
				$child = $this->load( $value );

				// To deal with the attributes, 
				// only works for attributes without a namespace, or in with xml namespace prefixes 
				if (count( $value->attributes() ) > 0  || count( $value->attributes("xml", TRUE) ) > 0 ) {   
					$child["@attributes"] = $this->getAttributes( $value );
				}
				// Also add the namespace when there is one
				if ( !empty( $uri ) ) {   
					$child["@namespace"] = $uri;
				}

				//Let see if the new child is not in the array
				if( !in_array( $key, array_keys($array) ) ) {
					$array[$key] = NULL;
					$array[$key][] = $child;
				}
				else {   
					//Add an element in an existing array
					$array[$key][] = $child;
				}

				$fils++;
			}
		}

		# no container, returning value
		if ( $fils == 0 ) {
			return array( (string) $xml );
		}

		return $array;
	}

	/**
	 * Support function for XML load function. Returns
	 * attribute parts for the XML array.
	 *
	 * @param object $xml SimpleXML object.
	 * @return array $array XML array.
	 */
	private function getAttributes( $xml ) {
		foreach( $xml->attributes() as $key => $value ) {
			$arr[$key] = (string) current( $value );
		}

		foreach( $xml->attributes( "xml", TRUE ) as $key => $value ) {
			$arr[$key][] = (string) current( $value );
			$arr[$key]["@namespace"] = "http://www.w3.org/XML/1998/namespace";
		}

		return $arr;
	}
}




$client = new SmbSoapClient( "skn" );

$client->setResource( "urn:isbn:0-486-27557-4" );
$client->setParameter( "version", "1.0" );
$client->setComment( "this is ok" );
$client->setRating( 0, -1, 5 );
$client->setReviewer( "butts", "seymour", "", 1 );
$client->insert();

#print_r( $client );

#print_r( $client->__getFunctions() );
#var_dump( $client->__getTypes() );
?>
