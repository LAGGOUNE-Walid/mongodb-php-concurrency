# mongodb-php-concurrency
Concurrency PHP library for MongoDB
### Requirements
- PHP >= 7.2
- Ev extension http://docs.php.net/manual/en/ev.setup.php
### Install
sudo composer require mongo-concurrency/mongo-concurrency @dev
### Examples
```php
<?php
require_once __DIR__ . "/vendor/autoload.php";
$collection 	= 	(new MongoDB\Client)->myDb;
$m		= 	new MongoConcurrency\Mongo($collection);
$textData	=	  [];
$options 	=	  []; 
$intData 	= 	[];
echo "Generating test data ... ";
for ($i=1; $i <= 10000  ; $i++) { 
	array_push($intData, [$i => $i]);
}
for ($i=0; $i <= 5000 ; $i++) { 
	array_push($textData, ["name" => "john"]);
}
echo "[+] \n";


// select from test1 for 5 seconds with no options array (options is the filter array in : https://docs.mongodb.com/php-library/v1.2/reference/method/MongoDBCollection-findOne/#phpmethod.MongoDB\Collection::findOne)
$m->selectFrom("test1", [], 5, function(iterable $results) {
	echo "  [+] End select : ".sizeof($results)."\n";
});

// insert in to test2 array textData for 1 second
$m->insertTo("test2", $textData, 1, function() {
	echo "  [+] End insert \n";
});

// update from test2 where name = john to name = alex for 6 seconds
$m->updateFrom("test2", ["name" => "john"], ["name" => "alex"], 6, function(int $modified) {
	echo " [+] End update : ".$modified." \n";
});

// delete from test2 where name = john for 5 seconds
$m->deleteFrom("test2", ["name" => "john"], 5, function(int $intDataeleted) {
	echo " [+] End delete : ".$intDataeleted."\n";
});
$m->run();
```
### Results 
![Image of Results](https://raw.githubusercontent.com/DilawskyJordan/mongodb-php-concurrency/master/Screenshot%20from%202020-03-01%2023-39-56.png)
