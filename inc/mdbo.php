<?php
/**
 * @file MySQL DataBase of Objects.
 * @copyright Alexey Ptitsyn <numidium.ru@gmail.com>, 2020.
 */

class MDBO
{
  public $_PDO;
  public $_table;

  /**
   * @constructor
   *
   * @param {string} $DBName - Database name.
   * @param {string} $DBHost - Database host.
   * @param {string} $DBUserName - Database username.
   * @param {string} $DBPassword - Database password.
   * @param {string} $DBTable - Database table name. Optional. Default `objects`
   *
   * @return {object}
   */
  function __construct($DBName, $DBHost, $DBUserName, $DBPassword, $DBTable='objects') {
    $this->_table = $DBTable;
    $this->_PDO = new PDO(
      'mysql:dbname=' . $DBName .
      ';host=' . $DBHost .
      ';charset=utf8',
      $DBUserName,
      $DBPassword,
      [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'",
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET CHARACTER SET 'utf8'",
      ]
    );

    $this->_PDO->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $this->_PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } // __construct();

  /**
   * Create object. Returns id(s) of created object(s).
   *
   * @param {array} $objs - Object or array of objects.
   *
   * @return {int|array}
   */
  public function create($objs) {
    // If array is sequential
    if(gettype($objs == 'array') && array_values($objs) === $objs) {

      foreach ($objs as $obj) {
        $this->createOne($obj);
      }

      return;
    }

    return $this->createOne($objs);
  } // create();

  /**
   * Returns id of new object.
   */
  protected function getNewObjectId() {
    $id = 0;

    $st = $this->_PDO->prepare('SELECT MAX(`object_id`) FROM ' . $this->_table);
    $st->execute();

    $lastObjId = $st->fetch(PDO::FETCH_NUM)[0];

    if($lastObjId !== null) {
      $id = $lastObjId + 1;
    }

    return $id;
  } // getnewObjectId();

  /**
   * Create one object. Returns newly created object ID.
   *
   * @param {array} $obj - Object as associative array.
   * @param {number} $desiredId - Desired object ID. Optional.
   *
   * @return {number}
   */
  public function createOne($obj, $desiredId=null) {
    if(isset($obj['_id'])) {
      unset($obj['_id']);
    }

    $newObjId = null;
    if($desiredId !== null) {
      $newObjId = $desiredId;
    }

    $getObjId = function() use (&$newObjId) {
      if($newObjId === null) {
        $newObjId = $this->getNewObjectId();
      }
      return $newObjId;
    };

    foreach ($obj as $property => $value) {
      if(gettype($value) == 'array') {
        if(array_values($value) != $value) {
          // Object
          $_subObjectId = $this->createOne($value);

          $this->createProperty(
            $getObjId(),
            $property,
            null, null, $_subObjectId, null
          );

          continue;
        }

        // Sequential array

        foreach ($value as $val) {
          $this->insertProperty($getObjId(), $property, $val);
        }

        continue;
      }

      // Insert only value as default:
      $this->insertProperty($getObjId(), $property, $value);
    } // ...for each value.

    return $newObjId;
  } // createOne();

  /**
   * Insert property to any object.
   *
   * @param {integer} $newObjId - Object ID.
   * @param {string} $property - Property name.
   * @param {mixed} $value - Property value. May be of type
   *                         boolean, integer, float or string.
   */
  private function insertProperty($newObjId, $property, $value) {
    switch (gettype($value)) {
      case 'boolean':
        $this->createProperty(
          $newObjId,
          $property,
          null, null, null, $value ? 1 : 0
        );
        break;
      case 'integer':
      case 'float':
        $this->createProperty($newObjId, $property, null, $value, null, null);
        break;
      case 'string':
        $this->createProperty($newObjId, $property, $value, null, null, null);
        break;

      default:
        throw new Exception('Strange property type.');
        break;
    }
  } // insertProperty();

