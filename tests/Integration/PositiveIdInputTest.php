<?php

declare(strict_types=1);

namespace Maatify\I18n\Tests\Integration;

use Maatify\I18n\Exception\I18nInvalidArgumentException;
use Maatify\I18n\Service\MissingCounterService;
use Maatify\I18n\Tests\Support\MysqlIntegrationTestCase;

final class PositiveIdInputTest extends MysqlIntegrationTestCase
{
    public function testDirectServiceIdsAreRejectedBeforeRepositoryAccess(): void
    {
        $counter = new MissingCounterService($this->summary, $this->keys, $this->keyStats);
        $calls = [
            'scope.setActive' => fn() => $this->scopeManagement->setActive(0, true),
            'scope.changeCode' => fn() => $this->scopeManagement->changeCode(-1, 'next'),
            'domain.setActive' => fn() => $this->domainManagement->setActive(0, true),
            'domain.changeCode' => fn() => $this->domainManagement->changeCode(-1, 'next'),
            'read.scope' => fn() => $this->managementRead->getScope(0),
            'read.domain' => fn() => $this->managementRead->getDomain(-1),
            'read.key' => fn() => $this->managementRead->getKey(0),
            'key.updateDescription' => fn() => $this->writer->updateKeyDescription(0, 'description'),
            'translation.delete' => fn() => $this->writer->deleteTranslation(null, -1),
            'counter.keyCreated' => fn() => $counter->onKeyCreated(0),
            'counter.keyDeleted' => fn() => $counter->onKeyDeleted(-1),
            'counter.translationCreated' => fn() => $counter->onTranslationCreated(null, 0),
            'counter.translationDeleted' => fn() => $counter->onTranslationDeleted(null, -1),
        ];

        foreach ($calls as $name => $call) {
            try {
                $call();
                self::fail($name . ' accepted a non-positive ID');
            } catch (I18nInvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
