<?php
/**********************************************************************
//
// Warpwire Sakai Connection Class 
// version 1.2.1
//
// Allows Sakai to make a SOAP authentication connection
// and bundle and return the results to Warpwire for
// authentication and processing
//
**********************************************************************/

/**********************************************************************
//
// Copyright 2015 Warpwire, Inc Licensed under the
//	 Educational Community License, Version 2.0 (the "License"); you may
//  not use this file except in compliance with the License. You may
//  obtain a copy of the License at
//
// http://www.osedu.org/licenses/ECL-2.0
//
//  Unless required by applicable law or agreed to in writing,
//  software distributed under the License is distributed on an "AS IS"
//  BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
//  or implied. See the License for the specific language governing
//  permissions and limitations under the License.
//	
**********************************************************************/

/**********************************************************************
//	You should not make modification below this line without first
//  understanding how this class works in totality
**********************************************************************/

class WWSakaiWebServices {

	// host name information
	private $_HOST = null;
	// basic user information
	private $_USER = null;
	// basic password information
	private $_PASS = null;
	// web service discovery for login
	private $__WSDL_LOGIN_URI = null;
	// web service discovery for script
	private $__WSDL_SCRIPT_URI = null;
	
	// Web Service Connection Objects
	private $_WS_LOGIN = null;
	private $_WS_SOAP = null;
	
	// _WSDL Service Files
	protected $_WSDL_MAPPING = array(
		'login' => 'setWSLoginUri',
		'script' => 'setWSScriptUri',
	);

	// create a new basic soap object with assumed credentials
	public function __construct($_host = '', $_user = '', $_pass = '', $__WSDL = array()) {
		// set the hostname
		$this->setHost($_host);
		// set the username
		$this->setUser($_user);
		// set the password
		$this->setPassword($_pass);
		// set the web service discovery files
		$this->set_WSDL($__WSDL);
	}
	
	// make a connection to the soap service
	public function connect() {
		// attempt to make a connection to the login endpoint
		try {
			$_connect = new SoapClient($this->getWSLoginUri());
			// the login object must be valid
			if (! $_connect instanceof SoapClient)
				throw new Exception('Login parameters are incorrect');
			// make sure the login function exists
			if (! $this->soapFunctionExists('login', $_connect))
				throw new Exception('The Web Service does not support the login function');
			// log in the user
			$this->_WS_LOGIN = $_connect->login($this->getUser(), $this->getPassword());
			// get a valid active session object
			$this->_WS_SOAP = new SoapClient($this->getWSScriptUri(), array('exceptions' => 0));
			// get the user eid
			if (! $this->soapFunctionExists('getUserDisplayName', $this->getWSSoap()))
				throw new Exception('Unable to get the user unique identifier');
		}
		// pass the exception to the handler
		catch (Exception $e) {
			$headers = @get_headers($this->getWSLoginUri());
			throw new Exception($e->getMessage().' Headers: '.var_export($headers, true));
		}
	}
	
	// set the hostname retroactively
	public function setHost($_host) {
		if ((isset($_host) && (! empty($_host))))
			$this->_HOST = trim($_host);
	}
	
	// allow the user name to be set retroactively
	public function setUser($_user) {
		if ((isset($_user) && (! empty($_user))))
			$this->_USER = $_user;
	}

	// allow the passord to be set retroactively
	public function setPassword($_password) {
		if ((isset($_password) && (! empty($_password))))
			$this->_PASS = $_password;
	}
	
	// allows the _WSDL to be set retoractively
	public function set_WSDL($__WSDL = array()) {
		// must be a valid array
		if (! is_array($__WSDL))
			return(false);
		// get the WSDL mapping
		$_mappings = $this->_WSDL_MAPPING;
		// map the corresponding keys with the discovery language file
		foreach($__WSDL AS $_defintion => $_value) {
			// make the definition key name lower case
			$_defintion = strtolower($_defintion);
			// determine if it exits in the mapping _WSDL mapping
			if (! isset($_mappings[$_defintion]))
				continue;
			// get the function for this mapping
			$_function = $_mappings[$_defintion];
			// set the value
			$this->$_function($_value);
		}
	}
	
