<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title></title>
  <style>
  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 1rem;
  }

  pre {
    font-size: 1.2rem;
  }
  </style>
</head>
<body>

<pre><?php
    require_once "../inc/mdbo.php";

    $dbObjects = new MDBO('dbobjects', 'localhost', 'dbobjects_user', 'dbobjects_password');

    // Clear
    // $st = $dbObjects->_PDO->prepare("
    //   DELETE FROM `objects` WHERE `object_id` IS NOT NULL
    // ");
    // $st->execute();

    // echo "DB cleared.\n\n";



    $time_start = microtime(true);

    // for($i = 0; $i < 1000; $i++) {
    //   $dbObjects->create([
    //     'name' => 'Object #' . $i,
    //     'number' => $i
    //   ]);
    // }

    $nums = [];
    for($i = 0; $i < 1000; $i++) {
      if($i % 3 == 0) {
        $nums[] = $i;
      }
    }


    $res = $dbObjects->find([
      'number' => [
        '$in' => $nums
      ],
    ]);

    var_dump($res);

    $time_end = microtime(true);

    $execution_time = ($time_end - $time_start);
    $execution_time = number_format($execution_time, 3, '.', ' ');
    echo '<b>Total Execution Time:</b> '.$execution_time.' s<br>';

  $mem = memory_get_usage() / 1024 / 1024;
  $mem = number_format($mem, 3, '.', ' ');
  echo "Memory: {$mem} Mb";

    // $dbObjects->create([
    //   '_id' => 0,
    //   'testS' => 'second object',
    //   'testN' => 123,
    //   'testB' => true,
    //   'testArr' => [
    //     5,
    //     7,
    //     'test'
    //   ],
    //   'testObj' => [
    //     'testObj1' => 1,
    //     'testObj2' => 'str',
    //     'testval3' => [
    //       'testval3-1' => 31,
    //       'testval3-2' => [
    //         'testval3-3' => 33
    //       ]
    //     ]
    //   ]
    // ]);

    // $objs = [];
    // foreach ($dbObjects->findRootObjectIds() as $id) {
    //   $objs[] = $dbObjects->read($id);
    // }
  ?></pre>

</body>
</html>
