<?
require("serviceREST.php");

$c = new serverREST();
$c->addFunction("helloWorld","GET","/hello/world/");
$c->execServer();
/*
header('HTTP/1.1 200 OK');
header('Content-type: application/json');
$data = $_REQUEST;
if($_SERVER['REQUEST_METHOD'] != "GET" && $_SERVER['REQUEST_METHOD'] != "POST")
{
	//parse_str(file_get_contents('php://input'), $data);
	$data = file_get_contents('php://input');
	$data = json_decode($data,true);
}
echo json_encode($data, JSON_FORCE_OBJECT);
*/
function helloWorld($data)
{
	return array(
		'statusCode' => 200,
		'data' => "Hello World Example!".print_r($data,true)."\n",
		'type' => null);
}
?>
