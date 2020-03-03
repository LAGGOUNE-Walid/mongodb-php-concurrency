<?php
require_once __DIR__ . "/vendor/autoload.php";
$collection 	= 	(new MongoDB\Client)->myDb;
$m 				= 	new Mongo\Mongo($collection);
$textData		=	[];
$options 		=	[]; 
$intData 		= 	[];
echo "Generating test data ... ";
for ($i=1; $i <= 10000  ; $i++) { 
	array_push($intData, [$i => $i]);
}
for ($i=0; $i <= 5000 ; $i++) { 
	array_push($textData, ["name" => "john"]);
}
echo "[+] \n";

$m->selectFrom("test1", [], 3, function(iterable $results) {
	echo "  [+] End select : ".sizeof($results)."\n";
});
$m->insertTo("test2", $textData, 3, function() {
	echo "  [+] End insert \n";
});
$m->updateFrom("test2", ["name" => "john"], ["name" => "alex"], 3, function(int $modified) {
	echo " [+] End update : ".$modified." \n";
});
$m->deleteFrom("test2", ["name" => "john"], 3, function(int $intDataeleted) {
	echo " [+] End delete : ".$intDataeleted."\n";
});
$m->run();