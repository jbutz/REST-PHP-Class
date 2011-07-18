<?
require("serviceREST.php");

$c = new clientREST();
print_r($c->execRequest("http://localhost/~jbutz/rest/hello/world","GET",array('hello','world')));
?>
