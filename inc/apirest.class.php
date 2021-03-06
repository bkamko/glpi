<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2015 Teclib'.

 http://glpi-project.org

 based on GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2014 by the INDEPNET Development Team.

 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/** @file
* @brief
*/

class APIRest extends API {
   protected $request_uri;
   protected $url_elements;
   protected $verb;
   protected $parameters;
   protected $debug = 0;
   protected $format = "json";


   /**
    * @see CommonGLPI::GetTypeName()
    */
   public static function getTypeName($nb = 0) {
      return __('Rest API');
   }


   /**
    * parse url and http body to retrieve :
    *  - HTTP VERB (GET/POST/DELETE/PUT)
    *  - Resource : Rest endpoint
    *  - Identifier
    *  - and parameters
    *
    *  And send to method corresponding identified resource
    *
    * @since version 9.1
    *
    * @return     json with response or error
    */
   public function call() {
      //parse http request and find parts
      $this->request_uri  = $_SERVER['REQUEST_URI'];
      $this->verb         = $_SERVER['REQUEST_METHOD'];
      $path_info          = str_replace("api/", "", trim($_SERVER['PATH_INFO'], '/'));
      $this->url_elements = explode('/', $path_info);
      $this->parseIncomingParams();

      // Add headers for CORS
      $this->cors($this->verb);

      // show debug if required
      if (isset($this->parameters['debug'])) {
         $this->debug = $this->parameters['debug'];
         if (empty($this->debug)) {
            $this->debug = 1;
         }

         if ($this->debug >= 2) {
            $this->showDebug();
         }
      }

      // retrieve requested resource
      $resource = trim(strval($this->url_elements[0]));

      // retrieve session (if exist)
      $this->retrieveSession();

      // retrieve param who permit session writing
      if (isset($this->parameters['session_write'])) {
         $this->session_write = (bool)$this->parameters['session_write'];
      }

      // inline documentation (api/)
      if (strlen($resource) == 0 || $resource == "api") {
         return $this->inlineDocumentation("apirest.md");

      ## DECLARE ALL ENDPOINTS ##
      // login into glpi
      } else if ($resource === "initSession") {
         $this->session_write = true;
         return $this->returnResponse($this->initSession($this->parameters));

      // logout from glpi
      } else if ($resource === "killSession") {
         $this->session_write = true;
         return $this->returnResponse($this->killSession());

      // change active entities
      } else if ($resource === "changeActiveEntities") {
         $this->session_write = true;
         return $this->returnResponse($this->changeActiveEntities($this->parameters));

      // get all entities of logged user
      } else if ($resource === "getMyEntities") {
         return $this->returnResponse($this->getMyEntities($this->parameters));

      // get curent active entity
      } else if ($resource === "getActiveEntities") {
         return $this->returnResponse($this->getActiveEntities($this->parameters));

      // change active profile
      } else if ($resource === "changeActiveProfile") {
         $this->session_write = true;
         return $this->returnResponse($this->changeActiveProfile($this->parameters));

      // get all profiles of current logged user
      } else if ($resource === "getMyProfiles") {
         return $this->returnResponse($this->getMyProfiles($this->parameters));

      // get current active profile
      } else if ($resource === "getActiveProfile") {
         return $this->returnResponse($this->getActiveProfile($this->parameters));

      // get complete php session
      } else if ($resource === "getFullSession") {
         return $this->returnResponse($this->getFullSession($this->parameters));

      // list searchOptions of an itemtype
      } else if ($resource === "listSearchOptions") {
         $itemtype = $this->getItemtype(1);
         return $this->returnResponse($this->listSearchOptions($itemtype, $this->parameters));

      // Search on itemtype
      } else if ($resource === "search") {
         self::checkSessionToken();

         $itemtype = $this->getItemtype(1, true, true);
         //clean stdObjects in parameter
         $params = json_decode(json_encode($this->parameters), true);
         //search
         $response =  $this->searchItems($itemtype, $params);

         //add pagination headers
         $additionalheaders = array();
         $additionalheaders["Content-Range"] = $response['content-range'];
         $additionalheaders["Accept-Range"]  = $itemtype." ".Toolbox::get_max_input_vars();

         // diffent http return codes for complete or partial response
         if ($response['count'] >= $response['count']) {
            $code = 200; // full content
         } else {
            $code = 206; // partial content
         }

         return $this->returnResponse($response, $code, $additionalheaders);

      // commonDBTM manipulation
      } else {
         $itemtype          = $this->getItemtype(0);
         $id                = $this->getId();
         $additionalheaders = array();
         $code              = 200;
         switch ($this->verb) {
            default:
            case "GET": // retrieve item(s)
               if ($id > 0 || $id == 0 && $itemtype == "Entity") {
                  $response = $this->getItem($itemtype, $id, $this->parameters);
                  if (isset($response['date_mod'])) {
                     $datemod = strtotime($response['date_mod']);
                     $additionalheaders['Last-Modified'] = date('r', $datemod);
                  }
               } else {
                  // return collection of items
                  $response = $this->getItems($itemtype, $this->parameters);

                  //add pagination headers
                  if (!isset($this->parameters['range'])) {
                     $this->parameters['range'] = "0-50";
                  }
                  $additionalheaders["Content-Range"] = $this->parameters['range']."/".count($response);
                  $additionalheaders["Accept-Range"]  = $itemtype." ".Toolbox::get_max_input_vars();
               }
               break;

            case "POST": // create item(s)
               $response = $this->createItems($itemtype, $this->parameters);
               $code = 201;
               if (count($response) == 1) {
                  // add a location targetting created element
                  $additionalheaders['location'] = self::$api_url.$itemtype."/".$response['id'];
               } else {
                  // add a link header targetting created elements
                  $additionalheaders['link'] = "";
                  foreach($response as $created_item) {
                     if ($created_item['id']) {
                        $additionalheaders['link'].= self::$api_url.$itemtype.
                                                     "/".$created_item['id'].",";
                     }
                  }
                  // remove last comma
                  $additionalheaders['link'] = trim($additionalheaders['link'], ",");
               }
               break;

            case "PUT": // update item(s)
               // if id is passed by query string, add it into input parameter
               $input = (array) ($this->parameters['input']);
               if ($id > 0 && !isset($input['id'])) {
                  $this->parameters['input']->id = $id;
               }
               $response = $this->updateItems($itemtype, $this->parameters);
               break;

            case "DELETE": //delete item(s)
               // if id is passed by query string, construct an object with it
               if ($id > 0) {
                  $code = 204;
                  //override input
                  $this->parameters['input'] = new stdClass();;
                  $this->parameters['input']->id = $id;
               }
               $response = $this->deleteItems($itemtype, $this->parameters);
               break;
         }
         return $this->returnResponse($response, $code, $additionalheaders);
      }

      $this->messageLostError();
   }


