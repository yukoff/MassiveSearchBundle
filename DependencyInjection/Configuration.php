<?php

/*
 * This file is part of the MassiveSearchBundle
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Massive\Bundle\SearchBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Returns the config tree builder.
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $treeBuilder->root('massive_search')
            ->children()
                ->arrayNode('services')
                    ->addDefaultsifNotSet()
                    ->children()
                        ->scalarNode('factory')->defaultValue('massive_search.factory_default')->end()
                    ->end()
                ->end()
                ->enumNode('adapter')
                    ->values(['zend_lucene', 'elastic', 'test'])
                    ->defaultValue('zend_lucene')->end()
                ->arrayNode('adapters')
                    ->addDefaultsifNotSet()
                    ->children()
                        ->arrayNode('zend_lucene')
                            ->addDefaultsifNotSet()
                            ->children()
                                ->booleanNode('hide_index_exception')->defaultValue(false)->end()
                                ->scalarNode('basepath')->defaultValue('%kernel.root_dir%/data')->end()
                                ->scalarNode('encoding')->defaultValue('UTF-8')->end()
                            ->end()
                        ->end()
                        ->arrayNode('elastic')
                            ->addDefaultsifNotSet()
                            ->children()
                                ->scalarNode('version')->defaultValue('5.0')->end()
                                ->arrayNode('hosts')
                                    ->defaultValue(['localhost:9200'])
                                    ->prototype('array')
                                        ->beforeNormalization()
                                        ->ifString()
                                            ->then(function($v) {
                                                $parts = parse_url($v);
                                                if (!$parts['port'] && !!$parts['scheme']) {
                                                    switch ($parts['scheme']) {
                                                        case 'https':
                                                            $parts['port'] = 443;
                                                            break;
                                                        case 'http':
                                                            $parts['port'] = 80;
                                                            break;
                                                    }
                                                }
                                                return $parts;
                                            })
                                        ->end()
                                        ->children()
                                            ->scalarNode('host')->isRequired()->end()
                                            ->scalarNode('port')->end()
                                            ->scalarNode('scheme')->end()
                                            ->scalarNode('user')->end()
                                            ->scalarNode('pass')->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('metadata')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('prefix')->defaultValue('massive')->end()
                        ->scalarNode('cache_dir')->defaultValue('%kernel.cache_dir%/massive-search')->end()
                        ->booleanNode('debug')->defaultValue('%kernel.debug%')->end()
                    ->end()
                ->end()
                ->arrayNode('persistence')
                    ->addDefaultsifNotSet()
                    ->children()
                        ->arrayNode('doctrine_orm')
                            ->canBeEnabled()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
