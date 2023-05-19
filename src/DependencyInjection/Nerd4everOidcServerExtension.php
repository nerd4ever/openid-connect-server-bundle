<?php
/**
 * @package   oidc-server-bundle
 * @author    Sileno de Oliveira Brito
 * @email     sobrito@nerd4ever.com.br
 * @copyright Copyright (c) 2023
 */

namespace Nerd4ever\OidcServerBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nerd4ever\OidcServerBundle\Persistence\Mapping\Driver;
use Nerd4ever\OidcServerBundle\Repository\IdentityProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Exception;

/**
 * My OidcServerExtension
 *
 * @package Nerd4ever\OidcServerBundle\DependencyInjection
 * @author Sileno de Oliveira Brito
 */
class Nerd4everOidcServerExtension extends Extension implements PrependExtensionInterface, CompilerPassInterface
{

    /**
     * Loads a specific configuration.
     *
     * @throws InvalidArgumentException|Exception When provided tag is not defined in this extension
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        $config = $this->processConfiguration(new Configuration(), $configs);

        // Obtém o valor de classname da configuração
        $providerClassName = $config['provider']['classname'];
        $this->configureProvider($container, $providerClassName);

        $sessionClassName = $config['session']['classname'];
        $entityManager = $config['session']['entity_manager'];
        if (!empty($entityManager)) {
            $this->configureSession($container, $sessionClassName, $entityManager);
        }
    }

    private function assertRequiredBundlesAreEnabled(ContainerBuilder $container): void
    {
        $requiredBundles = [
            'doctrine' => DoctrineBundle::class,
        ];

        foreach ($requiredBundles as $bundleAlias => $requiredBundle) {
            if (!$container->hasExtension($bundleAlias)) {
                throw new \LogicException(sprintf('Bundle \'%s\' needs to be enabled in your application kernel.', $requiredBundle));
            }
        }
    }

    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container)
    {
        $this->assertRequiredBundlesAreEnabled($container);
    }

    /**
     * Allow an extension to prepend the extension configurations.
     */
    public function prepend(ContainerBuilder $container)
    {
        // TODO: Implement prepend() method.
    }

    private function configureSession(ContainerBuilder $container, string $className, string $entityManager)
    {
        // Registra o serviço do driver personalizado
        $driver = new Definition(Driver::class, [$className]);
        $container->setDefinition('nerd4ever_oidc_server.persistence.mapping.driver', $driver);

        // Configura o driver personalizado
        $container->setParameter('nerd4ever_oidc_server.persistence.mapping.driver.class', Driver::class);
        $container->setParameter('nerd4ever_oidc_server.persistence.mapping.driver.arguments', [$className]);

        // Configura a classe da entidade de sessão
        $container->setParameter('nerd4ever_oidc_server.persistence.session.class', $className);

        // Configura o nome do gerenciador de entidades
        $container->setParameter('nerd4ever_oidc_server.persistence.session.entity_manager', $entityManager);
    }

    private function configureProvider(ContainerBuilder $container, string $className)
    {

        // Define o serviço com base no classname fornecido
        $container->register(IdentityProviderInterface::class, $className)
            ->setAutoconfigured(true)
            ->setPublic(true);
    }
}