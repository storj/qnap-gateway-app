<?php

# ------------------------------------------------------------------------
#  Set environment variables
# ------------------------------------------------------------------------
$filename = "config.json";

$platformBase   = $_SERVER['DOCUMENT_ROOT'];
$moduleBase     = $platformBase . dirname($_SERVER['PHP_SELF']) ;
$scriptsBase    = $moduleBase . '/scripts' ;


$file           = $moduleBase  . DIRECTORY_SEPARATOR . $filename  ;
$startScript    = $scriptsBase . DIRECTORY_SEPARATOR . 'gatewayrun.sh' ;
$configureScript     = $scriptsBase . DIRECTORY_SEPARATOR . 'gatewayconfigure.sh' ;
$stopScript     = $scriptsBase . DIRECTORY_SEPARATOR . 'gatewaystop.sh' ;
$updateScript = $scriptsBase . DIRECTORY_SEPARATOR . 'gatewayupdate.sh' ;
$isRunning      = $scriptsBase . DIRECTORY_SEPARATOR . 'isRunning.sh' ;
$dockerConfigFile = 'gateway/config.yaml';
logMessage("------------------------------------------------------------------------------");
logMessage("Platform Base($platformBase), ModuleBase($moduleBase) scriptBase($scriptsBase)");
# ------------------------------------------------------------------------


$output = "";

$data = json_decode(file_get_contents("php://input"), TRUE);

 // Saving Api Key, Encryption Passphrase and Satellite in JSON file.
if(isset($data['apiKey']) && isset($data['passphrase']) && isset($data['satellite'])){

    $apiKey = $data['apiKey'];
    $passphrase = $data['passphrase'];
    $satellite = $data['satellite'];
    $jsonString = file_get_contents($file);
    $data = json_decode($jsonString, true);

    $data['APIKey'] = $apiKey;
    $data['EncryptionPassphrase'] = $passphrase;
    $data['Satellite'] = $satellite;

     $data['AccessKey'] = "TestAccessKey";
    $data['SecretKey'] = "TestSecretKey";

    $newJsonString = json_encode($data);
    file_put_contents($file, $newJsonString);
  }


