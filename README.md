# AsyncWorker
This is a minimal object wrapper for pcntl_fork

### Example

#### Code

```
function my_funky_function_array($arg1, $arg2, $arg3)
{
    // do some work here
    sleep(2); //simulate working time

    // test arguments
    echo $arg1 . PHP_EOL;
    echo $arg2 . PHP_EOL;
    echo $arg3 . PHP_EOL;

    return array("returned_array");
}

$worker = new ASyncWorker("my_funky_function_array", "echo1", "echo2", "echo3"); //will start working immediately

// ... Do some other work ...

$result = $worker->wait();
if($worker->has_failed())
    echo "FAILURE" . PHP_EOL;
else
    print_r($result) . PHP_EOL;
    
```
