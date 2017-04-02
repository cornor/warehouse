<?php
/**
 * Created by PhpStorm.
 * User: cornor
 * Date: 2017/3/8
 * Time: 下午9:15
 */

namespace App\Console\Commands;

use App\DripEmailer;
use Illuminate\Console\Command;

class SendEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * @translator laravelacademy.org
     */
    protected $signature = 'email:send {test} {--sec=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send drip e-mails to a user';

    /**
     * Create a new command instance.
     *
     * @param  DripEmailer  $drip
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $password = $this->secret('What is the password?');
        var_dump($password);
       echo "here";
        var_dump($this->argument('test'));
        var_dump($this->option('sec'));
    }
}