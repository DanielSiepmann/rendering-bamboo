<?php

namespace TYPO3\Documentation\Tests\Commands;

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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Documentation\Rendering\Commands\PrepareDeploymentCommand;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class PrepareDeploymentCommandTest extends TestCase
{
    /**
     * @var PrepareDeploymentCommand
     */
    protected $subject;

    /**
     * @var MockObject
     */
    protected $inputMock;

    /**
     * @var MockObject
     */
    protected $outputMock;

    public function setUp()
    {
        $this->subject = new PrepareDeploymentCommand();
        $this->inputMock = $this->getMockBuilder(InputInterface::class)->getMock();
        $this->outputMock = $this->getMockBuilder(OutputInterface::class)->getMock();
    }

    /**
     * @test
     */
    public function missingComposerJsonIsHandled()
    {
        $this->inputMock->expects($this->any())
            ->method('getArgument')
            ->with('workingDir')
            ->willReturn('/some/folder/');

        $this->outputMock->expects($this->once())
            ->method('writeln')
            ->with('Could not find composer.json in "/some/folder/".');

        $exitCode = $this->subject->run($this->inputMock, $this->outputMock);
        $this->assertSame(1, $exitCode, 'Command did not exit with 1 for missing composer.json');
    }

    /**
     * @test
     */
    public function missingFolderForTargetFileIsHandled()
    {
        $fileSystem = vfsStream::setup('root', null, [
            'workingDir' => [
                'composer.json' => 'some file content',
            ],
        ]);

        $this->inputMock->expects($this->any())
            ->method('getArgument')
            ->withConsecutive(
                ['workingDir'],
                ['targetFile']
            )
            ->will($this->onConsecutiveCalls(
                $fileSystem->url() . '/workingDir/',
                '/targetDir/deployment_infos.sh'
            ));

        $this->outputMock->expects($this->once())
            ->method('writeln')
            ->with('Path to deployment file does not exist: "/targetDir/deployment_infos.sh".');

        $exitCode = $this->subject->run($this->inputMock, $this->outputMock);
        $this->assertSame(1, $exitCode, 'Command did not exit with 1 for missing composer.json');
    }

    /**
     * @test
     * @dataProvider validCallEnvironments
     */
    public function deploymentInfoFileIsGenerated(
        array $composerJsonContent,
        string $versionString,
        string $expectedFileContent
    ) {
        $fileSystem = vfsStream::setup('root', null, [
            'workingDir' => [
                'composer.json' => json_encode($composerJsonContent),
            ],
            'targetDir' => [],
        ]);

        $this->inputMock->expects($this->any())
            ->method('getArgument')
            ->withConsecutive(
                ['workingDir'],
                ['targetFile'],
                ['version']
            )
            ->will($this->onConsecutiveCalls(
                $fileSystem->url() . '/workingDir/',
                $fileSystem->url() . '/targetDir/deployment_infos.sh',
                $versionString
            ));

        $this->outputMock->expects($this->once())
            ->method('writeln')
            ->with('Generated: "vfs://root/targetDir/deployment_infos.sh".');

        $exitCode = $this->subject->run($this->inputMock, $this->outputMock);
        $this->assertSame(0, $exitCode, 'Command did not exit with success.');

        $this->assertSame(
            $expectedFileContent,
            file_get_contents($fileSystem->url() . '/targetDir/deployment_infos.sh'),
            'Generated deployment_infos.sh did not have expected file content.'
        );
    }

    public function validCallEnvironments(): array
    {
        return [
            // 'No Information Provided'
            // Some information not provided

            'System extension, types and patch level version is removed, while "v" prefix is removed' => [
                'composerJsonContent' => [
                    'type' => 'typo3-cms-framework',
                    'name' => 'typo3/cms-indexed-search',
                ],
                'versionString' => 'v10.1.2',
                'expectedFileContent' => implode(PHP_EOL, [
                    '#/bin/bash',
                    'type_long=core-extension',
                    'type_short=c',
                    'vendor=typo3',
                    'name=cms-indexed-search',
                    'version=10.1',
                ]),
            ],
            'System extension, types and patch level version is removed, works without "v" prefix' => [
                'composerJsonContent' => [
                    'type' => 'typo3-cms-framework',
                    'name' => 'typo3/cms-indexed-search',
                ],
                'versionString' => '10.1.2',
                'expectedFileContent' => implode(PHP_EOL, [
                    '#/bin/bash',
                    'type_long=core-extension',
                    'type_short=c',
                    'vendor=typo3',
                    'name=cms-indexed-search',
                    'version=10.1',
                ]),
            ],
            '3rd Party extension, different type and patch level version is kept, while "v" prefix is removed' => [
                'composerJsonContent' => [
                    'type' => 'typo3-cms-extension',
                    'name' => 'vendor/package-name',
                ],
                'versionString' => 'v10.1.2',
                'expectedFileContent' => implode(PHP_EOL, [
                    '#/bin/bash',
                    'type_long=extension',
                    'type_short=e',
                    'vendor=vendor',
                    'name=package-name',
                    'version=10.1.2',
                ]),
            ],
            '3rd Party extension, different type and patch level version is kept, works without "v" prefix' => [
                'composerJsonContent' => [
                    'type' => 'typo3-cms-extension',
                    'name' => 'vendor/package-name',
                ],
                'versionString' => '10.1.2',
                'expectedFileContent' => implode(PHP_EOL, [
                    '#/bin/bash',
                    'type_long=extension',
                    'type_short=e',
                    'vendor=vendor',
                    'name=package-name',
                    'version=10.1.2',
                ]),
            ],
            'Branch "master" is mapped to "latest"' => [
                'composerJsonContent' => [
                    'type' => 'typo3-cms-extension',
                    'name' => 'vendor/package-name',
                ],
                'versionString' => 'master',
                'expectedFileContent' => implode(PHP_EOL, [
                    '#/bin/bash',
                    'type_long=extension',
                    'type_short=e',
                    'vendor=vendor',
                    'name=package-name',
                    'version=latest',
                ]),
            ],
        ];
    }

    /**
     * @test
     * @dataProvider invalidComposerJson
     */
    public function composerJsonContentIsChecked(
        array $composerJsonContent,
        string $expectedMessage
    ) {
        $fileSystem = vfsStream::setup('root', null, [
            'workingDir' => [
                'composer.json' => json_encode($composerJsonContent),
            ],
            'targetDir' => [],
        ]);

        $this->inputMock->expects($this->any())
            ->method('getArgument')
            ->withConsecutive(
                ['workingDir'],
                ['targetFile'],
                ['version']
            )
            ->will($this->onConsecutiveCalls(
                $fileSystem->url() . '/workingDir/',
                $fileSystem->url() . '/targetDir/deployment_infos.sh',
                'master'
            ));

        $this->outputMock->expects($this->once())
            ->method('writeln')
            ->with($expectedMessage);

        $exitCode = $this->subject->run($this->inputMock, $this->outputMock);
        $this->assertSame(2, $exitCode, 'Command did not exit with 2, indicating an exception.');
    }

    public function invalidComposerJson(): array
    {
        return [
            'Type is missing in composer.json' => [
                'composerJsonContent' => [
                    'name' => 'typo3/cms-indexed-search',
                ],
                'expectedMessage' => '<error>No type defined.</error>',
            ],
            'Name is missing in composer.json' => [
                'composerJsonContent' => [
                    'type' => 'typo3-cms-framework',
                ],
                'expectedMessage' => '<error>No name defined.</error>',
            ],
        ];
    }

    // TODO: Test invalid values
}
