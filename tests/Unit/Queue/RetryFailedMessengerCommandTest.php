<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Queue;

use VanDerSangen\ProjectTemplateBundle\Queue\Command\RetryFailedMessengerCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class RetryFailedMessengerCommandTest extends TestCase
{
    private RetryFailedMessengerCommand $command;

    protected function setUp(): void
    {
        $this->command = new RetryFailedMessengerCommand();
    }

    public function testExecuteRunsRetryCommandWithFailedTransport(): void
    {
        $runInput = null;
        $retryCommand = $this->createMock(\Symfony\Component\Console\Command\Command::class);
        $retryCommand->expects($this->once())
            ->method('run')
            ->willReturnCallback(function (InputInterface $input, OutputInterface $output) use (&$runInput): int {
                $runInput = $input;
                return 0;
            });

        $application = $this->createMock(\Symfony\Component\Console\Application::class);
        $application->expects($this->once())
            ->method('find')
            ->with('messenger:failed:retry')
            ->willReturn($retryCommand);

        $this->command->setApplication($application);

        $input = new ArrayInput([]);
        $input->setInteractive(false);
        $output = new BufferedOutput();

        $code = $this->command->run($input, $output);

        $this->assertSame(0, $code);
        $this->assertInstanceOf(ArrayInput::class, $runInput);
        $this->assertSame('failed', $runInput->getParameterOption('--transport'));
        $this->assertFalse($runInput->getParameterOption('--force'));
    }

    public function testExecutePassesForceOptionToRetryCommand(): void
    {
        $runInput = null;
        $retryCommand = $this->createMock(\Symfony\Component\Console\Command\Command::class);
        $retryCommand->expects($this->once())
            ->method('run')
            ->willReturnCallback(function (InputInterface $input) use (&$runInput): int {
                $runInput = $input;
                return 0;
            });

        $application = $this->createMock(\Symfony\Component\Console\Application::class);
        $application->method('find')->with('messenger:failed:retry')->willReturn($retryCommand);

        $this->command->setApplication($application);

        $input = new ArrayInput(['--force' => true]);
        $input->setInteractive(false);

        $this->command->run($input, new BufferedOutput());

        $this->assertTrue($runInput->getParameterOption('--force'));
    }

    public function testExecutePassesMessageIdsToRetryCommand(): void
    {
        $runInput = null;
        $retryCommand = $this->createMock(\Symfony\Component\Console\Command\Command::class);
        $retryCommand->expects($this->once())
            ->method('run')
            ->willReturnCallback(function (InputInterface $input) use (&$runInput): int {
                $runInput = $input;
                return 0;
            });

        $application = $this->createMock(\Symfony\Component\Console\Application::class);
        $application->method('find')->with('messenger:failed:retry')->willReturn($retryCommand);

        $this->command->setApplication($application);

        $input = new ArrayInput(['id' => ['10', '20']]);
        $input->setInteractive(false);

        $this->command->run($input, new BufferedOutput());

        $ref = new \ReflectionClass($runInput);
        $params = $ref->getProperty('parameters')->getValue($runInput);
        $this->assertArrayHasKey('id', $params);
        $this->assertSame(['10', '20'], $params['id']);
    }

    public function testExecuteReturnsFailureWhenApplicationIsNull(): void
    {
        $this->command->setApplication(null);

        $input = new ArrayInput([]);
        $input->setInteractive(false);

        $code = $this->command->run($input, new BufferedOutput());

        $this->assertSame(1, $code);
    }

    public function testExecuteReturnsFailureWhenRetryCommandFails(): void
    {
        $retryCommand = $this->createMock(\Symfony\Component\Console\Command\Command::class);
        $retryCommand->method('run')->willReturn(1);

        $application = $this->createMock(\Symfony\Component\Console\Application::class);
        $application->method('find')->with('messenger:failed:retry')->willReturn($retryCommand);

        $this->command->setApplication($application);

        $input = new ArrayInput([]);
        $input->setInteractive(false);

        $code = $this->command->run($input, new BufferedOutput());

        $this->assertSame(1, $code);
    }
}