   /**
    * Retrieve and check itemtype from $this->url_elements
    *
    * @since version 9.1
    *
    * @param  integer $index         we'll find itemtype in this index of $this->url_elements
    * @param  boolean $recursive     can we go depper or we trigger an http error if we fail to find itemtype ?
    * @param  boolean $all_assets    if we can have allasset virtual type
    *
    * @return boolean
    */
   private function getItemtype($index = 0, $recursive = true, $all_assets = false) {
      if (isset($this->url_elements[$index]))  {
         if (class_exists($this->url_elements[$index])
               && is_subclass_of($this->url_elements[$index], 'CommonDBTM')
             || $all_assets
               && $this->url_elements[$index] == "AllAssets" ) {
            $itemtype = $this->url_elements[$index];

            if ($recursive && $additional_itemtype = $this->getItemtype(2, false)) {
               $this->parameters['parent_itemtype'] = $itemtype;
               $itemtype = $additional_itemtype;
            }

            return $itemtype;
         } else {
            $this->returnError(__("resource not found or not an instance of CommonDBTM"),
                               400,
                               "ERROR_RESOURCE_NOT_FOUND_NOR_COMMONDBTM");
         }
      } else if ($recursive) {
         $this->returnError(__("missing resource"), 400, "ERROR_RESOURCE_MISSING");
      }

      return false;
   }


   /**
    * Retrieve in url_element the current id. If we have a multiple id (ex /Ticket/1/TicketFollwup/2),
    * it always find the second
    *
    * @since version 9.1
    *
    * @return int id of current itemtype (or false if not found)
    */
   private function getId() {
      $id            = isset($this->url_elements[1]) && is_numeric($this->url_elements[1])
                       ?intval($this->url_elements[1])
                       :false;
      $additional_id = isset($this->url_elements[3]) && is_numeric($this->url_elements[3])
                       ?intval($this->url_elements[3])
                       :false;

      if ($additional_id || isset($this->parameters['parent_itemtype'])) {
         $this->parameters['parent_id'] = $id;
         $id = $additional_id;
      }

      return $id;
   }


