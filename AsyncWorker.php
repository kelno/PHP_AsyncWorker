<?php

/* Object pcntl_fork wrapper
Usages example are included as comments below

/!\ Important note: If given command return evaluates to false, this is considered an error. 
This is because call_user_func_array return false on error, so I can't distinguish between 
the function returning false or an error. Possible fix: http://stackoverflow.com/questions/11767728/what-if-call-user-func-is-supposed-to-return-false
*/
class ASyncWorker {

    private $pid = 0;
    private $failed = false;
    private $result_file_handler = null; //child will write result in this file
    private $success_file_handler = null; //child will write in this file if command was successful
    const SUCCESS_CODE = 'OK';

    function __construct() {
        $this->result_file_handler = tmpfile();
        $this->success_file_handler = tmpfile();
        if(!$this->result_file_handler || !$this->success_file_handler)
        {
            $this->failed = true;
            return;
        }
        $this->pid = pcntl_fork();

        if ($this->pid === -1) {
            $this->failed = true;
        } elseif ($this->pid === 0) {
            // $pid = 0, this is the child thread

            $args = func_get_args();
            if(sizeof($args) < 1)
                exit(1);

            $function = $args[0];
            array_shift($args); //remove function from array, leaving only function arguments

            $result = call_user_func_array($function, $args);
            if($result == FALSE) {
                exit(2);
            }

            $ok = fwrite($this->result_file_handler, serialize($result));
            if(!$ok) {
                echo "Failed to write" . PHP_EOL;
                exit(4);
            }

            $ok = fwrite($this->success_file_handler, self::SUCCESS_CODE);
            if(!$ok) {
                echo "Failed to write success " . PHP_EOL;
                exit(6);
            }

            exit(0); //exit this fork
        } //else this is the parent thread, nothing to do
    }

    function wait() {
        if($this->failed == true)
            return false;

        $status = null;
        pcntl_waitpid($this->pid, $status);

        //anormal exit !
        if(!pcntl_wifexited($status)) {
            $this->failed = true;
            return false;
        }

        if(pcntl_wexitstatus($status) != 0) {
            $this->failed = true;
            return false;
        }

        fseek($this->success_file_handler, 0);
        $success = fgets($this->success_file_handler);
        if($success != self::SUCCESS_CODE) {
            echo "Success file not found or invalid" . PHP_EOL;
            $this->failed = true;
            return false;
        }

        fseek($this->result_file_handler, 0);
        $result_serialized = fgets($this->result_file_handler);
        if($result_serialized === false) {
            echo "No result in file or couln't read it" . PHP_EOL;
            $this->failed = true;
            return false;
        }

        fclose($this->result_file_handler);
        fclose($this->success_file_handler);

        $result = unserialize($result_serialized);
        return $result;
    }

    function has_failed() {
        return $this->failed;
    }

}

/* Examples
function my_funky_function($arg1, $arg2)
{
    sleep(1);
    echo $arg1 . PHP_EOL;
    echo $arg2 . PHP_EOL;

    return "returned";
}

function my_funky_function_array($arg1, $arg2, $arg3)
{
    sleep(2);
    echo $arg1 . PHP_EOL;
    echo $arg2 . PHP_EOL;
    echo $arg3 . PHP_EOL;

    return array("returned_array");
}

$worker = new ASyncWorker("my_funky_function", "echo1", "echo2"); //will start working immediately
$worker2 = new ASyncWorker("my_funky_function_array", "echo3", "echo4", "echo5");

$result = $worker->wait();
if($worker->has_failed())
    echo "FAILURE" . PHP_EOL;
else
    echo "Result: $result" . PHP_EOL;

$result2 = $worker2->wait();
if($worker2->has_failed())
    echo "FAILURE" . PHP_EOL;
else
    echo "Result: " . print_r($result2) . PHP_EOL;
*/
