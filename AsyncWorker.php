<?php

/* Object pcntl_fork wrapper
Usage examples are included as comments below
*/
class AsyncWorker {

    private $pid = 0;
    private $failed = false;
    private $result_file_handler = null; //child will write result in this file
    private $success_file_handler = null; //child will write in this file if command was successful
    const SUCCESS_CODE = 'OK';

    public function __construct() {
        $this->result_file_handler = tmpfile();
        $this->success_file_handler = tmpfile();
        if(!$this->result_file_handler || !$this->success_file_handler)
        {
            trigger_error("ASyncWorker: Failed to create result or success file handles", E_USER_ERROR);
            $this->failed = true;
            return;
        }
        $this->pid = pcntl_fork();

        if ($this->pid === -1) {
            trigger_error("ASyncWorker: Failed to fork", E_USER_ERROR);
            $this->failed = true;
        } elseif ($this->pid === 0) {
            // $pid = 0, this is the child thread

            $args = func_get_args();
            if(sizeof($args) < 1) {
                trigger_error("ASyncWorker: No function to execute given", E_USER_ERROR);
                exit(1);
            }

            $function = $args[0];
            array_shift($args); //remove function from array, leaving only function arguments

            if(!is_callable($function)) {
                trigger_error("ASyncWorker: Function $function not found or not callable", E_USER_ERROR);
                exit(2);
            }

            $result = call_user_func_array($function, $args);
            /* do not check result value, call_user_func_array will return false on error, but error may be a valid return value of the given function.
            I assume is_callable prevent all errors cases for call_user_func_array, this may be wrong
            */

            $ok = fwrite($this->result_file_handler, serialize($result));
            if(!$ok) {
                trigger_error("ASyncWorker: Failed to write result file", E_USER_ERROR);
                exit(3);
            }

            $ok = fwrite($this->success_file_handler, self::SUCCESS_CODE);
            if(!$ok) {
                trigger_error("ASyncWorker: Failed to write success file", E_USER_ERROR);
                exit(4);
            }

            exit(0); //exit this fork
        } //else this is the parent thread, nothing to do
    }

    public function wait() {
        if($this->failed == true)
            return false;

        $status = null;
        pcntl_waitpid($this->pid, $status);

        //anormal exit !
        if(!pcntl_wifexited($status) || pcntl_wexitstatus($status) != 0) {
            trigger_error("ASyncWorker: Forked process did not exit properly", E_USER_ERROR);
            $this->failed = true;
            return false;
        }

        fseek($this->success_file_handler, 0);
        $success = fgets($this->success_file_handler);
        if($success != self::SUCCESS_CODE) {
            trigger_error("ASyncWorker: Success file not found or invalid", E_USER_ERROR);
            $this->failed = true;
            return false;
        }

        fseek($this->result_file_handler, 0);
        $result_serialized = fgets($this->result_file_handler);
        if($result_serialized === false) {
            trigger_error("ASyncWorker: No data in result file, or couldn't read it", E_USER_ERROR);
            $this->failed = true;
            return false;
        }

        fclose($this->result_file_handler);
        fclose($this->success_file_handler);

        $result = unserialize($result_serialized);
        return $result;
    }

    public function has_failed() {
        return $this->failed;
    }

}

/* Examples

function my_funky_function($arg1, $arg2)
{
    // do some work here
    sleep(1); //simulate working time

    // test arguments
    echo $arg1 . PHP_EOL;
    echo $arg2 . PHP_EOL;

    return "returned";
}

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

$worker = new AsyncWorker("my_funky_function", "echo1", "echo2"); //will start working immediately
$worker2 = new AsyncWorker("my_funky_function_array", "echo3", "echo4", "echo5");

$result = $worker->wait();
if($worker->has_failed())
    echo "FAILURE" . PHP_EOL;
else
    echo "Result: $result" . PHP_EOL;

$result2 = $worker2->wait();
if($worker2->has_failed())
    echo "FAILURE" . PHP_EOL;
else
    echo print_r($result2) . PHP_EOL;
*/
