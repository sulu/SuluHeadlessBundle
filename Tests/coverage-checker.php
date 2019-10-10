<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Node\Directory;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

require __DIR__ . '/../vendor/autoload.php';

// construct symfony io object to format output

$inputDefinition = new InputDefinition();
$inputDefinition->addArgument(new InputArgument('metric', InputArgument::REQUIRED));
$inputDefinition->addArgument(new InputArgument('threshold', InputArgument::REQUIRED));
$inputDefinition->addArgument(new InputArgument('paths', InputArgument::REQUIRED | InputArgument::IS_ARRAY));

// Trim any options passed to the command
$argvArguments = explode(' ', explode(' --', implode(' ', $argv))[0]);

$input = new ArgvInput($argvArguments, $inputDefinition);

$io = new SymfonyStyle($input, new ConsoleOutput());

$metric = $input->getArgument('metric');
$threshold = min(100, max(0, (float) $input->getArgument('threshold')));

// load code coverage report
$coverageReportPath = __DIR__ . '/../var/coverage.php';
if (!is_readable($coverageReportPath)) {
    $io->error('Coverage report file "' . $coverageReportPath . '" is not readable or does not exist.');
    exit(1);
}
$coverage = require $coverageReportPath;

$exit = 0;

foreach ($input->getArgument('paths') as $path) {
    $exit += assertCodeCoverage($coverage, $path, $metric, $threshold);
}

exit($exit);

function assertCodeCoverage(CodeCoverage $coverage, string $path, string $metric, float $threshold)
{
    global $io;

    $rootReport = $coverage->getReport();
    $pathReport = getReportForPath($rootReport, $path);

    if (!$pathReport) {
        $io->error('Coverage report for path "' . $path . '" not found.');

        return 1;
    }

    printCodeCoverageReport($pathReport);

    if ('line' === $metric) {
        $reportedCoverage = $pathReport->getLineExecutedPercent();
    } elseif ('method' === $metric) {
        $reportedCoverage = $pathReport->getTestedMethodsPercent();
    } elseif ('class' === $metric) {
        $reportedCoverage = $pathReport->getTestedClassesPercent();
    } else {
        $io->error('Coverage metric "' . $metric . '"" is not supported yet.');

        return 1;
    }

    $reportedCoverage = (float) $reportedCoverage;

    if ($reportedCoverage < $threshold) {
        $io->error(sprintf(
            'Code Coverage for metric "%s" and path "%s" is below threshold of %.2F%%.',
            $metric,
            $path,
            $threshold
        ));

        return 1;
    }

    $io->success(sprintf(
        'Code Coverage for metric "%s" and path "%s" is above threshold of %.2F%%.',
        $metric,
        $path,
        $threshold
    ));

    return 0;
}

function printCodeCoverageReport(Directory $pathReport): void
{
    global $io;

    $rightAlignedTableStyle = new TableStyle();
    $rightAlignedTableStyle->setPadType(STR_PAD_LEFT);

    $table = new Table($io);
    $table->setColumnWidth(0, 20);
    $table->setColumnStyle(1, $rightAlignedTableStyle);
    $table->setColumnStyle(2, $rightAlignedTableStyle);

    $table->setHeaders(['Coverage Metric', 'Relative Coverage', 'Absolute Coverage']);
    $table->addRow([
        'Line Coverage',
        sprintf('%.2F%%', $pathReport->getLineExecutedPercent()),
        sprintf('%d/%d', $pathReport->getNumExecutedLines(), $pathReport->getNumExecutableLines()),
    ]);
    $table->addRow([
        'Method Coverage',
        sprintf('%.2F%%', $pathReport->getTestedMethodsPercent()),
        sprintf('%d/%d', $pathReport->getNumTestedMethods(), $pathReport->getNumMethods()),
    ]);
    $table->addRow([
        'Class Coverage',
        sprintf('%.2F%%', $pathReport->getTestedClassesPercent()),
        sprintf('%d/%d', $pathReport->getNumTestedClasses(), $pathReport->getNumClasses()),
    ]);

    $io->title('Code coverage report for directory "' . $pathReport->getPath() . '"');
    $table->render();
    $io->newLine(2);
}

function getReportForPath(Directory $rootReport, string $path): ?Directory
{
    /** @var Directory $report */
    foreach ($rootReport as $report) {
        if (false !== mb_stripos($report->getPath(), $path)) {
            return $report;
        }
    }

    return null;
}
