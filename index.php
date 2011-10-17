<?php

try {
  // include the LinkedIn class
  require_once('linkedin_3.1.1.class.php');
  
  // start the session
  if(!session_start()) {
    throw new LinkedInException('This script requires session support, which appears to be disabled according to session_start().');
  }
  
  // display constants
  $API_CONFIG = array(
    'appKey'       => 'n81fhtjdo4h4',
	  'appSecret'    => 'qhmXE1itatrw74ch',
	  'callbackUrl'  => NULL 
  );
  define('CONNECTION_COUNT', 20);
  define('PORT_HTTP', '80');
  define('PORT_HTTP_SSL', '443');
  define('UPDATE_COUNT', 10);

  $type = LINKEDIN::_GET_TYPE;

  // set index
  $_REQUEST[$type] = (isset($_REQUEST[$type])) ? $_REQUEST[$type] : '';

  switch($_REQUEST[$type]) {
    case 'initiate':
      /**
       * Handle user initiated LinkedIn connection, create the LinkedIn object.
       */
        
      // check for the correct http protocol (i.e. is this script being served via http or https)
      if($_SERVER['HTTPS'] == 'on') {
        $protocol = 'https';
      } else {
        $protocol = 'http';
      }
      
      // set the callback url
      $API_CONFIG['callbackUrl'] = $protocol . '://' . $_SERVER['SERVER_NAME'] . ((($_SERVER['SERVER_PORT'] != PORT_HTTP) || ($_SERVER['SERVER_PORT'] != PORT_HTTP_SSL)) ? ':' . $_SERVER['SERVER_PORT'] : '') . $_SERVER['PHP_SELF'] . '?' . LINKEDIN::_GET_TYPE . '=initiate&' . LINKEDIN::_GET_RESPONSE . '=1';
      $SimpleLI = new LinkedIn($API_CONFIG);
      
      // check for response from LinkedIn
      $_GET[LINKEDIN::_GET_RESPONSE] = (isset($_GET[LINKEDIN::_GET_RESPONSE])) ? $_GET[LINKEDIN::_GET_RESPONSE] : '';
      if(!$_GET[LINKEDIN::_GET_RESPONSE]) {
        // LinkedIn hasn't sent us a response, the user is initiating the connection
        
        // send a request for a LinkedIn access token
        $response = $SimpleLI->retrieveTokenRequest();
        if($response['success'] === TRUE) {
          // store the request token
          $_SESSION['oauth']['linkedin']['request'] = $response['linkedin'];
          
          // redirect the user to the LinkedIn authentication/authorisation page to initiate validation.
          header('Location: ' . LINKEDIN::_URL_AUTH . $response['linkedin']['oauth_token']);
        } else {
          // bad token request
          echo "Request token retrieval failed:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($SimpleLI, TRUE) . "</pre>";
        }
      } else {
        // LinkedIn has sent a response, user has granted permission, take the temp access token, the user's secret and the verifier to request the user's real secret key
        $response = $SimpleLI->retrieveTokenAccess($_SESSION['oauth']['linkedin']['request']['oauth_token'], $_SESSION['oauth']['linkedin']['request']['oauth_token_secret'], $_GET['oauth_verifier']);
        if($response['success'] === TRUE) {
          // the request went through without an error, gather user's 'access' tokens
          $_SESSION['oauth']['linkedin']['access'] = $response['linkedin'];
          
          // set the user as authorized for future quick reference
          $_SESSION['oauth']['linkedin']['authorized'] = TRUE;
            
          // redirect the user back to the demo page
          header('Location: ' . $_SERVER['PHP_SELF']);
        } else {
          // bad token access
          echo "Access token retrieval failed:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($SimpleLI, TRUE) . "</pre>";
        }
      }
      break;

    case 'revoke':
      /**
       * Handle authorization revocation.
       */
                    
      // check the session
      if(!oauth_session_exists()) {
        throw new LinkedInException('This script requires session support, which doesn\'t appear to be working correctly.');
      }
      
      $SimpleLI = new LinkedIn($API_CONFIG);
      $SimpleLI->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
      $response = $SimpleLI->revoke();
      if($response['success'] === TRUE) {
        // revocation successful, clear session
        session_unset();
        $_SESSION = array();
        if(session_destroy()) {
          // session destroyed
          header('Location: ' . $_SERVER['PHP_SELF']);
        } else {
          // session not destroyed
          echo "Error clearing user's session";
        }
      } else {
        // revocation failed
        echo "Error revoking user's token:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($SimpleLI, TRUE) . "</pre>";
      }
      break;
    default:
      // Demo
      
    ?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>LinkedIn Profile Demo</title>

    <!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>
    <link rel="stylesheet" href="bootstrap.min.css">
    <style type="text/css">
      body {
        padding-top: 60px;
      }
    </style>
  </head>

  <body>
    <div class="topbar">
      <div class="fill">
        <div class="container">
          <a class="brand" href="#">LinkedIn Profile Demo</a>
        </div>
      </div>
    </div>
    <div class="container">
          <?php

          if(checkSession() === true) {
            // user is already connected
            $SimpleLI = new LinkedIn($API_CONFIG);
            $SimpleLI->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
            ?>
            
            <?php
            $response = $SimpleLI->profile('~:(id,first-name,last-name,headline,industry,summary,location,picture-url,positions,educations,recommendations-received,connections)');
            if($response['success'] === TRUE) {
              $response['linkedin'] = new SimpleXMLElement($response['linkedin']);

              $li_profile = $response['linkedin'];

              ?>
          <div class="well">
            <img src="<?php echo $li_profile->{'picture-url'} ?>" style="float: left; padding-right: 10px;">
            <h1><?php echo $li_profile->{'first-name'} . ' ' . $li_profile->{'last-name'} ?>
              <small><?php echo $li_profile->{'headline'} ?></small>
            </h1>
            <p><?php echo $li_profile->{'summary'} ?></p>
            <br>
            <?php 
              if(count($li_profile->{'recommendations-received'}->{'recommendation'}) > 0) {
                foreach ($li_profile->{'recommendations-received'}->{'recommendation'} as $rec) {
                ?>
                <blockquote>
                  <p><?php echo $rec->{'recommendation-text'} ?></p>
                  <small><?php echo $rec->{'recommender'}->{'first-name'} . ' ' . $rec->{'recommender'}->{'last-name'} ?></small>
                </blockquote>
                <?php
                }          
              }

            ?>
          </div>     
          <?php

              if(count($li_profile->{'connections'}->{'person'}) > 0) { 
                $connections = $li_profile->{'connections'}->{'person'};
              ?>
              
              <ul class="media-grid">
              <?php
                $con_count = 0;
                $show_cons = 16; // How many connections to show
                foreach ($connections as $person) {
                  if($con_count >= $show_cons) continue;
                  if(!isset($person->{'picture-url'})) continue;
                  $con_count++;
                ?>
                  <li><a href="<?php echo $person->{'site-standard-profile-request'}->{'url'} ?>"><img class="thumbnail" src="<?php echo $person->{'picture-url'} ?>" title="<?php echo $person->{'first-name'} . ' ' . $person->{'last-name'} . ', ' . $person->{'headline'} ?>"></a></li>
                <?php
                }          
              ?>
              </ul>
              <br>
              <?php
              }

              ?>
              <div class="row">
              <?php
              if(count($li_profile->{'positions'}->{'position'}) > 0) { 
              ?>
              
                <div class="span8">
                  <div class="well">
                    <strong>Work Experience:</strong>
              
                    <ul>
                    <?php
                      foreach ($li_profile->{'positions'}->{'position'} as $pos) {
                      ?>
                        <li><?php echo $pos->{'title'} . ', ' . $pos->{'company'}->{'name'} ?></li>
                      <?php
                      }          
                    ?>
                    </ul>
                  </div>
                </div>
              <?php
              }

              if(count($li_profile->{'educations'}->{'education'}) > 0) {
              ?>
                <div class="span8">
                  <div class="well">
                    <strong>Education:</strong>
              
                    <ul>
                    <?php
                      foreach ($li_profile->{'educations'}->{'education'} as $edu) {
                      ?>
                        
                        <li><?php echo $edu->{'school-name'} ?></li>
                      <?php
                      }          
                    ?>
                    </ul>
                  </div>
                </div>
              <?php
              }

              ?>
              </div><!-- work experience / education row -->

              <?php

              //echo "<pre>";
              //print_r($li_profile);

            } else {
              // profile retrieval failed
              echo "Error retrieving profile information:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response) . "</pre>";
            } 
            ?>
          <!--</div>--> <!-- hero-unit --><?php
          } else {
            // user isn't connected
            ?>
          <div class="hero-unit">
            <h1>LinkedIn Profile Demo</h1>
            <p>This app connects to the LinkedIn API and shows the user's profile.</p>
            <p><a class="btn primary large" href="?<?php echo $type ?>=initiate">Connect to LinkedIn &raquo;</a></p>
          </div>
            <?php
          }
          ?>
        </body>
      </html>
      <footer>
        <p>LI Profile - Demo by Eli Yelluas 
        <?php if(checkSession()) : ?>
        <a href="?<?php echo $type ?>=revoke">Revoke App Access&raquo;</a>
        </p>
        <?php endif ; ?>
      </footer>

    </div> <!-- /container -->
  </body>
</html>
      <?php
      break;
  }
} catch(LinkedInException $e) {
  // exception raised by library call
  echo $e->getMessage();
}

function checkSession() {
    $_SESSION['oauth']['linkedin']['authorized'] = (isset($_SESSION['oauth']['linkedin']['authorized'])) ? $_SESSION['oauth']['linkedin']['authorized'] : FALSE;

    return $_SESSION['oauth']['linkedin']['authorized'];
}

function oauth_session_exists() {
  if((is_array($_SESSION)) && (array_key_exists('oauth', $_SESSION))) {
    return TRUE;
  } else {
    return FALSE;
  }
}

?>
