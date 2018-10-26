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
            ->addArgument('workingDir', InputArgument::REQUIRED, 'Directory to work in.')
            ->addArgument('version', InputArgument::REQUIRED, 'The rendered version, e.g. "master" or "v10.5.2"')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check for all external dependencies first

        $workingDir = rtrim($input->getArgument('workingDir'), '/') . '/';
        $composerJson = $workingDir . 'composer.json';
        if (!file_exists($composerJson)) {
            $output->writeln('Could not find composer.json in "' . $workingDir . '".');
            return 1;
        }

        $outputFile = $input->getArgument('targetFile');
        if (!is_dir(dirname($outputFile))) {
            $output->writeln('Path to deployment file does not exist: "' . $outputFile . '".');
            return 1;
        }

        // If everything is fine, we go ahead

        try {
            $this->generateDeploymentFile(
                $outputFile,
                $this->generateDeploymentInfos($composerJson, $input->getArgument('version'))
            );
            $output->writeln('Generated: "' . $outputFile . '".');

            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return 2;
        }
    }

    protected function generateDeploymentInfos(string $composerJson, string $version): array
    {
        $composerContent = json_decode(file_get_contents($composerJson), true);
        $deploymentInfos = [
            'type_long' => $this->getTypeLong($composerContent),
            'type_short' => $this->getTypeShort($composerContent),
            'vendor' => $this->getComposerVendor($composerContent),
            'name' => $this->getComposerName($composerContent),
        ];
        $deploymentInfos['version'] = $this->getVersion($version, $deploymentInfos['type_short']);

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

    protected function getTypeLong(array $composerContent): string
    {
        if (!isset($composerContent['type'])) {
            throw new \Exception('No type defined.', 1532671586);
        }

        if ($composerContent['type'] === 'typo3-cms-documentation') {
            return 'manual';
        }

        if ($composerContent['type'] === 'typo3-cms-framework') {
            return 'core-extension';
        }

        if ($composerContent['type'] === 'typo3-cms-extension') {
            return 'extension';
        }

        return 'package';
    }

    protected function getTypeShort(array $composerContent): string
    {
        $typeLong = $this->getTypeLong($composerContent);

        if ($typeLong === 'manual') {
            return 'm';
        }

        if ($typeLong === 'core-extension') {
            return 'c';
        }

        if (in_array($typeLong, ['extension', 'package'])) {
            return 'p';
        }

        throw new \Exception('Unkown long type defined: "' . $typeLong . '".', 1533915213);
    }

    protected function getComposerVendor(array $composerContent): string
    {
        if (!isset($composerContent['name']) || trim($composerContent['name']) === '') {
            throw new \Exception('No name defined.', 1532671586);
        }

        return explode('/', $composerContent['name'])[0];
    }

    protected function getComposerName(array $composerContent): string
    {
        if (!isset($composerContent['name'])) {
            throw new \Exception('No name defined.', 1532671586);
        }

        $name = explode('/', $composerContent['name'])[1] ?? '';

        if (trim($name) === '') {
            throw new \Exception('No name defined.', 1533915440);
        }

        return $name;
    }

    /**
     * Converts incoming version string to version string used in documentation.
     */
    protected function getVersion(string $versionString, string $typeShort): string
    {
        if (trim($versionString) === 'master') {
            return 'latest';
        }

        // We do not keep the "v" prefix.
        if (strtolower($versionString[0]) === 'v') {
            $versionString = substr($versionString, 1);
        }

        // System extensions have further special handling.
        if ($typeShort === 'c') {
            return $this->getSystemExtensionVersion($versionString);
        }

        // TODO: Define behaviour for unkown versions?

        return $versionString;
    }

    /**
     * For system extensions, we do not keep patch level documentations.
     *
     * We therefore only use the major and minor version parts of a version string.
     */
    protected function getSystemExtensionVersion(string $versionString): string
    {
        $versionParts = explode('.', $versionString);

        return implode('.', array_slice($versionParts, 0, 2));
    }
}
