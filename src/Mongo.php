<?php 
namespace src;
use \Ev as Ev;
use \EvTimer as EvTimer;

class Mongo {

	/**
	 * @var collection to store the collection object
	 */
	protected $collection 				= 	null;

	/**
	 * @var splDoublyLinkedList to store the \SplDoublyLinkedList object
	 */
	protected $splDoublyLinkedList 		= 	null;

	/**
	 * @var splQueue to store the \SplQueue class
	 */
	protected $splQueue					=	null;

	/**
	 * @var cte is the repetition time of each event loop
	 */
	protected $cte 						=	0;

	/**
	 * @var id , each event have an id
	 */
	protected $id 						=	null;

	/**
	 * @var table to store the table to use it in a operation
	 */
	protected $table 					=	null;

	/**
	 * @var options to store the options of operation
	 */
	protected $options 					=	null;

	/**
	 * @var time of each operation
	 */
	protected $time   					=	0;

	/**
	 * @var $resultsFromSelects to store the callback of select operation
	 */
	protected $resultsFromSelects		=	null;

	/**
	 * @var $resultFunction to store the callback of the others operation
	 */
	protected $resultFunction			=	null;

	/**
	 * @var events to store all the events
	 */
	protected $events 					=	[];

	/**
	 * @var dataToInsert to store the data tha it will be inserted in the database
	 */
	protected $dataToInsert 			=	null;

	/**
	 * @var selects to store the select event data
	 */
	protected $selects 					=	[];

	/**
	 * @var inserted to store the number of inserted records
	 */
	protected $inserted 				=	[];

	/**
	 * @var lastUsedKey to store last key used in insert operation
	 */
	protected $lastUsedKey 				=	[];

	/**
	 * @var updateCount to store the number of updated
	 */
	protected $updateCount 				= 	0;

	/**
	 * @var deleteCount to store the number of deleted
	 */
	protected $deleteCount 				= 	0;

	/**
	 * @param collection object
	 */
	public function __construct(object $collection) {
		if (phpversion() < 7.1) {
			die("PHP version is < 7.1");
		}
		if (!class_exists("SplDoublyLinkedList")) {
			die("SplDoublyLinkedList class not existing in your PHP");
		}
		if (!class_exists("SplQueue")) {
			die("SplQueue class not existing in your PHP");
		}
		if (!extension_loaded("ev")) {
			die("Ev extension is not loaded");
		}
		$this->collection 			= 	$collection;
		$this->createSplDoublyLinkedList("splDoublyLinkedList", \SplDoublyLinkedList::IT_MODE_DELETE);
		$this->splQueue 			= 	new \SplQueue();
		$this->cte 					=	1/pow(10, 10);
		$this->splQueue->setIteratorMode(\SplDoublyLinkedList::IT_MODE_KEEP);
	}

	/**
	 * @method createSplDoublyLinkedList
	 * @param string $varName
	 * @param int mode https://www.php.net/manual/en/class.spldoublylinkedlist.php
	 * @return bool
	 */
	public function createSplDoublyLinkedList(string $varName, int $mode) : bool {
		$this->$varName 	= 	new \SplDoublyLinkedList();
		$this->$varName->setIteratorMode($mode);
		return true;
	}

	/**
	 * @method insertTo a table 
	 * @param string table 
	 * @param array data to insert
	 * @param float time in seconds
	 * @param callbale resultFunction 
	 * @return void	
	 */
	public function insertTo(string $table, array $data, float $time, callable $resultFunction): void {
		$this->eventId 				= 		uniqid();
		$this->table 				= 		$table;
		$this->splDoublyLinkedList->push([$this->eventId => $data]);
		$this->time 				=		$time;
		$this->resultFunction 		= 		$resultFunction;
		$this->enqueue("insert");
	}

	/**
	 * @method selectFrom
	 * @param string table 
	 * @param array options
	 * @param float time
	 * @param callable resultFunction
	 * @return void
	 */
	public function selectFrom(string $table, array $options, float $time, callable $resultFunction): void {
		$this->eventId 				= 	uniqid();
		$this->table 				=	$table;
		$this->options 				= 	$options;
		$this->time 				=	$time;
		$this->resultFunction 		= 	$resultFunction;
		$this->createSplDoublyLinkedList("resultsFromSelects", \SplDoublyLinkedList::IT_MODE_DELETE);
		$this->splDoublyLinkedList->push($this->eventId);
		$this->enqueue("select");
	}


	/**
	 * @method updateFrom
	 * @param stirng table
	 * @param array options
	 * @param array newData
	 * @param float time in seconds
	 * @param callbale resultFunction
	 * @return void
	 */
	public function updateFrom(string $table, array $options, array $newData, float $time, callable $resultFunction) : void{
		$this->eventId 				= 		uniqid();
		$this->table 				= 		$table;
		$this->options 				=		$options;
		$this->splDoublyLinkedList->push([$this->eventId => $newData]);
		$this->time 				=		$time;
		$this->resultFunction 		= 		$resultFunction;
		$this->enqueue("update");
	}