  /**
   * Write property to database directly. Set only ONE value for database
   * structure integrity. Skipped value types should be set to `null`.
   *
   * @param {integer} $objId - Object ID.
   * @param {string} $property - Property name.
   * @param {string} $valueString - Strign value.
   * @param {integer|float} $valueNumber - Number value.
   * @param {integer} $valueObject - Sub object ID.
   * @param {boolean} $valueBoolean - Boolean value.
   */
  private function createProperty($objId, $property, $valueString, $valueNumber, $valueObject, $valueBoolean) {
    $st = $this->_PDO->prepare(
      'INSERT INTO ' . $this->_table .
      ' (`object_id`, `property`, `value_string`, `value_number`, `value_object`, `value_boolean`)' .
      ' VALUES(:objId, :property, :valueString, :valueNumber, :valueObject, :valueBoolean)'
    );
    $st->execute([
      ':objId' => $objId,
      ':property' => $property,
      ':valueString' => $valueString,
      ':valueNumber' => $valueNumber,
      ':valueObject' => $valueObject,
      ':valueBoolean' => $valueBoolean
    ]);
  } // createProperty().

  /**
   * Returns database table field name as string. According to value type.
   *
   * @param {mixed} $val - Value.
   *
   * @return {string}
   */
  protected function fieldNameByValue($val) {
    switch (gettype($val)) {
      case 'boolean':
        return 'value_boolean';
        break;

      case 'integer':
      case 'float':
        return 'value_number';
        break;

      case 'string':
        return 'value_string';
      
      default:
        throw new Exception("Unable to determine variable type: " . $val);
        break;
    }
  } // fieldNameByValue();






