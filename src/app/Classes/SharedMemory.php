<?php

// Copyright (c) 2012, Klaus Silveira and contributors
// All rights reserved.

// Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

// Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
// Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
// Neither the name of SimpleSHM nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

// https://github.com/klaussilveira/SimpleSHM

// Moidified by Damián Curcio (2024)

namespace d4m111\threadManager\App\Classes;

class SharedMemory
{
    /**
     * Holds the system id for the shared memory block
     *
     * @var int
     * @access protected
     */
    protected $id;

    /**
     * Holds the shared memory block id returned by shmop_open
     *
     * @var int
     * @access protected
     */
    protected $shmid;

    /**
     * Holds the default permission (octal) that will be used in created memory blocks
     *
     * @var int
     * @access protected
     */
    protected $perms = 0644;

    /**
     * Shared memory block instantiation
     *
     * In the constructor we'll check if the block we're going to manipulate
     * already exists or needs to be created. If it exists, let's open it.
     *
     * @access public
     * @param string $id (optional) ID of the shared memory block you want to manipulate
     */
    public function __construct($id = null)
    {
        if($id === null) {
            $this->id = $this->generateID();
        } else {
            $this->id = $id;
        }

        if($this->exists($this->id)) {
            $this->shmid = shmop_open($this->id, "w", 0, 0);
        }
    }

    /**
     * Generates a random ID for a shared memory block
     *
     * @access protected
     * @return int System V IPC key generated from pathname and a project identifier
     */
    protected function generateID()
    {
        $id = ftok(__FILE__, "b");
        return $id;
    }

    /**
     * Checks if a shared memory block with the provided id exists or not
     *
     * In order to check for shared memory existance, we have to open it with
     * reading access. If it doesn't exist, warnings will be cast, therefore we
     * suppress those with the @ operator.
     *
     * @access public
     * @param string $id ID of the shared memory block you want to check
     * @return boolean True if the block exists, false if it doesn't
     */
    public function exists($id)
    {
        $status = @shmop_open($id, "a", 0, 0);
        return $status;
    }

    /**
     * Writes on a shared memory block
     *
     * First we check for the block existance, and if it doesn't, we'll create it. Now, if the
     * block already exists, we need to delete it and create it again with a new byte allocation that
     * matches the size of the data that we want to write there. We mark for deletion,  close the semaphore
     * and create it again.
     *
     * @access public
     * @param mixed $data The data that you wan't to write into the shared memory block
     */
    public function write(mixed $data): void
    {
        $data = serialize($data);

        $size = mb_strlen($data, '8bit');

        if($this->exists($this->id)) {
            shmop_delete($this->shmid);
            shmop_close($this->shmid);
        }

        $this->shmid = shmop_open($this->id, "c", $this->perms, $size);
        shmop_write($this->shmid, $data, 0);
    }

    /**
     * Reads from a shared memory block
     *
     * @access public
     * @return mixed The data read from the shared memory block
     */
    public function read(): mixed
    {
        $data = '';

        if($this->shmid){
            $size = shmop_size($this->shmid);
            $data = shmop_read($this->shmid, 0, $size);

            $data = unserialize($data);
        }

        return $data;
    }

    /**
     * empty shared memory block
     *
     * @access public
     * @return void 
     */
    public function empty(): void
    {
        
        $data = "";

        $this->write($data);
    }

    /**
     * apends to queue
     *
     * @access public
     * @param mixed $data The data that you wan't to write into the queue
     */
    public function appendToQueue(mixed $data): void
    {
        $queue = $this->getQueue();

        $queue[] = $data;

        $this->write($queue);
    }

    /**
     * Reads queue
     *
     * @access public
     * @return array The data read from the queue
     */
    public function getQueue(): array
    {
        $data = $this->read();

        $data = (!$data) ? [] : (array)$data;
        
        return $data;
    }

    /**
     * empty memory
     *
     * @access public
     * @return void 
     */
    public function emptyMem(): void
    {
        
        $data = "";

        $this->write($data);
    }

    /**
     * Mark a shared memory block for deletion
     *
     * @access public
     * @return void 
     */
    public function delete(): void
    {
        if($this->shmid){
            shmop_delete($this->shmid);
        }
    }

    /**
     * Gets the current shared memory block id
     *
     * @access public
     * @return int 
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Gets the current shared memory block permissions
     *
     * @access public
     */
    public function getPermissions(): int
    {
        return $this->perms;
    }

    /**
     * Sets the default permission (octal) that will be used in created memory blocks
     *
     * @access public
     * @param int $perms Permissions, in octal form
     * @return void
     */
    public function setPermissions(int $perms): void
    {
        $this->perms = $perms;
    }

    /**
     * Closes the shared memory block and stops manipulation
     *
     * @access public
     */
    public function __destruct()
    {
        if($this->shmid){
            shmop_close($this->shmid); 
        }
    }
}