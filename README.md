# MySQL DataBase of Objects

MDBO is a superstructure of MySQL database. It works over PDO (PHP Data Objects) and implements easy-to-use interface to *create, read, update and delete* objects in database table.

Objects that stored in MySQL database such way is compatible with JSON format. This compatibility allows you to write applications that interact database in JavaScript-way, like MongoDB or similar. E.g.: JavaScript web applications, which use MySQL database on the server to store its data.

*(Inspired by [NeDB](https://github.com/louischatriot/nedb))*

## Getting started

*(Database structure is stored in `example/example.sql`)*

```php
require_once __DIR__ . 'inc/mdbo.php';

$dbName = 'dbobjects';
$dbHost = 'localhost';
$dbUser = 'user';
$dbPassword = 'password';

$db = new MDBO($dbName, $dbHost, $dbUser, $dbPassword);

// Or:
$tableName = 'table_name';
$secondDb = new MDBO($dbName, $dbHost, $dbUser, $dbPassword, $tableName);
```

## Inserting documents

```php
$doc = [
  'one' => 'two',
  'num' => 20,
  'isGoodDocument' => true,
  'blank' => null,
  'nested' => [
    'another' => 'line 123'
  ]
];

$db->create([
  $doc
]);
```

Method `create()` takes one document or array of documents as a parameter.

If only one document is given as a parameter, it returns `_id` of newly created document.

## Finding documents

Finding documents depending on array with conditions:

```php
$objectWithConditions = [
  'fieldName' => 'My name'
];

$result = $db->find($objectWithConditions); // Returns objects which have
                                            // A field `fieldName`
                                            // with `My name` value.
```

This example fits simple requests, when you want to find the documents with cretain properties.

If a field needs to be checked in more complex way, you need to use operators:

```php
$res = $db->find([
  'myNumber' => [
    '$in' => [1, 6 ,3]
  ]
]); // Returns all documents, which `myNumber` value is one of those: 1, 6 or 3.
```

Operators:

- `$lt`, `$lte` - less than, less than or equal,
- `$gt`, `$gte` - greater than, greater than or equal,
- `$in` - in array,
- `$ne` - not equal,
- `$nin` - not in array,
- `$exists` - check if property exists (property with `null` value actually exists; its value just not defined).

## Removing documents

To remove document, you need to know its `_id`:

```php
$rowsDeleted = $db->delete(100); // Object with `_id` 100 will be deleted.
$rowsDeleted = $db->delete([10, 15]); // Objects with `_id` 10 and 15
                                      // will be deleted.
```

## Updating documents

To update document(s) `update()` method is used. New version of object must have the same `_id`.

```php
$doc = [
  '_id' => 1,
  'one' => 'two',
  'num' => 20,
  'isGoodDocument' => true,
  'blank' => null,
  'nested' => [
    'another' => 'line 123'
  ]
];

$db->update(
  $doc
);
```

While updating, the old version of object will be removed.

Also, you can update multiple objects:

```php
$db->update([ $doc1, $doc2, $doc3 ]);
```

## Data types supported

All data, stored in database, will be transformed to one of the type:

- String (MySQL type: text),
- Number (MySQL type: double),
- Boolean (MySQL type: tinyint),
- null (this property type is used, if value is not set, but property exist).

## JSON interaction.

```php
// ...Auhorization or login code ommited...
// And let we assume, that we've got a correct JSON on the input.

$jsonString = $_POST['json'];
$json = json_decode($jsonString);

$res = $db->find($json);

header('Content-type: application/json');
echo json_encode($res);
exit();
```

## Accessing PDO

Also, you can access [PDO](https://www.php.net/manual/en/book.pdo.php) instance directly:

```php
$st = $db->_PDO->prepare("SELECT * FROM `my_table`");
$st->execute();
$result = $st->fetchAll(PDO::FETCH_ASSOC);
```

...It is not clear, why you would need this. But the oportunity is interesting indeed.