   /**
    * Construct this->parameters from query string and http body
    *
    * @since version 9.1
    */
   public function parseIncomingParams() {
      $parameters = array();

      // first of all, pull the GET vars
      if (isset($_SERVER['QUERY_STRING'])) {
         parse_str($_SERVER['QUERY_STRING'], $parameters);
      }

      // now how about PUT/POST bodies? These override what we got from GET
      $body = trim(file_get_contents("php://input"));
      if (strlen($body) > 0 && $this->verb == "GET") {
         // GET method requires an empty body
         $this->returnError("GET Request should not have json payload (http body)", 400, "ERROR_JSON_PAYLOAD_FORBIDDEN");
      }

      $content_type = "";
      if(isset($_SERVER['CONTENT_TYPE'])) {
         $content_type = $_SERVER['CONTENT_TYPE'];
      }

      if (strpos($content_type, "application/json") !== false) {
         if($body_params = json_decode($body)) {
            foreach($body_params as $param_name => $param_value) {
               $parameters[$param_name] = $param_value;
            }
         } else if (strlen($body) > 0) {
            $this->returnError("JSON payload seems not valid", 400, "ERROR_JSON_PAYLOAD_INVALID", false);
         }
         $this->format = "json";

      } elseif (strpos($content_type, "multipart/form-data") !== false) {
         if (count($_FILES) <= 0) {
            // likely uploaded files is too big so $_REQUEST will be empty also.
            // see http://us.php.net/manual/en/ini.core.php#ini.post-max-size
            $this->returnError("The file seems too big", 400, "ERROR_UPLOAD_FILE_TOO_BIG_POST_MAX_SIZE", false);
         }

         // with this content_type, php://input is empty... (see http://php.net/manual/en/wrappers.php.php)
         if(!$uploadManifest = json_decode(stripcslashes($_REQUEST['uploadManifest']))) {
            $this->returnError("JSON payload seems not valid", 400, "ERROR_JSON_PAYLOAD_INVALID", false);
         }
         foreach($uploadManifest as $field => $value) {
            $parameters[$field] = $value;
         }
         $this->format = "json";

      } elseif (strpos($content_type, "application/x-www-form-urlencoded") !== false) {
         parse_str($body, $postvars);
         foreach($postvars as $field => $value) {
            $parameters[$field] = $value;
         }
         $this->format = "html";

      } else {
         $this->format = "html";
      }

      // try to retrieve basic auth
      if (isset($_SERVER['PHP_AUTH_USER'])
          && isset($_SERVER['PHP_AUTH_PW'])) {
         $parameters['login']    = $_SERVER['PHP_AUTH_USER'];
         $parameters['password'] = $_SERVER['PHP_AUTH_PW'];
      }

      // try to retrieve user_token in header
      if (isset($_SERVER['HTTP_AUTHORIZATION'])
          && strpos($_SERVER['HTTP_AUTHORIZATION'], 'user_token') !== false) {
         $auth = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
         if (isset($auth[1])) {
            $parameters['user_token'] = $auth[1];
         }
      }

      // try to retrieve session_token in header
      if (isset($_SERVER['HTTP_SESSION_TOKEN'])) {
         $parameters['session_token'] = $_SERVER['HTTP_SESSION_TOKEN'];
      }

      // try to retrieve app_token in header
      if (isset($_SERVER['HTTP_APP_TOKEN'])) {
         $parameters['app_token'] = $_SERVER['HTTP_APP_TOKEN'];
      }

      $this->parameters = $parameters;
   }


   /**
    * Generic function to send a message and an http code to client
    *
    * @since version 9.1
    *
    * @param mixed    $response          string message or array of data to send
    * @param integer  $httpcode          http code (see : https://en.wikipedia.org/wiki/List_of_HTTP_status_codes)
    * @param array    $aditionnalheaders headers to send with http response (must be an array(key => value))
    */
   public function returnResponse($response, $httpcode = 200, $aditionnalheaders = array()) {

      if (empty($httpcode)) {
         $httpcode = 200;
      }

      foreach($aditionnalheaders as $key => $value) {
         header("$key: $value");
      }

      http_response_code($httpcode);
      self::header($this->debug);

      if ($response !== null) {
         $json = json_encode($response, JSON_UNESCAPED_UNICODE
                                      | JSON_UNESCAPED_SLASHES
                                      | JSON_NUMERIC_CHECK
                                      | ($this->debug?JSON_PRETTY_PRINT:0));
      } else {
         $json = '';
      }

      if ($this->debug) {
         echo "<pre>";
         var_dump($response);
         echo "</pre>";
      } else {
         echo $json;
      }
      exit;
   }

   /**
    * Display the APIRest Documentation in Html (parsed from markdown)
    *
    * @since version 9.1
    *
    * @param      string  $file   relative path of documentation file
    */
   public function inlineDocumentation($file = "apirest.md") {
      global $CFG_GLPI;

      if ($this->format == "html") {
         parent::inlineDocumentation($file);
      } else if ($this->format == "json") {
         echo file_get_contents(GLPI_ROOT.'/'.$file);
      }
   }
}
