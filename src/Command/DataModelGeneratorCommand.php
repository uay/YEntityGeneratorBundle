<?php

namespace Uay\YEntityGeneratorBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Uay\YEntityGeneratorBundle\DataModelGenerator\DataModelGenerator;
use Uay\YEntityGeneratorBundle\DataModelGenerator\InputModel;
use Uay\YEntityGeneratorBundle\DependencyInjection\UayEntitiesExtension;
use Uay\YEntityGeneratorBundle\Utils\FileUtil;

class DataModelGeneratorCommand extends ContainerAwareCommand
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
        $pathApplication = \dirname($this->kernel->getRootDir());

        $pathEntities = $pathApplication . DIRECTORY_SEPARATOR . 'entities';

        if (!file_exists($pathEntities)) {
            FileUtil::mkdirRecursive($pathEntities);
        }

        $container = $this->kernel->getContainer();
        if ($container === null) {
            throw new \RuntimeException('Container must not be null!');
        }

        $config = $container->getParameter(UayEntitiesExtension::PARAMETER_CONFIG);
        $generator = new DataModelGenerator(new InputModel($config), $pathApplication, $pathEntities);

        $generator->read();

        $generator->generate();

        // Validate generated schema
        $command = $this->getApplication()->find('doctrine:schema:validate');
        return $command->run($input, $output);
    }
}
