<?php
/**********************************************************************
//
// Warpwire REST Connection Class 
// version 1.2.1
//
// Allows Sakai to make a REST authentication connection
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

class WWSakaiRest {

	// host name information
	private $_HOST = null;
	// basic user information
	private $_USER = null;
	// basic password information
	private $_PASS = null;
	
	// Web Service Connection Objects
	private $_WS_LOGIN = null;
	// cookie jar for web services
	private $_COOKIE_JAR = null;
	// user membership within Sakai
	private $_MEMBERSHIP = array();
	// the user id for the user
	private $_USER_ID;
	// the first name of the user
	private $_FIRST_NAME = null;
	// the last name of the user
	private $_LAST_NAME = null;
	// the unique identifier of the user
	private $_USER_UNIQUE_IDENTIFIER = null;
	// indicate that additional debugging should be shown
	private $_IS_DEV = false;

	// create a new basic soap object with assumed credentials
	public function __construct($_host = '', $_user = '', $_pass = '', $_params = array(), $_IS_DEV = false) {
		// set the hostname
		$this->setHost($_host);
		// set the username
		$this->setUser($_user);
		// set the password
		$this->setPassword($_pass);
		// set the debugging mode to allow for additional reporting
		if ((isset($_IS_DEV) && ($_IS_DEV == true)))
			$this->_IS_DEV = true;
	}
	
	// make a connection to the soap service
	public function connect() {
		// attempt to make a connection to the login endpoint
		try {
			// a previous session was not provided - use standard authentication method
			if (strlen(trim($this->getCacheSessionData())) <= 0) {
				// build the login URL
				$_loginUrl = $this->getHost().'session';
				// build the array to authenticate
				$_params = array(
					'_username' => $this->getUser(),
					'_password' => $this->getPassword()
				);
				// attempt to login to the service
				$_login = $this->request($_loginUrl, $_params);

				// the username or pass is not valid
				if (! isset($_login['http_code']))
					throw new Exception('Unable to connect to the authentication service');
				// ensure that the cookie jar is valid
				if ((! isset($_login['cookieJar'])) || (! is_file($_login['cookieJar'])))
					throw new Exception('Unable to save a cookie to sign you in. Please try again.');
				// set the cookie jar
				$this->setCookieJar($_login['cookieJar']);
			
				// detect if the login is valid
				if (($_login['http_code'] != 200) && ($_login['http_code'] != 201))
					throw new Exception('Login parameters are incorrect');
			// a previous session with data was found
			} else {
				// set a temporary cookie jar file
				$cookieJar = tempnam(sys_get_temp_dir(), "WarpwireSessionCookieJar_");
				$this->setCookieJar($cookieJar);
				@file_put_contents($cookieJar, $this->getCacheSessionData());
			}

			// assemble the session URL
			$_sessionUrl = $this->getHost().'session.json';
			// ensure that we can get the user identifier
			$_session = $this->request($_sessionUrl, array(), $this->getCookieJar());

			// decode the json object
			$_results = $this->getJson($_session['content']);
			
			// there must be at least one session collection record
			if ((! isset($_results['session_collection'])) || (empty($_results['session_collection'])))
				throw new Exception('Unable to get the user unique identifier (1)');
			
			// get the first entry
			$_sessionRecord = array_pop($_results['session_collection']);
			// the usereid value (user unique identifier) must be set
			if ((! isset($_sessionRecord['userEid'])) || (empty($_sessionRecord['userEid'])))
				throw new Exception('Unable to get the user unique identifier (2)');
			
			// the user id field must also be valid
			if ((! isset($_sessionRecord['userId'])) || (empty($_sessionRecord['userId'])))
				throw new Exception('Unable to get the user id');
			
			// set the user id
			$this->setUserId($_sessionRecord['userId']);
			// set the user unique identifier
			$this->setUserUniqueIdentifier($_sessionRecord['userEid']);
			
			// make a query for the membership list
			$_membershipURL = $this->getHost().'membership.json';
			// ensure that the user is a member of at least one course
			$_members = $this->request($_membershipURL, array(), $this->getCookieJar());

			// decode the json object
			$_results = $this->getJson($_members['content']);
			
			$_hasMembership = true;
			// the user is not a member of any membership collectin
			if ((! is_array($_results['membership_collection'])) || (empty($_results['membership_collection'])))
				$_hasMembership = false;
				//throw new Exception('You are not a member of any courses or sites. Please contact support.');
			
			// set the membership collection
			if($_hasMembership)
				$this->setMembership($_results['membership_collection']);
		}
		// pass the exception to the handler
		catch (Exception $e) {
			$_loginUrl = $this->getHost().'session';
			$headers = @get_headers($_loginUrl);
			// reset the cookie jar data
			$this->setCacheSessionData('');
			// clean up the connection - specifically unlinking the cookie jar file
			$this->cleanUp();
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

	// returns the user first name
	public function getUserFirstName() {
		$_firstName = trim($this->getFirstName());
		// return the value if it is not empty
		if (! empty($_firstName))
			return($_firstName);
			
		// assemble the site URL
		$_userUrl = $this->getHost().'user/current.json';
		// get a listing of all of the sites that the user is a member
		$_userData = $this->request($_userUrl, array(), $this->getCookieJar());
		// decode the json object
		$_results = $this->getJson($_userData['content']);
		// set the first name and last name
		if (isset($_results['firstName']))
			$this->setFirstName(trim($_results['firstName']));
		// set the last name
		if (isset($_results['lastName']))
			$this->setLastName(trim($_results['lastName']));
		// return the user first name
		return($this->getFirstName());		
	}
	
	// returns the user last name
	public function getUserLastName() {
		$_lastName = trim($this->getLastName());
		// return the value if it is not empty
		if (! empty($_lastName))
			return($_lastName);
			
		// assemble the site URL
		$_userUrl = $this->getHost().'user/current.json';
		// get a listing of all of the sites that the user is a member
		$_userData = $this->request($_userUrl, array(), $this->getCookieJar());
		// decode the json object
		$_results = $this->getJson($_userData['content']);
		// set the first name and last name
		if (isset($_results['firstName']))
			$this->setFirstName(trim($_results['firstName']));
		// set the last name
		if (isset($_results['lastName']))
			$this->setLastName(trim($_results['lastName']));
		// return the user first name
		return($this->getLastName());		
	}

	// get the user display name
	public function getUserDisplayName() {
		// the session must be active
		if (count($_memberships = ($this->getMembership())) <= 0)
			return('');
		// pop the first record off of the stack and return the display name
		$_membership = array_pop($_memberships);
		// the user display name is not valid
		if ((! isset($_membership['userDisplayName'])) || (empty($_membership['userDisplayName'])))
			return('');
		// return the user display name
		return($_membership['userDisplayName']);
	}

	// get the user email 
	public function getUserEmail() {
		// the session must be active
		if (count($_memberships = ($this->getMembership())) <= 0)
			return('');
		// pop the first record off of the stack and return the user email
		$_membership = array_pop($_memberships);
		// the user email is not valid
		if ((! isset($_membership['userEmail'])) || (empty($_membership['userEmail'])))
			return('');
		// return the user display email
		return($_membership['userEmail']);
	}

	// get the user id
	public function getUserId() {
		// return the user id
		return($this->_USER_ID);
	}

	// get the user unique identifier
	public function getUserUniqueIdentifier() {
		// return the user unique identifier
		return($this->_USER_UNIQUE_IDENTIFIER);
	}

	// get a list of all user sites for this user
	public function getAllUserSites() {
		// the session must be active
		if (count($_memberships = ($this->getMembership())) <= 0)
			return(array());
			
		// assemble the site URL
		$_siteUrl = $this->getHost().'site.json';
		// get a listing of all of the sites that the user is a member
		$_sitesData = $this->request($_siteUrl, array(), $this->getCookieJar());
		// decode the json object
		$_results = $this->getJson($_sitesData['content']);
		// the user is not a member of any site colleciton
		if (count($_results['site_collection']) <= 0)
			throw new Exception('You are not a member of any site collections. Please contact support.');
		
		// map the site collections
		$_sites = $_results['site_collection'];
		
		// iterate throught the document, storing the key and group name
		$_output = array();
		foreach($_sites AS $_site) {
			// all sites must have corresponding ids
			if (! isset($_site['id'])) continue;
			// add the title it exist
			$title = 'Unknown Title';
			if (isset($_site['title']))
				$title = trim($_site['title']);
			// add the object to the output array
			$_output[trim($_site['id'])] = $_site['title'];
		}
		return($_output);
	}

	// remove the cookie jar file
	public function cleanUp() {
		if ($this->getCookieJar() == '')
			return(true);
		// remove the cookie file
		@unlink($this->getCookieJar());
		return(true);
	}

	// a global comamnd to download files via curl
	public function request($url, $params = array(), $cookieJar = '') {
		try {
			// set the curl options			
			$options = array(
				CURLOPT_RETURNTRANSFER => true,     // return web page
				CURLOPT_HEADER         => false,    // don't return headers
				CURLOPT_FOLLOWLOCATION => true,     // follow redirects
				CURLOPT_ENCODING       => '',       // handle all encodings
				CURLOPT_USERAGENT      => 'Warpwire-Authenticator-REST', // who am i
				CURLOPT_AUTOREFERER    => true,     // set referer on redirect
				CURLOPT_CONNECTTIMEOUT => 10,      // timeout on connect
				CURLOPT_TIMEOUT        => 10,      // timeout on response
				CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
			);
			// set the request method
			if ((is_array($params)) && (! empty($params))) {
				$options[CURLOPT_POST] = true;
				$postFields = http_build_query($params);
				$options[CURLOPT_POSTFIELDS] = $postFields;
			}
			// set a cookie jar if it specified
			if ((isset($cookieJar)) && (! empty($cookieJar)) && (file_exists($cookieJar))){
				$options[CURLOPT_COOKIEFILE] = $cookieJar;
				$this->setCacheSessionData(@file_get_contents($cookieJar));
			}	
			// create a cookie jar
			else {
				$cookieJar = tempnam(sys_get_temp_dir(), "WarpwireSessionCookieJar_");
				$options[CURLOPT_COOKIEJAR] = $cookieJar;
				$this->setCacheSessionData(@file_get_contents($cookieJar));
			}

			// get a new curl handler
			$ch = curl_init($url);
			curl_setopt_array($ch, $options);
			$content = curl_exec($ch);
			$err = curl_errno($ch);
			$errmsg = curl_error($ch);
			$output = curl_getinfo($ch);
			curl_close($ch);
			$output['errno']   = $err;
			$output['errmsg']  = $errmsg;
			$output['content'] = $content;
			$output['cookieJar'] = $cookieJar;
			if ($this->_IS_DEV) {
				print('<p><br /><span style="font-weight:bold">DEV CURL Debug:</span>
					<textarea style="margin:0 auto;width:100%;max-width:400px;height:300px;">CURL Response:'.PHP_EOL.PHP_EOL.
					var_export($content, true).PHP_EOL.PHP_EOL.'CURL OUTPUT: '.
					var_export($output, true).'</textarea></p>');
			}
			return ($output);
		} catch (Exception $e) {
			throw $e;
		}
	}

	// returns if the last json parsed element was valid
	private function getJson($string) {
		$_output = @json_decode($string, true);
		// make sure the json was valid
		if (json_last_error() != JSON_ERROR_NONE)
			throw new Exception('Unable to parse the JSON output. Please contact support.');
		return($_output);
	}
						
	// naive retrieval function
	private function getHost() { return ($this->_HOST); }
	private function getUser() { return ($this->_USER); }
	private function getPassword() { return ($this->_PASS); }
	private function getWSLogin() { return ($this->_WS_LOGIN); }
	private function getCookieJar() { return ($this->_COOKIE_JAR); }
	private function getCacheSessionData() {
		if ((session_id() != '') && (isset($_SESSION['CACHE_SESSION_DATA'])))
			return($_SESSION['CACHE_SESSION_DATA']);
		return('');
	}
	private function getMembership() { return($this->_MEMBERSHIP); }
	private function getFirstName() { return($this->_FIRST_NAME); }
	private function getLastName() { return($this->_LAST_NAME); }
	private function setCookieJar($a) { $this->_COOKIE_JAR = $a; }
	private function setCacheSessionData($a) {
		if (session_id() == '')
			return(false);
		// set the cookie jar data
		$_SESSION['CACHE_SESSION_DATA'] = $a;
		// set the session expiration
		$_SESSION['EXPIRATION'] = strtotime('+4 hours', time());
	}
	private function setMembership($a) { $this->_MEMBERSHIP = $a; }
	private function setUserId($a) { $this->_USER_ID = $a; }
	private function setUserUniqueIdentifier($a) { $this->_USER_UNIQUE_IDENTIFIER = $a; }
	private function setFirstName($a) { $this->_FIRST_NAME = $a; }
	private function setLastName($a) { $this->_LAST_NAME = $a; }
}