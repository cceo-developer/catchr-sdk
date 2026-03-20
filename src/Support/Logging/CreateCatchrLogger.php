<?php

namespace CceoDeveloper\Catchr\Support\Logging;

use Monolog\Logger;

class CreateCatchrLogger
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger($config['name'] ?? 'catchr');

        $level = $config['level'] ?? Logger::DEBUG;
        $logger->pushHandler(new CatchrMonologHandler($level));

        return $logger;
    }
}