  /**
   * Find conditions.
   *
   * TODO: write documentation.
   */
  public function find($objCond) {
    $found = $this->findRootObjectIds();

    foreach ($objCond as $key => $value) {
      $complexRequest = false;
      switch (gettype($value)) {
        case 'boolean':
          $field = 'value_boolean';
          break;

        case 'integer':
        case 'float':
          $field = 'value_number';
          break;

        case 'string':
          $field = 'value_string';
          break;
        
        case 'array':
          $complexRequest = true;
          break;

        default:
          throw new Exception('Strange property type: ' . gettype($value));
          break;
      }

      if(!$complexRequest) {
        $st = $this->_PDO->prepare(
          'SELECT `object_id` FROM ' . $this->_table . '
          WHERE FIND_IN_SET(`object_id`, :rootObjects)
          AND `property` = :property
          AND `' . $field . '` = :value
        ');

        $st->execute([
          ':rootObjects' => implode(',', $found),
          ':property' => $key,
          ':value' => $value
        ]);
      }

      // If the request is complex:
      if($complexRequest) {
        $property = $key;
        $queryAddition = '';

        $i = 0;
        $opVals = [];
        foreach ($value as $operator => $opVal) {
          switch ($operator) {
            case '$in':
              $arr = $opVal;

              $queryAddition .= " AND `property` = :opVal{$i}";
              $opVals[":opVal{$i}"] = $property;

              $i++;

              foreach ($arr as $_k => $val) {
                $_op = $_k == 0 ? 'AND' : 'OR';
                $queryAddition .= " $_op `" . $this->fieldNameByValue($val) . "` = :opVal{$i}";
                $opVals[":opVal{$i}"] = $val;

                $i++;
              }

              break;

            case '$nin':
              $arr = $opVal;

              $queryAddition .= " AND `property` = :opVal{$i}";
              $opVals[":opVal{$i}"] = $property;

              $i++;

              foreach ($arr as $_k => $val) {
                $_op = 'AND';
                $queryAddition .= " $_op `" . $this->fieldNameByValue($val) . "` != :opVal{$i}";
                $opVals[":opVal{$i}"] = $val;

                $i++;
              }

              break;

            case '$ne':
              $queryAddition .= " AND `property` = :opVal{$i}";
              $opVals[":opVal{$i}"] = $property;

              $i++;

              $queryAddition .= " AND `" . $this->fieldNameByValue($opVal) . "` != :opVal{$i}";
              $opVals[":opVal{$i}"] = $opVal;
              break;

            case '$gt':
              $queryAddition .= " AND `property` = :opVal{$i}";
              $opVals[":opVal{$i}"] = $property;

              $i++;

              $queryAddition .= " AND `value_number` > :opVal{$i}";
              $opVals[":opVal{$i}"] = $opVal;
              break;
            
            case '$gte':
              $queryAddition .= " AND `property` = :opVal{$i}";
              $opVals[":opVal{$i}"] = $property;

              $i++;

              $queryAddition .= " AND `value_number` >= :opVal{$i}";
              $opVals[":opVal{$i}"] = $opVal;
              break;

            case '$lt':
              $queryAddition .= " AND `property` = :opVal{$i}";
              $opVals[":opVal{$i}"] = $property;

              $i++;

              $queryAddition .= " AND `value_number` < :opVal{$i}";
              $opVals[":opVal{$i}"] = $opVal;
              break;

            case '$lte':
              $queryAddition .= " AND `property` = :opVal{$i}";
              $opVals[":opVal{$i}"] = $property;

              $i++;

              $queryAddition .= " AND `value_number` <= :opVal{$i}";
              $opVals[":opVal{$i}"] = $opVal;
              break;

            case '$exists':
              if($opVal == true) {
                $j = $i+1;
                $queryAddition .= " AND `object_id` IN (
                  SELECT `object_id` FROM {$this->_table}
                    WHERE FIND_IN_SET(`object_id`, :opVal{$i})
                    AND `property` = :opVal{$j}
                )";

                $opVals[":opVal{$i}"] = implode(',', $found);
                $opVals[":opVal{$j}"] = $property;
                $i += 2; // Written intentionally
                break;
              }

              $j = $i+1;
              $queryAddition .= " AND `object_id` NOT IN (
                SELECT `object_id` FROM {$this->_table}
                  WHERE FIND_IN_SET(`object_id`, :opVal{$i})
                  AND `property` = :opVal{$j}
              )";

              $opVals[":opVal{$i}"] = implode(',', $found);
              $opVals[":opVal{$j}"] = $property;
              $i += 2; // Written intentionally
              break;

            default:
              throw new Exception("Unknown operator: " . $operator);
              break;
          }

          $i++;
        } // if complex request

        $st = $this->_PDO->prepare(
          'SELECT `object_id` FROM ' . $this->_table . '
          WHERE FIND_IN_SET(`object_id`, :rootObjects)' .
          $queryAddition
        );

        $baseRequest = [
          ':rootObjects' => implode(',', $found),
        ];

        $arrayValues = array_merge($baseRequest, $opVals);

        $st->execute($arrayValues);
      }

      $found = $st->fetchAll(PDO::FETCH_COLUMN);
      $found = array_unique($found);
    } // for each condition...

    $objects = [];
    foreach ($found as $foundId) {
      $objects[] = $this->read($foundId);
    }

