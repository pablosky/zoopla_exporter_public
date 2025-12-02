<?php
  // this class defines the cycle of getting properties
  class Exporter {
    public $token;
    public $username;
    public $password;
    public $datafeedID;
    public $branches;
    public $properties;

    function __construct($token, $username, $password, $datafeedID) {
      $this->token = $token;
      $this->password = $password;
      $this->username = $username;
      $this->datafeedID = $datafeedID;
      $this->properties = [];
    }

    function lastToken(){
      $tokens = array();
      $token_file = file('tokens.txt', FILE_IGNORE_NEW_LINES);
      foreach ($token_file as $line) {
          $tokens[] = trim($line);
      }
      $last_token = end($tokens);
      $last_token = explode("','", $last_token);
      $last_token = trim($last_token[0], "'");

      if($this->expiredToken($last_token) == true) {
        $this->getToken();
        return $this->token;
      } else {
        return $last_token;
      }
    }

    function expiredToken($check_token){
      $this->token = $check_token;
      $check = $this->getBranches();
      if ($check == "get_new_token") {
        print_r("expired token! \n");
        return true;
      } else {
        return false;
      }
    }

    function getToken(){
      $url = "http://webservices.vebra.com/export/$this->datafeedID/v10/branch";
      print_r("token is: ".$this->token."\n");
      print_r("getting new token! \n");
      $file = "headers.txt";
	    $fh = fopen($file, "w");
      //Start curl session
      $ch = curl_init($url);
	    //Define Basic HTTP Authentication method
	    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	    //Provide Username and Password Details
      $passkey = $this->username .":". $this->password;
      print_r($passkey);
	    curl_setopt($ch, CURLOPT_USERPWD, $passkey);
	    //Show headers in returned data but not body as we are only using this curl session to aquire and store the token
	    curl_setopt($ch, CURLOPT_HEADER, 1); 
	    curl_setopt($ch, CURLOPT_NOBODY, 1); 
	    //write the output (returned headers) to file
	    curl_setopt($ch, CURLOPT_FILE, $fh);
      //execute curl session
      curl_exec($ch);
      // close curl session
      curl_close($ch); 
      //close headers.txt file
      fclose($fh); 

      //read each line of the returned headers back into an array
      $headers = file('headers.txt', FILE_SKIP_EMPTY_LINES);
	
      //for each line of the array explode the line by ':' (Seperating the header name from its value)
      foreach ($headers as $headerLine) {

        $line = explode(':', $headerLine);
        $header = $line[0];
        $value = trim($line[1]);
        
        //If the request is successful and we are returned a token
        if($header == "Token") {
            //save token start and expire time (roughly)
            $tokenStart = time(); 
            $tokenExpire = $tokenStart + 60*60; 
            //save the token in a session variable (base 64 encoded)
            $this->token = base64_encode($value); 
            
            //For now write this new token, its start and expiry datetime into a .txt (appending not overwriting - this is for reference in case you loose your session data)
            $file = "tokens.txt";
            $fh = fopen($file, "a+");
            //write the line in
            $newLine = "'".$this->token."','".date('d/m/Y H:i:s', $tokenStart)."','".date('d/m/Y H:i:s', $tokenExpire)."'"."\n";
            fwrite($fh, $newLine);
            //Close file
            fclose($fh);
          }
          else {
            print_r("ERROR GETTING TOKEN");
          }
          
        }
      print_r("newww token is: ".$this->token."\n");
    }

    function getBranches(){
      $url = "https://webservices.vebra.com/export/$this->datafeedID/v12/branch";
      $file = "branches.xml";
		  $fh = fopen($file, "w");
		
      //Initiate a new curl session
      $ch = curl_init($url);
      //Don't require header this time as curl_getinfo will tell us if we get HTTP 200 or 401
      curl_setopt($ch, CURLOPT_HEADER, 0); 
      //Provide Token in header
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic '.$this->token));
      //Write returned XML to file
      curl_setopt($ch, CURLOPT_FILE, $fh);
      //Execute the curl session
      curl_exec($ch);
      //Close the curl session
      curl_close($ch);
      //Close the open file handle
      fclose($fh);
      //Store the curl session info/returned headers into the $info array
      $info = curl_getinfo($ch);
      $error = "";
      //Check if we have been authorised or not
      if($info['http_code'] == '401') {
        echo 'Token Failed - getToken() has been run!<br />';
        $error = "get_new_token";
      } elseif ($info['http_code'] == '200') {
        echo 'Token Worked - Success';
      }
      if($error != ""){
        return $error;
      }else {
        $xml = simplexml_load_file("branches.xml");
        $this->branches = $xml;
        return $xml;
      }
    }

    function getAllProperties() {
      $branches = [];

      foreach ($this->branches as $branch) {
        print_r($branch);
        // 	// cast the xml object to string
        $branch_request = (string) $branch->url[0];
        $branch_id = (string) $branch->branchid[0];
        $this->getProperties($branch_request, $branch_id);
      }
      //now that we have all properties we put them in a xml too
      $this->saveProperties();
    }

    function getProperties($branch_request, $branch_id){
      print_r("requesting ".$branch_request);
      print_r("\n");
      $url = $branch_request;
      $file = "properties" . $branch_id . ".xml";
		  $fh = fopen($file, "w");
		
      //Initiate a new curl session
      $ch = curl_init($url);
      //Don't require header this time as curl_getinfo will tell us if we get HTTP 200 or 401
      curl_setopt($ch, CURLOPT_HEADER, 0); 
      //Provide Token in header
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic '.$this->token));
      //Write returned XML to file
      curl_setopt($ch, CURLOPT_FILE, $fh);
      //Execute the curl session
      curl_exec($ch);
      //Close the curl session
      curl_close($ch);
      //Close the open file handle
      fclose($fh);
      //Store the curl session info/returned headers into the $info array
      $info = curl_getinfo($ch);
      
      //Check if we have been authorised or not
      if($info['http_code'] == '401') {
        echo 'Token Failed - getToken() has been run!<br />';
        //return  $this->getToken();
      } elseif ($info['http_code'] == '200') {
        echo "Token Worked - Success \n";
      }

      $xml = simplexml_load_file($file);
      array_push($this->properties, $xml);
      return $xml;
    }

    function saveProperties(){
      $xmlArray = $this->properties;
      $doc = new DOMDocument('1.0', 'UTF-8');

      // Create the root element
      $root = $doc->createElement('properties');
      $doc->appendChild($root);
      
      // Loop through the array and append each SimpleXMLElement to the root
      foreach ($xmlArray as $xml) {
          $importedNode = $doc->importNode(dom_import_simplexml($xml), true);
          $root->appendChild($importedNode);
      }
      
      // Save the XML document to a file
      $doc->save('exported_properties.xml');

    }
  }
  // ADD YOUR CREDENTIALS HERE
  $username = "";
  $password = "";
  $datafeedID = "";
  //TOKEN WILL BE LOADED
  $token = "";
  $exporter = new Exporter($token, $username, $password, $datafeedID);
  // token setup
  $exporter->token = $exporter->lastToken();
  print_r("setting token \n");
  print_r($exporter->token);
  // end of token setup

  // print_r($exporter->token);
  //$exporter->getToken();
  print_r("\n getting branches .. \n");
  print_r($exporter->getBranches());
  print_r("\n getting properties from the branches ..\n ");
  print_r($exporter->getAllProperties());
  print_r("\n exported propertiese check your exported_properties.xml file ..\n ");
  print_r($exporter->properties);

?>