	// returns whether a SOAP function exists
	public function soapFunctionExists($_needle = '', $_haystack = '') {
		if (! $_haystack instanceof SoapClient)
			return(false);
		// the need is empty
		if (empty($_needle))
			return(false);
		// get a list of functions
		$_functions = $_haystack->__getFunctions();
		// there are no functions
		if (count($_functions) <= 0)
			return(false);
		// iterate through the function list looking for the defintion
		foreach($_functions AS $_function) {
			if (stristr($_function, strtolower($_needle.'(')))
				return(true);
		}
		// needle not found
		return(false);
	}

	// get the user display name
	public function getUserDisplayName() {
		// the session must be active
		if (! $this->getWSSoap() instanceof SoapClient)
			return('');
		// the user display name function must be valid
		if (! $this->soapFunctionExists('getUserDisplayName', $this->getWSSoap()))
			return('');
		// return the user display name
		return($this->getWSSoap()->getUserDisplayName($this->getWSLogin(), $this->getUser()));
	}

	// get the user email 
	public function getUserEmail() {
		// the session must be active
		if (! $this->_WS_SOAP instanceof SoapClient)
			return('');
		// the email function must be valid
		if (! $this->soapFunctionExists('getUserEmail', $this->getWSSoap()))
			return('');
		// return the user email
		return($this->getWSSoap()->getUserEmail($this->getWSLogin(), $this->getUser()));
	}

	// get the user id
	public function getUserId() {
		// the session must be active
		if (! $this->getWSSoap() instanceof SoapClient)
			return('');
		// the user id function must be valid
		if (! $this->soapFunctionExists('getUserId', $this->getWSSoap()))
			return('');
		// return the user id
		return($this->getWSSoap()->getUserId($this->getWSLogin(), $this->getUser()));
	
	}

	// get the user unique identifier
	public function getUserUniqueIdentifier() {
		return($this->getUser());
	}

	// get a list of all user sites for this user
	public function getAllUserSites() {
		// the session must be active
		if (! $this->getWSSoap() instanceof SoapClient)
			return(array());
		// the email function must be valid
		if (! $this->soapFunctionExists('getSitesUserCanAccess', $this->getWSSoap()))
			return(array());
		$_sites = $this->getWSSoap()->getSitesUserCanAccess($this->getWSLogin());
		// make sure the sites is a valid XML document
		if (! ($_xml = @simplexml_load_string($_sites)))
			return(array());
		// iterate throught the document, storing the key and group name
		$_output = array();
		foreach($_xml->item AS $_site) {
			// all sites must have corresponding ids
			if (! isset($_site->siteId)) continue;
			// add the title it exist
			$title = 'Unknown Title';
			if (isset($_site->siteTitle))
				$title = trim((string)$_site->siteTitle);
			// add the object to the output array
			$_output[trim((string)$_site->siteId)] = $title;
		}
		return($_output);
	}
	public function cleanUp() {}

	// naive setting function
	private function setWSLoginUri($_val) { $this->__WSDL_LOGIN_URI = $_val; } 
	private function setWSScriptUri($_val) { $this->__WSDL_SCRIPT_URI = $_val; }
	private function setCacheSessionData($a) {
		if (session_id() == '')
			return(false);
		// set the cookie jar data
		$_SESSION['CACHE_SESSION_DATA'] = $a;
		// set the session expiration
		$_SESSION['EXPIRATION'] = strtotime('+4 hours', time());
	}
	
	// naive retrieval function
	private function getHost() { return ($this->_HOST); }
	private function getUser() { return ($this->_USER); }
	private function getPassword() { return ($this->_PASS); }
	private function getWSLogin() { return ($this->_WS_LOGIN); }
	private function getWSSoap() { return ($this->_WS_SOAP); }
	private function getWSLoginUri() { return($this->_HOST.$this->__WSDL_LOGIN_URI); }
	private function getWSScriptUri() { return($this->_HOST.$this->__WSDL_SCRIPT_URI); }
	private function getCacheSessionData() {
		if ((session_id() != '') && (isset($_SESSION['CACHE_SESSION_DATA'])))
			return($_SESSION['CACHE_SESSION_DATA']);
		return('');
	}
}