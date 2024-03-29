## :no_entry: Requirements:

- PHP 8.0 (or upper)
- Laravel 9 (or upper)

## :package: Instalation:

- Edit the `composer.json` file and add this block:

        "repositories": [
            {
                "type": "vcs",
                "url": "https://debianUserGit:C-wsF3UGhhkKY5zU1TUB@gitlab-dev.telecentro.net.ar/gnrdesarrollo/packages/thread-manager.git"
            }
        ]

- `composer require d4m111/thread-manager`

## :closed_book: Documentation:

### __construct(array $options = []): self
### config(array $options = []): self
`array $options`:
- `string processTitle (optional)`:  You can define a name to the process
- `int maxRunningThreads (optional)`: max number of simultaneous thread (by default is 1)
- `string logChannel (optional)`: Name of Laravel log channel. If it isn't defined, the application will not produce
- `boolean verbose (optional)`: If it is false, it only will prodece error logs. (by default is false)

### data(array $data): self
`$data`: List of element to be processed

### each(callable $callback): self
`$callback`: Funtion to be called in each iteration/thread

### readMem(): mixed
`return`: return shared memory info

### writeMem(mixed $data): void
`$data`: Overwrite shared memory info

### emptyMem(): void
Empty shared memory

### deleteMem(): void
Delete shared memory

### getMemQueue(): array
`return`: Overwrite shared memory info as array

### appendToMemQueue(mixed $data): void
`$data`: append to shared memory queue


## :wrench: Who to use:

        use d4m111\ThreadManager\App\Classes\ThreadService;
        
        $elements = ['first','second','third'];

        $service = new ThreadService([
            'processTitle' => 'test',
            'maxRunningThreads' => 2,
            'logChannel' => 'simple',
            'verbose' => true,
        ]);

        /* 
            Shared memeory was implemented because child process can't share information with the main process 
        */

        $service->appendToMemQueue('START');

        /* 
            It process data array in a different thread for each iteration. When the 'maxRunningThreads' number is reached, the application will wait for a thread to finish before launching new ones
        */

        $service->data($elements)->each(function($item,$index,$mainProcessPid,$threadPid) use (&$service){

            $service->appendToMemQueue($item);

            echo "> $item - $index | $mainProcessPid - $threadPid \n";

        });

        $response = $service->getMemQueue();

        var_dump($response);