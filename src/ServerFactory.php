<?php

namespace Parfeon\Bitrix\Mcp;

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;
use Psr\Log\AbstractLogger;

class StderrLogger extends AbstractLogger
{
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        foreach ($context as $k => $v) {
            if ($v instanceof \Throwable) {
                $context[$k] = get_class($v) . ': ' . $v->getMessage() . ' in ' . $v->getFile() . ':' . $v->getLine();
            }
        }
        $ctx = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        fwrite(STDERR, '[MCP ' . strtoupper($level) . '] ' . $message . $ctx . PHP_EOL);
    }
}

class ServerFactory
{
    public static function run(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $logger = new StderrLogger();

        $server = Server::make()
            ->withServerInfo('bitrix24-mcp', '0.1.0')
            ->withLogger($logger)
            ->build();

        $server->discover(
            basePath: dirname(__DIR__),
            scanDirs: ['src/Tools'],
            excludeDirs: []
        );

        $server->listen(new StdioServerTransport());
    }
}