    return $objects;
  } // find();



  /**
   * Outputs all root object ids.
   *
   * @return {array}
   */
  public function findRootObjectIds() {
    $st = $this->_PDO->prepare(
      'SELECT DISTINCT `object_id` FROM ' . $this->_table . '
      WHERE `object_id` NOT IN (
          SELECT DISTINCT `value_object` FROM ' . $this->_table . '
          WHERE `value_object` IS NOT NULL
        )
    ');

    $st->execute();

    return $st->fetchAll(PDO::FETCH_COLUMN);
  } // findRootObjectIds();

  /**
   * Read records with specified id. Returns associative array of them.
   *
   * @param {integer|array} $ids - Object ID(s).
   *
   * @return {Array}
   */
  public function read($ids) {
    if(gettype($ids) == 'array') {
      $result = [];

      foreach ($ids as $id) {
        $result[] = $this->readOne($id);
      }

      return $result;
    }

    $id = $ids;
    return $this->readOne($ids);
  } // read();

  /**
   * Read only one record and return it as associative array.
   *
   * @param {integer} $id - Object ID.
   *
   * @return {Array}
   */
  public function readOne($id, $isSkipId=false) {
    $st = $this->_PDO->prepare('SELECT * FROM `' . $this->_table . '`
      WHERE `object_id` = :id
    ');
    $st->execute([
      ':id' => $id
    ]);

    $records = $st->fetchAll();

    $obj = [];

    if($records && !$isSkipId) {
      $obj['_id'] = $id;
    }

    foreach ($records as $record) {
      $propertyName = $record['property'];

      if(!isset($obj[$propertyName])) {
        $obj[$propertyName] = [];
      }

      $stringVal = $record['value_string'];
      $numVal = $record['value_number'];
      $objVal = $record['value_object'];
      $boolVal = $record['value_boolean'];

      $_count = 0;
      if($stringVal) $_count++;
      if($numVal) $_count++;
      if($objVal) $_count++;
      if($boolVal) $_count++;
      if($_count > 1) {
        throw new Exception("Error in database structure! Record with id ${record['id']} has multiple values!");
      }

      if($stringVal) {
        $obj[$propertyName][] = $stringVal;
      }

      if($numVal) {
        $obj[$propertyName][] = $numVal;
      }

      if($objVal) {
        $obj[$propertyName][] = $this->readOne($objVal, true);
      }

      if($boolVal) {
        $obj[$propertyName][] = $boolVal ? true : false;
      }
    }

    foreach ($obj as $key => $value) {
      if(gettype($value) == 'array' && count($value) == 1) {
        $obj[$key] = $value[0];
      }
    }

    if($obj == []) {
      return null;
    }

    return $obj;
  } // readOne();

  /**
   * Update record(s)
   *
   * @param {array} $obj - Object or array of objects with `_id` field.
   */
  public function update($objs) {
    if(array_values($objs) == $objs) {
      foreach ($objs as $obj) {
        $this->updateOne($obj);
      }

      return;
    }

    $this->updateOne($objs);
  } // update();

  /**
   * Update one database object with the new one.
   * Object should have `_id` property. Returns object id.
   *
   * @param {array} $obj - Object as an associative array.
   *
   * @return {integer}
   */
  public function updateOne($obj) {
    if(!isset($obj['_id'])) {
      throw new Exception("No object id.");
    }

    $id = $obj['_id'];

    $num = $this->delete($id);

    if(!$num) {
      throw new Exception("Object not exist! Unable to update object.");
    }

    $this->createOne($obj, $id);
  } // updateOne();

  /**
   * Get all children objects from one. Returns array of object ids.
   *
   * @param {integer} $id - Object ID.
   *
   * @return {array}
   */
  private function getChildren($id) {
    $children = [];

    $st = $this->_PDO->prepare(
      'SELECT `value_object` FROM ' . $this->_table . '
       WHERE `object_id` = :id AND `value_object` IS NOT NULL
    ');
    $st->execute([
      ':id' => $id
    ]);

    $newChildren = $st->fetchAll(PDO::FETCH_COLUMN);

    if($newChildren) {
      $children = array_merge($children, $newChildren);

      foreach ($newChildren as $child) {
        $furtherChildren = $this->getChildren($child);
        $children = array_merge($children, $furtherChildren);
      }
    }

    $children = array_unique($children);

    return $children;
  } // getChildren();

  /**
   * Delete objects with ids and return number of ROWS deleted.
   *
   * @param {integer|array} $ids - Object ids.
   *
   * @return {int}
   */
  public function delete($ids) {
    if(gettype($ids) !== 'array') {
      $ids = [$ids];
    }

    $childrens = [];
    foreach ($ids as $_id) {
      $childrens = array_merge($childrens, $this->getChildren($_id));
    }

    $ids = array_merge($ids, $childrens);
    $ids = array_unique($ids);

    $id = implode(',', $ids);

    $st = $this->_PDO->prepare('DELETE FROM ' . $this->_table .
      ' WHERE FIND_IN_SET(`object_id`, :id)'
    );
    $st->execute([
      ':id' => $id
    ]);

    return $st->rowCount();
  } // delete();
} // class MDBO
