<?php

include 'db.php';

#### NOTE NOTE NOTE NOTE
# be VERY careful about $types passed into sql
# i = integer,  d = double,  s=string
# I have been burned using integer instead of double


date_default_timezone_set('America/New_York');

//if(isset($_SESSION['authenticated'])){
#   session_start();

$result = batchRequest();

#### $result = processRequest();
//} else {
//    ### must be logged in to use this...
//    header('HTTP/1.1 401 Unauthorized');
//    $response{'StatusCd'} = 401;
//    return $response;
//}
$json = json_encode($result);
echo $json;

function batchRequest(){
    $dbh = createDatabaseConnection();
    #echo "I am in the batchRequest\n";
    $data = getUserInfo($dbh,1);
    return $data;
}



########################################################
function processRequest(){
//  if (isset($_SESSION['customer_id'])) {
//    $customer_id = $_SESSION['customer_id'];
//  }   else  {
//     die ('invalid customer id');
//  }
    $customer_id = 1;  // TODO: temp hack
    switch ($_SERVER['REQUEST_METHOD']) {
       case 'POST':
             $result = processPost($customer_id);
             break;
       case 'GET':
             $result = processGet($customer_id);
             break;
       default:
             echo "Error:Invalid Request";
             break;
    }
    return $result;
}
####################  GETs ################################
function  processGet($customer_id){
    $dbh = createDatabaseConnection();
    $action = htmlspecialchars($_GET["action"]);
    switch ($action) {
       case 'getCriteriaRefData':
              $result = getCriteriaRefData($dbh);
              break;
       default:
             echo "Error:Invalid Request:Action not set properly";
             break;
    }
    return $result;
}
####################  POSTs ################################
function  processPost($customer_id){
    $postdata = file_get_contents("php://input");
    $request = json_decode($postdata);
    $dbh = createDatabaseConnection();
    $action = $request->action;

    switch ($action) {
       case 'saveCriteria':
             $result = saveCriteria($dbh, $request, $customer_id);
             break;
       default:
             echo "Error:Invalid Request:Action not set properly";
             break;
    }
    return $result;
}

#################### GETS #####################################################
function  getUserInfo($dbh, $customer_id){
    $query = "select * from users where idusers = ?";
    $types = 'i';  ## pass
    $params = array($customer_id);
    $data = execSqlSingleRowPREPARED($dbh, $query, $types, $params);
    return $data;
}


?>