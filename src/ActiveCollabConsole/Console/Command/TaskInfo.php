<?php


/*
* This file is part of the PHP CS utility.
*
* (c) Fabien Potencier <fabien@symfony.com>
*
* This source file is subject to the MIT license that is bundled
* with this source code in the file LICENSE.
*/

namespace ActiveCollabConsole\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ActiveCollabConsole\ActiveCollabConsole;

/**
* @author Kosta Harlan <kostajh@gmail.com>
*/
class TaskInfo extends Command
{
    /**
* @see Command
*/
    protected function configure()
    {
        $this
            ->setName('compile')
            ->setDescription('Compiles the fixer as a phar file')
        ;
    }

    /**
* @see Command
*/
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $compiler = new Compiler();
        $compiler->compile();
    }
}