// Run Gateway
if(isset($_POST['isRun']) && ($_POST['isRun'] == 1)) {
    logMessage("config.php called up with isRun (for Running gateway) 1 ");
    logEnvironment() ;
   
    $output = shell_exec("/bin/bash $startScript 2>&1 ");

    $jsonString = file_get_contents($file);
    $data = json_decode($jsonString, true);
    $data['last_log'] = $output;
    $newJsonString = json_encode($data);
    file_put_contents($file, $newJsonString);
    echo $output;


  }

  // Configure Geteway
  else if(isset($_POST['isConfig']) && ($_POST['isConfig'] == 1)){

    $_address  = $_POST["address"];
    $_server   = $_POST["server"];
    $_api  = $_POST["api"];
    $_satellite      = $_POST["satellite"];
    $_encryptionPassphrase = $_POST['encryptionPassphrase'];
  
    $properties = array(
      'Port'  => $_address,
      'Server Address'  => $_server,
      'APIKey'=> $_api,
      'Passphrase' =>  $_encryptionPassphrase,
      'Satellite' => $_satellite,
      );
    file_put_contents($file, json_encode($properties));

    $content = file_get_contents($file);
    $properties = json_decode($content, true);

    logMessage("config.php called up with isConfgigureAjax 1 ");
    $output = shell_exec("/bin/bash $configureScript $_address $_satellite $_api $_encryptionPassphrase 2>&1 ");

    /* Reading Access key and secret key from YAML*/
    
    $searchaccesskey = 'minio.access-key';
    $searchsecretkey = 'minio.secret-key';

    header('Content-Type: text/plain');

    $contents = shell_exec('export PATH=$PATH:/share/CACHEDEV1_DATA/.qpkg/container-station/bin ; docker run --rm -v $(pwd)/gateway:/root/.local/share/storj/gateway --entrypoint /bin/cat storjlabs/gateway:ca666a0-v1.1.1-go1.13.8 /root/.local/share/storj/gateway/config.yaml 2>&1 ');


    $pattern = preg_quote($searchaccesskey, '/');
    $pattern = "/^\s*${pattern}\s*:.*\$/m";

    $pattern1 = preg_quote($searchsecretkey, '/');
    $pattern1 = "/^\s*${pattern1}\s*:.*\$/m";

    if(preg_match_all($pattern, $contents, $matches)){
      $accesskey = implode("\n", $matches[0]);
    }
      else{
          echo "No matches found";
    }

    if(preg_match_all($pattern1, $contents, $matches1)){
      $secretkey = implode("\n", $matches1[0]);
    }
    else{
   echo "No matches found";
    }
    $parts = explode(':', $accesskey);
    $parts1 = explode(':', $secretkey);
    $accesskey = str_replace(' ', '', $parts[1]);
    $secretkey = str_replace(' ', '', $parts1[1]);

    /* Update File again with Log value as well */
    $properties['last_log'] = $output ;
    $properties['AccessKey'] = $accesskey ;
    $properties['SecretKey'] = $secretkey ;
    file_put_contents($file, json_encode($properties));

  }


  // Update Gateway
  else if(isset($_POST['isUpdateAjax']) && ($_POST['isUpdateAjax'] == 1)){
    $content = file_get_contents($file);
    $properties = json_decode($content, true);

    logMessage("config called to update GATEWAY ");
    $server_address = $_SERVER['SERVER_ADDR'] ;
    //Log to be worked upon to correct the path and parameters
    $output = shell_exec("/bin/bash $updateScript 2>&1 ");

    /* Update File again with Log value as well */
    $properties['last_log'] = $output ;
    file_put_contents($file, json_encode($properties));

  } 

 // Stop Gateway
 else if(isset($_POST['isStop']) && ($_POST['isStop'] == 1)){
   
   // Excute shell script for stoping gateway process
   logMessage("config.php called up with isStopAjax 1 ");
    logEnvironment() ;
   
    $output = shell_exec("/bin/bash $stopScript 2>&1 ");

    $jsonString = file_get_contents($file);
    $data = json_decode($jsonString, true);
    $data['last_log'] = $output;
    $newJsonString = json_encode($data);
    file_put_contents($file, $newJsonString);
    echo $output;

 }


  // Check Geteway Process
  else if(isset($_POST['checkProcess']) && ($_POST['checkProcess'] == 1)) {
    $output = shell_exec("/bin/bash $isRunning ");
    logMessage("Run status of container is $output ");
    echo $output;
  }


 // Checking Geteway Status
  else if(isset($data['status']) ){
    if($data['status'] =="Start Gateway"){
       echo "Conneted";
    }else if ($data['status'] =="Stop Gateway") {
      echo "Stopped";
    }else if ($data['status'] =="Restart Gateway") {
      echo "Restarting";
    }else if ($data['status'] =="Cheking Process") {
      // Checking Geteway Process running or not
      echo "Conneted";
    }
  }



 else {
  // DEFAULT : Load contents at start
  logMessage("config called up with for loading ");
  //
  // checking if file exists.
  if(file_exists($file)){
  $content = file_get_contents($file);
  $prop = json_decode($content, true);
  logMessage("Loaded properties : " . print_r($prop, true));


      if($prop['Port'] == "" && $prop['Port'] == null && $prop['Server Address'] == "" && $prop['Server Address'] == null && $prop['APIKey'] == "" && $prop['APIKey'] == null && $prop['Satellite'] == "" && $prop['Satellite'] == null &&   $prop['AccessKey'] == "" && $prop['SecretKey'] == null && $prop['AccessKey'] == null && $prop['SecretKey'] == ""){
      echo "<script>location.href = 'index.html';</script>";
    }
  }

{

?>
<?php include 'header.php';?>
<style>
code {
        white-space: pre-wrap; /* preserve WS, wrap as necessary, preserve LB */
        /* white-space: pre-line; /* collapse WS, preserve LB */
}
</style>
<link href="./resources/css/config.css" type="text/css" rel="stylesheet">
  <div>
    <nav class="navbar">
      <a class="navbar-brand" href="index.php"><img src="./resources/img/logo.svg" /></a>
    </nav>
    <div class="row">
      <?php include 'menu.php'; ?>
          <?php
          if ( $output ){
          } else {

          ?>
          <div class="col-10 config-page">
            <div class="container-fluid">
              <h2>Setup</h2>
              <a href="https://documentation.tardigrade.io/api-reference/s3-gateway" target="_blank"><p class="header-link">Documentation ></p></a>
                 
                <!-- <div style="display:none" id="storjrows"> -->
                <div class="row segment">
                  <div class="column col-md-2"><div class="segment-icon port-icon"></div></div>
                  <div class="column col-md-10 segment-content">
                    <h4 class="segment-title">Port Forwarding</h4>
                    <p class="segment-msg">Port that will be used to run the endpoint</p>
                    <span id="externalAddressval"></span><span style="display:none;" id="editexternalAddressbtn"><button class="segment-btn editbtn" data-toggle="modal" data-target="#externalAddress">
                      Edit External Address
                    </button></span>
                    <button class="segment-btn" data-toggle="modal" data-target="#externalAddress" id="externalAddressbtn">
                      Add External Address
                    </button>
                    <div class="modal fade" id="externalAddress" tabindex="-1" role="dialog" aria-labelledby="externalAddress" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title">Port Forwarding</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <p class="modal-input-title">Host Address</p>
                          <input class="modal-input" id="host_address" name="host_address" type="text" class="quantity" placeholder="127.0.0.1:7777:7777" value="<?php if(isset($prop['Port'])) echo $prop['Port'] ?>"/>
                            <p class="host_token_msg msg" style="display:none;">Enter Valid Host Address</p>
                          </div>
                          <div class="modal-footer">
                            <button class="modal-btn" data-dismiss="modal">Close</button>
                            <button class="modal-btn" id="create_address">Set External Address</button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row segment">
                  <div class="column col-md-2"><div class="segment-icon wallet-icon"></div></div>
                  <div class="column col-md-10 segment-content">
                    <h4 class="segment-title">Server Address</h4>
                    <p class="segment-msg">Address to serve S3 api over</p>
                    <span id="wallettbtnval"></span><span style="display:none;" id="editwallettbtn"><button class="segment-btn editbtn" data-toggle="modal" data-target="#walletAddress">
                        Edit Server Address
                      </button></span>
                    <button class="segment-btn" data-toggle="modal" data-target="#walletAddress" id="addwallettbtn">
                      Add Server Address
                    </button>
                    <div class="modal fade" id="walletAddress" tabindex="-1" role="dialog" aria-labelledby="walletAddress" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title">Server Address</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <p class="modal-input-title">Server Address</p>
                            <input class="modal-input" name="Server Address" id="wallet_address" placeholder="0.0.0.0:7777" value="<?php if(isset($prop['Server Address'])) echo $prop['Server Address'] ?>"/>
                            <p class="wallet_token_msg msg" style="display:none;">This is required Field</p>
                          </div>
                          <div class="modal-footer">
                            <button class="modal-btn" data-dismiss="modal">Close</button>
                            <button class="modal-btn" id="create_wallet">Set Server Address</button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row segment">
                  <div class="column col-md-2"><div class="segment-icon storage-icon"></div></div>
                  <div class="column col-md-10 segment-content">
                    <h4 class="segment-title">API Key</h4>
                    <p class="segment-msg">Enter the API key you generated</p>
                    <span id="storagebtnval"></span><span style="display:none;" id="editstoragebtn"><button class="segment-btn editbtn" data-toggle="modal" data-target="#storageAllocation">
                      Edit API Key
                    </button></span>
                    <button class="segment-btn" data-toggle="modal" data-target="#storageAllocation" id="addstoragebtn">
                      Set API Key
                    </button>
                    <div class="modal fade" id="storageAllocation" tabindex="-1" role="dialog" aria-labelledby="storageAllocation" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title">API Key</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <p class="modal-input-title">API Key</p>
                            <input class="modal-input shorter" id="storage_allocate" name="storage_allocate" type="text" step="1" min="1" class="quantity" placeholder="Storj API Key" value="<?php if(isset($prop['APIKey'])) echo $prop['APIKey'] ?>"/>
                          <p class="storage_token_msg msg" style="display:none;">This is required Field</p>
                          </div>
                          <div class="modal-footer">
                            <button class="modal-btn" data-dismiss="modal">Close</button>
                            <button class="modal-btn" id="allocate_storage">Set API Key</button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>


               
                <div class="row segment">
                  <div class="column col-md-2"><div class="segment-icon email-icon"></div></div>
                  <div class="column col-md-10 segment-content">
                    <h4 class="segment-title">Satellite</h4>
                    <p class="segment-msg">Enter the satellite address corresponding to the satellite you've created your account on</p>
                    <span id="emailAddressval"></span><span style="display:none;" id="editemailAddressbtn"><button class="segment-btn editbtn" data-toggle="modal" data-target="#emailAddress">
                      Edit Satellite
                    </button></span>
                    <button class="segment-btn" data-toggle="modal" data-target="#emailAddress" id="emailAddressbtn">
                      Add Satellite
                    </button>
                    <div class="modal fade" id="emailAddress" tabindex="-1" role="dialog" aria-labelledby="email_address" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title">Satellite</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <p class="modal-input-title">Satellite</p>
                            <input class="modal-input" id="email_address" name="email_address" type="text" placeholder="<nodeid>@<address>:<port>" value="<?php if(isset($prop['Satellite'])) echo $prop['Satellite'] ?>"/>
                            <p class="email_token_msg msg" style="display:none;">This is required Field</p>
                          </div>
                          <div class="modal-footer">
                            <button class="modal-btn" data-dismiss="modal">Close</button>
                            <button class="modal-btn" id="create_emailaddress">Set Satellite</button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row segment">
                  <div class="column col-md-2"><div class="segment-icon directory-icon"></div></div>
                  <div class="column col-md-10 segment-content">
                    <h4 class="segment-title">Encryption Passphrase</h4>
                    <p class="segment-msg">Create and confirm an encryption passphrase, which is used to encrypt your files before they are uploaded</p>
                      <span id="directorybtnval" cl></span><span style="display:none;" id="editdirectorybtn"><button class="segment-btn editbtn" data-toggle="modal" data-target="#directory">
                      Edit Encryption Passphrase
                    </button></span>
                    <button class="segment-btn" data-toggle="modal" data-target="#directory" id="adddirectorybtn">
                      Set Encryption Passphrase
                    </button>
                    <div class="modal fade" id="directory" tabindex="-1" role="dialog" aria-labelledby="directory" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="identity">Encryption Passphrase</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <p class="modal-input-title">Encryption Passphrase</p>
                          <input style="width: 280px;" class="modal-input" id="storage_directory" name="storage_directory" placeholder="Encryption Passphrase"   />
                            <p class="directory_token_msg msg" style="display:none;position: relative;left: 34px;">This is required Field</p>
                          </div>
                          <div class="modal-footer">
                            <button class="modal-btn" data-dismiss="modal">Close</button>
                            <button class="modal-btn" id="create_directory">Set Encryption Passphrase</button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>


                <div class="bottom-buttons">
                   <button type="button" class="btn btn-primary configbtns" id="updatebtn">Update Gateway</button>
                  <div style="position: absolute;display: inline-block;left: 30%;">
                    <button type="button" disabled  id="stopbtn" class="btn btn-primary configbtns" style="cursor: not-allowed;">Configure Gateway</button>&nbsp;&nbsp;
                  <button type="button"  id="startbtn" class="btn btn-primary configbtns">Run Gateway</button>
                  <button type="button"  id="stop" class="btn btn-primary configbtns" >Stop Gateway</button>
                </div><br><br>
              <!-- log message -->
              <iframe>
                <p  id="msg"></p>
              </iframe>  
            </div>
          </div>
          <?php }
        } ?>
  </div>

  <p id="last_log" style="display: none;"><?php if(isset($prop['last_log'])) echo $prop['last_log'] ?></p>

<?php include 'footer.php';?>
<script type="text/javascript" src="./resources/js/config.js"></script>

<?php

}

function logEnvironment() {
  logMessage(
    "\n----------------------------------------------\n"
    . "ENV is : " . print_r($_ENV, true)
    . "POST is : " . print_r($_POST, true)
    . "SERVER is : " . print_r($_SERVER, true)
    . "----------------------------------------------\n"
  );
}

function logMessage($message) {
    $file = "/var/log/GATEWAY" ;
    // $file = "test" ;
    $message = preg_replace('/\n$/', '', $message);
    $date = `date` ; $timestamp = str_replace("\n", " ", $date);
    file_put_contents($file, $timestamp . $message . "\n", FILE_APPEND);
}

?>
