<?php

namespace TYPO3\Documentation\Search\Commands;

/*
 * Copyright (C) 2018 Daniel Siepmann <coding@daniel-siepmann.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Documentation\Search\Indexer\ManualIndexer;

/**
 * Indexes a single manual.
 *
 * Fatches all necessary sources for indexing. Analyses the sources and adds
 * them to search index.
 */
class IndexManualCommand extends Command
{
    /**
     * @var ManualIndexer
     */
    protected $manualIndexer;

    protected function configure()
    {
        $this
            ->setName('index:manual')
            ->setDescription('Index a single manual.')

            ->addArgument('manualUrl', InputArgument::REQUIRED, 'The full url to the manual start page, which is "some/path/" without Index.html or anything else.')
        ;

        $this->manualIndexer = new ManualIndexer();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $manualUrl = $input->getArgument('manualUrl');

        $this->manualIndexer->reindexManual($manualUrl);

        $output->writeln('Finished indexing of "' . $manualUrl . '".');
        return 0;
    }
}
