<?

header('Content-type: text/json');

ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE);
//error_reporting(E_ALL);
date_default_timezone_set('UTC');
include "../inc/misc.php";
require_once "../inc/const.php";
require_once 'api.php';
$ios_api = new Ios_api();

if (isset($_GET['method'])) {
    switch ($_GET['method']) {
        case 'getJobs':
            echo $ios_api->getJobs();
            break;

        case 'getSectors':
            echo $ios_api->getSectors();
            break;

        case 'getWorks':
            echo $ios_api->getWorks();
            break;
    }
} else if (isset($_POST['getJobsGzip'])) {
    echo gzencode($ios_api->getJobs());
} else if (isset($_POST['getSectorsGzip'])) {
    echo gzencode($ios_api->getSectors());
} else if (isset($_POST['getWorksGzip'])) {
//+         print_r($api->getWorks()); exit;
    echo gzencode($ios_api->getWorks());
} else if (isset($_POST['getVesselsGzip'])) {
    $offset = $_POST['getVesselsGzip'];
//         print_r($api->getVessels($offset)); exit;
    echo gzencode($ios_api->getVessels($offset));
} else if (isset($_POST['setVessels'])) {
//    echo "setVessels function";
    $ios_api->setVessels();
//    return true;
} else if (isset($_POST['getVesselsGzipExtended'])) {
    $search_str = $_POST['getVesselsGzipExtended'];
//         print_r($api->getVesselsExtended($search_str)); exit;
    echo gzencode($ios_api->getVesselsExtended($search_str));
} else if (isset($_POST['getVesselsGzipExtended1'])) {
    $search_str = $_POST['getVesselsGzipExtended1'];
    print_r($ios_api->getVesselsExtended1($search_str));
    exit;
    echo gzencode($ios_api->getVesselsExtended1($search_str));
} else if (isset($_POST['loginUser'])) {
    $data = $_POST['loginUser'];
    echo $ios_api->loginUser($data);
} else if (isset($_POST['fbLoginUser'])) {
    $data = $_POST['fbLoginUser'];
    echo $ios_api->fbLoginUser($data);
} else if (isset($_POST['regUser'])) {
//     $data = $_POST['regUser'];		old
//     $photo = null;
// 
//     if (isset($_FILES['photo']))
//         $photo = $_FILES['photo'];
// 
//     echo $ios_api->regUser($data, $photo);
    
    $data = $_POST['regUser'];
    echo $ios_api->regUser($data);
}
else if (isset($_POST['setFriends'])) {
    $data = $_POST['setFriends'];
    echo $ios_api->setFriends($data);
} else if (isset($_POST['checkUser'])) {
    $data = $_POST['checkUser'];
    echo $ios_api->checkUser($data);
} else if (isset($_POST['update'])) {
    $data = $_POST['update'];
    $photo = null;

    if (isset($_FILES['photo']))
        $photo = $_FILES['photo'];

    echo $ios_api->update($data, $photo);
}

else if (isset($_POST['updatePhoto'])) {
    $data = $_POST['updatePhoto'];
    $photo = null;
   
    if (isset($_FILES['photo']))
        $photo = $_FILES['photo'];

    echo $ios_api->updatePhoto($data, $photo);
}

else if (isset($_POST['getFriends'])) {
    $data = $_POST['getFriends'];
    echo $ios_api->getFriends($data);
} else if (isset($_POST['getUser'])) {
    $data = $_POST['getUser'];
    echo $ios_api->getUser($data);
} else if (isset($_POST['test'])) {
    echo md5('1258447'); die;
    print_r($ios_api->test($_POST['test']));
} else if (isset($_POST['myTest'])) {
    $data = $_POST['myTest'];
    if (isset($_FILES['photo']))
        $photo = $_FILES['photo'];
// 	echo $ios_api->myTest($data, $photo);
	echo $ios_api->SuggestionsAndRequests2($data);
} else if (isset($_POST['sendMessage'])) {
    $data = $_POST['sendMessage'];
    echo $ios_api->sendMessage($data);
} else if (isset($_POST['updatePass'])) {
    $data = $_POST['updatePass'];
    echo $ios_api->updatePass($data);
} 
 else if (isset($_POST['deleteFriends'])) {
    $data = $_POST['deleteFriends'];
    echo $ios_api->deleteFriends($data);
} 
 else if (isset($_GET['google_play_android_url'])) {
    echo json_encode(array("androidURL" => GOOGLE_PLAY_ANDROID_URL));
} 

 else if (isset($_POST['peopleMayKnow'])) {
    $data = $_POST['peopleMayKnow'];
    echo $ios_api->peopleMayKnow($data);
} 
 else if (isset($_POST['SuggestionsAndRequests'])) {
    $data = $_POST['SuggestionsAndRequests'];
    echo $ios_api->SuggestionsAndRequests($data);
} 
 else if (isset($_POST['acceptConnectRequest'])) {
    $data = $_POST['acceptConnectRequest'];
    echo $ios_api->acceptConnectRequest($data);
} 
 else if (isset($_POST['requestToFriends'])) {
    $data = $_POST['requestToFriends'];
    echo $ios_api->requestToFriends($data);
} 
<<<<<<< HEAD
=======
 else if (isset($_POST['spamWall'])) {
    $data = $_POST['spamWall'];
    echo $ios_api->spamWall($data);
} 
>>>>>>> 02fd690946ad290c346b51e523c2038a4b64ef0b
else
    echo "Method error";
?>