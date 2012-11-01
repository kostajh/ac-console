<?php

namespace ActiveCollabConsole\Console;

use Symfony\Component\Console\Application as BaseApplication;
use ActiveCollabConsole\Console\Command\UserTasksCommand;
use ActiveCollabConsole\Console\Command\TaskInfoCommand;
use ActiveCollabConsole\ActiveCollabConsole;

/**
* @author Kosta Harlan <kostajh@gmail.com>
*/
class Application extends BaseApplication
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        error_reporting(-1);

        parent::__construct('ActiveCollab Console', ActiveCollabConsole::VERSION);

        $this->add(new UserTasksCommand());
        $this->add(new TaskInfoCommand());
    }

    /**
     * Return long version.
     *
     * @return the version info for the application.
     */
    public function getLongVersion()
    {
        return parent::getLongVersion().' by <comment>Kosta Harlan</comment>';
    }
}