	/**
	 * @method deleteFrom
	 * @param stirng table
	 * @param array options
	 * @param float time in seconds
	 * @param callbale resultFunction
	 * @return void
	 */
	public function deleteFrom(string $table, array $options, float $time, callable $resultFunction) : void {
		$this->eventId 				= 		uniqid();
		$this->table 				= 		$table;
		$this->options 				=		$options;
		$this->splDoublyLinkedList->push([$this->eventId => []]);
		$this->time 				=		$time;
		$this->resultFunction 		= 		$resultFunction;
		$this->enqueue("delete");
	}

	/**
	 * @method enqueue 
	 * @param string type of the queue (insert, select, update, delete)
	 * @return void
	 */
	public function enqueue(string $type) : void {
		switch ($type) {
			case 'insert':
				$this->enqueueInsert();
			break;
			
			case 'select':
				$this->enqueueSelect();
			break;

			case 'update':
				$this->enqueueUpdate();
			break;

			case 'delete':
				$this->enqueueDelete();
			break;
		}
	}

	/**
	 * @method enqueueSelect
	 * To add select operation to the queue
	 * @return void
	 */
	public function enqueueSelect() : void {
		$this->splDoublyLinkedList->rewind();
		while ($this->splDoublyLinkedList->valid() AND !is_array($this->splDoublyLinkedList->current())) {
			$this->splQueue->enqueue([
				"table" 			=> 		$this->table,
				"type"				=> 		"select",
				"options" 			=> 		$this->options,
				"callback" 			=> 		$this->resultFunction,
				"time"				=>		$this->time,
				"id" 				=> 		uniqid(),
			]);
			$this->splDoublyLinkedList->next();
		}
	}

	/**
	 * @method enqueueSelect
	 * To add select operation to the queue
	 * @return void
	 */
	public function enqueueInsert() : void {
		$this->splDoublyLinkedList->rewind();
		while ($this->splDoublyLinkedList->valid() AND is_array($this->splDoublyLinkedList->current())) {
			$this->splQueue->enqueue([
				"table" 			=> 		$this->table,
				"options" 			=> 		$this->options,
				"type" 				=> 		"insert",
				"data"				=>		$this->splDoublyLinkedList->current()[$this->eventId],
				"callback" 			=> 		$this->resultFunction,
				"time"				=>		$this->time,
				"id" 				=> 		uniqid(),
				"toInsert"			=> 		sizeof($this->splDoublyLinkedList->current()[$this->eventId]),
			]);
			$this->splDoublyLinkedList->next();
		}
	}

	/**
	 * @method enqueueUpdate
	 * To add update operation to the queue
	 * @return void
	 */
	public function enqueueUpdate() : void {
		$this->splDoublyLinkedList->rewind();
		while ($this->splDoublyLinkedList->valid() AND is_array($this->splDoublyLinkedList->current())) {
			$this->splQueue->enqueue([
				"table" 			=> 		$this->table,
				"options" 			=> 		$this->options,
				"type" 				=> 		"update",
				"newData"			=>		$this->splDoublyLinkedList->current()[$this->eventId],
				"callback" 			=> 		$this->resultFunction,
				"time"				=>		$this->time,
				"id" 				=> 		uniqid(),
			]);
			$this->splDoublyLinkedList->next();
		}
	}

	/**
	 * @method enqueueDelete
	 * To add delete operation to the queue
	 * @return void
	 */
	public function enqueueDelete() : void {
		$this->splDoublyLinkedList->rewind();
		while ($this->splDoublyLinkedList->valid() AND is_array($this->splDoublyLinkedList->current())) {
			$this->splQueue->enqueue([
				"table" 			=> 		$this->table,
				"options" 			=> 		$this->options,
				"type" 				=> 		"delete",
				"callback" 			=> 		$this->resultFunction,
				"time"				=>		$this->time,
				"id" 				=> 		uniqid(),
			]);
			$this->splDoublyLinkedList->next();
		}
	}

	/**
	 * @method run
	 * To start running the queues
	 * @return void
	 */
	public function run() : void {
		$this->splQueue->rewind();
		if($this->splQueue->isEmpty()) {
			die("splQueue is empty , please add some operations. \n");
		}
		$this->createEvents();
	}

	/**
	 * @method createEvents
	 * To create Event for each operation in queues
	 * @return void
	 */
	public function createEvents() : void {
		while ($this->splQueue->valid()) {
			switch ($this->splQueue->current()["type"]) {
				case 'insert':
					$this->inserted[$this->splQueue->current()["id"]] 		= 	0;
					$this->lastUsedKey[$this->splQueue->current()["id"]] 	= 	0;
					$this->createEventForInsert($this->splQueue->current());
				break;
				case 'select':
					$this->selects[$this->splQueue->current()["id"]] 	= 	0;
					$this->createSplDoublyLinkedList("resultsFromSelects", \SplDoublyLinkedList::IT_MODE_DELETE);
					$this->createEventForSelect($this->splQueue->current());
				break;
				case 'update':
					$this->createEventForUpdate($this->splQueue->current());
				break;
				case 'delete':
					$this->createEventForDelete($this->splQueue->current());
				break;
			}
			$this->splQueue->next();
		}
		Ev::run();
	}

