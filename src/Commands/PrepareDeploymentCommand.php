<?php

namespace TYPO3\Documentation\Rendering\Commands;

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

/**
 * Prepares deployment.
 *
 * E.g. define some variables based on rendered project.
 */
class PrepareDeploymentCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('prepare:deployment')
            ->setDescription('Prepares deployment, e.g. define some variables.')

            ->addArgument('targetFile', InputArgument::REQUIRED, 'Path to file containing the output.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists('composer.json')) {
            $output->writeln('Could not find composer.json');
            exit(1);
        }

        $outputFile = $this->input->getArgument('targetFile');
        if (!is_dir(dirname($outputFile))) {
            $output->writeln('Path to deployment file does not exist: "' . $outputFile . '".');
            exit(1);
        }

        $this->generateDeploymentFile($outputFile, $this->generateDeploymentInfos());
    }

    protected function generateDeploymentInfos(): array
    {
        $composerContent = json_decode(file_get_contents('composer.json'), true);
        $deploymentInfos = [
            'type_long' => $this->getTypeLong($composerContent),
            'type_short' => $this->getTypeShort($composerContent),
            'vendor' => $this->getComposerVendor($composerContent),
            'name' => $this->getComposerName($composerContent),
            'version' => $this->getVersion(),
        ];

        return $deploymentInfos;
    }

    protected function generateDeploymentFile(string $outputFile, array $deploymentInfos)
    {
        $fileContent = ['#/bin/bash'];
        foreach ($deploymentInfos as $key => $value) {
            $fileContent[] = $key . '=' . $value;
        }

        file_put_contents($outputFile, implode($fileContent, PHP_EOL));
    }

    protected function getTypeLong(array $composerFile): string
    {
        if (!isset($composerFile['type'])) {
            throw new \Exception('No type defined.', 1532671586);
        }

        if ($composerFile['type'] === 'typo3-cms-extension') {
            return 'extension';
        }
        if ($composerFile['type'] === 'typo3-cms-framework') {
            return 'core-extension';
        }

        return '';
    }

    protected function getTypeShort(array $composerFile): string
    {
        $typeLong = $this->getTypeLong($composerFile);

        if ($typeLong === 'extension') {
            return 'e';
        }
        if ($typeLong === 'core-extension') {
            return 'c';
        }

        return '';
    }

    protected function getComposerVendor(array $composerFile): string
    {
        if (!isset($composerFile['name'])) {
            throw new \Exception('No name defined.', 1532671586);
        }

        return explode('/', $composerFile['name'])[0];
    }

    protected function getComposerName(array $composerFile): string
    {
        if (!isset($composerFile['name'])) {
            throw new \Exception('No name defined.', 1532671586);
        }

        return explode('/', $composerFile['name'])[1];
    }

    protected function getVersion(): string
    {
        // TODO: Fetch from input?! From bamboo, environment.
        return '1.0.0';
    }
}
