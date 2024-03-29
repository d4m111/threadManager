<?php

namespace d4m111\threadManager\App\Classes;

use Throwable;

use Illuminate\Support\Facades\Log;

use d4m111\threadManager\App\Classes\SharedMemory;


class ThreadManager
{

    // Maximum number of simultaneous threads that I'm going to allow
    protected $maxRunningThreads = 1;
    // Name that will be given to the process and its children
    protected $processTitle = null;
    // If it is false, only error will be logged
    protected $verbose = false;
    // Indicates which log should be written to
    protected $logChannel = null;

    protected $sharedMemoryId = null; 

    protected $mainProcessPid = null;

    private $data = [];

    const INTERVAL_BETWEEN_THREADS_MILLIS = 50;

    public function __construct($options = [])
    {
        $this->config($options);
    }

    /**
     * Configuration parameters
     *
     * @param array $options array of elements.
     * @return self Objet instance.
     */
    public function config(array $options = []): self
    {
        $this->processTitle = $options['processTitle'] ?? $this->processTitle;

        $this->maxRunningThreads = (isset($options['maxRunningThreads']) && is_int($options['maxRunningThreads'])) ? $options['maxRunningThreads'] : $this->maxRunningThreads;

        $this->logChannel = $options['logChannel'] ?? $this->logChannel;

        $this->verbose = $options['verbose'] ?? $this->verbose;

        return $this;
    }

    private function log(string $level,int $mainProcessId,string|int $threadId,string $message): void
    {
        $label = $this->processTitle ?? __CLASS__;

        $log = "[NAME: $label][MAIN PROC: {$mainProcessId}][THREAD: {$threadId}] {$message}";

        if($this->logChannel){
            
            if($level == 'info' && $this->verbose){
                
                optional(Log::channel($this->logChannel))->info($log);

            }else if($level == 'error'){
                
                optional(Log::channel($this->logChannel))->error($log);
            }

        }
    }

    private function getSharedMemory(): SharedMemory
    {
        $memory = new SharedMemory($this->sharedMemoryId);
        
        if(!$this->sharedMemoryId){
            $this->sharedMemoryId = $memory->getId();
        }      

        return $memory;
    }

    /**
     * Delete shared memory
     *
     * @return void
     */
    public function deleteMem(): void
    {
        if($this->sharedMemoryId){
            $memory = $this->getSharedMemory();
            $memory->delete();
        }
    }

    /**
     * get the shared memory
     *
     * @return mixed
     */
    public function readMem(): mixed
    {
        $memory = $this->getSharedMemory();
        
        return $memory->read();
    }

    /**
     *  overwrite into shared memory 
     *
     * @param mixed data to be inserted
     * @return void
     */
    public function writeMem(mixed $data): void
    {
        $memory = $this->getSharedMemory();
        $memory->write($data);
    }

    /**
     * Empty shared memory
     *
     * @return void
     */
    public function emptyMem(): void
    {
        $memory = $this->getSharedMemory();
        $memory->empty();
    }

    /**
     * Get the shared memory queue
     *
     * @return array
     */
    public function getMemQueue(): array
    {
        $memory = $this->getSharedMemory();
        
        return $memory->getQueue();
    }

    /**
     * Add an elemento to the shared memory queue
     *
     * @param mixed data to be inserted
     * @return void
     */
    public function appendToMemQueue(mixed $data): void
    {
        $memory = $this->getSharedMemory();
        $memory->appendToQueue($data);
    }

    /**
     * Get the main procces ID
     *
     * @return int Main process ID
     */
    public function getPid(): int
    {
        return getmypid();
    }

    /**
     * Data to be proccesed
     *
     * @param array $data Data array.
     * @return self self instance
     */
    public function data(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Proccess each item creating a thread and calling the callback function
     *
     * @param callable $callback Function to be called.
     * @return self self instance
     */
    public function each(callable $callback): self
    {

        $this->mainProcessPid = $this->getPid();

        $activeThreads = 0;

        $this->log('info',$this->mainProcessPid,'MAIN',"=> START MAIN PROCESS");

        foreach ($this->data as $index => $element) {

            // dentro del forking no conviene usar excepciones
            $pid = @pcntl_fork();

            if ($pid == -1) {

                $message = "Couldn't fork";
                $this->log('error',$this->mainProcessPid,$pid,$message);
                
                throw new Exception($message);

            } else if (!$pid) {
                
                // child

                $threadPid = $this->getPid();

                if($this->processTitle){
                    cli_set_process_title("{$this->processTitle}-{$this->mainProcessPid}-{$threadPid}");
                }   

                try {

                    $callback(
                        $element,
                        $index,
                        $this->mainProcessPid,
                        $threadPid
                    );
                
                $this->log('info',$this->mainProcessPid,$threadPid," -> START THREAD");

                } catch (Throwable $exception) {
                    
                    $this->log('error',$this->mainProcessPid,$threadPid,$exception->getMessage().PHP_EOL.$exception->getTraceAsString());

                    throw $exception;
                }

                // Avoid a burst of processes and prevents a load peak on the server.
                usleep( self::INTERVAL_BETWEEN_THREADS_MILLIS * 1000 );

                exit(0);

            } else {

                // parent
                                
                $activeThreads++;

                if ($activeThreads >= $this->maxRunningThreads) {
                    
                    $threadPid = pcntl_wait($status);

                    $exitStatus = pcntl_wexitstatus($status);
                    
                    $this->log('info',$this->mainProcessPid,$threadPid," <- END THREAD - WITH STATUS $exitStatus");

                    $activeThreads--;
                }
            }
        }

        // Wait for child process ending
        while (($threadPid = pcntl_waitpid(0, $status)) !== -1) {
            
            $exitStatus = pcntl_wexitstatus($status);
            
            $this->log('info',$this->mainProcessPid,$threadPid," <- END THREAD - WITH STATUS $exitStatus");
        }

        $this->log('info',$this->mainProcessPid,'MAIN',"<= END MAIN PROCESS");

        return $this;
    }

    public function __destruct()
    {
        if($this->mainProcessPid == $this->getPid()){

            $this->deleteMem();
        }
    }
}