	/**
	 * @method createEventForSelect
	 * @param array data
	 * To create Event for select operation in queue
	 * @return int
	 */
	public function createEventForSelect(array $data) : int {
		return array_push($this->events, 
			new EvTimer(0, $this->cte, function($w) use ($data) {
				$table = $data["table"];
				$startTime = microtime(true);
				while (true) {
					if (empty($data)) {
						$w->stop();
						break;
					}
					$reuslts = $this->fetchResults($this->collection, $table, $data["options"], $this->selects[$data["id"]]);
					if ($reuslts->isDead() or is_null($reuslts)) {
						$this->resultsFromSelects->rewind();
						call_user_func($data["callback"], $this->resultsFromSelects);
						$w->stop();
						break;
					}else {
						$this->resultsFromSelects->push($reuslts);
						$this->selects[$data["id"]]++;
					}
					$end = microtime(true) - $startTime;
					if ($end >= $data["time"]) {
						break;
					}
				}
			})
		);
	}

	/**
	 * @method fetchResults
	 * @param object collection
	 * @param string table
	 * @param array options
	 * @param int skip
	 * To fetch data from a collection
	 * @return object of MongoDB\Driver\Cursor
	 */
	public function fetchResults(object $collection, string $table, array $options, int $skip) : object {
		return ($skip == 0) ? 
			$collection->$table->find($options, ['limit' => 1]) : 
			$collection->$table->find($options, ['skip' => $skip]);
	}

	/**
	 * @method createEventForInsert
	 * @param array data
	 * To create Event for insert operation in queue
	 * @return int
	 */
	public function createEventForInsert(array $data) : int {
		return array_push($this->events, 
			new EvTimer(0, $this->cte, function($w) use ($data) {
				if (empty($data)) {
					$w->stop();
				}
				$this->createSplDoublyLinkedList("dataToInsert", \SplDoublyLinkedList::IT_MODE_DELETE);
				$this->dataToInsert->push($data["data"]);
				$this->dataToInsert->rewind();
				$last_key 				= key(array_slice($this->dataToInsert->current(), -1, 1, TRUE ));
				$startTime = microtime(true);
				for ($i = $this->lastUsedKey[$data["id"]]; $i <= $last_key ; $i++) {
					if ($i == $last_key) {
						call_user_func($data["callback"]);
						$w->stop();
						break;
					} 
					$end = microtime(true) - $startTime;
					if ($end >= $data["time"]) {
						$this->lastUsedKey[$data["id"]] = $i;
						break;
					}
					$table = $data["table"];
					$this->collection->$table->insertOne($this->dataToInsert->current()[$i]);
				}
			})
		);
	}

	/**
	 * @method createEventForUpdate
	 * @param array data
	 * To create Event for update operation in queue
	 * @return int
	 */
	public function createEventForUpdate(array $data) : int {
		if (!$this->checkIfCollectionExists($data["table"])) {
			die("Collection : ".$data["table"]." not found .".PHP_EOL);
		}
		return array_push($this->events, 
			new EvTimer(0, $this->cte, function($w) use ($data) {
				$table = $data["table"];
				$startTime = microtime(true);
				while(true) {
					if (empty($data)) {
						$w->stop();
					}
					$updateResult = $this->collection->$table->updateOne($data["options"], ['$set' => $data["newData"]]);
					if ($updateResult->getModifiedCount() == 0) {
						call_user_func($data["callback"], $this->updateCount);
						$w->stop();
						break;
					}
					$this->updateCount++;
					$end = microtime(true) - $startTime;
					if ($end >= $data["time"]) {
						break;
					}
				}	
			})
		);
	}


	/**
	 * @method createEventForDelete
	 * @param array data
	 * To create Event for delete operation in queue
	 * @return int
	 */
	public function createEventForDelete(array $data) : int {
		if (!$this->checkIfCollectionExists($data["table"])) {
			die("Collection : ".$data["table"]." not found .".PHP_EOL);
		}
		return array_push($this->events, 
			new EvTimer(0, $this->cte, function($w) use ($data) {
				$table = $data["table"];
				$startTime = microtime(true);
				while(true) {
					if (empty($data)) {
						$w->stop();
					}
					$deleteResult = $this->collection->$table->deleteOne($data["options"]);
					if ($deleteResult->getDeletedCount() == 0) {
						call_user_func($data["callback"], $this->deleteCount);
						$w->stop();
						break;
					}
					$this->deleteCount++;
					$end = microtime(true) - $startTime;
					if ($end >= $data["time"]) {
						break;
					}
				}	
			})
		);
	}
	
	/**
	 * @method checkIfCollectionExists 
	 * @param string table name
	 * @return boolean
	 */
	public function checkIfCollectionExists(string $table) : bool {
		foreach ($this->collection->listCollections() as $collection) {
			if ($collection["name"] == $table) {
				return true;
			}
		}
		return false;
	}

}