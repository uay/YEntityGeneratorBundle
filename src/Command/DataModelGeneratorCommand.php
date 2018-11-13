<?php

namespace Uay\YEntityGeneratorBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Uay\YEntityGeneratorBundle\DataModelGenerator\DataModelGenerator;

class DataModelGeneratorCommand extends Command
{
    protected static $defaultName = 'entities:generate';

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(static::$defaultName);
        $this->setDescription('Generates the entities');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pathApplication = realpath($this->kernel->getRootDir() . DIRECTORY_SEPARATOR . '..');

        $pathEntities = realpath($pathApplication . DIRECTORY_SEPARATOR . 'entities');

        $generator = new DataModelGenerator($pathApplication, $pathEntities);

        $generator->read();

        $generator->generate();

        // Validate generated schema
        $command = $this->getApplication()->find('doctrine:schema:validate');
        return $command->run($input, $output);
    }
}
