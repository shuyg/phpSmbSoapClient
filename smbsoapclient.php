<?php
/**
* PHP package for submitting SMO records via SOAP to Edurep.
*
* @version 0.0.5
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

	# regular expressions
	const URLRE = "/^([a-z][a-z0-9\*\-\.]*):\/\/(?:(?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*(?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@)?(?:(?:[a-z0-9\-\.]|%[0-9a-f]{2})+|(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\]))(?::[0-9]+)?(?:[\/|\?](?:[\w#!:\.\?\+=&@!$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})*)?$/";
	const URNRE = '/^urn:[a-z0-9][a-z0-9-]{1,31}:([a-z0-9()+,-.:=@;$_!*\']|%(0[1-9a-f]|[1-9a-f][0-9a-f]))+$/i';
	const DATERE = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|(\+|-)\d{2}:\d{2})$/';

	# checks if needed vars have been inserted
	private $content = FALSE;
	private $resource = FALSE;

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
			"description" => "",
			"dtreviewed" => "",
			"type" => "",
			"permalink" => "" ),
		"complex" => array(
			"rating" => array(
				"rating" => NULL,
				"worst" => 1,
				"best" => 5 ),
			"tags" => array(),
			"license" => array(
				"description" => "",
				"ref" => "" ) ) );

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
	* Sets the smoId. For SMB this should be prefixed by
	* "supplierId.". If this is not the case, this function
	* will do that.
	*
	* @param string $id ID to set.
	*/
	public function setSmoId( $id ) {
		if ( empty( $id ) ) {
			throw new InvalidArgumentException( "Use a non empty SMO ID." );
		}
		
		if ( substr( $id, 0, strlen( $this->supplierId ) + 1 ) == $this->supplierId."." ) {
			$this->smoId = $id;
		}
		else {
		    $this->smoId = $this->supplierId.".".$id;
		}
	}

	/**
	* Check if input is URI by seeing if it is either a URN or URL.
	*
	* @param string $uri URI to set.
	* @see https://github.com/fkooman/php-urn-validator
	* @see http://archive.mattfarina.com/2009/01/08/rfc-3986-url-validation/
	*/
	public function setResource( $uri ) {
		if ( preg_match( self::URNRE, $uri ) || preg_match( self::URLRE, $uri ) ) {
			$this->resource = TRUE;
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
		if ( $this->validateRating( $rating, $worst, $best ) ) {
			$this->content = TRUE;
			$this->smoValues["complex"]["rating"]["rating"] = $rating;
			$this->smoValues["complex"]["rating"]["worst"] = $worst;
			$this->smoValues["complex"]["rating"]["best"] = $best;
		}
	}

	public function setTag( $name, $ref = "", $rating = 0, $worst = 0, $best = 0 ) {
		if ( !empty( $name ) ) {
			$this->content = TRUE;
			$tag["name"] = $name;
			if ( $this->validateRating( $rating, $worst, $best ) ) {
				$tag["rating"] = $rating;
				$tag["worst"] = $worst;
				$tag["best"] = $best;
			}
			if ( preg_match( self::URLRE, $ref ) ) {
				$tag["ref"] = $ref;
			}
			$this->smoValues["complex"]["tags"][] = $tag;
		}
	}

	/**
	* Creates a vCard with FN, and optionally a N and ORG.
	* Also, sets an optional SMO userId.
	*/
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

	/**
	* Validates for the correct format. Using this function only
	* works for inserts. Updates always use the current datetime.
	*
	* @param datetime $date The insert datetime.
	*/
	public function setDate( $date ) {
		if ( !preg_match( self::DATERE, $date ) ) {
			throw new InvalidArgumentException( "Not a valid date: ".$date );
		}
		$this->setParameter( "dtreviewed", $date );
	}

	public function setLicense( $description, $ref = "" ) {
		if ( !empty( $description ) ) {
			$this->smoValues["complex"]["license"]["description"] = $description;

			if ( preg_match( self::URLRE, $ref ) ) {
				$this->smoValues["complex"]["license"]["ref"] = $ref;
			}
		}
	}

	/**
	* Generic function to set a simple parameter (no tags for instance).
	*
	* @param string $key SMO field name.
	* @param string $value SMO field value.
	*/
	public function setParameter( $key, $value ) {
		if ( array_key_exists( $key, $this->smoValues["simple"] ) && !empty( $value ) ) {
			$this->smoValues["simple"][$key] = $value;
		}
	}

	#------------------
	# SMB Soap actions
	#------------------

	public function insert() {
		if ( empty( $this->smoValues["simple"]["dtreviewed"] ) ) {
			$this->setParameter( "dtreviewed", date('c') );
		}
		$this->createSmoRequest();
		$xmlVar = new SoapVar( "<ns1:insertSMO>".$this->smo."</ns1:insertSMO>", XSD_ANYXML );
		$this->insertSMO( $xmlVar );
		$this->processResponse( $this->__getLastResponse() );
	}

	public function update() {
		if ( empty( $this->smoId ) ) {
			throw new UnexpectedValueException( "Use an existing SMO id." );
		}

		$this->setParameter( "dtreviewed", date('c') );
		$this->createSmoRequest();
		$xmlVar = new SoapVar( "<ns1:updateSMO>".$this->smo."</ns1:updateSMO>", XSD_ANYXML );
		$this->updateSMO( $xmlVar );
		$this->processResponse( $this->__getLastResponse() );
	}

	public function delete() {
		if ( empty( $this->smoId) ) {
			throw new UnexpectedValueException( "Use an existing SMO id." );
		}

		$this->createSmoRequest();
		$xmlVar = new SoapVar( "<ns1:deleteSMO>".$this->smo."</ns1:deleteSMO>", XSD_ANYXML );
		$this->deleteSMO( $xmlVar );
		$this->processResponse( $this->__getLastResponse() );
	}

	#------------------
	# Other functions
	#------------------

	private function createSmoRequest() {
		if ( !$this->content || !$this->resource ) {
			throw new UnexpectedValueException( "Provide at least a comment, rating or tag and a resource." );
		}

		$xmlstring = "<smd:smo xmlns:smd=\"http://xsd.kennisnet.nl/smd/1.0/\" xmlns:hreview=\"http://xsd.kennisnet.nl/smd/hreview/1.0/\">";
		$xmlstring .= "<smd:supplierId>".$this->supplierId."</smd:supplierId>";
	
		if ( !empty( $this->userId ) ) {
			$xmlstring .= "<smd:userId>".$this->userId."</smd:userId>";
		}
		if ( !empty( $this->smoId ) ) {
			$xmlstring .= "<smd:smoId>".$this->smoId."</smd:smoId>";
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

		if ( count( $this->smoValues["complex"]["tags"] ) > 0 ) {
			$xmlstring .= "<hreview:tags>";
			foreach( $this->smoValues["complex"]["tags"] as $tag ) {
				$xmlstring .= "<hreview:tag>";
				$xmlstring .= "<hreview:name>".$tag["name"]."</hreview:name>";
				if ( array_key_exists( "ref", $tag  ) ) {
					$xmlstring .= "<hreview:ref>".$tag["ref"]."</hreview:ref>";
				}
				if ( array_key_exists( "rating", $tag ) ) {
					$xmlstring .= "<hreview:rating hreview:worst=\"".$tag["worst"]."\" hreview:best=\"".$tag["best"]."\">".$tag["rating"]."</hreview:rating>";
				}
				$xmlstring .= "</hreview:tag>";
			}
			$xmlstring .= "</hreview:tags>";
		}

		if ( !empty( $this->smoValues["complex"]["license"]["description"] ) ) {
			$xmlstring .= "<hreview:license>";
			$xmlstring .= "<hreview:description>".$this->smoValues["complex"]["license"]["description"]."</hreview:description>";
			if ( !empty( $this->smoValues["complex"]["license"]["ref"] ) ) {
				$xmlstring .= "<hreview:ref>".$this->smoValues["complex"]["license"]["ref"]."</hreview:ref>";
			}
			$xmlstring .= "</hreview:license>";
		}

		$xmlstring .= "</hreview:hReview>";
		$xmlstring .= "</smd:smo>";
		$this->smo = $xmlstring;
	}

	/**
	* Processes SMB response to a request and primarily checks if
	* there were no errors. In case of an insertSMO, sets the smoId
	* returned by SMB in case there was no smoID to begin with.
	*
	* @param string $xmlstring The raw xml response.
	*/
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
		
		# Set smoID from response SMO
	}

	/**
	* Validates a rating triplet.
	*
	* @param numeric $rating Rating
	* @param numeric $worst Low end of scale.
	* @param numeric $best High end of scale.
	* @return bool Whether it validates or not.
	*/
	private function validateRating( $rating, $worst, $best ) {
		if ( is_numeric( $rating ) && is_numeric( $worst ) && is_numeric( $best ) && $rating >= $worst && $rating <= $best && $worst != $best ) {
			return TRUE;
		}
		else {
			return FALSE;
		}
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
?>
