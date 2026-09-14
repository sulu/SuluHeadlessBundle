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

namespace Sulu\Bundle\HeadlessBundle\Content\StructureResolver;

use Sulu\Component\Content\Compat\Structure\PageBridge;
use Sulu\Component\Content\Compat\StructureInterface;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

class LocalizationResolverExtension implements StructureResolverExtensionInterface
{
    private RequestAnalyzerInterface $requestAnalyzer;
    private WebspaceManagerInterface $webspaceManager;

    public function __construct(
        RequestAnalyzerInterface $requestAnalyzer,
        WebspaceManagerInterface $webspaceManager
    )
    {
        $this->requestAnalyzer = $requestAnalyzer;
        $this->webspaceManager = $webspaceManager;
    }

    public function getKey(): string
    {
        return 'localizations';
    }

    public function resolve(StructureInterface $requestedStructure, string $locale): mixed
    {
        $webspace = $this->requestAnalyzer->getWebspace();

        if (null !== ($portal = $this->requestAnalyzer->getPortal())) {
            $allLocalizations = $portal->getLocalizations();
        } else {
            $allLocalizations = $webspace->getLocalizations();
        }

        $pageUrls = [];
        if ($requestedStructure instanceof PageBridge) {
            $pageUrls = $requestedStructure->getUrls();
        }

        $localizations = [];

        foreach ($allLocalizations as $localization) {
            $locale = $localization->getLocale();

            $alternate = true;
            if (\array_key_exists($locale, $pageUrls)) {
                $url = $this->webspaceManager->findUrlByResourceLocator($pageUrls[$locale], null, $locale);
                if ($url === null) {
                    $url = $pageUrls[$locale];
                }
            } else {
                $alternate = false;
                $url = $this->webspaceManager->findUrlByResourceLocator('/', null, $locale);
            }

            $localizations[$locale] = [
                'locale' => $locale,
                'url' => $url,
                'country' => $localization->getCountry(),
                'alternate' => $alternate,
            ];
        }
        return $localizations;
    }
}
